<?php
/**
 * SIA 2027 Party Agent Portal - Modern Sidebar Dashboard Layout
 * Powered by PHP, HTML5 & Tailwind CSS
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!is_authenticated()) {
    header('Location: login.php');
    exit;
}

$agent = current_agent();
$pdo = get_db_connection();

// Refresh current agent details from DB
$agent_stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
$agent_stmt->execute([$agent['id']]);
$db_agent = $agent_stmt->fetch();
if ($db_agent) {
    $agent = $db_agent;
    $_SESSION['agent'] = $agent;
}

$results_stmt = $pdo->prepare("SELECT * FROM result_sheets WHERE agent_id = ? ORDER BY id DESC");
$results_stmt->execute([$agent['id']]);
$past_results = $results_stmt->fetchAll();

$incidents_stmt = $pdo->prepare("SELECT * FROM incidents WHERE agent_id = ? ORDER BY id DESC");
$incidents_stmt->execute([$agent['id']]);
$past_incidents = $incidents_stmt->fetchAll();

// Announcements targeted to agent
$ann_stmt = $pdo->prepare("
    SELECT a.*, r.read_at
    FROM announcements a
    LEFT JOIN announcement_reads r ON a.id = r.announcement_id AND r.agent_id = ?
    WHERE (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP)
      AND (a.audience = 'all' OR a.audience = ? OR a.audience = ?)
    ORDER BY a.created_at DESC
");
$ann_stmt->execute([
    $agent['id'],
    'lga:' . ($agent['lga_id'] ?? $agent['lga']),
    'agent:' . $agent['id']
]);
$announcements = $ann_stmt->fetchAll();

$unread_announcements_count = 0;
foreach ($announcements as $ann) {
    if (empty($ann['read_at'])) {
        $unread_announcements_count++;
    }
}

// Live Macro Totals from SQLite local db
$live_macro = $pdo->query("
    SELECT 
        COUNT(*) as total_pus_logged,
        COALESCE(SUM(lp_votes), 0) as total_lp,
        COALESCE(SUM(total_valid_votes), 0) as total_valid
    FROM result_sheets
    WHERE is_current = 1
")->fetch();
$macro_valid = max(1, (int)$live_macro['total_valid']);
$macro_lp_pct = round(((int)$live_macro['total_lp'] / $macro_valid) * 100, 1);

$flash = get_flash();
$csrf_token = csrf_token();
$is_pending = ($agent['status'] === 'pending');
$is_suspended = ($agent['status'] === 'suspended');
$can_submit = ($agent['status'] === 'accredited');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
  <title>Party Agent Command Dashboard • SIA 2027</title>
  <link rel="icon" type="image/png" href="./assets/images/logos/lp_logo.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

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

  <style>
    html, body {
      font-family: 'Plus Jakarta Sans', 'Outfit', ui-sans-serif, system-ui, -apple-system, sans-serif;
      background-color: #f8fafc;
      color: #0f172a;
      letter-spacing: -0.015em;
    }
    .material-symbols-outlined {
      font-family: 'Material Symbols Outlined';
      font-weight: normal;
      font-style: normal;
      font-size: 20px;
      line-height: 1;
      display: inline-block;
      text-transform: none;
      letter-spacing: normal;
      word-wrap: normal;
      white-space: nowrap;
      direction: ltr;
      vertical-align: middle;
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .material-symbols-outlined.fill-1 {
      font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
    }
    .top-card {
      background: #ffffff;
      border: 1px solid rgba(226, 232, 240, 0.9);
      border-radius: 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 4px 16px -2px rgba(0, 0, 0, 0.03);
    }
  </style>
</head>
<body class="min-h-screen bg-slate-50 flex w-full max-w-full overflow-x-hidden">

  <!-- ========================================================= -->
  <!-- 1. MODERN LEFT SIDEBAR MENU (Fixed Desktop, Slideout Mobile) -->
  <!-- ========================================================= -->
  <aside id="agentSidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 flex flex-col justify-between transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out shadow-xl lg:shadow-none">
    
    <!-- Top Brand & Agent Badge -->
    <div>
      <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-3 text-decoration-none">
          <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white font-bold text-base flex items-center justify-center tracking-tight shadow-sm">
            SIA
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-slate-900 font-bold text-lg tracking-tight">SIA 2027</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 uppercase">
                Console
              </span>
            </div>
            <span class="text-[11px] text-slate-400 block font-normal">Command Headquarters</span>
          </div>
        </a>
        <button onclick="toggleSidebar()" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center">
          <span class="material-symbols-outlined text-lg leading-none">close</span>
        </button>
      </div>

      <!-- Agent Profile Card -->
      <div class="p-4 mx-4 my-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
        <div class="relative group cursor-pointer flex-shrink-0" onclick="openPhotoModal()" title="Click to view or snap face photo">
          <?php if (!empty($agent['photo']) && file_exists(__DIR__ . '/' . $agent['photo'])): ?>
            <img src="<?= htmlspecialchars($agent['photo']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>" class="w-11 h-11 rounded-xl object-cover border-2 border-emerald-600 shadow-xs">
          <?php else: ?>
            <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-bold text-sm">
              <?= strtoupper(substr($agent['name'], 0, 2)) ?>
            </div>
          <?php endif; ?>
          <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-slate-900 text-white flex items-center justify-center text-[9px] shadow-xs group-hover:bg-emerald-600 transition-colors">
            <span class="material-symbols-outlined text-[10px] leading-none">photo_camera</span>
          </span>
        </div>
        <div class="min-w-0 flex-1">
          <span class="block font-bold text-slate-900 text-xs truncate"><?= htmlspecialchars($agent['name']) ?></span>
          <span class="block text-[10px] font-mono text-slate-500 truncate"><?= htmlspecialchars($agent['agent_code']) ?></span>
          <?php if ($agent['status'] === 'accredited'): ?>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              Accredited LP Agent
            </span>
          <?php elseif ($agent['status'] === 'pending'): ?>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
              Pending Accreditation
            </span>
          <?php else: ?>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-red-700">
              <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
              Account <?= htmlspecialchars(ucfirst($agent['status'])) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Navigation Menu Items -->
      <nav class="px-4 space-y-1.5">
        <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
          Agent Navigation
        </div>

        <a href="#overview" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200">
          <span class="material-symbols-outlined text-base">bar_chart</span>
          <span>Overview</span>
        </a>

        <a href="#announcements-section" onclick="closeSidebarMobile()" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-emerald-800 hover:bg-slate-100 transition-colors">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-base">campaign</span>
            <span>Announcements</span>
          </div>
          <?php if ($unread_announcements_count > 0): ?>
            <span id="unreadAnnBadge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-600 text-white">
              <?= $unread_announcements_count ?>
            </span>
          <?php endif; ?>
        </a>

        <a href="#ec8a-section" onclick="closeSidebarMobile()" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-emerald-800 hover:bg-slate-100 transition-colors">
          <span class="material-symbols-outlined text-base">upload</span>
          <span>Upload result</span>
        </a>

        <a href="#incident-section" onclick="closeSidebarMobile()" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-red-700 hover:bg-red-50 transition-colors">
          <span class="material-symbols-outlined text-base">emergency</span>
          <span>Report incident</span>
        </a>

        <a href="#history-section" onclick="closeSidebarMobile()" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition-colors">
          <span class="material-symbols-outlined text-base">history</span>
          <span>Submission History</span>
        </a>

        <button type="button" onclick="openPhotoModal()" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition-colors text-left">
          <span class="material-symbols-outlined text-base">photo_camera</span>
          <span>Agent Facial Snapshot</span>
        </button>

        <button type="button" onclick="openPasswordModal()" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition-colors text-left">
          <span class="material-symbols-outlined text-base">lock</span>
          <span>Change Password</span>
        </button>

        <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition-colors">
          <span class="material-symbols-outlined text-base">public</span>
          <span>View Public Gateway ↗</span>
        </a>
      </nav>
    </div>

    <!-- Bottom Sidebar Section (Live Pill & Logout) -->
    <div class="p-4 border-t border-slate-100 space-y-3">
      <div class="px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs">
        <span class="font-bold text-emerald-950 flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
          <span>sia2027.sqlite</span>
        </span>
        <span class="text-[10px] font-bold text-emerald-700 bg-white px-2 py-0.5 rounded-full border border-emerald-200">LOCAL DB</span>
      </div>

      <div class="flex items-center justify-between px-2 py-1">
        <span class="text-xs font-mono text-slate-500">Device Status</span>
        <div id="onlineBadge" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600 text-white font-mono">
          <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
          <span id="onlineBadgeText">ONLINE</span>
        </div>
      </div>

      <a href="auth.php?action=logout" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-sm font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition-all text-decoration-none">
        <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        <span>Logout</span>
      </a>
    </div>

  </aside>

  <!-- Backdrop Overlay for Mobile Sidebar -->
  <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm hidden lg:hidden"></div>

  <!-- ========================================================= -->
  <!-- 2. MAIN CONTENT AREA (Offset by sidebar on desktop)       -->
  <!-- ========================================================= -->
  <div class="flex-1 lg:pl-72 flex flex-col min-h-screen w-full max-w-full min-w-0 overflow-x-hidden">
    
    <!-- Top Mobile / Utility Header -->
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 px-3 sm:px-6 lg:px-8 py-2.5 sm:py-3.5 flex items-center justify-between w-full max-w-full gap-2 overflow-hidden">
      <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 sm:flex-initial">
        <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 flex-shrink-0 cursor-pointer" aria-label="Open Agent Menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <div class="flex items-center gap-1 text-xs sm:text-sm font-medium text-slate-500 min-w-0 truncate">
          <span class="hidden md:inline text-slate-400">Adamawa State / </span>
          <span class="text-slate-900 font-bold truncate max-w-[95px] sm:max-w-none"><?= htmlspecialchars($agent['lga']) ?></span>
          <span class="text-slate-300">/</span>
          <span class="text-slate-600 truncate max-w-[95px] sm:max-w-none"><?= htmlspecialchars($agent['ward']) ?></span>
        </div>
      </div>

      <div class="flex items-center gap-1.5 sm:gap-2.5 flex-shrink-0">
        <button type="button" onclick="openPasswordModal()" class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors">
          <span class="material-symbols-outlined text-sm">lock</span>
          <span>Password</span>
        </button>
        <a href="#ec8a-section" class="inline-flex items-center gap-1 px-2.5 sm:px-3 py-1.5 rounded-full text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 whitespace-nowrap">
          <span class="material-symbols-outlined text-sm sm:text-base leading-none">upload</span>
          <span class="hidden sm:inline">Upload result</span>
          <span class="sm:hidden">Upload</span>
        </a>
        <a href="#incident-section" class="inline-flex items-center gap-1 px-2.5 sm:px-3 py-1.5 rounded-full text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 whitespace-nowrap">
          <span class="material-symbols-outlined text-sm sm:text-base leading-none">emergency</span>
          <span class="hidden sm:inline">Report incident</span>
          <span class="sm:hidden">Report</span>
        </a>
        <a href="auth.php?action=logout" class="inline-flex items-center justify-center p-1.5 sm:px-3 sm:py-1.5 rounded-full text-xs font-semibold text-slate-600 hover:text-slate-900 bg-slate-100 transition-colors" title="Logout">
          <span class="material-symbols-outlined text-sm sm:text-base leading-none">logout</span>
          <span class="hidden sm:inline ml-1">Logout</span>
        </a>
      </div>
    </header>

    <!-- Main Content Container (Full Width) -->
    <main class="p-3 sm:p-6 lg:p-8 space-y-6 sm:space-y-8 w-full max-w-full min-w-0" id="overview">

      <!-- Flash Notification -->
      <?php if ($flash): ?>
      <div class="p-4 rounded-2xl border flex items-center justify-between <?= $flash['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800' ?>">
        <div class="flex items-center gap-2 text-sm font-semibold">
          <?= $flash['type'] === 'error' ? '<span class="material-symbols-outlined text-red-600 text-lg">warning</span>' : '<span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>' ?>
          <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">&times;</button>
      </div>
      <?php endif; ?>

      <!-- Pending Accreditation Notice Banner -->
      <?php if ($is_pending): ?>
      <div class="p-5 rounded-3xl bg-amber-500/10 border-2 border-amber-500/40 text-amber-950 flex items-start gap-4">
        <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white font-black text-lg flex items-center justify-center flex-shrink-0 shadow-sm">
          <span class="material-symbols-outlined text-xl">hourglass_empty</span>
        </div>
        <div class="space-y-1">
          <h3 class="font-bold text-sm sm:text-base text-amber-950 m-0">Account Pending Official Accreditation</h3>
          <p class="text-xs sm:text-sm text-amber-900 leading-relaxed m-0 font-normal">
            Your agent profile has been registered and is currently under review by the LGA Electoral Coordinator and Central Command. Form EC8A transmission is locked until an administrator reviews and clears your credentials. You may still review guidelines and receive broadcast announcements.
          </p>
        </div>
      </div>
      <?php elseif ($is_suspended): ?>
      <div class="p-5 rounded-3xl bg-red-500/10 border-2 border-red-500/40 text-red-950 flex items-start gap-4">
        <div class="w-10 h-10 rounded-2xl bg-red-600 text-white font-black text-lg flex items-center justify-center flex-shrink-0 shadow-sm">
          <span class="material-symbols-outlined text-xl">block</span>
        </div>
        <div class="space-y-1">
          <h3 class="font-bold text-sm sm:text-base text-red-950 m-0">Agent Account Suspended</h3>
          <p class="text-xs sm:text-sm text-red-900 leading-relaxed m-0 font-normal">
            Your agent transmission access has been temporarily suspended by Central Command. Please contact your LGA Party Coordinator or Election Command Room immediately.
          </p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Offline Submission Queue Banner -->
      <div id="offlineQueueBar" class="hidden p-4 sm:p-5 rounded-3xl bg-amber-500/15 border-2 border-amber-500/30 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="w-3 h-3 rounded-full bg-amber-600 animate-ping flex-shrink-0"></span>
          <div>
            <h4 class="font-bold text-sm text-amber-950 m-0">Offline Submissions Stored on Device</h4>
            <span id="offlineQueueText" class="text-xs text-amber-900">0 results queued awaiting internet restoration</span>
          </div>
        </div>
        <button type="button" onclick="syncOfflineQueue()" class="px-4 py-2 rounded-full text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-sm transition-all flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-sm">bolt</span>
          <span>Transmit Queued Now</span>
        </button>
      </div>

      <!-- Hero Identity Card -->
      <section class="top-card p-4 sm:p-6 lg:p-8 space-y-5 sm:space-y-6 border-l-4 border-l-emerald-700 w-full max-w-full min-w-0 overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 sm:gap-6 min-w-0">
          <div class="space-y-2 min-w-0 flex-1">
            <div class="inline-flex items-center gap-2 text-[10px] sm:text-xs font-bold uppercase tracking-wider text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200 max-w-full">
              <span class="w-2 h-2 rounded-full bg-emerald-600 flex-shrink-0"></span>
              <span class="truncate">PARTY AGENT COMMAND &bull; LABOUR PARTY (LP)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-normal text-slate-900 tracking-[-0.03em] m-0 break-words">
              Welcome, <?= htmlspecialchars($agent['name']) ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 max-w-4xl leading-relaxed font-normal break-words m-0">
              Official field agent for <strong>SIA 2027 Electoral Command</strong>. Transmit verified Form EC8A tally sheets, track field voter turnout, and report real-time incidents directly to central headquarters.
            </p>
          </div>

          <div class="bg-slate-50 border border-slate-200 p-3 sm:p-4 rounded-2xl flex items-center gap-3 sm:gap-4 w-full lg:w-auto min-w-0 flex-shrink-0">
            <div class="relative group cursor-pointer flex-shrink-0" onclick="openPhotoModal()" title="Click to view or update photo">
              <?php if (!empty($agent['photo']) && file_exists(__DIR__ . '/' . $agent['photo'])): ?>
                <img src="<?= htmlspecialchars($agent['photo']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl object-cover border-2 border-emerald-600 shadow-sm">
              <?php else: ?>
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-bold text-base sm:text-lg">
                  <?= strtoupper(substr($agent['name'], 0, 2)) ?>
                </div>
              <?php endif; ?>
              <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] shadow-xs group-hover:bg-emerald-600 transition-colors">
                <span class="material-symbols-outlined text-xs leading-none">photo_camera</span>
              </span>
            </div>
            <div class="space-y-0.5 min-w-0 flex-1">
              <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">ASSIGNED PU</span>
              <span class="block text-xs font-bold text-slate-900 truncate" title="<?= htmlspecialchars($agent['polling_unit']) ?>"><?= htmlspecialchars($agent['polling_unit']) ?></span>
              <?php if ($can_submit): ?>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                  Accredited &amp; Active
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                  <?= htmlspecialchars(ucfirst($agent['status'])) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-4 pt-4 border-t border-slate-100 w-full min-w-0">
          <div class="bg-slate-50 p-3 sm:p-4 rounded-2xl border border-slate-200/80 min-w-0 overflow-hidden">
            <span class="block text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Assigned LGA</span>
            <span class="text-sm sm:text-lg font-bold text-slate-950 truncate block"><?= htmlspecialchars($agent['lga']) ?></span>
            <span class="block text-[10px] sm:text-[11px] text-slate-500 mt-0.5 truncate">Adamawa State</span>
          </div>
          <div class="bg-slate-50 p-3 sm:p-4 rounded-2xl border border-slate-200/80 min-w-0 overflow-hidden">
            <span class="block text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Assigned Ward</span>
            <span class="text-sm sm:text-lg font-bold text-slate-950 truncate block"><?= htmlspecialchars($agent['ward']) ?></span>
            <span class="block text-[10px] sm:text-[11px] text-emerald-700 font-semibold mt-0.5 truncate">Collation Sector</span>
          </div>
          <div class="bg-slate-50 p-3 sm:p-4 rounded-2xl border border-slate-200/80 min-w-0 overflow-hidden">
            <span class="block text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">EC8A Status</span>
            <span class="text-sm sm:text-lg font-bold text-emerald-700 truncate block">
              <?= count($past_results) > 0 ? 'Uploaded (' . count($past_results) . ')' : 'Pending' ?>
            </span>
            <span class="block text-[10px] sm:text-[11px] text-slate-500 mt-0.5 truncate">Tamper-Proof Ledger</span>
          </div>
          <div class="bg-slate-50 p-3 sm:p-4 rounded-2xl border border-slate-200/80 min-w-0 overflow-hidden">
            <span class="block text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Election Day</span>
            <span class="text-sm sm:text-lg font-bold text-slate-950 truncate block">06 Feb 2027</span>
            <span class="block text-[10px] sm:text-[11px] text-red-600 font-bold mt-0.5 truncate">&bull; Governorship</span>
          </div>
        </div>
      </section>

      <!-- Broadcast Announcements Section -->
      <section class="top-card p-4 sm:p-6 lg:p-8 space-y-4 w-full max-w-full min-w-0 overflow-hidden" id="announcements-section">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-800 text-xl">campaign</span>
            <h3 class="font-bold text-slate-900 text-lg m-0">Command Broadcasts & Alerts</h3>
          </div>
          <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
            <?= count($announcements) ?> Messages
          </span>
        </div>

        <?php if (empty($announcements)): ?>
          <div class="text-center py-6 text-slate-400 text-sm">
            No active announcements for your polling unit or LGA at this time.
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($announcements as $ann): ?>
              <?php 
                $is_unread = empty($ann['read_at']);
                $priority_classes = [
                    'critical' => 'bg-red-50 border-red-200 text-red-900',
                    'urgent' => 'bg-amber-50 border-amber-200 text-amber-900',
                    'normal' => 'bg-slate-50 border-slate-200 text-slate-800'
                ][$ann['priority'] ?? 'normal'] ?? 'bg-slate-50 border-slate-200 text-slate-800';
              ?>
              <div class="p-4 rounded-2xl border <?= $priority_classes ?> space-y-2">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?= $ann['priority'] === 'critical' ? 'bg-red-200 text-red-900' : ($ann['priority'] === 'urgent' ? 'bg-amber-200 text-amber-900' : 'bg-slate-200 text-slate-800') ?>">
                      <?= htmlspecialchars(strtoupper($ann['priority'] ?? 'ANNOUNCEMENT')) ?>
                    </span>
                    <h4 class="font-bold text-sm m-0 text-slate-950"><?= htmlspecialchars($ann['title']) ?></h4>
                  </div>
                  <span class="text-[11px] text-slate-500 font-mono"><?= date('M d, H:i', strtotime($ann['created_at'])) ?></span>
                </div>
                <p class="text-xs text-slate-700 leading-relaxed m-0 whitespace-pre-wrap"><?= htmlspecialchars($ann['body']) ?></p>
                <div class="flex items-center justify-between pt-1 border-t border-slate-200/60">
                  <span class="text-[10px] text-slate-500">Audience: <?= htmlspecialchars($ann['audience']) ?></span>
                  <?php if ($is_unread): ?>
                    <button type="button" onclick="markAnnouncementRead(<?= (int)$ann['id'] ?>, this)" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-900 bg-white border border-emerald-300 px-3 py-1 rounded-full shadow-2xs transition-all">
                      <span class="material-symbols-outlined text-xs">check</span>
                      <span>Mark as Read</span>
                    </button>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-[11px] text-slate-400 font-medium">
                      <span>Read <?= date('M d, H:i', strtotime($ann['read_at'])) ?></span>
                      <span class="material-symbols-outlined text-xs text-emerald-600">check</span>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- 2. Form EC8A Result Transmission Form -->
      <section class="top-card p-4 sm:p-6 lg:p-10 space-y-6 sm:space-y-8 w-full max-w-full min-w-0 overflow-hidden" id="ec8a-section">
        <div class="border-b border-slate-100 pb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold text-emerald-800 uppercase tracking-wider mb-1">
              <span>Official Collation Module</span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-normal text-slate-900 tracking-[-0.03em] m-0">
              Transmit Form EC8A Polling Unit Results
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 m-0 mt-1 font-light">
              Enter the exact physical vote counts recorded on the official Form EC8A signed by the INEC Presiding Officer and party agents.
            </p>
          </div>
          <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start sm:self-auto">
            Form EC8A &bull; Tamper Proof
          </span>
        </div>

        <form id="ec8aForm" onsubmit="handleResultSubmit(event)" enctype="multipart/form-data" class="space-y-8">
          <input type="hidden" name="action" value="submit_result">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

          <!-- Photo Upload Box -->
          <div class="bg-slate-50 border-2 border-dashed border-slate-300 rounded-3xl p-6 sm:p-8 text-center hover:border-emerald-600 transition-colors">
            <div class="max-w-md mx-auto space-y-3">
              <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">photo_camera</span>
              </div>
              <div>
                <label for="ec8a_photo" class="font-bold text-sm text-slate-950 hover:text-emerald-700 cursor-pointer block">
                  Snap or Upload Signed Form EC8A Photo
                </label>
                <span class="text-xs text-slate-500">Supports JPG, PNG, WEBP, PDF up to 15MB</span>
              </div>
              <input type="file" id="ec8a_photo" name="ec8a_photo" accept="image/jpeg,image/png,image/webp,application/pdf" onchange="previewImage(this)" class="hidden">
              <div>
                <button type="button" onclick="document.getElementById('ec8a_photo').click()" class="inline-flex items-center gap-2 bg-white hover:bg-slate-100 text-slate-800 font-semibold px-5 py-2.5 rounded-full text-xs border border-slate-300 shadow-sm cursor-pointer transition-all">
                  <span>Select EC8A Document</span>
                  <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                  </svg>
                </button>
              </div>
              <div id="imagePreviewContainer" class="hidden pt-3">
                <div class="relative inline-block border-2 border-emerald-500 rounded-2xl overflow-hidden shadow-md">
                  <img id="imagePreview" src="#" alt="Form EC8A Preview" class="max-h-48 rounded-lg object-contain">
                  <span id="fileNameLabel" class="block bg-slate-950 text-white text-[10px] p-1 font-mono"></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Vote Inputs -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 m-0">
                Enter Recorded Votes By Party (17 Official Parties)
              </h3>
              <span class="text-xs text-slate-500 font-medium">Server Validated • Anti-Overvoting</span>
            </div>

            <!-- Premier Party Card: Labour Party (LP) -->
            <div class="bg-emerald-50/70 border-2 border-emerald-500 rounded-2xl p-4 sm:p-5 space-y-2 shadow-sm">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-extrabold text-sm shadow-sm">LP</div>
                  <div>
                    <span class="font-extrabold text-slate-950 text-base block">Labour Party (LP)</span>
                    <span class="text-xs text-emerald-800 font-semibold">Senator Ishaku Abbo / SIA 2027 Campaign</span>
                  </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-200 text-emerald-900 border border-emerald-300">
                  Primary Mandate
                </span>
              </div>
              <div class="pt-1">
                <label for="lp_votes" class="block text-xs font-bold text-emerald-900 uppercase tracking-wider mb-1">
                  Recorded Labour Party Votes
                </label>
                <input type="number" id="lp_votes" name="lp_votes" min="0" placeholder="0" oninput="calculateTallies()" class="w-full bg-white text-slate-950 font-extrabold text-3xl border-2 border-emerald-400 rounded-xl px-4 py-2.5 outline-none focus:ring-4 focus:ring-emerald-400/20 text-right shadow-inner" required>
              </div>
            </div>

            <!-- Other 16 Political Parties Grid -->
            <div class="space-y-2">
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Other Registered Parties (Form EC8A Field Tallies)</span>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php
                foreach (get_official_parties() as $code => $p):
                    if ($code === 'LP') continue;
                    $col = $p['column'];
                ?>
                  <div class="bg-slate-50 border border-slate-200 hover:border-slate-300 rounded-xl p-3 space-y-1.5 transition-colors">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg <?= $p['bg_class'] ?> text-white flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                          <?= htmlspecialchars($code) ?>
                        </span>
                        <span class="font-bold text-slate-800 text-xs truncate max-w-[120px]" title="<?= htmlspecialchars($p['name']) ?>">
                          <?= htmlspecialchars($p['name']) ?>
                        </span>
                      </div>
                    </div>
                    <input type="number" id="<?= $col ?>" name="<?= $col ?>" min="0" placeholder="0" oninput="calculateTallies()" class="w-full bg-white text-slate-950 font-bold text-lg border border-slate-300 rounded-lg px-3 py-1.5 outline-none focus:border-slate-500 text-right shadow-inner">
                  </div>
                <?php endforeach; ?>

                <!-- Rejected / Invalid Votes -->
                <div class="bg-red-50/50 border border-red-200 rounded-xl p-3 space-y-1.5">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <span class="w-7 h-7 rounded-lg bg-red-600 text-white flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                        <span class="material-symbols-outlined text-sm">close</span>
                      </span>
                      <span class="font-bold text-red-900 text-xs">Rejected / Invalid</span>
                    </div>
                  </div>
                  <input type="number" id="rejected_votes" name="rejected_votes" min="0" placeholder="0" oninput="calculateTallies()" class="w-full bg-white text-slate-950 font-bold text-lg border border-red-300 rounded-lg px-3 py-1.5 outline-none focus:border-red-500 text-right shadow-inner">
                </div>
              </div>
            </div>
          </div>

          <!-- Total Accredited -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-100/70 p-6 rounded-2xl border border-slate-200">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                Total Accredited Voters (from BVAS)
              </label>
              <input type="number" id="total_accredited" name="total_accredited" min="0" placeholder="e.g. 450" oninput="calculateTallies()" class="w-full bg-white text-slate-950 font-bold text-xl border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-emerald-600">
            </div>

            <div class="flex flex-col justify-center space-y-2 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Total Valid Votes:</span>
                <span id="displayValidVotes" class="font-bold text-slate-950 text-base">0</span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Labour Party (LP) Margin:</span>
                <span id="displayLpPct" class="font-bold text-emerald-700 text-base">0.0%</span>
              </div>
              <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100">
                <span class="text-slate-500">Reconciliation:</span>
                <span id="displayBalance" class="font-semibold text-slate-600">Enter numbers</span>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
              Presiding Officer Name / Field Observations
            </label>
            <textarea name="notes" rows="2" placeholder="e.g. Signed by party agents, peaceful turnout without incident." class="w-full bg-white text-slate-950 border border-slate-300 rounded-xl p-3.5 text-sm outline-none focus:border-emerald-600"></textarea>
          </div>

          <!-- Field Transmission Location & Internet Status -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5 flex items-center justify-between">
              <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-emerald-700 text-sm">cell_tower</span>
                <span>Transmission Location / Internet Status <span class="text-red-500">*</span></span>
              </span>
              <span class="text-[10px] font-normal text-slate-400 normal-case">Ground Reporting vs Travel</span>
            </label>
            <div class="relative">
              <select 
                name="transmission_status" 
                id="transmission_status" 
                required 
                class="w-full bg-white text-slate-900 font-semibold border border-slate-300 rounded-xl px-4 py-3 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 transition-all appearance-none cursor-pointer pr-10 shadow-xs"
              >
                <option value="Reporting on PU ground" selected>1. Reporting on PU ground</option>
                <option value="Took me 10 mins to get internet">2. Took me 10 mins to get internet</option>
                <option value="I have to travel for 30 mins to get internet">3. I have to travel for 30 mins to get internet</option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-500">
                <span class="material-symbols-outlined text-base">expand_more</span>
              </div>
            </div>
            <p class="text-[11px] text-slate-500 mt-1 m-0">
              State whether you are transmitting directly on site at the polling unit or traveled to obtain internet connectivity.
            </p>
          </div>

          <div class="flex items-center justify-end pt-2">
            <?php if ($can_submit): ?>
              <button type="submit" id="submitResultBtn" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-3.5 rounded-full cursor-pointer text-sm shadow-md transition-all">
                <span>Transmit EC8A Results to HQ</span>
                <span>&rarr;</span>
              </button>
            <?php else: ?>
              <button type="button" disabled class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-slate-300 text-slate-500 font-bold px-8 py-3.5 rounded-full cursor-not-allowed text-sm">
                <span class="material-symbols-outlined text-sm">lock</span>
                <span>Transmission Locked (Accreditation Required)</span>
              </button>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <!-- 3. Two-Column Submissions & Incident Modules -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 w-full max-w-full min-w-0" id="history-section">
        
        <!-- History List -->
        <section class="top-card p-4 sm:p-6 lg:p-8 space-y-4 w-full max-w-full min-w-0 overflow-hidden">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-slate-700 text-xl">description</span>
              <h3 class="font-bold text-slate-900 text-lg m-0">Transmitted Results</h3>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
              <?= count($past_results) ?> Logged
            </span>
          </div>

          <div class="space-y-3" id="resultsHistoryList">
            <?php if (empty($past_results)): ?>
              <div class="text-center py-8 text-slate-400 text-sm">No submissions recorded yet.</div>
            <?php else: ?>
              <?php foreach ($past_results as $res): ?>
                <?php
                  $status = $res['verification_status'] ?? 'pending';
                  $badge_class = [
                      'verified' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                      'pending'  => 'bg-amber-100 text-amber-800 border-amber-300',
                      'flagged'  => 'bg-rose-100 text-rose-800 border-rose-300',
                      'rejected' => 'bg-red-100 text-red-800 border-red-300',
                  ][$status] ?? 'bg-slate-100 text-slate-800 border-slate-300';
                  $status_label = [
                      'verified' => '<span class="material-symbols-outlined text-xs align-middle">verified</span> Verified',
                      'pending'  => '<span class="material-symbols-outlined text-xs align-middle">hourglass_empty</span> Pending Review',
                      'flagged'  => '<span class="material-symbols-outlined text-xs align-middle">flag</span> Flagged for Audit',
                      'rejected' => '<span class="material-symbols-outlined text-xs align-middle">cancel</span> Rejected',
                  ][$status] ?? ucfirst($status);
                ?>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                  <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                      <span class="font-bold text-sm text-slate-900 block"><?= htmlspecialchars($res['polling_unit']) ?></span>
                      <span class="text-[11px] text-slate-500"><?= htmlspecialchars($res['ward'] . ', ' . $res['lga']) ?> &bull; <?= date('M d, H:i', strtotime($res['created_at'])) ?></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <?php if (!$res['is_current']): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-700">
                          Superseded
                        </span>
                      <?php else: ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                          Current
                        </span>
                      <?php endif; ?>
                      <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $badge_class ?> inline-flex items-center gap-1">
                        <?= $status_label ?>
                      </span>
                    </div>
                  </div>

                  <div class="grid grid-cols-3 gap-2 text-xs pt-1 border-t border-slate-100">
                    <div><span class="text-slate-500">LP:</span> <strong class="text-emerald-700"><?= number_format($res['lp_votes']) ?></strong></div>
                    <div><span class="text-slate-500">APC:</span> <strong class="text-slate-900"><?= number_format($res['apc_votes']) ?></strong></div>
                    <div><span class="text-slate-500">PDP:</span> <strong class="text-slate-900"><?= number_format($res['pdp_votes']) ?></strong></div>
                  </div>
                  <div class="grid grid-cols-3 gap-2 text-xs text-slate-600">
                    <div><span class="text-slate-400">Total Valid:</span> <?= number_format($res['total_valid_votes'] ?? 0) ?></div>
                    <div><span class="text-slate-400">Accredited:</span> <?= number_format($res['total_accredited'] ?? $res['total_accredited_voters'] ?? 0) ?></div>
                    <div><span class="text-slate-400">Rejected:</span> <?= number_format($res['rejected_votes'] ?? 0) ?></div>
                  </div>

                  <div class="flex items-center gap-1.5 text-xs text-slate-700 bg-white px-3 py-1.5 rounded-xl border border-slate-200">
                    <span class="material-symbols-outlined text-xs text-emerald-700">cell_tower</span>
                    <span class="text-[11px] font-semibold text-slate-800"><?= htmlspecialchars($res['transmission_status'] ?? 'Reporting on PU ground') ?></span>
                  </div>

                  <?php if (!empty($res['review_note'])): ?>
                    <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs">
                      <strong>HQ Audit Note:</strong> <?= htmlspecialchars($res['review_note']) ?>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($res['ec8a_image'])): ?>
                    <div class="pt-1">
                      <a href="uploads/ec8a/<?= htmlspecialchars($res['ec8a_image']) ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                        <span class="material-symbols-outlined text-sm">photo_camera</span>
                        <span>View Uploaded Form EC8A ↗</span>
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <!-- Incident Module -->
        <section class="top-card p-4 sm:p-6 lg:p-8 space-y-4 w-full max-w-full min-w-0 overflow-hidden" id="incident-section">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-red-600 text-xl">emergency</span>
              <h3 class="font-bold text-slate-900 text-lg m-0">Report Field Incident</h3>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-full bg-red-50 text-red-700 border border-red-200">
              HQ Hotline
            </span>
          </div>

          <form id="incidentForm" onsubmit="handleIncidentSubmit(event)" class="space-y-4">
            <input type="hidden" name="action" value="submit_incident">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Incident Category</label>
              <select name="category" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-medium text-slate-900">
                <option value="BVAS Malfunction / Failure">BVAS Malfunction / Fingerprint Failure</option>
                <option value="Late Arrival of INEC Officials">Late Arrival of INEC Officials</option>
                <option value="Ballot Box Tampering / Snatching">Ballot Box Tampering / Snatching</option>
                <option value="Voter Intimidation / Disruption">Voter Intimidation / Disruption</option>
                <option value="Peaceful Situation Report">Peaceful Situation Report</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Urgency</label>
                <select name="severity" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-medium text-slate-900">
                  <option value="normal">Normal</option>
                  <option value="urgent">Urgent</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Brief Title</label>
                <input type="text" name="title" placeholder="e.g. BVAS frozen at PU" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900" required>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Details</label>
              <textarea name="description" rows="3" placeholder="Describe the incident clearly..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-sm text-slate-900" required></textarea>
            </div>

            <button type="submit" id="submitIncidentBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-full text-sm shadow-sm transition-all">
              Transmit Field Incident to Central Command
            </button>
          </form>
        </section>

      </div>

    </main>
  </div>

  <!-- Change Password Modal -->
  <div id="passwordModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-slate-700 text-xl">lock</span>
          <h3 class="font-bold text-slate-900 text-lg m-0">Change Agent Password</h3>
        </div>
        <button onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-700 flex items-center justify-center p-1">
          <span class="material-symbols-outlined text-xl leading-none">close</span>
        </button>
      </div>

      <form id="passwordForm" onsubmit="handleChangePassword(event)" class="space-y-4">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Current Password</label>
          <input type="password" name="current_password" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600">
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">New Password (min 8 chars)</label>
          <input type="password" name="new_password" minlength="8" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600">
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Confirm New Password</label>
          <input type="password" name="confirm_password" minlength="8" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600">
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button type="button" onclick="closePasswordModal()" class="px-5 py-2.5 rounded-full text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
          <button type="submit" id="savePasswordBtn" class="px-6 py-2.5 rounded-full text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white shadow-sm transition-all">Save New Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Agent Face Photo / Snapshot Modal -->
  <div id="photoModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-5 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-700 text-xl">photo_camera</span>
          <h3 class="font-bold text-slate-900 text-lg m-0">Agent Face Photo Snapshot</h3>
        </div>
        <button onclick="closePhotoModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="flex flex-col items-center gap-4 text-center">
        <div class="w-28 h-28 rounded-2xl bg-slate-100 border-2 border-emerald-600 overflow-hidden shadow-inner flex items-center justify-center relative">
          <?php if (!empty($agent['photo']) && file_exists(__DIR__ . '/' . $agent['photo'])): ?>
            <img id="dashModalPhotoImg" src="<?= htmlspecialchars($agent['photo']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>" class="w-full h-full object-cover">
          <?php else: ?>
            <img id="dashModalPhotoImg" src="" alt="Photo" class="hidden w-full h-full object-cover">
            <span id="dashModalPhotoPlaceholder" class="material-symbols-outlined text-5xl text-slate-300">account_circle</span>
          <?php endif; ?>
        </div>
        <div class="space-y-1">
          <h4 class="font-bold text-sm text-slate-900 m-0"><?= htmlspecialchars($agent['name']) ?></h4>
          <span class="text-xs font-mono text-slate-500 block"><?= htmlspecialchars($agent['agent_code']) ?></span>
          <p class="text-xs text-slate-500 m-0 leading-relaxed">
            Snap a fresh portrait selfie or upload a passport photo to document your accreditation credentials.
          </p>
        </div>
      </div>

      <form id="dashUpdatePhotoForm" onsubmit="handleDashboardPhotoUpdate(event)" class="space-y-3">
        <input type="hidden" name="action" value="update_agent_photo">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <input type="file" id="dashCameraInput" name="photo" accept="image/*" capture="user" onchange="previewDashModalPhoto(this)" class="hidden">
        <input type="file" id="dashGalleryInput" accept="image/jpeg,image/png,image/webp" onchange="previewDashModalPhoto(this, true)" class="hidden">

        <div class="grid grid-cols-2 gap-2.5 pt-2">
          <button type="button" onclick="document.getElementById('dashCameraInput').click()" class="w-full py-2.5 rounded-xl text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white inline-flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer">
            <span class="material-symbols-outlined text-sm">photo_camera</span>
            <span>Snap Camera</span>
          </button>
          <button type="button" onclick="document.getElementById('dashGalleryInput').click()" class="w-full py-2.5 rounded-xl text-xs font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-sm">upload_file</span>
            <span>Choose File</span>
          </button>
        </div>

        <div id="dashPhotoSelectedLabel" class="text-[11px] text-center text-slate-500 font-medium min-h-[18px]"></div>

        <button type="submit" id="dashSavePhotoBtn" disabled class="w-full py-3 rounded-full text-xs font-bold bg-slate-300 text-slate-500 shadow-sm transition-all flex items-center justify-center gap-1.5 cursor-not-allowed">
          <span>Save Face Photo</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Toast -->
  <div id="dashboardToast" class="fixed bottom-6 right-6 z-50 transform translate-y-32 opacity-0 transition-all duration-300 bg-slate-900 text-white px-5 py-3 rounded-full shadow-2xl flex items-center gap-3 text-sm font-semibold pointer-events-none">
    <span id="dashToastIcon" class="material-symbols-outlined text-emerald-400 text-[20px]">check_circle</span>
    <span id="toastMessage">Command updated</span>
  </div>

  <!-- Script -->
  <script>
    const OFFLINE_QUEUE_KEY = 'sia2027_offline_ec8a_queue';

    function toggleSidebar() {
      const sb = document.getElementById('agentSidebar');
      const bd = document.getElementById('sidebarBackdrop');
      sb.classList.toggle('-translate-x-full');
      bd.classList.toggle('hidden');
    }
    function closeSidebarMobile() {
      if (window.innerWidth < 1024) {
        document.getElementById('agentSidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarBackdrop').classList.add('hidden');
      }
    }

    function openPasswordModal() {
      document.getElementById('passwordModal').classList.remove('hidden');
      document.getElementById('passwordModal').classList.add('flex');
    }
    function closePasswordModal() {
      document.getElementById('passwordModal').classList.add('hidden');
      document.getElementById('passwordModal').classList.remove('flex');
    }

    function openPhotoModal() {
      const modal = document.getElementById('photoModal');
      if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      }
    }

    function closePhotoModal() {
      const modal = document.getElementById('photoModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    }

    function previewDashModalPhoto(input, isGallery = false) {
      if (!input.files || !input.files[0]) return;
      const file = input.files[0];
      const cameraInput = document.getElementById('dashCameraInput');
      const galleryInput = document.getElementById('dashGalleryInput');
      if (isGallery) {
        try {
          const dt = new DataTransfer();
          dt.items.add(file);
          cameraInput.files = dt.files;
        } catch (e) {
          cameraInput.removeAttribute('name');
          galleryInput.setAttribute('name', 'photo');
        }
      } else {
        cameraInput.setAttribute('name', 'photo');
        galleryInput.removeAttribute('name');
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        const img = document.getElementById('dashModalPhotoImg');
        const placeholder = document.getElementById('dashModalPhotoPlaceholder');
        const btn = document.getElementById('dashSavePhotoBtn');
        const label = document.getElementById('dashPhotoSelectedLabel');

        if (img) {
          img.src = e.target.result;
          img.classList.remove('hidden');
        }
        if (placeholder) placeholder.classList.add('hidden');

        if (btn) {
          btn.disabled = false;
          btn.className = 'w-full py-3 rounded-full text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer';
        }
        if (label) {
          label.innerHTML = `<span class="text-emerald-700 font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-xs">check_circle</span> Ready to save: ${file.name}</span>`;
        }
      };
      reader.readAsDataURL(file);
    }

    async function handleDashboardPhotoUpdate(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('dashSavePhotoBtn');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<span>Saving Photo...</span>';
      btn.disabled = true;

      try {
        const fd = new FormData(form);
        const res = await fetch('api.php', {
          method: 'POST',
          body: fd,
          headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
          showToast('Agent face photo updated successfully!', 'success');
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast(data.message || 'Failed to update photo', 'error');
          btn.innerHTML = originalText;
          btn.disabled = false;
        }
      } catch (err) {
        showToast('Network error while saving photo. Try again.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    }

    const OFFICIAL_PARTY_CODES = [
      'a', 'aa', 'aac', 'adc', 'adp', 'apc', 'apga', 'app', 'dlp', 'lp', 'ndc', 'nnpp', 'nrm', 'pdp', 'sdp', 'ypp', 'zlp'
    ];

    function calculateTallies() {
      let totalValid = 0;
      let lp = 0;
      OFFICIAL_PARTY_CODES.forEach(code => {
        const inp = document.getElementById(code + '_votes');
        const val = inp ? (parseInt(inp.value) || 0) : 0;
        totalValid += val;
        if (code === 'lp') lp = val;
      });
      const rejected = parseInt(document.getElementById('rejected_votes')?.value) || 0;
      const accredited = parseInt(document.getElementById('total_accredited')?.value) || 0;

      const totalCast = totalValid + rejected;

      document.getElementById('displayValidVotes').textContent = totalValid.toLocaleString();

      if (totalValid > 0) {
        const lpPct = ((lp / totalValid) * 100).toFixed(1);
        document.getElementById('displayLpPct').textContent = `${lpPct}% (LP)`;
      } else {
        document.getElementById('displayLpPct').textContent = '0.0%';
      }

      const balElem = document.getElementById('displayBalance');
      if (accredited > 0) {
        if (totalCast === accredited) {
          balElem.innerHTML = `<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-xs">check_circle</span> Balanced: ${totalCast}/${accredited} Cast = Accredited</span>`;
          balElem.className = 'font-bold text-emerald-700 text-xs';
        } else if (totalCast > accredited) {
          balElem.innerHTML = `<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-xs">warning</span> Over-voting: ${totalCast} cast > ${accredited} accredited!</span>`;
          balElem.className = 'font-bold text-red-600 text-xs';
        } else {
          balElem.textContent = `Under-voting: ${totalCast} cast of ${accredited} accredited`;
          balElem.className = 'font-semibold text-slate-500 text-xs';
        }
      } else {
        balElem.textContent = 'Enter accredited count';
        balElem.className = 'font-semibold text-slate-400 text-xs';
      }
    }

    let selectedFileDataUrl = null;
    let selectedFileName = null;

    function previewImage(input) {
      if (input.files && input.files[0]) {
        const file = input.files[0];
        selectedFileName = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
          selectedFileDataUrl = e.target.result;
          const prev = document.getElementById('imagePreview');
          if (file.type === 'application/pdf') {
            prev.src = './assets/images/pdf_icon.png';
          } else {
            prev.src = e.target.result;
          }
          document.getElementById('fileNameLabel').textContent = file.name;
          document.getElementById('imagePreviewContainer').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
      }
    }

    // Offline Queue Helpers
    function getOfflineQueue() {
      try {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
      } catch (e) {
        return [];
      }
    }

    function saveOfflineQueue(queue) {
      localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
      updateOfflineQueueUI();
    }

    function updateOfflineQueueUI() {
      const queue = getOfflineQueue();
      const bar = document.getElementById('offlineQueueBar');
      const txt = document.getElementById('offlineQueueText');
      if (!bar || !txt) return;
      if (queue.length > 0) {
        bar.classList.remove('hidden');
        txt.textContent = `${queue.length} Form EC8A submission${queue.length > 1 ? 's' : ''} stored offline awaiting internet`;
      } else {
        bar.classList.add('hidden');
      }
    }

    function dataURItoBlob(dataURI) {
      const parts = dataURI.split(',');
      const byteString = atob(parts[1]);
      const mimeString = parts[0].split(':')[1].split(';')[0];
      const ab = new ArrayBuffer(byteString.length);
      const ia = new Uint8Array(ab);
      for (let i = 0; i < byteString.length; i++) {
        ia[i] = byteString.charCodeAt(i);
      }
      return new Blob([ab], { type: mimeString });
    }

    async function syncOfflineQueue() {
      const queue = getOfflineQueue();
      if (queue.length === 0) return;
      if (!navigator.onLine) {
        showToast('Device is still offline. Please connect to internet to sync.', 'warning');
        return;
      }

      showToast(`Syncing ${queue.length} offline submission(s)...`, 'info');
      const remaining = [];

      for (const item of queue) {
        try {
          const fd = new FormData();
          for (const key in item.fields) {
            fd.append(key, item.fields[key]);
          }
          if (item.fileData && item.fileName) {
            const blob = dataURItoBlob(item.fileData);
            fd.append('ec8a_photo', blob, item.fileName);
          }
          const res = await fetch('api.php', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json' }
          });
          const data = await res.json();
          if (!data.success) {
            console.error('Offline item rejected by server:', data.message);
            showToast(`Sync rejected: ${data.message}`, 'error');
          }
        } catch (err) {
          remaining.push(item);
        }
      }

      saveOfflineQueue(remaining);
      if (remaining.length === 0) {
        showToast('All queued offline results transmitted successfully!', 'success');
        setTimeout(() => window.location.reload(), 1500);
      } else {
        showToast(`${remaining.length} item(s) could not sync. Retrying shortly.`, 'warning');
      }
    }

    async function handleResultSubmit(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('submitResultBtn');
      btn.innerHTML = '<span>Transmitting...</span>';
      btn.disabled = true;

      // Check if client is offline
      if (!navigator.onLine) {
        queueResultOffline(form);
        return;
      }

      try {
        const formData = new FormData(form);
        const res = await fetch('api.php', {
          method: 'POST',
          body: formData,
          headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
          showToast('Form EC8A result transmitted successfully!', 'success');
          setTimeout(() => window.location.reload(), 1200);
        } else {
          showToast(data.message, 'error');
          btn.innerHTML = '<span>Transmit EC8A Results to HQ &rarr;</span>';
          btn.disabled = false;
        }
      } catch (err) {
        console.warn('Network transmission error, queuing offline:', err);
        queueResultOffline(form);
      }
    }

    function queueResultOffline(form) {
      const btn = document.getElementById('submitResultBtn');
      const queue = getOfflineQueue();
      const fields = {};
      const fd = new FormData(form);
      for (const [k, v] of fd.entries()) {
        if (k !== 'ec8a_photo') {
          fields[k] = v;
        }
      }

      queue.push({
        id: Date.now(),
        createdAt: new Date().toISOString(),
        fields: fields,
        fileData: selectedFileDataUrl,
        fileName: selectedFileName
      });

      saveOfflineQueue(queue);
      showToast('Form EC8A saved in offline device queue. Will sync on reconnection.', 'info');
      form.reset();
      document.getElementById('imagePreviewContainer').classList.add('hidden');
      selectedFileDataUrl = null;
      selectedFileName = null;
      btn.innerHTML = '<span>Transmit EC8A Results to HQ &rarr;</span>';
      btn.disabled = false;
    }

    async function handleIncidentSubmit(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('submitIncidentBtn');
      btn.innerText = 'Dispatching alert...';
      btn.disabled = true;

      try {
        const formData = new FormData(form);
        const res = await fetch('api.php', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.success) {
          showToast('Incident alert dispatched to Central Command.', 'alert');
          form.reset();
          setTimeout(() => window.location.reload(), 1200);
        } else {
          showToast(data.message, 'error');
          btn.innerText = 'Transmit Field Incident to Central Command';
          btn.disabled = false;
        }
      } catch (err) {
        showToast('Network error while dispatching incident alert.', 'error');
        btn.innerText = 'Transmit Field Incident to Central Command';
        btn.disabled = false;
      }
    }

    async function handleChangePassword(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('savePasswordBtn');
      btn.innerText = 'Saving...';
      btn.disabled = true;

      try {
        const formData = new FormData(form);
        const res = await fetch('api.php', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.success) {
          showToast('Password updated successfully!', 'success');
          form.reset();
          closePasswordModal();
        } else {
          showToast(data.message, 'error');
        }
      } catch (err) {
        showToast('Error updating password.', 'error');
      } finally {
        btn.innerText = 'Save New Password';
        btn.disabled = false;
      }
    }

    async function markAnnouncementRead(id, btn) {
      try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const fd = new FormData();
        fd.append('action', 'mark_announcement_read');
        fd.append('announcement_id', id);
        fd.append('id', id);
        fd.append('csrf_token', csrf);
        const res = await fetch('api.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.success) {
          if (btn) {
            btn.innerHTML = '<span class="material-symbols-outlined text-xs">check</span> Read';
            btn.disabled = true;
            btn.className = 'inline-flex items-center gap-1 text-[11px] font-bold text-slate-400 bg-slate-100 px-3 py-1 rounded-full cursor-default';
          }
          showToast('Announcement marked as read', 'success');
          const badge = document.getElementById('unreadAnnBadge');
          if (badge) {
            let count = parseInt(badge.textContent) || 0;
            count = Math.max(0, count - 1);
            if (count > 0) {
              badge.textContent = count;
            } else {
              badge.classList.add('hidden');
            }
          }
        }
      } catch (e) {
        console.error(e);
      }
    }

    function showToast(msg, type = 'success') {
      const toast = document.getElementById('dashboardToast');
      const text = document.getElementById('toastMessage');
      const icon = document.getElementById('dashToastIcon');
      if (toast && text) {
        let iconName = 'check_circle';
        let iconColor = 'text-emerald-400';
        let cleanMsg = msg || '';
        if (typeof cleanMsg === 'string') {
          cleanMsg = cleanMsg.replace(/^[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]\s*/u, '').replace(/\s*[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]$/u, '');

          if (cleanMsg.toLowerCase().includes('fail') || cleanMsg.toLowerCase().includes('reject') || cleanMsg.toLowerCase().includes('error') || type === 'error') {
            iconName = 'cancel';
            iconColor = 'text-red-400';
          } else if (cleanMsg.toLowerCase().includes('warn') || cleanMsg.toLowerCase().includes('offline') || type === 'warning') {
            iconName = 'warning';
            iconColor = 'text-amber-400';
          } else if (cleanMsg.toLowerCase().includes('alert') || cleanMsg.toLowerCase().includes('incident') || type === 'alert') {
            iconName = 'emergency';
            iconColor = 'text-red-400';
          } else if (cleanMsg.toLowerCase().includes('sync') || cleanMsg.toLowerCase().includes('queue') || cleanMsg.toLowerCase().includes('internet')) {
            iconName = 'sync';
            iconColor = 'text-sky-400';
          } else {
            iconName = 'check_circle';
            iconColor = 'text-emerald-400';
          }
        }
        if (icon) {
          icon.textContent = iconName;
          icon.className = `material-symbols-outlined text-[20px] ${iconColor}`;
        }
        text.textContent = cleanMsg;
        toast.classList.remove('translate-y-32', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
        setTimeout(() => {
          toast.classList.remove('translate-y-0', 'opacity-100');
          toast.classList.add('translate-y-32', 'opacity-0');
        }, 3500);
      }
    }

    function updateNetworkStatus() {
      const badge = document.getElementById('onlineBadge');
      const txt = document.getElementById('onlineBadgeText');
      if (navigator.onLine) {
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600 text-white font-mono';
        txt.textContent = 'ONLINE';
      } else {
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-600 text-white font-mono';
        txt.textContent = 'OFFLINE';
      }
    }

    window.addEventListener('online', () => {
      updateNetworkStatus();
      showToast('Internet connection restored. Synchronizing queue...', 'info');
      syncOfflineQueue();
    });

    window.addEventListener('offline', () => {
      updateNetworkStatus();
      showToast('You are offline. Submissions will be safely queued on device.', 'warning');
    });

    window.addEventListener('DOMContentLoaded', () => {
      updateNetworkStatus();
      updateOfflineQueueUI();
    });
  </script>
</body>
</html>
