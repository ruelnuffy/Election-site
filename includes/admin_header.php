<?php
/**
 * SIA 2027 Admin Command Center - Shared Header & Navigation
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();

// Enforce admin authentication
$admin = require_admin_auth();
$pdo = get_db_connection();

// Refresh admin details from DB
$adm_stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$adm_stmt->execute([$admin['id']]);
$db_admin = $adm_stmt->fetch();
if (!$db_admin || $db_admin['status'] !== 'active') {
    unset($_SESSION['admin']);
    header('Location: admin_login.php');
    exit;
}
$admin = $db_admin;
$_SESSION['admin'] = $admin;

$role = $admin['role']; // superadmin | coordinator | analyst
$coordinator_lga_id = $admin['lga_id'] ? (int)$admin['lga_id'] : null;
$coordinator_lga_name = null;
if ($coordinator_lga_id) {
    $lga_stmt = $pdo->prepare("SELECT name FROM lgas WHERE id = ?");
    $lga_stmt->execute([$coordinator_lga_id]);
    $coordinator_lga_name = $lga_stmt->fetchColumn() ?: null;
}

$page_title = $page_title ?? 'Command Center';
$active_page = $active_page ?? 'overview';

// Restrict superadmin-only pages
if (in_array($active_page, ['settings', 'admin-users'], true) && $role !== 'superadmin') {
    header('Location: admin_overview.php');
    exit;
}

$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
  <title><?= htmlspecialchars($page_title) ?> • SIA 2027 Command Center</title>
  <link rel="icon" type="image/png" href="./assets/images/logos/lp_logo.png">

  <!-- Typography & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', '"Outfit"', 'system-ui', 'sans-serif'],
          },
          colors: {
            lp: {
              DEFAULT: '#008751',
              dark: '#00683e',
              light: '#e6f4ee'
            }
          }
        }
      }
    }
  </script>

  <style>
    body {
      font-family: 'Plus Jakarta Sans', 'Outfit', ui-sans-serif, system-ui, -apple-system, sans-serif;
      letter-spacing: -0.015em;
      background-color: #f8fafc;
      color: #0f172a;
    }
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
    .top-glass-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 1.25rem;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 9999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
    .spinning {
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
  </style>
</head>
<body data-page="<?= htmlspecialchars($active_page) ?>" class="h-full flex overflow-hidden bg-slate-50 text-slate-900 antialiased">

  <!-- =========================================================================
       1. WHITE THEME SIDEBAR NAVIGATION
       ========================================================================= -->
  <aside id="sidebar" class="w-64 sm:w-72 bg-white border-r border-slate-200/90 flex flex-col justify-between z-30 transition-all duration-300">
    <div class="overflow-y-auto custom-scrollbar flex-1">
      <!-- Top Brand -->
      <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <a href="admin_overview.php" class="flex items-center gap-3 text-decoration-none group">
          <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 shadow-xs p-1 flex items-center justify-center overflow-hidden transition-transform group-hover:scale-105">
            <img src="./assets/images/logos/lp_logo.png" alt="Labour Party" class="w-full h-full object-contain">
          </div>
          <div>
            <div class="flex items-center gap-1.5">
              <span class="text-slate-900 font-bold text-base tracking-tight">SIA 2027</span>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                HQ
              </span>
            </div>
            <span class="text-[11px] text-slate-500 block font-medium">Command Dashboard</span>
          </div>
        </a>
      </div>

      <!-- Identity Card -->
      <div class="m-3 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-emerald-700 font-bold flex items-center justify-center text-xs shadow-xs">
          <?= strtoupper(substr($admin['name'], 0, 2)) ?>
        </div>
        <div class="min-w-0 flex-1">
          <span class="block text-xs font-bold text-slate-900 truncate"><?= htmlspecialchars($admin['name']) ?></span>
          <div class="flex items-center gap-1.5 mt-0.5">
            <?php if ($role === 'superadmin'): ?>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-200 uppercase">
                Superadmin
              </span>
            <?php elseif ($role === 'coordinator'): ?>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200 uppercase">
                Coordinator &bull; <?= htmlspecialchars($coordinator_lga_name ?: 'LGA') ?>
              </span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                Analyst
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Nav Items -->
      <nav class="px-3 py-2 space-y-1">
        <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Core Operations
        </div>

        <?php
        $nav_items = [
            ['page' => 'overview', 'href' => 'admin_overview.php', 'icon' => 'bar_chart', 'label' => 'Overview'],
            ['page' => 'senatorial', 'href' => 'admin_senatorial.php', 'icon' => 'hub', 'label' => 'Senatorial Districts'],
            ['page' => 'review-queue', 'href' => 'admin_review.php', 'icon' => 'receipt_long', 'label' => 'Review Queue', 'badge' => 'badgePendingQueue', 'badge_class' => 'bg-amber-100 text-amber-800 border border-amber-200'],
            ['page' => 'discrepancies', 'href' => 'admin_discrepancies.php', 'icon' => 'warning', 'label' => 'Discrepancies', 'badge' => 'badgeDiscrepancies', 'badge_class' => 'bg-rose-100 text-rose-700 border border-rose-200'],
            ['page' => 'agents', 'href' => 'admin_agents.php', 'icon' => 'badge', 'label' => 'Field Agents'],
            ['page' => 'coverage', 'href' => 'admin_coverage.php', 'icon' => 'pin_drop', 'label' => 'Coverage'],
            ['page' => 'incidents', 'href' => 'admin_incidents.php', 'icon' => 'emergency', 'label' => 'Incidents', 'badge' => 'badgeCriticalIncidents', 'badge_class' => 'hidden bg-rose-600 text-white animate-pulse'],
            ['page' => 'announcements', 'href' => 'admin_broadcasts.php', 'icon' => 'campaign', 'label' => 'Broadcasts'],
        ];

        foreach ($nav_items as $item) {
            $is_curr = ($active_page === $item['page']);
            $cls = $is_curr 
                ? 'bg-emerald-50 text-emerald-800 font-bold border-l-4 border-emerald-600 shadow-xs' 
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-semibold';
            ?>
            <a href="<?= $item['href'] ?>" id="nav-<?= $item['page'] ?>" class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors <?= $cls ?>">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-base <?= ($item['page'] === 'incidents' && $is_curr) ? 'text-rose-600' : '' ?>"><?= $item['icon'] ?></span>
                <span><?= $item['label'] ?></span>
              </div>
              <?php if (!empty($item['badge'])): ?>
                <span id="<?= $item['badge'] ?>" class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $item['badge_class'] ?>">0</span>
              <?php endif; ?>
            </a>
            <?php
        }
        ?>

        <div class="pt-3 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Governance &amp; Data
        </div>

        <?php
        $gov_items = [
            ['page' => 'reports', 'href' => 'admin_reports.php', 'icon' => 'download', 'label' => 'Reports & Exports'],
            ['page' => 'geography', 'href' => 'admin_geography.php', 'icon' => 'map', 'label' => 'Geography & PUs'],
        ];
        if ($role === 'superadmin') {
            $gov_items[] = ['page' => 'settings', 'href' => 'admin_settings.php', 'icon' => 'settings', 'label' => 'Settings'];
            $gov_items[] = ['page' => 'admin-users', 'href' => 'admin_users.php', 'icon' => 'shield', 'label' => 'Admin Users'];
        }

        foreach ($gov_items as $item) {
            $is_curr = ($active_page === $item['page']);
            $cls = $is_curr 
                ? 'bg-emerald-50 text-emerald-800 font-bold border-l-4 border-emerald-600 shadow-xs' 
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-semibold';
            ?>
            <a href="<?= $item['href'] ?>" id="nav-<?= $item['page'] ?>" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs transition-colors <?= $cls ?>">
              <span class="material-symbols-outlined text-base"><?= $item['icon'] ?></span>
              <span><?= $item['label'] ?></span>
            </a>
            <?php
        }
        ?>
      </nav>
    </div>

    <!-- Bottom Sidebar -->
    <div class="p-4 border-t border-slate-100 bg-white">
      <div class="flex items-center gap-2">
        <button type="button" onclick="openPasswordModal()" class="flex-1 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-all text-center flex items-center justify-center gap-1.5 cursor-pointer">
          <span class="material-symbols-outlined text-sm">lock</span>
          <span>Password</span>
        </button>
        <a href="logout.php" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-all">
          Logout
        </a>
      </div>
    </div>
  </aside>

  <!-- =========================================================================
       2. MAIN CONTENT STAGE
       ========================================================================= -->
  <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
    <!-- Top Live Ribbon -->
    <header class="h-16 border-b border-slate-200/80 px-6 sm:px-8 flex items-center justify-between bg-white flex-shrink-0 z-20">
      <div class="flex items-center gap-3">
        <h2 id="topPageTitle" class="text-lg font-bold text-slate-900 m-0 tracking-tight"><?= htmlspecialchars($page_title) ?></h2>
        <?php if ($coordinator_lga_name): ?>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
            LGA: <?= htmlspecialchars($coordinator_lga_name) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-3">
        <button onclick="refreshCurrentModule(this)" id="btnManualRefresh" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-all cursor-pointer">
          <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          <span>Sync</span>
        </button>

        <a href="index.php" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 bg-white border border-slate-200 hover:border-slate-300 transition-colors">
          <span>Public Gateway</span>
          <span class="text-slate-400">↗</span>
        </a>
      </div>
    </header>

    <!-- Main Dynamic Container with clean spacing -->
    <main class="flex-1 overflow-y-auto custom-scrollbar p-6 sm:p-8 space-y-6 bg-slate-50" id="mainContainer">
      <div id="moduleView" class="max-w-7xl mx-auto w-full">
        <div class="text-center py-20 text-slate-400">
          <div class="w-8 h-8 mx-auto border-2 border-emerald-600 border-t-transparent rounded-full animate-spin mb-3"></div>
          <span>Loading telemetry...</span>
        </div>
      </div>

