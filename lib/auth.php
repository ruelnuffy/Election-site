<?php
/**
 * Authentication & Access Control Library
 * SIA 2027 Party Agent & Command Portal
 */

require_once __DIR__ . '/audit.php';

function start_secure_session(): void {
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// -------------------------------------------------------------
// Rate Limiting
// -------------------------------------------------------------

function is_login_rate_limited(PDO $pdo, string $ip, string $email, int $max_attempts = 5, int $decay_minutes = 15): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip = ? AND LOWER(email) = LOWER(?)
            AND attempted_at >= datetime('now', '-' || ? || ' minutes')
        ");
        $stmt->execute([$ip, $email, $decay_minutes]);
        return (int)$stmt->fetchColumn() >= $max_attempts;
    } catch (Throwable) {
        return false;
    }
}

function record_login_attempt(PDO $pdo, string $ip, string $email): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$ip, strtolower(trim($email))]);
    } catch (Throwable $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

function clear_login_attempts(PDO $pdo, string $ip, string $email): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip = ? AND LOWER(email) = LOWER(?)");
        $stmt->execute([$ip, strtolower(trim($email))]);
    } catch (Throwable) {
        // Ignore failure
    }
}

// -------------------------------------------------------------
// Agent Auth
// -------------------------------------------------------------

function is_agent_authenticated(): bool {
    return isset($_SESSION['agent']) && !empty($_SESSION['agent']['id']);
}

function current_agent(): ?array {
    return $_SESSION['agent'] ?? null;
}

function require_agent_auth(): array {
    if (!is_agent_authenticated()) {
        $is_json = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in as an accredited Party Agent.']);
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please sign in to access the Party Agent Portal.'];
            header('Location: login.php');
        }
        exit;
    }
    return $_SESSION['agent'];
}

// -------------------------------------------------------------
// Admin Auth
// -------------------------------------------------------------

function is_admin_authenticated(): bool {
    return isset($_SESSION['admin']) && !empty($_SESSION['admin']['id']);
}

function current_admin(): ?array {
    return $_SESSION['admin'] ?? null;
}

function require_admin_auth(array $allowed_roles = []): array {
    if (!is_admin_authenticated()) {
        $is_json = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Administrator authentication required.']);
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Administrator authentication required.'];
            header('Location: admin_login.php');
        }
        exit;
    }

    $admin = $_SESSION['admin'];
    if (!empty($allowed_roles) && !in_array($admin['role'], $allowed_roles, true)) {
        $is_json = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_json) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access forbidden. Your admin role (' . htmlspecialchars($admin['role']) . ') lacks sufficient permissions.']);
        } else {
            http_response_code(403);
            die('Access forbidden. Your administrative role (' . htmlspecialchars($admin['role']) . ') does not permit this action.');
        }
        exit;
    }

    return $admin;
}

// -------------------------------------------------------------
// District Manager Auth
// -------------------------------------------------------------

function is_district_manager_authenticated(): bool {
    return isset($_SESSION['district_manager']) && !empty($_SESSION['district_manager']['id']);
}

function current_district_manager(): ?array {
    return $_SESSION['district_manager'] ?? null;
}

function require_district_manager_auth(?string $required_district = null): array {
    if (!is_district_manager_authenticated()) {
        $is_json = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        $redirect_url = 'management_login.php' . ($required_district ? '?district=' . urlencode($required_district) : '');
        if ($is_json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized. District Management authentication required.', 'redirect' => $redirect_url]);
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please sign in to access your Senatorial District Management Dashboard.'];
            header('Location: ' . $redirect_url);
        }
        exit;
    }

    $manager = $_SESSION['district_manager'];
    if ($required_district !== null && $manager['district'] !== $required_district && $manager['district'] !== 'all') {
        $redirect_url = 'management_login.php?district=' . urlencode($required_district);
        header('Location: ' . $redirect_url);
        exit;
    }

    return $manager;
}

