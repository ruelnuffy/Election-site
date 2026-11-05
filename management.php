<?php
/**
 * SIA 2027 Senatorial District Management Command Portal
 * Dedicated Executive Management Dashboard for Every Senatorial District
 * Completely Separate from IT Admin Sign-in • Zero-Friction Leadership Access
 */
require_once __DIR__ . '/config.php';
$districts = get_senatorial_districts();

$district_param = strtolower(trim($_GET['district'] ?? ''));
$is_gateway = empty($district_param) || !isset($districts[$district_param]);

if (!$is_gateway) {
    $active_district = $districts[$district_param];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 text-slate-100 scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>
    <?= $is_gateway ? 'SIA 2027 | Senatorial Districts Management Gateway' : htmlspecialchars($active_district['name']) . ' &bull; Management Command' ?>
  </title>
  <link rel="icon" type="image/png" href="./assets/images/logos/lp_logo.png">

  <!-- Typography: Plus Jakarta Sans & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Outfit:wght@100..900&display=swap" rel="stylesheet">

  <!-- Google Material Symbols Icons -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', '"Outfit"', 'system-ui', '-apple-system', 'sans-serif'],
            heading: ['"Plus Jakarta Sans"', 'Helvetica', 'Arial', 'sans-serif'],
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
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
    }
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      vertical-align: middle;
    }
    .material-symbols-outlined.fill-1 {
      font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
    }
    .pulse-subtle {
      animation: subtlePulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes subtlePulse {
      0%, 100% { opacity: 1; }
      50% { opacity: .35; }
    }
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>
<body class="min-h-full flex flex-col bg-slate-950 text-slate-100 antialiased selection:bg-emerald-600 selection:text-white">

  <!-- =========================================================================
       1. EXECUTIVE MANAGEMENT TOPBAR
       ========================================================================= -->
  <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 sm:h-20 gap-3">
        
        <!-- Left: Branding -->
        <a href="management.php" class="flex items-center gap-3 text-decoration-none group">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white font-black text-base flex items-center justify-center tracking-tight shadow-lg shadow-emerald-950/50 group-hover:scale-105 transition-transform">
            SIA
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-white font-extrabold text-base sm:text-lg tracking-tight group-hover:text-emerald-400 transition-colors">
                SIA 2027
              </span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                District Management
              </span>
            </div>
            <span class="text-xs text-slate-400 hidden sm:block">
              <?= $is_gateway ? 'Senatorial Districts Command Gateway' : htmlspecialchars($active_district['name']) ?>
            </span>
          </div>
        </a>

        <!-- Right: Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          
          <!-- Live Telemetry Pill -->
          <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-subtle"></span>
            <span class="hidden sm:inline">LIVE COMMAND ROOM</span>
            <span class="sm:hidden">LIVE</span>
          </div>

          <?php if (!$is_gateway): ?>
            <!-- Back to Gateway -->
            <a href="management.php" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 px-3.5 py-2 rounded-xl transition-all">
              <span class="material-symbols-outlined text-sm">grid_view</span>
              <span class="hidden md:inline">All Districts</span>
            </a>
          <?php endif; ?>

          <!-- Situation Room Link -->
          <a href="index.php" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 border border-slate-700/80 px-3.5 py-2 rounded-xl transition-all">
            <span class="material-symbols-outlined text-sm">home</span>
            <span class="hidden lg:inline">Situation Room</span>
          </a>

          <!-- Manual Refresh -->
          <button onclick="refreshDistrictData()" id="refreshBtn" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-colors border border-slate-800" title="Refresh Live District Feed">
            <span class="material-symbols-outlined text-lg leading-none" id="refreshIcon">refresh</span>
          </button>

        </div>

      </div>
    </div>
  </header>

  <?php if ($is_gateway): ?>
  <!-- =========================================================================
       2. GATEWAY VIEW: SELECT YOUR SENATORIAL DISTRICT
       ========================================================================= -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl space-y-4">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
        <span class="material-symbols-outlined text-sm">shield</span>
        <span>Electoral Operation Command</span>
      </div>
      <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
        Senatorial District Management Command
      </h1>
      <p class="text-sm sm:text-base text-slate-400 max-w-3xl leading-relaxed">
        Select a Senatorial District to access its dedicated management dashboard. Each zonal command provides direct operational controls: LGA coordinators communication desk, real-time Section 179(2)(b) constitutional spread tracking, Form EC8A sheet inspection, district incident resolution, and direct agent broadcast dispatching.
      </p>
    </div>

    <!-- 3 Senatorial District Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      
      <!-- Adamawa North -->
      <?php $d_north = $districts['north']; ?>
      <div class="bg-slate-900 border border-slate-800 hover:border-emerald-500/50 rounded-2xl p-6 shadow-xl flex flex-col justify-between transition-all group">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-lg">
              <?= $d_north['code'] ?>
            </span>
            <span class="text-xs font-semibold text-slate-400">HQ: <strong class="text-white"><?= $d_north['hq'] ?></strong></span>
          </div>
          <div>
            <h2 class="text-xl font-black text-white group-hover:text-emerald-400 transition-colors">
              <?= $d_north['short_name'] ?>
            </h2>
            <p class="text-xs text-slate-400 mt-1">
              <?= $d_north['strategic_role'] ?>
            </p>
          </div>
          <div class="bg-slate-950/60 rounded-xl p-3.5 space-y-2 border border-slate-800 text-xs">
            <div class="flex items-center justify-between text-slate-400">
              <span>Local Governments:</span>
              <strong class="text-white font-mono">5 LGAs</strong>
            </div>
            <div class="flex items-center justify-between text-slate-400">
              <span>Polling Units:</span>
              <strong class="text-white font-mono">924 PUs</strong>
            </div>
            <div class="flex items-center justify-between text-slate-400">
              <span>Registered Voters:</span>
              <strong class="text-white font-mono">605,876</strong>
            </div>
          </div>
          <div class="text-[11px] text-slate-500">
            <strong>LGAs:</strong> Madagali, Maiha, Michika, Mubi North, Mubi South
          </div>
        </div>
        <div class="pt-6">
          <a href="management.php?district=north" class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-lg shadow-emerald-950 transition-all">
            <span>Open North Management Command</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </a>
        </div>
      </div>

      <!-- Adamawa Central -->
      <?php $d_central = $districts['central']; ?>
      <div class="bg-slate-900 border border-slate-800 hover:border-blue-500/50 rounded-2xl p-6 shadow-xl flex flex-col justify-between transition-all group">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-bold text-blue-400 bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-lg">
              <?= $d_central['code'] ?>
            </span>
            <span class="text-xs font-semibold text-slate-400">HQ: <strong class="text-white"><?= $d_central['hq'] ?></strong></span>
          </div>
          <div>
            <h2 class="text-xl font-black text-white group-hover:text-blue-400 transition-colors">
              <?= $d_central['short_name'] ?>
            </h2>
            <p class="text-xs text-slate-400 mt-1">
              <?= $d_central['strategic_role'] ?>
            </p>
          </div>
          <div class="bg-slate-950/60 rounded-xl p-3.5 space-y-2 border border-slate-800 text-xs">
            <div class="flex items-center justify-between text-slate-400">
              <span>Local Governments:</span>
              <strong class="text-white font-mono">7 LGAs</strong>
            </div>
            <div class="flex items-center justify-between text-slate-400">
              <span>Polling Units:</span>
              <strong class="text-white font-mono">1,686 PUs</strong>
            </div>
            <div class="flex items-center justify-between text-slate-400">
              <span>Registered Voters:</span>
              <strong class="text-white font-mono">1,104,880</strong>
            </div>
          </div>
          <div class="text-[11px] text-slate-500">
            <strong>LGAs:</strong> Fufore, Girei, Gombi, Hong, Song, Yola North, Yola South
          </div>
        </div>
        <div class="pt-6">
          <a href="management.php?district=central" class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-lg shadow-blue-950 transition-all">
            <span>Open Central Management Command</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </a>
        </div>
      </div>

      <!-- Adamawa South -->
      <?php $d_south = $districts['south']; ?>
      <div class="bg-slate-900 border border-slate-800 hover:border-amber-500/50 rounded-2xl p-6 shadow-xl flex flex-col justify-between transition-all group">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-bold text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2.5 py-1 rounded-lg">
              <?= $d_south['code'] ?>
            </span>
            <span class="text-xs font-semibold text-slate-400">HQ: <strong class="text-white"><?= $d_south['hq'] ?></strong></span>
          </div>
          <div>
            <h2 class="text-xl font-black text-white group-hover:text-amber-400 transition-colors">
              <?= $d_south['short_name'] ?>
            </h2>
            <p class="text-xs text-slate-400 mt-1">
              <?= $d_south['strategic_role'] ?>
            </p>
          </div>
          <div class="bg-slate-950/60 rounded-xl p-3.5 space-y-2 border border-slate-800 text-xs">
            <div class="flex items-center justify-between text-slate-400">
              <span>Local Governments:</span>
              <strong class="text-white font-mono">9 LGAs</strong>
            </div>
            <div class="flex items-center justify-between text-slate-400">
              <span>Polling Units:</span>
              <strong class="text-white font-mono">1,491 PUs</strong>
            </div>
            <div class="flex items-center justify-between text-slate-400">
              <span>Registered Voters:</span>
              <strong class="text-white font-mono">969,815</strong>
            </div>
          </div>
          <div class="text-[11px] text-slate-500">
            <strong>LGAs:</strong> Demsa, Ganye, Guyuk, Jada, Lamurde, Mayo-Belwa, Numan, Shelleng, Toungo
          </div>
        </div>
        <div class="pt-6">
          <a href="management.php?district=south" class="w-full inline-flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-500 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-lg shadow-amber-950 transition-all">
            <span>Open South Management Command</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </a>
        </div>
      </div>

    </div>

  </main>

  <?php else: ?>

  <!-- =========================================================================
       3. DEDICATED DISTRICT MANAGEMENT DASHBOARD VIEW
       ========================================================================= -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- District Command Hero Header -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-lg text-xs font-black uppercase font-mono bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
              <?= htmlspecialchars($active_district['code']) ?>
            </span>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
              <?= htmlspecialchars($active_district['name']) ?>
            </h1>
          </div>
          <p class="text-xs sm:text-sm text-slate-400 mt-1">
            Zonal Headquarters: <strong class="text-white"><?= htmlspecialchars($active_district['hq']) ?></strong> &bull; 
            Strategic Delimitation: <span class="text-emerald-400 font-semibold"><?= htmlspecialchars($active_district['strategic_role']) ?></span>
          </p>
        </div>

        <!-- Management Actions Toolbar -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Compose Broadcast Button -->
          <button onclick="openBroadcastModal()" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-bold px-3.5 py-2.5 rounded-xl shadow-md transition-all cursor-pointer">
            <span class="material-symbols-outlined text-sm">campaign</span>
            <span>Dispatch Directive</span>
          </button>

          <!-- Export CSV -->
          <a href="api.php?action=senatorial_lga_breakdown&district=<?= $district_param ?>&export=csv" class="inline-flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs sm:text-sm font-semibold px-3.5 py-2.5 rounded-xl border border-slate-700 transition-all text-decoration-none">
            <span class="material-symbols-outlined text-sm">download</span>
            <span>Export CSV</span>
          </a>

          <!-- District Switcher Ribbon -->
          <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
            <a href="management.php?district=north" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors <?= $district_param === 'north' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">North</a>
            <a href="management.php?district=central" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors <?= $district_param === 'central' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' ?>">Central</a>
            <a href="management.php?district=south" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors <?= $district_param === 'south' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white' ?>">South</a>
          </div>
        </div>
      </div>
    </div>

    <!-- =========================================================================
         4. CORE MANAGEMENT KPI METRICS
         ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- KPI 1: Labour Party Candidate Lead -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-400 uppercase tracking-wider">
          <span>Priority Candidate (LP)</span>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">SIA 2027</span>
        </div>
        <div>
          <div class="text-2xl sm:text-3xl font-black text-white" id="kpiLpVotes">--</div>
          <div class="text-xs font-semibold text-emerald-400 flex items-center gap-1 mt-0.5" id="kpiLpPct">
            <span class="material-symbols-outlined text-sm">trending_up</span>
            <span>--% of Valid District Votes</span>
          </div>
        </div>
        <div class="text-[11px] text-slate-400 border-t border-slate-800/80 pt-2 flex items-center justify-between">
          <span>Margin of Lead:</span>
          <strong class="text-white font-mono" id="kpiLpMargin">--</strong>
        </div>
      </div>

      <!-- KPI 2: Section 179(2)(b) 25% Spread Compliance -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-400 uppercase tracking-wider">
          <span>25% Constitutional Spread</span>
          <span class="material-symbols-outlined text-slate-400 text-base" title="Section 179(2)(b)">gavel</span>
        </div>
        <div>
          <div class="text-2xl sm:text-3xl font-black text-white" id="kpiSpreadCount">-- / --</div>
          <div class="text-xs font-bold mt-1" id="kpiSpreadBadge">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-400">Auditing...</span>
          </div>
        </div>
        <div class="text-[11px] text-slate-400 border-t border-slate-800/80 pt-2 flex items-center justify-between">
          <span>Statutory Rule:</span>
          <span class="text-emerald-400 font-semibold">1999 Constitution</span>
        </div>
      </div>

      <!-- KPI 3: PU Collation Progress -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-400 uppercase tracking-wider">
          <span>PU Collation Progress</span>
          <span class="material-symbols-outlined text-slate-400 text-base">how_to_vote</span>
        </div>
        <div>
          <div class="text-2xl sm:text-3xl font-black text-white" id="kpiPuCount">-- / --</div>
          <div class="text-xs font-semibold text-slate-400 mt-1 flex items-center gap-1.5">
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden flex-1">
              <div id="kpiPuBar" class="bg-emerald-500 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
            <span class="text-xs font-mono font-bold text-white" id="kpiPuPctText">0%</span>
          </div>
        </div>
        <div class="text-[11px] text-slate-400 border-t border-slate-800/80 pt-2 flex items-center justify-between">
          <span>Reg. Voters:</span>
          <strong class="text-white font-mono" id="kpiRegVoters">--</strong>
        </div>
      </div>

      <!-- KPI 4: Ground Telemetry Distribution -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-400 uppercase tracking-wider">
          <span>Ground Telemetry</span>
          <span class="material-symbols-outlined text-slate-400 text-base">cell_tower</span>
        </div>
        <div class="space-y-1 text-xs">
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-slate-300">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span>Direct on PU Ground</span>
            </span>
            <strong class="font-mono text-white" id="teleGround">0</strong>
          </div>
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-slate-300">
              <span class="w-2 h-2 rounded-full bg-amber-400"></span>
              <span>10m Transit Upload</span>
            </span>
            <strong class="font-mono text-white" id="tele10m">0</strong>
          </div>
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-slate-300">
              <span class="w-2 h-2 rounded-full bg-rose-400"></span>
              <span>30m Remote Blindspot</span>
            </span>
            <strong class="font-mono text-white" id="tele30m">0</strong>
          </div>
        </div>
        <div class="text-[11px] text-slate-400 border-t border-slate-800/80 pt-1.5 flex items-center justify-between">
          <span>Transmission Integrity:</span>
          <span class="text-emerald-400 font-bold" id="teleAuditConf">High</span>
        </div>
      </div>

    </div>

    <!-- =========================================================================
         5. MANAGEMENT TABS NAVIGATION
         ========================================================================= -->
    <div class="border-b border-slate-800 flex items-center gap-2 overflow-x-auto no-scrollbar">
      <button onclick="switchManagementTab('collation')" id="mtab-collation" class="management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-emerald-500 text-emerald-400 transition-all whitespace-nowrap">
        <span class="material-symbols-outlined text-base">table_chart</span>
        <span>LGA Collation League</span>
      </button>
      <button onclick="switchManagementTab('coordinators')" id="mtab-coordinators" class="management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-all whitespace-nowrap">
        <span class="material-symbols-outlined text-base">supervisor_account</span>
        <span>LGA Coordinators Desk</span>
      </button>
      <button onclick="switchManagementTab('ec8a')" id="mtab-ec8a" class="management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-all whitespace-nowrap">
        <span class="material-symbols-outlined text-base">image</span>
        <span>Form EC8A Inspector</span>
      </button>
      <button onclick="switchManagementTab('incidents')" id="mtab-incidents" class="management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-all whitespace-nowrap">
        <span class="material-symbols-outlined text-base">crisis_alert</span>
        <span>Incident Resolution Desk</span>
      </button>
      <button onclick="switchManagementTab('agents')" id="mtab-agents" class="management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-all whitespace-nowrap">
        <span class="material-symbols-outlined text-base">verified_user</span>
        <span>Field Agent Telemetry</span>
      </button>
    </div>

    <!-- =========================================================================
         TAB 1: LGA COLLATION LEAGUE TABLE
         ========================================================================= -->
    <div id="view-collation" class="management-view space-y-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
              <span class="material-symbols-outlined text-emerald-400">analytics</span>
              <span>Comparative LGA Collation Table &bull; <?= htmlspecialchars($active_district['short_name']) ?></span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Collation progress, LP vote share, Section 179(2)(b) quota, and Ward drill-downs.
            </p>
          </div>
          <div class="text-xs text-slate-500 font-mono" id="lgaUpdatedNotice">
            Fetching data...
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs sm:text-sm">
            <thead>
              <tr class="bg-slate-950/60 border-b border-slate-800 text-slate-400 text-[11px] uppercase tracking-wider font-semibold">
                <th class="p-3 sm:p-4">LGA Name</th>
                <th class="p-3 sm:p-4">Collation Progress</th>
                <th class="p-3 sm:p-4 text-right">LP (SIA)</th>
                <th class="p-3 sm:p-4 text-right">APC</th>
                <th class="p-3 sm:p-4 text-right">PDP</th>
                <th class="p-3 sm:p-4 text-right">LP %</th>
                <th class="p-3 sm:p-4 text-center">25% Quota</th>
                <th class="p-3 sm:p-4 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="lgaTableBody" class="divide-y divide-slate-800/60">
              <tr>
                <td colspan="8" class="p-8 text-center text-slate-500">
                  Loading district collation data...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- =========================================================================
         TAB 2: LGA COORDINATORS DESK
         ========================================================================= -->
    <div id="view-coordinators" class="management-view hidden space-y-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-5">
        <div class="pb-4 border-b border-slate-800 flex items-center justify-between mb-4">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
              <span class="material-symbols-outlined text-emerald-400">supervisor_account</span>
              <span>LGA Coordinators Command Roster</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Assigned field leadership per Local Government Area</p>
          </div>
          <span class="text-xs text-slate-400 font-mono" id="coordCountBadge">5 LGAs</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="coordinatorsGrid">
          <div class="p-8 text-center text-slate-500 col-span-3">Loading coordinator roster...</div>
        </div>
      </div>
    </div>

    <!-- =========================================================================
         TAB 3: FORM EC8A INSPECTOR
         ========================================================================= -->
    <div id="view-ec8a" class="management-view hidden space-y-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-5">
        <div class="pb-4 border-b border-slate-800 flex items-center justify-between mb-4">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
              <span class="material-symbols-outlined text-emerald-400">document_scanner</span>
              <span>Form EC8A Ground Result Sheets Gallery</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Auditing transmitted result sheets, tallies, and agent signatures</p>
          </div>
          <span class="text-xs text-slate-400 font-mono" id="ec8aCountBadge">Recent submissions</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="ec8aGrid">
          <div class="p-8 text-center text-slate-500 col-span-3">Loading Form EC8A records...</div>
        </div>
      </div>
    </div>

    <!-- =========================================================================
         TAB 4: INCIDENT RESOLUTION DESK
         ========================================================================= -->
    <div id="view-incidents" class="management-view hidden space-y-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-5">
        <div class="pb-4 border-b border-slate-800 flex items-center justify-between mb-4">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
              <span class="material-symbols-outlined text-rose-500">crisis_alert</span>
              <span>Zonal Incident Resolution & Security Desk</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage live threat escalation, deploy response, and record resolution notes</p>
          </div>
          <span class="text-xs text-slate-400 font-mono" id="incidentTotalBadge">0 incidents</span>
        </div>

        <div class="space-y-3" id="managementIncidentsList">
          <div class="p-8 text-center text-slate-500">Loading district incidents...</div>
        </div>
      </div>
    </div>

    <!-- =========================================================================
         TAB 5: FIELD AGENT TELEMETRY ROSTER
         ========================================================================= -->
    <div id="view-agents" class="management-view hidden space-y-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-5">
        <div class="pb-4 border-b border-slate-800 flex items-center justify-between mb-4">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
              <span class="material-symbols-outlined text-emerald-400">verified_user</span>
              <span>Accredited Field Agent Telemetry</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Facial photo identification, polling unit deployment, and ground transmission health</p>
          </div>
          <span class="text-xs text-slate-400 font-mono" id="agentsTotalBadge">0 active</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="managementAgentsGrid">
          <div class="p-8 text-center text-slate-500 col-span-3">Loading field agents...</div>
        </div>
      </div>
    </div>

  </main>

  <!-- =========================================================================
       6. WARD DRILL-DOWN MODAL
       ========================================================================= -->
  <div id="wardModal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-slate-900 rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-800 text-slate-100">
      
      <!-- Modal Header -->
      <div class="p-4 sm:p-5 border-b border-slate-800 flex items-center justify-between bg-slate-950">
        <div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-400">account_tree</span>
            <h3 class="text-base sm:text-lg font-black text-white" id="wardModalTitle">Ward Drill-Down</h3>
          </div>
          <p class="text-xs text-slate-400 mt-0.5" id="wardModalSub">Detailed collation across all electoral wards</p>
        </div>
        <button onclick="closeWardModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
          <span class="material-symbols-outlined text-xl">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3" id="wardModalBody">
        <div class="p-8 text-center text-slate-400">Loading wards...</div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 border-t border-slate-800 bg-slate-950 flex items-center justify-between text-xs text-slate-400">
        <span>Verified Ground Collation</span>
        <button onclick="closeWardModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl transition-colors">
          Close
        </button>
      </div>

    </div>
  </div>

  <!-- =========================================================================
       7. DIRECTIVE / BROADCAST MODAL
       ========================================================================= -->
  <div id="broadcastModal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-slate-900 rounded-2xl shadow-2xl max-w-lg w-full flex flex-col overflow-hidden border border-slate-800 text-slate-100">
      
      <div class="p-4 sm:p-5 border-b border-slate-800 flex items-center justify-between bg-slate-950">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-400">campaign</span>
          <h3 class="text-base font-black text-white">Dispatch Zonal Directive</h3>
        </div>
        <button onclick="closeBroadcastModal()" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>

      <form onsubmit="handleSendBroadcast(event)" class="p-5 space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Target Audience</label>
          <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-emerald-400 font-bold">
            All Accredited Field Agents in <?= htmlspecialchars($active_district['name']) ?>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Priority Level</label>
          <select id="bcastPriority" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-white focus:border-emerald-500 focus:outline-none">
            <option value="urgent">Urgent Directive</option>
            <option value="critical">Critical Security Alert</option>
            <option value="normal">Standard Operation Update</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Directive Headline</label>
          <input type="text" id="bcastTitle" placeholder="e.g. Ensure Form EC8A is signed by Presiding Officer" required class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-white focus:border-emerald-500 focus:outline-none">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Directive Instructions</label>
          <textarea id="bcastBody" rows="4" placeholder="Type clear instructions for all agents in your Senatorial District..." required class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-white focus:border-emerald-500 focus:outline-none"></textarea>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" onclick="closeBroadcastModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl">
            Cancel
          </button>
          <button type="submit" id="bcastSubmitBtn" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md transition-all">
            Dispatch Now &rarr;
          </button>
        </div>
      </form>

    </div>
  </div>

  <!-- =========================================================================
       8. FORM EC8A IMAGE PREVIEW MODAL
       ========================================================================= -->
  <div id="imageModal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-slate-900 rounded-2xl shadow-2xl max-w-2xl w-full flex flex-col overflow-hidden border border-slate-800 text-slate-100">
      <div class="p-3.5 border-b border-slate-800 flex items-center justify-between bg-slate-950">
        <span class="text-xs font-bold text-white" id="imageModalTitle">Form EC8A Preview</span>
        <button onclick="closeImageModal()" class="p-1 text-slate-400 hover:text-white rounded-lg">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>
      <div class="p-4 flex items-center justify-center bg-slate-950 max-h-[75vh] overflow-auto">
        <img id="imageModalImg" src="" alt="Form EC8A" class="max-w-full h-auto rounded-lg shadow-md border border-slate-800">
      </div>
    </div>
  </div>

  <!-- =========================================================================
       9. JAVASCRIPT CONTROLLER FOR MANAGEMENT ACTIONS
       ========================================================================= -->
  <script>
    const currentDistrict = '<?= $district_param ?>';
    let autoRefreshTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
      loadManagementData();
      autoRefreshTimer = setInterval(() => {
        loadManagementData(true);
      }, 25000);
    });

    function switchManagementTab(tabName) {
      document.querySelectorAll('.management-nav-tab').forEach(t => {
        t.className = 'management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-all whitespace-nowrap';
      });
      const activeTab = document.getElementById(`mtab-${tabName}`);
      if (activeTab) {
        activeTab.className = 'management-nav-tab inline-flex items-center gap-2 py-3 px-4 text-xs sm:text-sm font-bold border-b-2 border-emerald-500 text-emerald-400 transition-all whitespace-nowrap';
      }

      document.querySelectorAll('.management-view').forEach(v => v.classList.add('hidden'));
      const activeView = document.getElementById(`view-${tabName}`);
      if (activeView) {
        activeView.classList.remove('hidden');
      }

      if (tabName === 'coordinators') loadCoordinators();
      if (tabName === 'ec8a') loadResultSheets();
      if (tabName === 'incidents') loadIncidents();
      if (tabName === 'agents') loadAgents();
    }

    async function refreshDistrictData() {
      const icon = document.getElementById('refreshIcon');
      icon.classList.add('animate-spin');
      await loadManagementData();
      setTimeout(() => {
        icon.classList.remove('animate-spin');
      }, 600);
    }

    async function loadManagementData(silent = false) {
      try {
        if (!silent) {
          document.getElementById('lgaUpdatedNotice').textContent = 'Refreshing tallies...';
        }

        const [overviewRes, lgaRes] = await Promise.all([
          fetch(`api.php?action=senatorial_overview&district=${currentDistrict}`).then(r => r.json()),
          fetch(`api.php?action=senatorial_lga_breakdown&district=${currentDistrict}`).then(r => r.json())
        ]);

        if (overviewRes.success) renderOverviewKPIs(overviewRes);
        if (lgaRes.success) renderLgaTable(lgaRes.lgas);

        document.getElementById('lgaUpdatedNotice').textContent = `Live: ${new Date().toLocaleTimeString()}`;

      } catch (err) {
        console.error('Failed to load management data:', err);
        document.getElementById('lgaUpdatedNotice').textContent = 'Error connecting to HQ';
      }
    }

    function renderOverviewKPIs(data) {
      const kpis = data.kpis;

      // 1. LP Votes
      document.getElementById('kpiLpVotes').textContent = Number(kpis.lp_votes).toLocaleString();
      document.getElementById('kpiLpPct').innerHTML = `
        <span class="material-symbols-outlined text-sm">trending_up</span>
        <span>${kpis.lp_percentage}% of Valid District Votes</span>
      `;
      document.getElementById('kpiLpMargin').textContent = (kpis.margin_lead >= 0 ? '+' : '') + Number(kpis.margin_lead).toLocaleString() + ' votes';

      // 2. 25% Spread Compliance
      document.getElementById('kpiSpreadCount').textContent = `${kpis.passed_25_count} / ${kpis.passed_25_total} LGAs`;
      const isPassed = kpis.passed_25_all;
      const badgeClass = isPassed 
        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' 
        : 'bg-amber-500/20 text-amber-400 border border-amber-500/30';
      const badgeText = isPassed 
        ? '✓ Section 179(2)(b) Quota Satisfied' 
        : `${kpis.passed_25_total - kpis.passed_25_count} LGA(s) Below 25% Threshold`;
      document.getElementById('kpiSpreadBadge').innerHTML = `
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold ${badgeClass}">${badgeText}</span>
      `;

      // 3. PU Progress
      document.getElementById('kpiPuCount').textContent = `${Number(kpis.reported_pus).toLocaleString()} / ${Number(kpis.total_pus).toLocaleString()}`;
      document.getElementById('kpiPuBar').style.width = `${kpis.reporting_percentage}%`;
      document.getElementById('kpiPuPctText').textContent = `${kpis.reporting_percentage}%`;
      document.getElementById('kpiRegVoters').textContent = Number(kpis.total_registered).toLocaleString();

      // 4. Ground Telemetry
      const tele = data.transmission_health || { ground: 0, transit_10m: 0, transit_30m: 0 };
      document.getElementById('teleGround').textContent = Number(tele.ground).toLocaleString();
      document.getElementById('tele10m').textContent = Number(tele.transit_10m).toLocaleString();
      document.getElementById('tele30m').textContent = Number(tele.transit_30m).toLocaleString();

      const totalTrans = tele.ground + tele.transit_10m + tele.transit_30m;
      const groundRatio = totalTrans > 0 ? (tele.ground / totalTrans) * 100 : 100;
      document.getElementById('teleAuditConf').textContent = groundRatio >= 80 ? 'High (Direct PU)' : 'Medium (Transit Uploads)';
    }

    function renderLgaTable(lgas) {
      const tbody = document.getElementById('lgaTableBody');
      if (!lgas || lgas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-slate-500">No LGA records found for this district.</td></tr>`;
        return;
      }

      tbody.innerHTML = lgas.map(lga => {
        const passed25 = lga.passed_25_pct;
        const spreadBadge = passed25 
          ? `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
              <span class="material-symbols-outlined text-xs">check_circle</span>
              <span>Passed</span>
             </span>`
          : `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
              <span class="material-symbols-outlined text-xs">warning</span>
              <span>${lga.total_valid > 0 ? 'Deficit' : 'Pending'}</span>
             </span>`;

        return `
          <tr class="hover:bg-slate-800/40 transition-colors">
            <td class="p-3 sm:p-4">
              <div class="font-bold text-white">${escapeHtml(lga.name)}</div>
              <div class="text-[11px] text-slate-400 font-mono">${Number(lga.total_registered).toLocaleString()} reg. voters</div>
            </td>
            <td class="p-3 sm:p-4">
              <div class="flex items-center gap-2">
                <div class="w-16 sm:w-24 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                  <div class="bg-emerald-500 h-full rounded-full" style="width: ${lga.reporting_percentage}%"></div>
                </div>
                <span class="text-xs font-mono font-semibold text-slate-300">${lga.reported_pus}/${lga.total_pus} (${lga.reporting_percentage}%)</span>
              </div>
            </td>
            <td class="p-3 sm:p-4 text-right font-black text-emerald-400">
              ${Number(lga.lp_votes).toLocaleString()}
            </td>
            <td class="p-3 sm:p-4 text-right font-mono text-slate-400">
              ${Number(lga.apc_votes).toLocaleString()}
            </td>
            <td class="p-3 sm:p-4 text-right font-mono text-slate-400">
              ${Number(lga.pdp_votes).toLocaleString()}
            </td>
            <td class="p-3 sm:p-4 text-right font-mono font-bold ${passed25 ? 'text-emerald-400' : 'text-slate-300'}">
              ${lga.lp_percentage}%
            </td>
            <td class="p-3 sm:p-4 text-center">
              ${spreadBadge}
            </td>
            <td class="p-3 sm:p-4 text-center">
              <button onclick="openWardModal(${lga.id}, '${escapeHtml(lga.name)}')" 
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white transition-all border border-slate-700">
                <span class="material-symbols-outlined text-sm">visibility</span>
                <span>Inspect Wards</span>
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    async function loadCoordinators() {
      const grid = document.getElementById('coordinatorsGrid');
      grid.innerHTML = '<div class="p-8 text-center text-slate-500 col-span-3">Loading LGA coordinators...</div>';

      try {
        const res = await fetch(`api.php?action=management_coordinators&district=${currentDistrict}`).then(r => r.json());
        if (!res.success || !res.coordinators || res.coordinators.length === 0) {
          grid.innerHTML = '<div class="p-8 text-center text-slate-500 col-span-3">No coordinators mapped for this district.</div>';
          return;
        }

        document.getElementById('coordCountBadge').textContent = `${res.coordinators.length} LGAs mapped`;

        grid.innerHTML = res.coordinators.map(c => `
          <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 flex flex-col justify-between space-y-3">
            <div>
              <div class="flex items-center justify-between">
                <span class="text-xs font-mono font-bold text-emerald-400 uppercase">${escapeHtml(c.lga_name)}</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
              </div>
              <h3 class="font-bold text-white text-sm mt-1">${escapeHtml(c.coordinator_name)}</h3>
              <p class="text-xs text-slate-400 font-mono">${escapeHtml(c.coordinator_email)}</p>
            </div>
            <div class="border-t border-slate-800/80 pt-2 flex items-center justify-between text-xs text-slate-400">
              <span>Collation Progress:</span>
              <strong class="text-white font-mono">${c.reported_pus}/${c.total_pus} (${c.reporting_percentage}%)</strong>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-1">
              <a href="mailto:${escapeHtml(c.coordinator_email)}" class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-colors">
                <span class="material-symbols-outlined text-sm">mail</span>
                <span>Email</span>
              </a>
              <button onclick="alert('LGA Coordinator: ${escapeHtml(c.coordinator_name)}\\nLGA: ${escapeHtml(c.lga_name)}\\nContact: ${escapeHtml(c.coordinator_email)}')" class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 text-xs font-bold border border-emerald-500/30 transition-colors">
                <span class="material-symbols-outlined text-sm">contact_phone</span>
                <span>Contact</span>
              </button>
            </div>
          </div>
        `).join('');

      } catch (err) {
        grid.innerHTML = '<div class="p-8 text-center text-rose-400 col-span-3">Failed to load coordinator roster.</div>';
      }
    }

    async function loadResultSheets() {
      const grid = document.getElementById('ec8aGrid');
      grid.innerHTML = '<div class="p-8 text-center text-slate-500 col-span-3">Fetching Form EC8A ground sheets...</div>';

      try {
        const res = await fetch(`api.php?action=management_result_sheets&district=${currentDistrict}&limit=30`).then(r => r.json());
        if (!res.success || !res.sheets || res.sheets.length === 0) {
          grid.innerHTML = '<div class="p-8 text-center text-slate-500 col-span-3">No Form EC8A result sheets submitted for this district yet.</div>';
          return;
        }

        document.getElementById('ec8aCountBadge').textContent = `${res.sheets.length} sheets audited`;

        grid.innerHTML = res.sheets.map(s => {
          const hasImage = !!s.ec8a_image;
          const imgSrc = hasImage ? (s.ec8a_image.startsWith('http') ? s.ec8a_image : s.ec8a_image) : null;
          
          return `
            <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 flex flex-col justify-between space-y-3">
              <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-white truncate">${escapeHtml(s.lga)} &bull; ${escapeHtml(s.ward)}</span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold ${s.verification_status === 'verified' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'} uppercase">
                    ${escapeHtml(s.verification_status)}
                  </span>
                </div>
                <div class="text-xs text-slate-400 line-clamp-1 font-mono">${escapeHtml(s.polling_unit)}</div>
                
                ${hasImage ? `
                  <div onclick="openImageModal('${escapeHtml(imgSrc)}', '${escapeHtml(s.polling_unit)}')" class="w-full h-32 bg-slate-900 rounded-lg overflow-hidden border border-slate-800 cursor-pointer relative group">
                    <img src="${escapeHtml(imgSrc)}" alt="EC8A" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1 text-white text-xs font-bold">
                      <span class="material-symbols-outlined text-sm">zoom_in</span>
                      <span>Enlarge Sheet</span>
                    </div>
                  </div>
                ` : `
                  <div class="w-full h-20 bg-slate-900 rounded-lg border border-dashed border-slate-800 flex items-center justify-center text-slate-500 text-xs">
                    No image attached
                  </div>
                `}

                <div class="bg-slate-900/80 rounded-lg p-2.5 flex items-center justify-between text-xs">
                  <div>
                    <span class="text-slate-500 text-[10px] uppercase block">LP (SIA)</span>
                    <strong class="text-emerald-400 font-mono text-sm">${Number(s.lp_votes).toLocaleString()}</strong>
                  </div>
                  <div>
                    <span class="text-slate-500 text-[10px] uppercase block">APC</span>
                    <strong class="text-slate-300 font-mono text-sm">${Number(s.apc_votes).toLocaleString()}</strong>
                  </div>
                  <div>
                    <span class="text-slate-500 text-[10px] uppercase block">PDP</span>
                    <strong class="text-slate-300 font-mono text-sm">${Number(s.pdp_votes).toLocaleString()}</strong>
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-800 pt-2 flex items-center justify-between text-[11px] text-slate-400">
                <span class="truncate">Agent: ${escapeHtml(s.agent_name || s.agent_code || 'Accredited')}</span>
                <span class="text-emerald-400 font-medium">${escapeHtml(s.transmission_status || 'PU Ground')}</span>
              </div>
            </div>
          `;
        }).join('');

      } catch (err) {
        grid.innerHTML = '<div class="p-8 text-center text-rose-400 col-span-3">Failed to load Form EC8A records.</div>';
      }
    }

    async function loadIncidents() {
      const container = document.getElementById('managementIncidentsList');
      container.innerHTML = '<div class="p-8 text-center text-slate-500">Loading incident tickets...</div>';

      try {
        const res = await fetch(`api.php?action=senatorial_incidents&district=${currentDistrict}`).then(r => r.json());
        if (!res.success || !res.incidents || res.incidents.length === 0) {
          container.innerHTML = '<div class="p-8 text-center text-slate-500">Zero active incidents recorded in this Senatorial District.</div>';
          document.getElementById('incidentTotalBadge').textContent = '0 logged';
          return;
        }

        document.getElementById('incidentTotalBadge').textContent = `${res.incidents.length} recorded`;

        container.innerHTML = res.incidents.map(inc => {
          const isCritical = inc.severity === 'Critical';
          return `
            <div class="bg-slate-950/90 border ${isCritical ? 'border-rose-900/60 bg-rose-950/20' : 'border-slate-800'} rounded-xl p-4 space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase ${isCritical ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-300'}">
                      ${escapeHtml(inc.severity)}
                    </span>
                    <h3 class="text-white font-bold text-sm">${escapeHtml(inc.title || 'Electoral Threat')}</h3>
                  </div>
                  <p class="text-xs text-slate-400 mt-1">${escapeHtml(inc.description)}</p>
                </div>
                <div class="text-right">
                  <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase ${inc.status === 'resolved' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}">
                    Status: ${escapeHtml(inc.status || 'Received')}
                  </span>
                </div>
              </div>

              <div class="flex flex-wrap items-center justify-between border-t border-slate-800/80 pt-3 gap-3">
                <span class="text-xs text-slate-500 font-mono">Location: ${escapeHtml(inc.lga)} &bull; ${escapeHtml(inc.ward || 'Ward')} &bull; ${escapeHtml(inc.polling_unit || 'PU')}</span>
                <div class="flex items-center gap-2">
                  <button onclick="promptIncidentUpdate(${inc.id}, '${escapeHtml(inc.status)}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all">
                    Update Resolution &rarr;
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');

      } catch (err) {
        container.innerHTML = '<div class="p-8 text-center text-rose-400">Failed to load incident feed.</div>';
      }
    }

    async function promptIncidentUpdate(id, currentStatus) {
      const newStatus = prompt(`Update status for Incident #${id}:\n(received, in-progress, escalated, resolved)`, currentStatus);
      if (!newStatus) return;

      const note = prompt("Enter resolution or action note:", "Reviewed and addressed by Zonal Management");
      if (note === null) return;

      try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', newStatus);
        formData.append('resolution_note', note);

        const res = await fetch('api.php?action=management_update_incident', {
          method: 'POST',
          body: formData
        }).then(r => r.json());

        if (res.success) {
          alert(res.message);
          loadIncidents();
        } else {
          alert(res.message || 'Error updating incident.');
        }
      } catch (err) {
        alert('Failed to connect to server.');
      }
    }

    async function loadAgents() {
      const grid = document.getElementById('managementAgentsGrid');
      grid.innerHTML = '<div class="p-8 text-center text-slate-500 col-span-3">Loading accredited field agents...</div>';

      try {
        const res = await fetch(`api.php?action=senatorial_agents&district=${currentDistrict}`).then(r => r.json());
        if (!res.success || !res.agents || res.agents.length === 0) {
          grid.innerHTML = '<div class="p-8 text-center text-slate-500 col-span-3">No field agents registered for this district yet.</div>';
          document.getElementById('agentsTotalBadge').textContent = '0 active';
          return;
        }

        document.getElementById('agentsTotalBadge').textContent = `${res.agents.length} active`;

        grid.innerHTML = res.agents.map(ag => {
          const photoSrc = ag.photo ? (ag.photo.startsWith('http') ? ag.photo : ag.photo) : null;
          return `
            <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-3.5 flex items-center gap-3">
              <div class="w-12 h-12 rounded-xl bg-slate-900 border border-slate-800 overflow-hidden flex-shrink-0 flex items-center justify-center">
                ${photoSrc 
                  ? `<img src="${escapeHtml(photoSrc)}" alt="${escapeHtml(ag.name)}" class="w-full h-full object-cover">`
                  : `<span class="material-symbols-outlined text-slate-500 text-2xl">person</span>`
                }
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-white truncate">${escapeHtml(ag.name)}</span>
                  <span class="text-[10px] font-mono text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">${escapeHtml(ag.agent_code || 'SIA-AGENT')}</span>
                </div>
                <div class="text-[11px] text-slate-400 truncate">${escapeHtml(ag.lga)} &bull; ${escapeHtml(ag.ward || 'Ward')}</div>
                <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                  <span>${escapeHtml(ag.latest_transmission || 'On PU Ground')}</span>
                </div>
              </div>
            </div>
          `;
        }).join('');

      } catch (err) {
        grid.innerHTML = '<div class="p-8 text-center text-rose-400 col-span-3">Failed to load field agents.</div>';
      }
    }

    async function handleSendBroadcast(e) {
      e.preventDefault();
      const title = document.getElementById('bcastTitle').value.trim();
      const body = document.getElementById('bcastBody').value.trim();
      const priority = document.getElementById('bcastPriority').value;
      const btn = document.getElementById('bcastSubmitBtn');

      if (!title || !body) return;

      btn.disabled = true;
      btn.textContent = 'Dispatching...';

      try {
        const formData = new FormData();
        formData.append('district', currentDistrict);
        formData.append('title', title);
        formData.append('body', body);
        formData.append('priority', priority);

        const res = await fetch('api.php?action=management_broadcast', {
          method: 'POST',
          body: formData
        }).then(r => r.json());

        if (res.success) {
          alert(`Success! ${res.message}`);
          closeBroadcastModal();
          document.getElementById('bcastTitle').value = '';
          document.getElementById('bcastBody').value = '';
        } else {
          alert(res.message || 'Error sending directive.');
        }
      } catch (err) {
        alert('Failed to dispatch directive.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Dispatch Now →';
      }
    }

    async function openWardModal(lgaId, lgaName) {
      const modal = document.getElementById('wardModal');
      const body = document.getElementById('wardModalBody');
      const title = document.getElementById('wardModalTitle');
      const sub = document.getElementById('wardModalSub');

      title.textContent = `${lgaName} — Electoral Ward Drill-Down`;
      sub.textContent = 'Inspecting all electoral wards and EC8A collation progress';
      body.innerHTML = '<div class="p-8 text-center text-slate-400">Loading ward data...</div>';
      modal.classList.remove('hidden');
      modal.classList.add('flex');

      try {
        const res = await fetch(`api.php?action=senatorial_ward_breakdown&lga_id=${lgaId}`).then(r => r.json());
        if (!res.success || !res.wards || res.wards.length === 0) {
          body.innerHTML = '<div class="p-8 text-center text-slate-400">No ward records found for this LGA.</div>';
          return;
        }

        body.innerHTML = `
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase font-semibold">
                  <th class="p-2.5">Ward Name</th>
                  <th class="p-2.5 text-center">PUs</th>
                  <th class="p-2.5 text-right">Reg. Voters</th>
                  <th class="p-2.5 text-right font-bold text-emerald-400">LP Votes</th>
                  <th class="p-2.5 text-right">APC</th>
                  <th class="p-2.5 text-right">PDP</th>
                  <th class="p-2.5 text-right">LP %</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/80">
                ${res.wards.map(w => `
                  <tr class="hover:bg-slate-800/40">
                    <td class="p-2.5 font-bold text-white">${escapeHtml(w.name)}</td>
                    <td class="p-2.5 text-center font-mono text-slate-300">${w.reported_pus}/${w.total_pus}</td>
                    <td class="p-2.5 text-right font-mono text-slate-400">${Number(w.total_registered).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-black text-emerald-400">${Number(w.lp_votes).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-mono text-slate-400">${Number(w.apc_votes).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-mono text-slate-400">${Number(w.pdp_votes).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-mono font-bold ${w.passed_25_pct ? 'text-emerald-400' : 'text-slate-300'}">${w.lp_percentage}%</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      } catch (err) {
        body.innerHTML = '<div class="p-8 text-center text-rose-400">Failed to load ward data.</div>';
      }
    }

    function closeWardModal() {
      const modal = document.getElementById('wardModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    function openBroadcastModal() {
      const modal = document.getElementById('broadcastModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeBroadcastModal() {
      const modal = document.getElementById('broadcastModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    function openImageModal(src, title) {
      document.getElementById('imageModalImg').src = src;
      document.getElementById('imageModalTitle').textContent = `Form EC8A &bull; ${title}`;
      const modal = document.getElementById('imageModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeImageModal() {
      const modal = document.getElementById('imageModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
  </script>
  <?php endif; ?>

</body>
</html>
