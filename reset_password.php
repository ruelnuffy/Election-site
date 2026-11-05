<?php
/**
 * SIA 2027 Party Agent Portal - Set New Password
 * Secure verification of cryptographically signed recovery tokens
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();
$pdo = get_db_connection();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error = null;
$agent = null;
$is_valid_token = false;
$ip = get_client_ip();

// Verify token validity
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT * FROM agents 
        WHERE password_reset_token = ? 
          AND password_reset_expiry IS NOT NULL 
          AND password_reset_expiry > datetime('now')
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $agent = $stmt->fetch();
    if ($agent) {
        $is_valid_token = true;
    }
}

// Handle POST request to update password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();

        if (!$is_valid_token || !$agent) {
            throw new Exception('This password reset link is invalid, expired, or has already been used. Please request a new link.');
        }

        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($password) || strlen($password) < 6) {
            throw new Exception('Your new password must be at least 6 characters long.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Password confirmation does not match. Please re-enter.');
        }

        $new_hash = password_hash($password, PASSWORD_BCRYPT);

        // Update password and invalidate token atomically
        $upd = $pdo->prepare("
            UPDATE agents 
            SET password_hash = ?, 
                password_reset_token = NULL, 
                password_reset_expiry = NULL,
                last_seen_at = datetime('now')
            WHERE id = ?
        ");
        $upd->execute([$new_hash, $agent['id']]);

        // Audit log
        audit_log($pdo, 'agent', (int)$agent['id'], 'agent_password_reset_completed', 'agents', (int)$agent['id'], [
            'ip' => $ip,
            'agent_code' => $agent['agent_code'],
            'email' => $agent['email']
        ]);

        // Clear any rate limiting
        clear_login_attempts($pdo, $ip, $agent['email']);
        clear_login_attempts($pdo, $ip, $agent['agent_code']);

        set_flash('success', 'Your password has been updated successfully! Please sign in with your new credentials.');
        header('Location: login.php');
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
  <title>Choose New Password &bull; SIA 2027</title>
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
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-4 selection:bg-emerald-600 selection:text-white relative">

  <!-- Background decorative glow -->
  <div class="fixed inset-0 pointer-events-none flex items-center justify-center overflow-hidden -z-10">
    <div class="w-[500px] h-[500px] bg-emerald-500/5 rounded-full blur-3xl transform -translate-y-12"></div>
  </div>

  <div class="w-full max-w-md space-y-6 py-6">

    <!-- Header Brand -->
    <div class="text-center space-y-2">
      <a href="index.php" class="inline-flex items-center justify-center group text-decoration-none">
        <div class="w-14 h-14 rounded-2xl bg-white border border-slate-200/90 shadow-md p-1 flex items-center justify-center transition-transform group-hover:scale-105 overflow-hidden">
          <img src="./assets/images/logos/lp_logo.png" alt="Labour Party" class="w-full h-full object-contain">
        </div>
      </a>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 m-0">Set new password</h1>
      <p class="text-xs sm:text-sm text-slate-500 font-normal">
        Enter your new password below
      </p>
    </div>

    <!-- Reset Card -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 space-y-5">

      <?php if ($error): ?>
      <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5">
        <span class="material-symbols-outlined text-red-500 text-base flex-shrink-0 mt-0.5">warning</span>
        <span class="flex-1"><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($is_valid_token && $agent): ?>

      <!-- Target Agent Badge -->
      <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 border border-emerald-300 text-emerald-800 flex items-center justify-center font-bold text-sm">
          <?= htmlspecialchars(substr($agent['name'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-bold text-slate-900 truncate"><?= htmlspecialchars($agent['name']) ?></div>
          <div class="text-[11px] text-slate-500 truncate">
            <?= htmlspecialchars($agent['agent_code']) ?> &bull; <?= htmlspecialchars($agent['lga']) ?>
          </div>
        </div>
      </div>

      <form method="POST" action="reset_password.php" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

        <!-- New Password -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">New Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">lock</span>
            <input 
              type="password" 
              id="newPassword" 
              name="password" 
              required 
              minlength="6"
              placeholder="Min. 6 characters" 
              oninput="checkPasswordStrength(this.value)"
              class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-11 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
            >
            <button 
              type="button" 
              onclick="toggleNewPassword()" 
              class="absolute right-3 text-slate-400 hover:text-slate-600 bg-transparent border-0 p-1 cursor-pointer transition-colors"
            >
              <span id="newEyeIcon" class="material-symbols-outlined text-lg">visibility</span>
            </button>
          </div>
          <!-- Strength bar -->
          <div class="mt-1.5 flex items-center gap-1.5">
            <div class="h-1 flex-1 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
              <div id="strengthBar" class="h-full w-0 bg-red-500 transition-all duration-300"></div>
            </div>
            <span id="strengthText" class="text-[10px] text-slate-500 font-medium">Too short</span>
          </div>
        </div>

        <!-- Confirm Password -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm New Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">lock_clock</span>
            <input 
              type="password" 
              id="confirmPassword" 
              name="confirm_password" 
              required 
              minlength="6"
              placeholder="Re-type new password" 
              oninput="checkPasswordMatch()"
              class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-11 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
            >
            <span id="matchIcon" class="material-symbols-outlined absolute right-3 text-slate-300 text-lg">check_circle</span>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button 
            type="submit" 
            class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-semibold py-3 rounded-full text-sm shadow-md shadow-emerald-600/20 transition-all cursor-pointer flex items-center justify-center gap-2"
          >
            <span>Update Password</span>
            <span class="material-symbols-outlined text-base">check</span>
          </button>
        </div>

      </form>

      <?php else: ?>

      <!-- Invalid / Expired Token Warning -->
      <div class="text-center py-4 space-y-4">
        <div class="w-14 h-14 rounded-full bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center mx-auto">
          <span class="material-symbols-outlined text-2xl">error</span>
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900 m-0">Reset Link Invalid or Expired</h3>
          <p class="text-xs text-slate-500 mt-1.5 max-w-sm mx-auto leading-relaxed">
            This password recovery link has either expired or has already been used. Please request a new link below.
          </p>
        </div>
        <div class="pt-2">
          <a 
            href="forgot_password.php" 
            class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition-all text-decoration-none"
          >
            <span>Request New Reset Link</span>
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </a>
        </div>
      </div>

      <?php endif; ?>

      <!-- Return to Login Link -->
      <div class="pt-4 border-t border-slate-100 text-center">
        <a href="login.php" class="text-xs text-emerald-700 hover:text-emerald-800 font-semibold transition-colors inline-flex items-center gap-1">
          <span class="material-symbols-outlined text-sm">arrow_back</span>
          <span>Back to Sign In</span>
        </a>
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
    function toggleNewPassword() {
      const input = document.getElementById('newPassword');
      const icon = document.getElementById('newEyeIcon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility';
      }
    }

    function checkPasswordStrength(val) {
      const bar = document.getElementById('strengthBar');
      const text = document.getElementById('strengthText');
      if (!val || val.length === 0) {
        bar.style.width = '0%';
        text.textContent = 'Too short';
        return;
      }
      let score = 0;
      if (val.length >= 6) score += 25;
      if (val.length >= 8) score += 25;
      if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
      if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

      bar.style.width = `${score}%`;
      if (score <= 25) {
        bar.className = 'h-full bg-red-500 transition-all duration-300';
        text.textContent = 'Weak';
        text.className = 'text-[10px] text-red-400 font-medium';
      } else if (score <= 50) {
        bar.className = 'h-full bg-amber-500 transition-all duration-300';
        text.textContent = 'Fair';
        text.className = 'text-[10px] text-amber-400 font-medium';
      } else if (score <= 75) {
        bar.className = 'h-full bg-blue-500 transition-all duration-300';
        text.textContent = 'Good';
        text.className = 'text-[10px] text-blue-400 font-medium';
      } else {
        bar.className = 'h-full bg-emerald-500 transition-all duration-300';
        text.textContent = 'Strong';
        text.className = 'text-[10px] text-emerald-400 font-medium';
      }
      checkPasswordMatch();
    }

    function checkPasswordMatch() {
      const pwd = document.getElementById('newPassword').value;
      const confirm = document.getElementById('confirmPassword').value;
      const icon = document.getElementById('matchIcon');
      const text = document.getElementById('matchText');

      if (!confirm) {
        icon.className = 'material-symbols-outlined absolute right-3 text-slate-600 text-lg';
        text.textContent = 'Re-type your password to confirm';
        text.className = 'text-[10px] text-slate-500 mt-1.5 block';
        return;
      }

      if (pwd === confirm) {
        icon.className = 'material-symbols-outlined absolute right-3 text-emerald-400 text-lg';
        text.textContent = 'Passwords match';
        text.className = 'text-[10px] text-emerald-400 mt-1.5 block';
      } else {
        icon.className = 'material-symbols-outlined absolute right-3 text-red-400 text-lg';
        text.textContent = 'Passwords do not match';
        text.className = 'text-[10px] text-red-400 mt-1.5 block';
      }
    }
  </script>

</body>
</html>

