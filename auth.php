<?php
/**
 * SIA 2027 Party Agent Portal - Authentication Controller
 * Handles Sign In, Sign Up, and Session Management
 * Hardened with Rate Limiting, CSRF, Session Regeneration, and Audit Logging
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$ip = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db_connection();

    // Verify CSRF token on all POST requests
    if (!verify_csrf_request()) {
        audit_log($pdo, 'visitor', null, 'agent_csrf_failed', 'auth', null, ['ip' => $ip, 'action' => $action]);
        respond_error('Security validation failed (invalid CSRF token). Please refresh and try again.', $is_ajax, 403);
    }

    // -------------------------------------------------------------
    // ACTION: LOGIN / SIGN IN
    // -------------------------------------------------------------
    if ($action === 'login' || isset($_POST['login_btn'])) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            respond_error('Please enter both your email address and password.', $is_ajax, 400);
        }

        // Enforce rate limiting: 5 failed attempts per 15 minutes per IP + email
        if (is_login_rate_limited($pdo, $ip, $email, 5, 15)) {
            audit_log($pdo, 'visitor', null, 'agent_login_rate_limited', 'agent', null, ['email' => $email, 'ip' => $ip]);
            respond_error('Too many failed sign-in attempts. For security, your account is temporarily locked for 15 minutes.', $is_ajax, 429);
        }

        $stmt = $pdo->prepare("SELECT * FROM agents WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $agent = $stmt->fetch();

        if ($agent && password_verify($password, $agent['password_hash'])) {
            // Check suspension
            if (($agent['status'] ?? '') === 'suspended') {
                audit_log($pdo, 'agent', (int)$agent['id'], 'agent_login_blocked_suspended', 'agent', (int)$agent['id'], ['email' => $email]);
                respond_error('Your agent accreditation has been suspended. Please contact your LGA coordinator.', $is_ajax, 403);
            }

            // Successful login: regenerate session ID to protect against session fixation
            session_regenerate_id(true);

            // Clear previous rate limiting attempts
            clear_login_attempts($pdo, $ip, $email);

            // Update last_seen_at
            try {
                $pdo->prepare("UPDATE agents SET last_seen_at = datetime('now') WHERE id = ?")->execute([$agent['id']]);
            } catch (Throwable) {}

            // Audit log
            audit_log($pdo, 'agent', (int)$agent['id'], 'agent_login_success', 'agent', (int)$agent['id']);

            // Store in session (excluding password hash)
            unset($agent['password_hash']);
            $_SESSION['agent'] = $agent;

            $status_note = ($agent['status'] === 'pending') ? " (Account pending accreditation approval)" : "";
            set_flash('success', "Welcome back, {$agent['name']}! Connected to Command HQ.{$status_note}");

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Signed in successfully!',
                    'status' => $agent['status'],
                    'redirect' => 'dashboard.php'
                ]);
                exit;
            } else {
                header('Location: dashboard.php');
                exit;
            }
        } else {
            // Record failed attempt
            record_login_attempt($pdo, $ip, $email);
            audit_log($pdo, 'visitor', null, 'agent_login_failed', 'agent', null, ['email' => $email, 'ip' => $ip]);
            respond_error('Invalid email address or password. Please verify your agent credentials.', $is_ajax, 400);
        }
    }

    // -------------------------------------------------------------
    // ACTION: SIGN UP / REGISTER NEW PARTY AGENT
    // -------------------------------------------------------------
    if ($action === 'signup' || isset($_POST['signup_btn'])) {
        $name = sanitize($_POST['name'] ?? '');
        $state = sanitize($_POST['state'] ?? 'Adamawa State');
        $lga = sanitize($_POST['lga'] ?? '');
        $ward = sanitize($_POST['ward'] ?? '');
        $polling_unit = sanitize($_POST['polling_unit'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email)) {
            respond_error('Please provide your full legal name and email address.', $is_ajax, 400);
        }

        if (empty($password) || strlen($password) < 6) {
            respond_error('Password must be at least 6 characters long.', $is_ajax, 400);
        }

        if (empty($lga) || empty($ward) || empty($polling_unit)) {
            respond_error('Please select your assigned Local Government, Ward, and Polling Unit.', $is_ajax, 400);
        }

        // Check if email already registered
        $stmt = $pdo->prepare("SELECT id FROM agents WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            respond_error('An agent account with this email already exists. Please sign in.', $is_ajax, 400);
        }

        // Generate unique Agent Code: SIA-LP-AD-YY-XXXX
        $agent_code = 'SIA-LP-AD-' . date('y') . '-' . str_pad((string)mt_rand(100, 9999), 4, '0', STR_PAD_LEFT);
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // New self-signups strictly start as 'pending' until approved by admin/coordinator
        $initial_status = 'pending';

        $stmt = $pdo->prepare("
            INSERT INTO agents (name, email, password_hash, phone, role, state, lga, ward, polling_unit, agent_code, status, created_at, last_seen_at)
            VALUES (?, ?, ?, ?, 'agent', ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            $name,
            $email,
            $password_hash,
            sanitize($_POST['phone'] ?? ''),
            $state,
            $lga,
            $ward,
            $polling_unit,
            $agent_code,
            $initial_status
        ]);

        $new_agent_id = (int)$pdo->lastInsertId();

        // Audit log
        audit_log($pdo, 'agent', $new_agent_id, 'agent_signup_pending', 'agent', $new_agent_id, [
            'name' => $name,
            'email' => $email,
            'agent_code' => $agent_code,
            'status' => 'pending'
        ]);

        // Regenerate session ID and log agent in as pending
        session_regenerate_id(true);

        $stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$new_agent_id]);
        $agent = $stmt->fetch();
        unset($agent['password_hash']);
        $_SESSION['agent'] = $agent;

        set_flash('success', "Registration submitted! Your Agent ID is {$agent_code}. Your account is pending coordinator approval before EC8A submission.");

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Agent registration submitted successfully! Awaiting coordinator accreditation.',
                'status' => 'pending',
                'redirect' => 'dashboard.php'
            ]);
            exit;
        } else {
            header('Location: dashboard.php');
            exit;
        }
    }
}

// -------------------------------------------------------------
// GET ACTIONS: LOGOUT / CHECK
// -------------------------------------------------------------
if ($action === 'logout') {
    $agent = current_agent();
    if ($agent) {
        $pdo = get_db_connection();
        audit_log($pdo, 'agent', (int)$agent['id'], 'agent_logout', 'agent', (int)$agent['id']);
    }
    unset($_SESSION['agent']);
    session_destroy();
    session_start();
    set_flash('success', 'You have been safely signed out of Command HQ.');
    header('Location: index.php');
    exit;
}

if ($action === 'reset_password') {
    $token = $_GET['token'] ?? '';
    $email = $_GET['email'] ?? '';
    header("Location: reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email));
    exit;
}

if ($action === 'csrf' || $action === 'get_csrf') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'csrf_token' => csrf_token()
    ]);
    exit;
}

if ($action === 'check') {
    header('Content-Type: application/json');
    if (is_authenticated()) {
        $agent = current_agent();
        echo json_encode([
            'authenticated' => true,
            'csrf_token' => csrf_token(),
            'status' => $agent['status'] ?? 'accredited',
            'agent' => [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'email' => $agent['email'],
                'lga' => $agent['lga'],
                'ward' => $agent['ward'],
                'polling_unit' => $agent['polling_unit'],
                'agent_code' => $agent['agent_code']
            ]
        ]);
    } else {
        echo json_encode([
            'authenticated' => false,
            'csrf_token' => csrf_token()
        ]);
    }
    exit;
}

// Helper to respond with error
function respond_error(string $message, bool $is_ajax, int $code = 400): void {
    if ($is_ajax) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    } else {
        set_flash('error', $message);
        header('Location: index.php#portal');
        exit;
    }
}
