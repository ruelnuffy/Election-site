<?php
/**
 * SIA 2027 Party Agent Portal - Password Recovery Request
 * Secure self-service token generation for polling unit agents
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();
$pdo = get_db_connection();

$error = null;
$success = null;
$generated_link = null;
$ip = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();

        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            throw new Exception('Please enter your registered email address or your official Agent ID.');
        }

        // Rate limiting check: 5 attempts per 15 minutes
        if (is_login_rate_limited($pdo, $ip, 'pwd_reset_' . $identifier, 5, 15)) {
            audit_log($pdo, 'anonymous', null, 'password_reset_throttled', 'agents', null, [
                'ip' => $ip,
                'identifier' => $identifier
            ]);
            throw new Exception('Too many recovery requests. Please wait 15 minutes before trying again.');
        }

        // Look up agent by email or agent_code
        $stmt = $pdo->prepare("
            SELECT * FROM agents 
            WHERE LOWER(email) = LOWER(?) OR UPPER(agent_code) = UPPER(?) 
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $agent = $stmt->fetch();

        if ($agent) {
            // Generate secure 64-hex-char token
            $token = bin2hex(random_bytes(32));

            // Token valid for 1 hour
            $upd = $pdo->prepare("
                UPDATE agents 
                SET password_reset_token = ?, 
                    password_reset_expiry = datetime('now', '+1 hour') 
                WHERE id = ?
            ");
            $upd->execute([$token, $agent['id']]);

            // Construct direct reset URL
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
            $generated_link = "{$scheme}://{$host}/reset_password.php?token={$token}&email=" . urlencode($agent['email']);

            // Attempt email dispatch via mail()
            $subject = "SIA 2027 Agent Password Reset - Adamawa State";
            $message = "Hello {$agent['name']},\n\n";
            $message .= "A password reset request was submitted for your SIA 2027 Party Agent account ({$agent['agent_code']}).\n\n";
            $message .= "Click the secure link below to reset your password (valid for 1 hour):\n";
            $message .= "{$generated_link}\n\n";
            $message .= "If you did not request this, you can safely disregard this email.\n\n";
            $message .= "SIA 2027 Electoral Command Headquarters\nAdamawa State";
            $headers = "From: no-reply@sia2027.org\r\nX-Mailer: PHP/" . phpversion();

            @mail($agent['email'], $subject, $message, $headers);

            // Audit log
            audit_log($pdo, 'agent', (int)$agent['id'], 'agent_password_reset_requested', 'agents', (int)$agent['id'], [
                'ip' => $ip,
                'email' => $agent['email'],
                'agent_code' => $agent['agent_code']
            ]);

            $success = "Password recovery instructions generated successfully for {$agent['name']}.";
        } else {
            // Record rate limit attempt
            record_login_attempt($pdo, $ip, 'pwd_reset_' . $identifier);
            // Even if not found, we display a safe confirmation message to prevent enumeration
            $success = "If an accredited account matches that identifier, password recovery instructions have been prepared.";
        }

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
  <title>Reset Agent Password &bull; SIA 2027</title>
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
      <a href="index.php" class="inline-flex items-center justify-center gap-2 group text-decoration-none">
        <div class="w-12 h-12 rounded-2xl bg-emerald-600 group-hover:bg-emerald-700 text-white font-bold text-lg flex items-center justify-center shadow-lg shadow-emerald-600/20 transition-transform group-hover:scale-105">
          SIA
        </div>
      </a>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 m-0">Reset password</h1>
      <p class="text-xs sm:text-sm text-slate-500 font-normal">
        Enter your email or Agent ID to receive a recovery link
      </p>
    </div>

    <!-- Recovery Card -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 space-y-5">

      <?php if ($error): ?>
      <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5">
        <span class="material-symbols-outlined text-red-500 text-base flex-shrink-0 mt-0.5">warning</span>
        <span class="flex-1"><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5">
        <span class="material-symbols-outlined text-emerald-600 text-base flex-shrink-0 mt-0.5">check_circle</span>
        <span class="flex-1"><?= htmlspecialchars($success) ?></span>
      </div>

        <?php if ($generated_link): ?>
        <!-- Field Agent Offline / Direct Recovery Box -->
        <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200/80 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-emerald-800 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">link</span>
              Reset Link
            </span>
          </div>
          <div class="relative flex items-center">
            <input 
              type="text" 
              id="resetLinkField" 
              readonly 
              value="<?= htmlspecialchars($generated_link) ?>" 
              class="w-full bg-white border border-emerald-300 rounded-xl px-3 py-2 text-xs text-emerald-950 outline-none font-mono select-all"
            >
          </div>
          <div class="flex items-center gap-2 pt-1">
            <button 
              type="button" 
              onclick="copyResetLink()" 
              id="copyBtn" 
              class="flex-1 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer border border-slate-300 shadow-sm"
            >
              <span class="material-symbols-outlined text-sm">content_copy</span>
              <span id="copyBtnText">Copy Link</span>
            </button>
            <a 
              href="<?= htmlspecialchars($generated_link) ?>" 
              class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-md shadow-emerald-600/20"
            >
              <span>Reset Now &rarr;</span>
            </a>
          </div>
        </div>
        <?php endif; ?>

      <?php else: ?>

      <form method="POST" action="forgot_password.php" class="space-y-4">
        <?= csrf_field() ?>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">
            Email or Agent ID
          </label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">fingerprint</span>
            <input 
              type="text" 
              name="identifier" 
              required 
              placeholder="name@example.com or Agent Code" 
              value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" 
              class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
            >
          </div>
        </div>

        <div class="pt-2">
          <button 
            type="submit" 
            class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-semibold py-3 rounded-full text-sm shadow-md shadow-emerald-600/20 transition-all cursor-pointer flex items-center justify-center gap-2"
          >
            <span>Send Reset Link</span>
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </button>
        </div>
      </form>

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
    function copyResetLink() {
      const input = document.getElementById('resetLinkField');
      if (!input) return;
      input.select();
      input.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(input.value).then(() => {
        const text = document.getElementById('copyBtnText');
        text.textContent = 'Copied!';
        setTimeout(() => {
          text.textContent = 'Copy Link';
        }, 2500);
      }).catch(() => {
        document.execCommand('copy');
      });
    }
  </script>

</body>
</html>

