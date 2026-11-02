<?php
/**
 * SIA 2027 Party Agent Portal - Official Sign-In Gateway
 * Secure Authentication for Accredited Polling Unit and Ward Agents
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();
$pdo = get_db_connection();

// If already authenticated as agent, redirect to dashboard
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$info = null;
$ip = get_client_ip();

// Handle flash messages
$flash = get_flash();
if ($flash) {
    if ($flash['type'] === 'error') {
        $error = $flash['message'];
    } else {
        $info = $flash['message'];
    }
}

// Check for redirect query param
$redirect = $_GET['redirect'] ?? 'dashboard.php';
if (!in_array($redirect, ['dashboard.php', 'index.php', 'index.html'], true)) {
    $redirect = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();

        $identifier = trim($_POST['identifier'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if (empty($identifier) || empty($password)) {
            throw new Exception('Please enter both your registered email or Agent ID, and your password.');
        }

        // Rate limiting check: 5 attempts per 15 minutes
        if (is_login_rate_limited($pdo, $ip, $identifier, 5, 15)) {
            audit_log($pdo, 'anonymous', null, 'agent_login_throttled', 'agents', null, [
                'ip' => $ip,
                'identifier' => $identifier
            ]);
            throw new Exception('Too many failed sign-in attempts. For security, access is temporarily locked for 15 minutes.');
        }

        // Search agent by email or agent_code
        $stmt = $pdo->prepare("
            SELECT * FROM agents 
            WHERE LOWER(email) = LOWER(?) OR UPPER(agent_code) = UPPER(?) 
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $agent = $stmt->fetch();

        if (!$agent || !password_verify($password, $agent['password_hash'])) {
            record_login_attempt($pdo, $ip, $identifier);
            audit_log($pdo, 'anonymous', null, 'agent_login_failed', 'agents', $agent['id'] ?? null, [
                'ip' => $ip,
                'identifier' => $identifier,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            throw new Exception('Invalid email / Agent ID or password. Please verify your credentials.');
        }

        // Check if agent status is suspended
        if (($agent['status'] ?? '') === 'suspended') {
            audit_log($pdo, 'agent', (int)$agent['id'], 'agent_login_blocked_suspended', 'agents', (int)$agent['id']);
            throw new Exception('Your agent accreditation has been suspended. Please contact your LGA coordinator.');
        }

        // Clear rate limiting records
        clear_login_attempts($pdo, $ip, $identifier);

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Update last_seen_at
        try {
            $upd = $pdo->prepare("UPDATE agents SET last_seen_at = datetime('now') WHERE id = ?");
            $upd->execute([$agent['id']]);
        } catch (Throwable) {}

        // Audit successful login
        audit_log($pdo, 'agent', (int)$agent['id'], 'agent_login_success', 'agents', (int)$agent['id'], [
            'ip' => $ip,
            'agent_code' => $agent['agent_code'],
            'status' => $agent['status']
        ]);

        // Store agent in session (excluding password hash)
        unset($agent['password_hash']);
        $_SESSION['agent'] = $agent;

        if ($agent['status'] === 'pending') {
            set_flash('info', "Welcome, {$agent['name']}. Your accreditation is pending coordinator review.");
        } else {
            set_flash('success', "Welcome back, {$agent['name']}. Connected to SIA 2027 Command HQ.");
        }

        header("Location: {$redirect}");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Party Agent Sign-In &bull; SIA 2027</title>
  <link rel="icon" type="image/png" href="./assets/images/logos/lp_logo.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  <style>
    .material-symbols-outlined {
      font-family: 'Material Symbols Outlined';
      font-weight: normal;
      font-style: normal;
      font-size: 20px;
      line-height: 1;
      display: inline-block;
      vertical-align: middle;
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
  </style>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', '"Outfit"', 'system-ui', 'sans-serif'],
          },
          colors: {
            brand: {
              50: '#ecfdf5',
              100: '#d1fae5',
              500: '#10b981',
              600: '#059669',
              700: '#047857',
              800: '#065f46',
              900: '#064e3b',
              950: '#022c22',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-4 selection:bg-emerald-600 selection:text-white relative overflow-x-hidden">

  <!-- Subtle background glow -->
  <div class="fixed inset-0 pointer-events-none flex items-center justify-center overflow-hidden -z-10">
    <div class="w-[500px] h-[500px] bg-emerald-500/5 rounded-full blur-3xl transform -translate-y-12"></div>
  </div>

  <div class="w-full max-w-md space-y-6 py-6">

    <!-- Header Brand -->
    <div class="text-center space-y-2">
      <a href="index.php" class="inline-flex items-center justify-center gap-2 group text-decoration-none">
        <div class="w-12 h-12 rounded-2xl bg-emerald-600 group-hover:bg-emerald-700 text-white font-bold text-lg flex items-center justify-center shadow-lg shadow-emerald-600/20 transition-transform group-hover:scale-105">
          SIA
        </div>
      </a>
      <h1 class="text-2xl font-bold tracking-tight text-slate-900 m-0">Sign in</h1>
      <p class="text-xs sm:text-sm text-slate-500 font-normal">
        Party Agent Portal &bull; Adamawa State
      </p>
    </div>

    <!-- Sign In Card -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 space-y-5">

      <?php if ($error): ?>
      <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5">
        <span class="material-symbols-outlined text-red-500 text-base flex-shrink-0 mt-0.5">warning</span>
        <span class="flex-1"><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($info): ?>
      <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5">
        <span class="material-symbols-outlined text-emerald-600 text-base flex-shrink-0 mt-0.5">check_circle</span>
        <span class="flex-1"><?= htmlspecialchars($info) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

        <!-- Identifier (Email or Agent ID) -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">
            Email or Agent ID
          </label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">person</span>
            <input 
              type="text" 
              name="identifier" 
              required 
              autocomplete="username"
              placeholder="name@example.com or Agent Code" 
              value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" 
              class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
            >
          </div>
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-semibold text-slate-700">Password</label>
            <a href="forgot_password.php" class="text-xs text-emerald-700 hover:text-emerald-800 transition-colors font-semibold">
              Forgot password?
            </a>
          </div>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">lock</span>
            <input 
              type="password" 
              id="passwordInput"
              name="password" 
              required 
              autocomplete="current-password"
              placeholder="••••••••••••" 
              class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-11 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400"
            >
            <button 
              type="button" 
              onclick="togglePasswordVisibility()" 
              class="absolute right-3 text-slate-400 hover:text-slate-600 bg-transparent border-0 p-1 cursor-pointer transition-colors"
              aria-label="Toggle password visibility"
            >
              <span id="pwdToggleIcon" class="material-symbols-outlined text-lg">visibility</span>
            </button>
          </div>
        </div>

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between pt-0.5">
          <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-600 select-none">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 bg-white text-emerald-600 focus:ring-emerald-500 h-4 w-4">
            <span>Remember me</span>
          </label>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button 
            type="submit" 
            class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-semibold py-3 rounded-full text-sm shadow-md shadow-emerald-600/20 transition-all cursor-pointer flex items-center justify-center gap-2"
          >
            <span>Sign In</span>
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </button>
        </div>
      </form>

      <!-- Signup Callout -->
      <div class="pt-4 border-t border-slate-100 text-center">
        <p class="text-xs text-slate-500 m-0">
          Don't have an account? 
          <a href="signup.php" class="text-emerald-700 hover:text-emerald-800 font-semibold ml-1 transition-colors">
            Apply for accreditation &rarr;
          </a>
        </p>
      </div>

    </div>

    <!-- Quick Footer Links -->
    <div class="text-center text-xs text-slate-500">
      <a href="index.php" class="text-slate-500 hover:text-slate-800 transition-colors inline-flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        <span>Back to Website</span>
      </a>
    </div>

  </div>

  <script>
    function togglePasswordVisibility() {
      const input = document.getElementById('passwordInput');
      const icon = document.getElementById('pwdToggleIcon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility';
      }
    }
  </script>

</body>
</html>

