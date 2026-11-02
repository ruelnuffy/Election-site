<?php
/**
 * SIA 2027 Election Intelligence & Party Agent Portal
 * One-Time First Run Setup for Superadmin
 * Self-locks permanently after the first administrator is created.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();
$pdo = get_db_connection();

// Check if any admin exists
$admin_count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($admin_count > 0) {
    // Permanently locked
    header('Location: admin_login.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            throw new Exception('All fields are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid official email address.');
        }

        if (strlen($password) < 10) {
            throw new Exception('Password must be at least 10 characters long for security compliance.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Password confirmation does not match.');
        }

        // Double check admin count under transaction
        $pdo->beginTransaction();
        $count_check = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($count_check > 0) {
            $pdo->rollBack();
            throw new Exception('Setup has already been locked by another session.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("
            INSERT INTO admins (name, email, password_hash, role, status, must_change_password, created_at)
            VALUES (?, ?, ?, 'superadmin', 'active', 0, datetime('now'))
        ");
        $stmt->execute([$name, $email, $hash]);
        $admin_id = (int)$pdo->lastInsertId();

        audit_log($pdo, 'admin', $admin_id, 'first_run_setup', 'admins', $admin_id, [
            'email' => $email,
            'name' => $name,
            'note' => 'One-time superadmin account created. Setup permanently locked.'
        ]);

        $pdo->commit();

        // Log the newly created superadmin in securely
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id' => $admin_id,
            'name' => $name,
            'email' => $email,
            'role' => 'superadmin',
            'lga_id' => null,
            'status' => 'active',
            'must_change_password' => 0
        ];

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Superadmin account initialized successfully. One-time setup is now permanently locked.'
        ];

        header('Location: admin.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>First-Run Superadmin Setup • SIA 2027 Command Center</title>
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
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 selection:bg-emerald-600 selection:text-white">

  <div class="w-full max-w-md space-y-6">
    <!-- Header Card -->
    <div class="text-center space-y-2">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 text-white font-black text-2xl shadow-lg shadow-emerald-600/30 mb-2">
        SIA
      </div>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white m-0">Initial System Setup</h1>
      <p class="text-xs sm:text-sm text-slate-400 font-light">
        Create the primary <strong>Superadministrator</strong> account for Adamawa State Command Headquarters. This page self-locks permanently once submitted.
      </p>
    </div>

    <!-- Setup Box -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md space-y-6">
      
      <?php if ($error): ?>
      <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs sm:text-sm flex items-center gap-2.5">
        <span class="material-symbols-outlined text-red-400 text-base">warning</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="setup.php" class="space-y-4">
        <?= csrf_field() ?>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Superadmin Full Name</label>
          <input type="text" name="name" required placeholder="e.g. Lead Electoral Director" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-4 py-3 text-sm text-white outline-none transition-all placeholder:text-slate-600">
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Official Email Address</label>
          <input type="email" name="email" required placeholder="superadmin@sia2027.ng" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-4 py-3 text-sm text-white outline-none transition-all placeholder:text-slate-600">
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Master Password (min 10 characters)</label>
          <input type="password" name="password" minlength="10" required placeholder="••••••••••••" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-4 py-3 text-sm text-white outline-none transition-all placeholder:text-slate-600">
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Confirm Master Password</label>
          <input type="password" name="confirm_password" minlength="10" required placeholder="••••••••••••" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-4 py-3 text-sm text-white outline-none transition-all placeholder:text-slate-600">
        </div>

        <div class="pt-2">
          <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-full text-sm shadow-lg shadow-emerald-600/20 transition-all cursor-pointer">
            Initialize Superadmin &amp; Lock Setup &rarr;
          </button>
        </div>
      </form>
    </div>

    <!-- Security Footnote -->
    <div class="text-center text-xs text-slate-500 space-y-1">
      <p>Protected by cryptographic bcrypt hashing and audit telemetry.</p>
      <p>SIA 2027 &bull; Adamawa State Electoral Command</p>
    </div>
  </div>

</body>
</html>

