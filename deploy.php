<?php
/**
 * SIA 2027 Election Intelligence & Party Agent Command Portal
 * Automated Deployment & First-Run Setup Engine
 * 
 * Supports both CLI execution (`php deploy.php --cli`)
 * and Web Browser wizard (`http://your-domain/deploy.php`).
 * Self-locks with `database/installed.lock` upon completion.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$is_cli = (php_sapi_name() === 'cli' || defined('STDIN'));
$root_dir = __DIR__;
$lock_file = $root_dir . '/database/installed.lock';

// Pre-flight System Diagnostics
$diagnostics = [
    'php_version' => [
        'name' => 'PHP Version (>= 8.1)',
        'required' => true,
        'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'value' => PHP_VERSION,
        'hint' => 'Upgrade PHP to 8.1, 8.2, 8.3, or 8.4 in cPanel Select PHP Version.'
    ],
    'pdo' => [
        'name' => 'PDO Extension',
        'required' => true,
        'ok' => extension_loaded('pdo'),
        'value' => extension_loaded('pdo') ? 'Installed' : 'Missing',
        'hint' => 'Enable pdo extension in php.ini or cPanel.'
    ],
    'pdo_sqlite' => [
        'name' => 'PDO SQLite Driver',
        'required' => true,
        'ok' => extension_loaded('pdo_sqlite'),
        'value' => extension_loaded('pdo_sqlite') ? 'Installed' : 'Missing',
        'hint' => 'Enable pdo_sqlite extension in cPanel PHP extensions.'
    ],
    'fileinfo' => [
        'name' => 'FileInfo (MIME verification)',
        'required' => true,
        'ok' => extension_loaded('fileinfo'),
        'value' => extension_loaded('fileinfo') ? 'Installed' : 'Missing',
        'hint' => 'Enable fileinfo extension to validate EC8A result sheet images.'
    ],
    'gd' => [
        'name' => 'GD Library (Image Handling)',
        'required' => false,
        'ok' => extension_loaded('gd'),
        'value' => extension_loaded('gd') ? 'Installed' : 'Optional (Missing)',
        'hint' => 'Recommended for photo previews and EC8A image verification.'
    ],
    'mbstring' => [
        'name' => 'Multibyte String (mbstring)',
        'required' => true,
        'ok' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'Installed' : 'Missing',
        'hint' => 'Enable mbstring for UTF-8 electoral data handling.'
    ]
];

// Ensure database and uploads folders exist with permissions
@mkdir($root_dir . '/database', 0777, true);
@chmod($root_dir . '/database', 0777);
@mkdir($root_dir . '/uploads', 0777, true);
@chmod($root_dir . '/uploads', 0777);
@mkdir($root_dir . '/database/seed', 0777, true);

// Directory writability diagnostics with active file write test
$test_db_file = $root_dir . '/database/.perm_test';
$can_write_db = (@file_put_contents($test_db_file, '1') !== false);
if ($can_write_db) { @unlink($test_db_file); }
$database_writable = is_writable($root_dir . '/database') || $can_write_db;

$test_up_file = $root_dir . '/uploads/.perm_test';
$can_write_up = (@file_put_contents($test_up_file, '1') !== false);
if ($can_write_up) { @unlink($test_up_file); }
$uploads_writable = is_writable($root_dir . '/uploads') || $can_write_up;

$diagnostics['db_write'] = [
    'name' => 'Database Directory Writable (database/)',
    'required' => true,
    'ok' => $database_writable,
    'value' => $database_writable ? 'Writable' : 'Read-Only (chmod 777 needed)',
    'hint' => 'In cPanel File Manager: right-click database folder -> Change Permissions -> Set to 777 or 755.'
];

$diagnostics['uploads_write'] = [
    'name' => 'Uploads Directory Writable (uploads/)',
    'required' => true,
    'ok' => $uploads_writable,
    'value' => $uploads_writable ? 'Writable' : 'Read-Only (chmod 777 needed)',
    'hint' => 'In cPanel File Manager: right-click uploads folder -> Change Permissions -> Set to 777 or 755.'
];

$all_required_ok = true;
foreach ($diagnostics as $d) {
    if ($d['required'] && !$d['ok']) {
        $all_required_ok = false;
        break;
    }
}

// Function to run the full setup
function run_full_setup(array $options = []): array {
    global $root_dir, $lock_file;

    require_once $root_dir . '/config.php';
    require_once $root_dir . '/db.php';
    require_once $root_dir . '/lib/audit.php';
    require_once $root_dir . '/lib/settings.php';

    $logs = [];
    $logs[] = "Connecting to database layer...";
    $pdo = get_db_connection();

    // 1. Run Auto-Migrations
    $logs[] = "Executing idempotent database auto-migrations (13 tables)...";
    migrate_database($pdo);
    $total_lgas = (int)$pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn();
    $total_wards = (int)$pdo->query("SELECT COUNT(*) FROM wards")->fetchColumn();
    $total_pus = (int)$pdo->query("SELECT COUNT(*) FROM polling_units")->fetchColumn();
    $logs[] = "Tables verified: lgas, wards, polling_units, admins, result_sheets, result_sheet_revisions, audit_log, incidents, announcements, announcement_reads, settings, login_attempts, agents.";
    $logs[] = "Official Adamawa Geography synchronized: {$total_lgas} LGAs, {$total_wards} Wards, {$total_pus} Polling Units.";

    // 2. Baseline Settings
    $logs[] = "Initializing election operational parameters...";
    set_setting($pdo, 'election_name', 'SIA 2027 Adamawa State Governorship Collation');
    set_setting($pdo, 'election_date', '2027-02-06');
    set_setting($pdo, 'submissions_open', '1');
    set_setting($pdo, 'public_results_visible', '1');
    set_setting($pdo, 'public_label_text', 'Party agent tallies, unofficial. Not INEC results.');
    set_setting($pdo, 'require_review_before_public', '1');
    set_setting($pdo, 'max_upload_mb', '10');

    // 3. Ensure Baseline Party Agent exists
    $logs[] = "Configuring primary seed Party Agent (agent@sia2027.org)...";
    $agent_stmt = $pdo->prepare("
        INSERT INTO agents (name, email, password_hash, phone, role, state, lga, ward, polling_unit, agent_code, status, created_at)
        VALUES (?, ?, ?, ?, 'agent', 'Adamawa State', ?, ?, ?, ?, 'accredited', datetime('now'))
        ON CONFLICT(email) DO UPDATE SET status = 'accredited'
    ");
    $agent_stmt->execute([
        'Ibrahim Sani',
        'agent@sia2027.org',
        password_hash('password123', PASSWORD_BCRYPT),
        '+234 803 123 4567',
        'Yola North',
        'Alkalawa Ward',
        'PU 001 - Central Primary School, Yola',
        'SIA-LP-AD-2027-0104'
    ]);

    // 4. Configure Superadmin
    $admin_email = !empty($options['admin_email']) ? strtolower(trim($options['admin_email'])) : 'superadmin@sia2027.ng';
    $admin_name = !empty($options['admin_name']) ? trim($options['admin_name']) : 'Lead Superadministrator';
    $admin_password = !empty($options['admin_password']) ? trim($options['admin_password']) : 'Superadmin2027!';
    $must_change = !empty($options['admin_password']) ? 0 : 1;

    $check_admin = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
    $check_admin->execute([$admin_email]);
    $existing_admin_id = $check_admin->fetchColumn();

    if (!$existing_admin_id) {
        $logs[] = "Creating Superadministrator account: {$admin_email}...";
        $ins_admin = $pdo->prepare("
            INSERT INTO admins (name, email, password_hash, role, lga_id, status, must_change_password, created_at)
            VALUES (?, ?, ?, 'superadmin', NULL, 'active', ?, datetime('now'))
        ");
        $ins_admin->execute([
            $admin_name,
            $admin_email,
            password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]),
            $must_change
        ]);
        $new_admin_id = (int)$pdo->lastInsertId();
        audit_log($pdo, 'system', $new_admin_id, 'deployment_superadmin_created', 'admins', $new_admin_id, [
            'email' => $admin_email,
            'name' => $admin_name
        ]);
    } else {
        $logs[] = "Updating existing Superadministrator credentials for: {$admin_email}...";
        $upd_admin = $pdo->prepare("
            UPDATE admins 
            SET password_hash = ?, 
                status = 'active', 
                must_change_password = ? 
            WHERE id = ?
        ");
        $upd_admin->execute([
            password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]),
            $must_change,
            $existing_admin_id
        ]);
    }

    // 5. Seed Optional Demo Dataset if requested
    $demo_seeded = false;
    if (!empty($options['seed_demo'])) {
        $logs[] = "Seeding demo simulation suite (administrators, 30 agents, 100 EC8A sheets, 20 incidents)...";
        if (file_exists($root_dir . '/seed_demo.php')) {
            // Execute demo seeder silently
            ob_start();
            $argv = ['seed_demo.php', '--demo'];
            $_SERVER['argv'] = $argv;
            include $root_dir . '/seed_demo.php';
            ob_end_clean();
            $demo_seeded = true;
            $logs[] = "Demo simulation suite loaded successfully.";
        }
    }

    // 6. Write Lock File
    $lock_data = [
        'deployed_at' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'superadmin_email' => $admin_email,
        'demo_seeded' => $demo_seeded
    ];
    @file_put_contents($lock_file, json_encode($lock_data, JSON_PRETTY_PRINT));
    $logs[] = "Setup locked successfully (database/installed.lock created).";

    return [
        'success' => true,
        'logs' => $logs,
        'admin_email' => $admin_email,
        'admin_password' => $admin_password,
        'demo_seeded' => $demo_seeded
    ];
}

// -------------------------------------------------------------
// CLI EXECUTION HANDLER
// -------------------------------------------------------------
if ($is_cli) {
    echo "=================================================================\n";
    echo " SIA 2027 ELECTION INTELLIGENCE PORTAL - AUTOMATED DEPLOYMENT\n";
    echo "=================================================================\n\n";

    echo "--> Checking System Environment:\n";
    foreach ($diagnostics as $key => $diag) {
        $status = $diag['ok'] ? "[OK]" : ($diag['required'] ? "[FAIL]" : "[WARN]");
        printf("  %-45s %-20s %s\n", $diag['name'], $diag['value'], $status);
    }
    echo "\n";

    if (!$all_required_ok) {
        echo "[ERROR] System environment does not satisfy all minimum requirements.\n";
        echo "Please resolve the failed checks above and re-run.\n";
        exit(1);
    }

    $with_demo = in_array('--demo', $argv ?? []) || in_array('--seed-demo', $argv ?? []);
    $custom_email = null;
    $custom_pass = null;

    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--admin-email=')) {
            $custom_email = substr($arg, 14);
        }
        if (str_starts_with($arg, '--admin-password=')) {
            $custom_pass = substr($arg, 17);
        }
    }

    echo "--> Running Deployment & Database Initialization...\n";
    $result = run_full_setup([
        'admin_email' => $custom_email,
        'admin_password' => $custom_pass,
        'seed_demo' => $with_demo
    ]);

    foreach ($result['logs'] as $log) {
        echo "  [OK] {$log}\n";
    }

    echo "\n=================================================================\n";
    echo " DEPLOYMENT COMPLETED SUCCESSFULLY!\n";
    echo "=================================================================\n\n";
    echo "Portal Access Coordinates:\n";
    echo "-----------------------------------------------------------------\n";
    echo "1. Public Collation Live Results:  http://127.0.0.1:8080/index.php\n";
    echo "2. Party Agent Collation Portal:   http://127.0.0.1:8080/login.php\n";
    echo "   - Agent Email:     agent@sia2027.org\n";
    echo "   - Agent Password:  password123\n";
    echo "3. Admin Command Center:           http://127.0.0.1:8080/admin_login.php\n";
    echo "   - Admin Email:     {$result['admin_email']}\n";
    echo "   - Admin Password:  {$result['admin_password']}\n";
    if ($result['demo_seeded']) {
        echo "   - Demo Agents:     agent1.demo@sia2027.ng to agent30.demo@sia2027.ng\n";
        echo "   - Demo Agent Pass: AgentPassword2027!\n";
    }
    echo "-----------------------------------------------------------------\n";
    echo "To reset or re-run setup, delete `database/installed.lock`.\n\n";
    exit(0);
}

// -------------------------------------------------------------
// WEB BROWSER WIZARD HANDLER
// -------------------------------------------------------------
require_once $root_dir . '/config.php';
require_once $root_dir . '/lib/csrf.php';

$already_locked = file_exists($lock_file);
$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_locked) {
    try {
        require_csrf();
        if (!$all_required_ok) {
            throw new Exception("System requirements are not satisfied. Please resolve issues highlighted below.");
        }

        $admin_name = trim($_POST['admin_name'] ?? 'Lead Superadministrator');
        $admin_email = trim($_POST['admin_email'] ?? 'superadmin@sia2027.ng');
        $admin_password = $_POST['admin_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $seed_demo = !empty($_POST['seed_demo']);

        if (!empty($admin_password)) {
            if (strlen($admin_password) < 8) {
                throw new Exception("Superadmin password must be at least 8 characters long.");
            }
            if ($admin_password !== $confirm_password) {
                throw new Exception("Passwords do not match.");
            }
        } else {
            $admin_password = 'Superadmin2027!';
        }

        $result = run_full_setup([
            'admin_name' => $admin_name,
            'admin_email' => $admin_email,
            'admin_password' => $admin_password,
            'seed_demo' => $seed_demo
        ]);

        $already_locked = true;
    } catch (Exception $e) {
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
  <title>System Deployment &amp; Setup • SIA 2027</title>
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
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 sm:p-6 selection:bg-emerald-600 selection:text-white">

  <div class="w-full max-w-2xl space-y-6">

    <!-- Header Branding -->
    <div class="text-center space-y-2">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 text-white font-black text-2xl shadow-lg shadow-emerald-600/30 mb-1">
        SIA
      </div>
      <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white m-0">
        Portal Deployment &amp; Setup
      </h1>
      <p class="text-xs sm:text-sm text-slate-400 font-normal max-w-lg mx-auto">
        SIA 2027 Election Intelligence &amp; Party Agent Command Portal (Adamawa State Collation).
      </p>
    </div>

    <?php if ($result && $result['success']): ?>
    <!-- SUCCESS DEPLOYMENT REPORT -->
    <div class="bg-slate-900/90 border border-emerald-500/40 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md space-y-6">
      <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
        <div class="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
          <span class="material-symbols-outlined text-xl">check_circle</span>
        </div>
        <div>
          <h2 class="text-lg font-bold text-white m-0">Deployment Completed &amp; Locked</h2>
          <span class="text-xs text-emerald-400">Database migrated, baseline data seeded, and system protected.</span>
        </div>
      </div>

      <!-- Execution Log -->
      <div class="bg-slate-950 rounded-2xl p-4 font-mono text-xs space-y-1 text-slate-300 max-h-48 overflow-y-auto border border-slate-800">
        <?php foreach ($result['logs'] as $log): ?>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
            <span><?= htmlspecialchars($log) ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Credentials Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">Admin Command</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Superadmin</span>
          </div>
          <div class="text-xs space-y-1">
            <div><span class="text-slate-500">Email:</span> <strong class="text-white"><?= htmlspecialchars($result['admin_email']) ?></strong></div>
            <div><span class="text-slate-500">Password:</span> <code class="text-emerald-300 bg-slate-900 px-1.5 py-0.5 rounded"><?= htmlspecialchars($result['admin_password']) ?></code></div>
          </div>
          <a href="admin_login.php" class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:text-emerald-300 font-semibold pt-1">
            Open Admin Login &rarr;
          </a>
        </div>

        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">Party Agent Portal</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Field Agent</span>
          </div>
          <div class="text-xs space-y-1">
            <div><span class="text-slate-500">Email:</span> <strong class="text-white">agent@sia2027.org</strong></div>
            <div><span class="text-slate-500">Password:</span> <code class="text-emerald-300 bg-slate-900 px-1.5 py-0.5 rounded">password123</code></div>
          </div>
          <a href="index.php#portal" class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:text-emerald-300 font-semibold pt-1">
            Open Agent Portal &rarr;
          </a>
        </div>
      </div>

      <div class="pt-2 flex flex-col sm:flex-row gap-3">
        <a href="index.php" class="flex-1 text-center bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 px-6 rounded-full text-sm shadow-lg shadow-emerald-600/20 transition-all text-decoration-none">
          Launch Public Dashboard
        </a>
        <a href="admin_login.php" class="flex-1 text-center bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-full text-sm transition-all text-decoration-none">
          Sign In to Admin Command
        </a>
      </div>
    </div>

    <?php elseif ($already_locked): ?>
    <!-- ALREADY INSTALLED & LOCKED VIEW -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md space-y-6 text-center">
      <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
        <span class="material-symbols-outlined text-2xl">lock</span>
      </div>
      <div class="space-y-1">
        <h2 class="text-xl font-bold text-white m-0">System Already Deployed &amp; Locked</h2>
        <p class="text-xs text-slate-400 max-w-md mx-auto">
          The deployment file has already run. For security, setup cannot be re-executed without removing the lock file <code class="text-emerald-400">database/installed.lock</code>.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
        <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-white font-semibold py-3 px-4 rounded-xl text-sm transition-all text-decoration-none">
          Live Public Results &rarr;
        </a>
        <a href="admin_login.php" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-4 rounded-xl text-sm shadow-md transition-all text-decoration-none">
          Admin Command Login &rarr;
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- ACTIVE INSTALLATION WIZARD -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md space-y-6">

      <?php if ($error): ?>
      <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs sm:text-sm flex items-center gap-2.5">
        <span class="material-symbols-outlined text-red-400 text-base">warning</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <!-- Pre-flight Status -->
      <div class="space-y-3">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 m-0">Pre-Flight System Check</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
          <?php foreach ($diagnostics as $key => $d): ?>
            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800/80 flex items-center justify-between">
              <span class="text-slate-300"><?= htmlspecialchars($d['name']) ?></span>
              <span class="font-bold font-mono px-2 py-0.5 rounded text-[10px] <?= $d['ok'] ? 'bg-emerald-500/20 text-emerald-400' : ($d['required'] ? 'bg-red-500/20 text-red-400' : 'bg-amber-500/20 text-amber-300') ?>">
                <?= htmlspecialchars($d['value']) ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!$all_required_ok): ?>
      <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs space-y-2">
        <div class="font-bold flex items-center gap-2">
          <span class="material-symbols-outlined text-amber-400 text-base">warning</span>
          <span>Please resolve permission or extension requirements before continuing:</span>
        </div>
        <ul class="list-disc list-inside space-y-1 text-slate-400 text-[11px]">
          <?php foreach ($diagnostics as $d): ?>
            <?php if ($d['required'] && !$d['ok']): ?>
              <li><strong class="text-amber-200"><?= htmlspecialchars($d['name']) ?>:</strong> <?= htmlspecialchars($d['hint']) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Deployment Configuration Form -->
      <form method="POST" action="deploy.php" class="space-y-4 pt-2">
        <?= csrf_field() ?>

        <div class="border-t border-slate-800 pt-4 space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 m-0">Administrator Account</h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Superadmin Name</label>
              <input type="text" name="admin_name" value="Lead Superadministrator" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-sm text-white outline-none">
            </div>

            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Superadmin Email</label>
              <input type="email" name="admin_email" value="superadmin@sia2027.ng" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-sm text-white outline-none">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Superadmin Master Password</label>
              <input type="password" name="admin_password" value="Superadmin2027!" required class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-sm text-white outline-none">
            </div>

            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Confirm Master Password</label>
              <input type="password" name="confirm_password" value="Superadmin2027!" required class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-sm text-white outline-none">
            </div>
          </div>
          <p class="text-[11px] text-slate-500 italic m-0">Pre-filled with default password <code class="text-emerald-400">Superadmin2027!</code>. You may change this now or update it in the admin dashboard later.</p>
        </div>

        <div class="border-t border-slate-800 pt-4">
          <label class="flex items-center gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800 cursor-pointer hover:border-slate-700 transition-colors">
            <input type="checkbox" name="seed_demo" value="1" checked class="w-4 h-4 text-emerald-600 rounded bg-slate-900 border-slate-700 focus:ring-emerald-500 cursor-pointer">
            <div class="text-xs">
              <span class="font-bold text-white block">Pre-populate Demo Simulation Suite</span>
              <span class="text-slate-400">Seeds 30 party agents, 100 EC8A result sheets, and 20 incidents for instant demonstration.</span>
            </div>
          </label>
        </div>

        <div class="pt-2">
          <button type="submit" <?= !$all_required_ok ? 'disabled' : '' ?> class="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-full text-sm shadow-lg shadow-emerald-600/20 transition-all cursor-pointer">
            Run Everything &amp; Initialize Portal &rarr;
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Security Footnote -->
    <div class="text-center text-xs text-slate-500 space-y-1">
      <p>SIA 2027 Election Intelligence &bull; Adamawa State Command Headquarters</p>
      <p>Self-contained vanilla PHP 8.4 + SQLite architecture &bull; Zero Node/Composer dependencies</p>
    </div>

  </div>

</body>
</html>

