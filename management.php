<?php
/**
 * SIA 2027 Senatorial District Management Command Dashboard
 * One Unified Management Dashboard with Real-Time Analytics & Reports
 * Authenticated per District via district_managers table in database
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();

// Enforce District Manager Authentication
$manager = require_district_manager_auth();

$districts = get_senatorial_districts();
$manager_district = $manager['district'] ?? 'north';

// Allow viewing specific district if manager has access or explicitly switched
$district_param = strtolower(trim($_GET['district'] ?? $manager_district));
if (!isset($districts[$district_param])) {
    $district_param = isset($districts[$manager_district]) ? $manager_district : 'north';
}
$active_district = $districts[$district_param];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-800 scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIA 2027 | <?= htmlspecialchars($active_district['name']) ?> &bull; Management Command</title>
  <link rel="icon" type="image/png" href="./assets/images/logos/lp_logo.png">

  <!-- Typography: Plus Jakarta Sans & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

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
    @media print {
      body { background: white !important; color: black !important; }
      aside, header, .no-print { display: none !important; }
      .print-only { display: block !important; }
    }
  </style>
</head>
<body class="min-h-full flex h-screen overflow-hidden bg-slate-50 text-slate-800 antialiased selection:bg-emerald-600 selection:text-white">

  <!-- Mobile Backdrop Overlay -->
  <div id="sidebarBackdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 hidden lg:hidden transition-opacity"></div>

  <!-- =========================================================================
       1. LEFT SIDEBAR: GROUPED DASHBOARD CONTROLS
       ========================================================================= -->
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200/90 flex flex-col justify-between transition-transform duration-300 transform -translate-x-full lg:translate-x-0 lg:static no-print flex-shrink-0">
    
    <!-- Sidebar Top / Brand -->
    <div class="p-5 border-b border-slate-100 space-y-4">
      <div class="flex items-center justify-between">
        <a href="management.php" class="flex items-center gap-3 text-decoration-none group">
          <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 shadow-xs p-1 flex items-center justify-center overflow-hidden transition-transform group-hover:scale-105">
            <img src="./assets/images/logos/lp_logo.png" alt="Labour Party" class="w-full h-full object-contain">
          </div>
          <div>
            <span class="font-bold text-slate-900 text-sm tracking-tight block">SIA 2027</span>
            <span class="text-[11px] text-slate-500 block font-medium">Zonal Command</span>
          </div>
        </a>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          Live
        </span>
      </div>

      <!-- District Switcher Segmented Control -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5 px-1">
          Senatorial District
        </div>
        <div class="bg-slate-100 p-1 rounded-xl grid grid-cols-3 gap-1 text-center">
          <a href="management.php?district=north" 
             class="py-1.5 px-2 rounded-lg text-xs font-bold transition-all text-decoration-none <?= $district_param === 'north' ? 'bg-white text-emerald-700 shadow-xs ring-1 ring-slate-200/50' : 'text-slate-500 hover:text-slate-900' ?>">
            North
          </a>
          <a href="management.php?district=central" 
             class="py-1.5 px-2 rounded-lg text-xs font-bold transition-all text-decoration-none <?= $district_param === 'central' ? 'bg-white text-emerald-700 shadow-xs ring-1 ring-slate-200/50' : 'text-slate-500 hover:text-slate-900' ?>">
            Central
          </a>
          <a href="management.php?district=south" 
             class="py-1.5 px-2 rounded-lg text-xs font-bold transition-all text-decoration-none <?= $district_param === 'south' ? 'bg-white text-emerald-700 shadow-xs ring-1 ring-slate-200/50' : 'text-slate-500 hover:text-slate-900' ?>">
            South
          </a>
        </div>
      </div>
    </div>

    <!-- Sidebar Navigation Groups -->
    <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
      
      <!-- Group 1: Operations -->
      <div class="space-y-1">
        <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
          Operations
        </div>
        <button onclick="switchManagementTab('collation')" id="mtab-collation" 
                class="management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-colors bg-emerald-50 text-emerald-800 border border-emerald-100/80">
          <span class="material-symbols-outlined text-lg">table_chart</span>
          <span>Collation League</span>
        </button>
        <button onclick="switchManagementTab('reports')" id="mtab-reports" 
                class="management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
          <span class="material-symbols-outlined text-lg">summarize</span>
          <span>Analytics & Reports</span>
        </button>
      </div>

      <!-- Group 2: Field Command -->
      <div class="space-y-1">
        <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
          Field Command
        </div>
        <button onclick="switchManagementTab('coordinators')" id="mtab-coordinators" 
                class="management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
          <span class="material-symbols-outlined text-lg">supervisor_account</span>
          <span>Coordinators Desk</span>
        </button>
        <button onclick="switchManagementTab('agents')" id="mtab-agents" 
                class="management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
          <span class="material-symbols-outlined text-lg">verified_user</span>
          <span>Agent Telemetry</span>
        </button>
      </div>

      <!-- Group 3: Audit & Resolution -->
      <div class="space-y-1">
        <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
          Audit & Resolution
        </div>
        <button onclick="switchManagementTab('ec8a')" id="mtab-ec8a" 
                class="management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
          <span class="material-symbols-outlined text-lg">document_scanner</span>
          <span>Form EC8A Inspector</span>
        </button>
        <button onclick="switchManagementTab('incidents')" id="mtab-incidents" 
                class="management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
          <span class="material-symbols-outlined text-lg">crisis_alert</span>
          <span>Incident Desk</span>
        </button>
      </div>

      <!-- Quick Action: Dispatch Directive -->
      <div class="pt-2 px-1">
        <button onclick="openBroadcastModal()" class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white py-2.5 px-3 rounded-xl text-xs font-semibold transition-all flex items-center justify-center gap-2 shadow-xs shadow-emerald-600/20 cursor-pointer">
          <span class="material-symbols-outlined text-base">campaign</span>
          <span>Dispatch Directive</span>
        </button>
      </div>

    </div>

    <!-- Sidebar Bottom: Director Info & Sign Out -->
    <div class="p-4 border-t border-slate-100 bg-slate-50/60 space-y-3">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center text-sm shadow-xs flex-shrink-0">
          <?= strtoupper(substr($manager['name'] ?? 'M', 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-xs font-bold text-slate-900 truncate"><?= htmlspecialchars($manager['name']) ?></div>
          <div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($active_district['hq']) ?> Zonal Command</div>
        </div>
      </div>
      <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200/60">
        <a href="index.php" class="text-slate-500 hover:text-slate-800 transition-colors">Public Room</a>
        <a href="management_logout.php" class="text-rose-600 hover:text-rose-700 font-semibold flex items-center gap-1 transition-colors">
          <span class="material-symbols-outlined text-sm">logout</span>
          <span>Sign Out</span>
        </a>
      </div>
    </div>

  </aside>

  <!-- =========================================================================
       2. MAIN CONTENT AREA (CLEAN WHITE THEME)
       ========================================================================= -->
  <div class="flex-1 flex flex-col h-screen overflow-y-auto bg-slate-50 min-w-0">
    
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200/80 px-4 sm:px-6 py-3.5 flex items-center justify-between sticky top-0 z-20 no-print flex-shrink-0">
      <div class="flex items-center gap-3 min-w-0">
        <!-- Mobile hamburger -->
        <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl" aria-label="Toggle sidebar">
          <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="min-w-0">
          <h1 class="text-sm sm:text-base font-bold text-slate-900 truncate leading-tight">
            <?= htmlspecialchars($active_district['name']) ?>
          </h1>
          <p class="text-xs text-slate-500 font-normal truncate mt-0.5">
            Zonal HQ: <strong class="text-slate-700"><?= htmlspecialchars($active_district['hq']) ?></strong> &bull; <?= count($active_district['lgas']) ?> LGAs &bull; <span class="text-emerald-700 font-medium"><?= htmlspecialchars($active_district['strategic_role']) ?></span>
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
        <span class="text-xs text-slate-400 font-mono hidden md:inline" id="lgaUpdatedNotice">Live: Connecting...</span>
        
        <button onclick="refreshDistrictData()" id="refreshBtn" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-colors border border-slate-200" title="Refresh Live District Feed">
          <span class="material-symbols-outlined text-lg leading-none" id="refreshIcon">refresh</span>
        </button>

        <a href="api.php?action=senatorial_lga_breakdown&district=<?= $district_param ?>&export=csv" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 shadow-2xs transition-colors">
          <span class="material-symbols-outlined text-sm">download</span>
          <span class="hidden sm:inline">Export CSV</span>
        </a>
      </div>
    </header>

    <!-- Main Scrollable Body -->
    <main class="flex-1 p-4 sm:p-6 space-y-6 max-w-7xl w-full mx-auto">
      
      <!-- 4 Core High-Impact KPI Metrics Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print">
        
        <!-- KPI 1: Labour Party Candidate Lead -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs space-y-2">
          <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
            <span>Priority Candidate</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">LP &bull; SIA</span>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight" id="kpiLpVotes">--</div>
            <div class="text-xs font-semibold text-emerald-700 flex items-center gap-1 mt-0.5" id="kpiLpPct">
              <span class="material-symbols-outlined text-sm">trending_up</span>
              <span>--% of Valid District Votes</span>
            </div>
          </div>
          <div class="text-xs text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
            <span>Lead Margin:</span>
            <strong class="text-slate-900 font-mono font-bold" id="kpiLpMargin">--</strong>
          </div>
        </div>

        <!-- KPI 2: Section 179(2)(b) 25% Spread Compliance -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs space-y-2">
          <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
            <span>25% Constitutional Spread</span>
            <span class="material-symbols-outlined text-slate-400 text-base" title="Section 179(2)(b)">gavel</span>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight" id="kpiSpreadText">-- / <?= count($active_district['lgas']) ?></div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-2 overflow-hidden">
              <div class="bg-emerald-600 h-full rounded-full transition-all duration-500" style="width: 0%" id="kpiSpreadBar"></div>
            </div>
          </div>
          <div class="text-xs text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
            <span>Section 179(2)(b):</span>
            <span id="kpiSpreadBadge" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
              Evaluating
            </span>
          </div>
        </div>

        <!-- KPI 3: Polling Unit Collation Progress -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs space-y-2">
          <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
            <span>Collation Progress</span>
            <span class="material-symbols-outlined text-slate-400 text-base">how_to_vote</span>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight" id="kpiPuCount">-- / --</div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-2 overflow-hidden">
              <div class="bg-emerald-600 h-full rounded-full transition-all duration-500" style="width: 0%" id="kpiPuBar"></div>
            </div>
          </div>
          <div class="text-xs text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
            <span>Reporting Rate:</span>
            <strong class="text-slate-900 font-mono font-bold" id="kpiPuPctText">--%</strong>
          </div>
        </div>

        <!-- KPI 4: Direct PU Ground Transmission Health -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs space-y-2">
          <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
            <span>Ground Telemetry</span>
            <span class="material-symbols-outlined text-slate-400 text-base">cell_tower</span>
          </div>
          <div class="space-y-1 text-xs pt-0.5">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1.5 text-slate-600">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Direct on PU Ground</span>
              </span>
              <strong class="font-mono text-slate-900 font-bold" id="teleGround">0</strong>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1.5 text-slate-600">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Transit Upload (10-30m)</span>
              </span>
              <strong class="font-mono text-slate-900 font-bold" id="tele10m">0</strong>
            </div>
            <div class="hidden" id="tele30m">0</div>
          </div>
          <div class="text-xs text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
            <span>Transmission Health:</span>
            <span class="text-emerald-700 font-bold text-xs" id="teleAuditConf">High (PU Direct)</span>
          </div>
        </div>

      </div>

      <!-- =========================================================================
           TAB 1: LGA COLLATION LEAGUE TABLE
           ========================================================================= -->
      <div id="view-collation" class="management-view space-y-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
          <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2 m-0">
                <span class="material-symbols-outlined text-emerald-600 text-lg">table_chart</span>
                <span>Comparative LGA Collation Table &bull; <?= htmlspecialchars($active_district['short_name']) ?></span>
              </h2>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-400 font-mono"><?= count($active_district['lgas']) ?> LGAs</span>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
              <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-slate-600 text-[11px] uppercase tracking-wider font-semibold">
                  <th class="p-3.5 sm:p-4">LGA Name</th>
                  <th class="p-3.5 sm:p-4">Collation Progress</th>
                  <th class="p-3.5 sm:p-4 text-right">LP (SIA)</th>
                  <th class="p-3.5 sm:p-4 text-right">APC</th>
                  <th class="p-3.5 sm:p-4 text-right">PDP</th>
                  <th class="p-3.5 sm:p-4 text-right">LP %</th>
                  <th class="p-3.5 sm:p-4 text-center">25% Quota</th>
                  <th class="p-3.5 sm:p-4 text-center">Action</th>
                </tr>
              </thead>
              <tbody id="lgaTableBody" class="divide-y divide-slate-100">
                <tr>
                  <td colspan="8" class="p-8 text-center text-slate-400">
                    Loading district collation data...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- =========================================================================
           TAB 2: ANALYTICS & EXECUTIVE REPORTS
           ========================================================================= -->
      <div id="view-reports" class="management-view hidden space-y-5">
        
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-3">
            <div>
              <h2 class="text-base sm:text-lg font-bold text-slate-900 m-0">
                Analytics & Reports &bull; <?= htmlspecialchars($active_district['name']) ?>
              </h2>
              <p class="text-xs text-slate-500 mt-0.5">Collation tallies, candidate margins, and statutory spread compliance</p>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-all">
                <span class="material-symbols-outlined text-sm">print</span>
                <span>Print</span>
              </button>
              <a href="api.php?action=senatorial_lga_breakdown&district=<?= $district_param ?>&export=csv" class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-xs transition-all text-decoration-none">
                <span class="material-symbols-outlined text-sm">download</span>
                <span>Export CSV</span>
              </a>
            </div>
          </div>

          <!-- 3 Concise Executive Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-1">
              <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">1. Constitutional Spread</span>
              <div class="text-base sm:text-lg font-extrabold text-slate-900" id="repSpreadSummary">Auditing LGAs...</div>
              <p class="text-xs text-slate-600" id="repSpreadNote">Calculation in progress...</p>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-1">
              <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">2. Candidate Lead Margin</span>
              <div class="text-base sm:text-lg font-extrabold text-emerald-700" id="repLeadSummary">Evaluating votes...</div>
              <p class="text-xs text-slate-600" id="repLeadNote">Tracking margin over 2nd place...</p>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-1">
              <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">3. Direct Ground Uploads</span>
              <div class="text-base sm:text-lg font-extrabold text-slate-900" id="repGroundSummary">Telemetry active</div>
              <p class="text-xs text-slate-600" id="repGroundNote">Auditing direct PU uploads...</p>
            </div>
          </div>

          <!-- Candidate Vote Distribution -->
          <div class="space-y-3">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Candidate Vote Share</h3>
            <div class="bg-slate-50 rounded-xl border border-slate-200/80 p-4 space-y-3" id="repPartyList">
              <div class="text-center text-xs text-slate-400 py-4">Generating party vote breakdown...</div>
            </div>
          </div>

        </div>

      </div>

      <!-- =========================================================================
           TAB 3: LGA COORDINATORS DESK
           ========================================================================= -->
      <div id="view-coordinators" class="management-view hidden space-y-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs p-5 sm:p-6">
          <div class="pb-4 border-b border-slate-100 flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2 m-0">
                <span class="material-symbols-outlined text-emerald-600 text-lg">supervisor_account</span>
                <span>LGA Coordinators Command Roster</span>
              </h2>
            </div>
            <span class="text-xs text-slate-500 font-mono" id="coordCountBadge"><?= count($active_district['lgas']) ?> LGAs</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="coordinatorsGrid">
            <div class="p-8 text-center text-slate-400 col-span-3">Loading coordinator roster...</div>
          </div>
        </div>
      </div>

      <!-- =========================================================================
           TAB 4: FORM EC8A INSPECTOR
           ========================================================================= -->
      <div id="view-ec8a" class="management-view hidden space-y-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs p-5 sm:p-6">
          <div class="pb-4 border-b border-slate-100 flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2 m-0">
                <span class="material-symbols-outlined text-emerald-600 text-lg">document_scanner</span>
                <span>Form EC8A Inspector</span>
              </h2>
            </div>
            <span class="text-xs text-slate-500 font-mono" id="ec8aCountBadge">Recent submissions</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="ec8aGrid">
            <div class="p-8 text-center text-slate-400 col-span-3">Loading Form EC8A records...</div>
          </div>
        </div>
      </div>

      <!-- =========================================================================
           TAB 5: INCIDENT RESOLUTION DESK
           ========================================================================= -->
      <div id="view-incidents" class="management-view hidden space-y-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs p-5 sm:p-6">
          <div class="pb-4 border-b border-slate-100 flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2 m-0">
                <span class="material-symbols-outlined text-rose-600 text-lg">crisis_alert</span>
                <span>Incident Desk</span>
              </h2>
            </div>
            <span class="text-xs text-slate-500 font-mono" id="incidentTotalBadge">0 logged</span>
          </div>

          <div class="space-y-3" id="managementIncidentsList">
            <div class="p-8 text-center text-slate-400">Loading district incidents...</div>
          </div>
        </div>
      </div>

      <!-- =========================================================================
           TAB 6: FIELD AGENT TELEMETRY
           ========================================================================= -->
      <div id="view-agents" class="management-view hidden space-y-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs p-5 sm:p-6">
          <div class="pb-4 border-b border-slate-100 flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2 m-0">
                <span class="material-symbols-outlined text-emerald-600 text-lg">verified_user</span>
                <span>Agent Telemetry</span>
              </h2>
            </div>
            <span class="text-xs text-slate-500 font-mono" id="agentsTotalBadge">0 active</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="managementAgentsGrid">
            <div class="p-8 text-center text-slate-400 col-span-3">Loading field agents...</div>
          </div>
        </div>
      </div>

    </main>

  </div>

  <!-- =========================================================================
       3. WARD DRILL-DOWN MODAL (CLEAN WHITE)
       ========================================================================= -->
  <div id="wardModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4 no-print">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 text-slate-800">
      
      <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600">account_tree</span>
            <h3 class="text-base font-bold text-slate-900 m-0" id="wardModalTitle">Ward Drill-Down</h3>
          </div>
          <p class="text-xs text-slate-500 mt-0.5 m-0" id="wardModalSub">Inspecting all electoral wards</p>
        </div>
        <button onclick="closeWardModal()" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors">
          <span class="material-symbols-outlined text-xl">close</span>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3" id="wardModalBody">
        <div class="p-8 text-center text-slate-400">Loading wards...</div>
      </div>

      <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs text-slate-500">
        <span>Verified Ground Collation</span>
        <button onclick="closeWardModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold rounded-xl transition-colors">
          Close
        </button>
      </div>

    </div>
  </div>

  <!-- =========================================================================
       4. DIRECTIVE / BROADCAST MODAL (CLEAN WHITE)
       ========================================================================= -->
  <div id="broadcastModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4 no-print">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full flex flex-col overflow-hidden border border-slate-200 text-slate-800">
      
      <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-600">campaign</span>
          <h3 class="text-base font-bold text-slate-900 m-0">Dispatch Zonal Directive</h3>
        </div>
        <button onclick="closeBroadcastModal()" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>

      <form onsubmit="handleSendBroadcast(event)" class="p-5 space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Target Audience</label>
          <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-emerald-700 font-bold">
            All Accredited Field Agents in <?= htmlspecialchars($active_district['name']) ?>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Priority Level</label>
          <select id="bcastPriority" class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 rounded-xl p-2.5 text-xs text-slate-800 font-medium">
            <option value="normal">Normal Operational Directive</option>
            <option value="high">High Priority Action Required</option>
            <option value="urgent">Urgent Security Alert</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Directive Title</label>
          <input type="text" id="bcastTitle" required placeholder="e.g. Ensure physical copy of Form EC8A is signed" class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 rounded-xl p-2.5 text-xs text-slate-800">
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Message Content</label>
          <textarea id="bcastBody" rows="4" required placeholder="Type the operational instruction to be broadcast to all agent mobile devices..." class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 rounded-xl p-2.5 text-xs text-slate-800"></textarea>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" onclick="closeBroadcastModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Cancel</button>
          <button type="submit" id="bcastSubmitBtn" class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all">Dispatch Now &rarr;</button>
        </div>
      </form>

    </div>
  </div>

  <!-- =========================================================================
       5. FORM EC8A IMAGE PREVIEW MODAL (CLEAN WHITE)
       ========================================================================= -->
  <div id="imageModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4 no-print">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full flex flex-col overflow-hidden border border-slate-200 text-slate-800">
      <div class="p-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <span class="text-xs font-bold text-slate-900" id="imageModalTitle">Form EC8A Preview</span>
        <button onclick="closeImageModal()" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>
      <div class="p-3 bg-slate-100 flex items-center justify-center max-h-[75vh] overflow-auto">
        <img src="" id="imageModalImg" alt="EC8A Sheet" class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-sm">
      </div>
      <div class="p-3 border-t border-slate-100 bg-white flex items-center justify-between text-xs">
        <span class="text-slate-500">Official PU Transmitted Result</span>
        <button onclick="closeImageModal()" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-lg text-xs">Close</button>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       6. JAVASCRIPT CONTROLLER FOR MANAGEMENT ACTIONS & REPORTS
       ========================================================================= -->
  <script>
    const currentDistrict = '<?= $district_param ?>';
    let autoRefreshTimer = null;
    let cachedOverviewData = null;

    document.addEventListener('DOMContentLoaded', () => {
      loadManagementData();
      autoRefreshTimer = setInterval(() => {
        loadManagementData(true);
      }, 25000);
    });

    function toggleMobileSidebar() {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebarBackdrop');
      const isClosed = sidebar.classList.contains('-translate-x-full');
      if (isClosed) {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
      } else {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
      }
    }

    function closeMobileSidebar() {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebarBackdrop');
      sidebar.classList.add('-translate-x-full');
      backdrop.classList.add('hidden');
    }

    function switchManagementTab(tabName) {
      // Update sidebar nav items styling
      document.querySelectorAll('.management-nav-tab').forEach(t => {
        t.className = 'management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors';
      });
      const activeTab = document.getElementById(`mtab-${tabName}`);
      if (activeTab) {
        activeTab.className = 'management-nav-tab w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-colors bg-emerald-50 text-emerald-800 border border-emerald-100/80';
      }

      // Toggle visible view
      document.querySelectorAll('.management-view').forEach(v => v.classList.add('hidden'));
      const activeView = document.getElementById(`view-${tabName}`);
      if (activeView) {
        activeView.classList.remove('hidden');
      }

      closeMobileSidebar();

      if (tabName === 'reports' && cachedOverviewData) renderExecutiveReport(cachedOverviewData);
      if (tabName === 'coordinators') loadCoordinators();
      if (tabName === 'ec8a') loadResultSheets();
      if (tabName === 'incidents') loadIncidents();
      if (tabName === 'agents') loadAgents();
    }

    async function refreshDistrictData() {
      const icon = document.getElementById('refreshIcon');
      icon.classList.add('animate-spin');
      await loadManagementData(false);
      setTimeout(() => icon.classList.remove('animate-spin'), 600);
    }

    async function loadManagementData(isBackground = false) {
      try {
        const [overviewRes, lgaRes] = await Promise.all([
          fetch(`api.php?action=senatorial_overview&district=${currentDistrict}`).then(r => r.json()),
          fetch(`api.php?action=senatorial_lga_breakdown&district=${currentDistrict}`).then(r => r.json())
        ]);

        if (overviewRes.success) {
          cachedOverviewData = overviewRes;
          renderOverviewKPIs(overviewRes);
          renderExecutiveReport(overviewRes);
        }
        if (lgaRes.success) renderLgaTable(lgaRes.lgas);

        document.getElementById('lgaUpdatedNotice').textContent = `Live: ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
      } catch (err) {
        if (!isBackground) console.error("District management fetch error:", err);
      }
    }

    function renderOverviewKPIs(data) {
      const kpis = data.kpis;
      
      // 1. LP Candidate Metrics
      document.getElementById('kpiLpVotes').textContent = Number(kpis.lp_votes).toLocaleString();
      document.getElementById('kpiLpPct').innerHTML = `
        <span class="material-symbols-outlined text-sm">trending_up</span>
        <span>${kpis.lp_percentage}% of Valid District Votes</span>
      `;
      const leadMargin = Number(kpis.margin_lead);
      document.getElementById('kpiLpMargin').textContent = (leadMargin >= 0 ? '+' : '') + leadMargin.toLocaleString() + ' votes';
      
      // 2. 25% Spread Compliance
      document.getElementById('kpiSpreadText').textContent = `${kpis.passed_25_count} / ${kpis.passed_25_total}`;
      const spreadPct = kpis.passed_25_total > 0 ? (kpis.passed_25_count / kpis.passed_25_total) * 100 : 0;
      document.getElementById('kpiSpreadBar').style.width = `${spreadPct}%`;

      const badge = document.getElementById('kpiSpreadBadge');
      if (kpis.passed_25_all) {
        badge.className = 'inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200';
        badge.innerHTML = '<span class="material-symbols-outlined text-xs">verified</span> Compliant';
      } else {
        badge.className = 'inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200';
        badge.innerHTML = `<span class="material-symbols-outlined text-xs">pending</span> Deficit (${kpis.passed_25_total - kpis.passed_25_count} LGAs)`;
      }

      // 3. PU Progress
      document.getElementById('kpiPuCount').textContent = `${Number(kpis.reported_pus).toLocaleString()} / ${Number(kpis.total_pus).toLocaleString()}`;
      document.getElementById('kpiPuBar').style.width = `${kpis.reporting_percentage}%`;
      document.getElementById('kpiPuPctText').textContent = `${kpis.reporting_percentage}%`;

      // 4. Ground Telemetry
      const tele = data.transmission_health || { ground: 0, transit_10m: 0, transit_30m: 0 };
      document.getElementById('teleGround').textContent = Number(tele.ground).toLocaleString();
      document.getElementById('tele10m').textContent = Number(tele.transit_10m + tele.transit_30m).toLocaleString();
      
      const totalUploads = tele.ground + tele.transit_10m + tele.transit_30m;
      const groundRatio = totalUploads > 0 ? (tele.ground / totalUploads) * 100 : 100;
      document.getElementById('teleAuditConf').textContent = groundRatio >= 80 ? 'High (PU Direct)' : 'Medium (Transit)';
    }

    function renderExecutiveReport(data) {
      const kpis = data.kpis;
      
      // 1. Spread summary
      document.getElementById('repSpreadSummary').textContent = `${kpis.passed_25_count} of ${kpis.passed_25_total} LGAs Passed`;
      document.getElementById('repSpreadNote').textContent = kpis.passed_25_all 
        ? 'Full Section 179(2)(b) statutory compliance achieved across all local governments.' 
        : `Deficit in ${kpis.passed_25_total - kpis.passed_25_count} LGA(s). Ongoing collation.`;

      // 2. Lead summary
      document.getElementById('repLeadSummary').textContent = (kpis.margin_lead >= 0 ? '+' : '') + Number(kpis.margin_lead).toLocaleString() + ' Votes Margin';
      document.getElementById('repLeadNote').textContent = kpis.is_leading 
        ? `Senator Ishaku Abbo (LP) holds the lead with ${kpis.lp_percentage}% of valid votes.` 
        : 'Collation underway; monitoring second-round margins.';

      // 3. Ground summary
      const tele = data.transmission_health || { ground: 0, transit_10m: 0, transit_30m: 0 };
      const tot = tele.ground + tele.transit_10m + tele.transit_30m;
      const pctGround = tot > 0 ? Math.round((tele.ground / tot) * 100) : 100;
      document.getElementById('repGroundSummary').textContent = `${pctGround}% Direct PU Ground`;
      document.getElementById('repGroundNote').textContent = `${tele.ground} PU ground uploads vs ${tele.transit_10m + tele.transit_30m} transit uploads.`;

      // 4. Candidate Party Breakdown in Report
      const pList = document.getElementById('repPartyList');
      if (data.parties && data.parties.length > 0) {
        pList.innerHTML = data.parties.slice(0, 5).map(p => `
          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold ${p.is_priority ? 'text-emerald-700' : 'text-slate-700'}">${escapeHtml(p.name)} (${escapeHtml(p.code)}) &bull; ${escapeHtml(p.candidate)}</span>
              <span class="font-mono font-bold text-slate-900">${Number(p.votes).toLocaleString()} votes (${p.percentage}%)</span>
            </div>
            <div class="w-full bg-slate-200/80 rounded-full h-2 overflow-hidden">
              <div class="h-full rounded-full ${p.is_priority ? 'bg-emerald-600' : 'bg-slate-400'}" style="width: ${p.percentage}%"></div>
            </div>
          </div>
        `).join('');
      }
    }

    function renderLgaTable(lgas) {
      const tbody = document.getElementById('lgaTableBody');
      if (!lgas || lgas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-slate-400">No LGA records found for this district.</td></tr>`;
        return;
      }

      tbody.innerHTML = lgas.map(lga => {
        const passed25 = lga.passed_25_pct;
        const spreadBadge = passed25 
          ? `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
              <span class="material-symbols-outlined text-xs">check_circle</span>
              <span>Passed</span>
             </span>`
          : `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
              <span class="material-symbols-outlined text-xs">warning</span>
              <span>${lga.total_valid > 0 ? 'Deficit' : 'Pending'}</span>
             </span>`;

        return `
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="p-3.5 sm:p-4">
              <div class="font-bold text-slate-900">${escapeHtml(lga.name)}</div>
              <div class="text-[11px] text-slate-400 font-mono">${Number(lga.total_registered).toLocaleString()} registered</div>
            </td>
            <td class="p-3.5 sm:p-4">
              <div class="flex items-center gap-2">
                <div class="w-16 sm:w-24 bg-slate-200/80 rounded-full h-1.5 overflow-hidden">
                  <div class="bg-emerald-600 h-full rounded-full" style="width: ${lga.reporting_percentage}%"></div>
                </div>
                <span class="text-xs font-mono font-medium text-slate-600">${lga.reported_pus}/${lga.total_pus} (${lga.reporting_percentage}%)</span>
              </div>
            </td>
            <td class="p-3.5 sm:p-4 text-right font-extrabold text-emerald-700">
              ${Number(lga.lp_votes).toLocaleString()}
            </td>
            <td class="p-3.5 sm:p-4 text-right font-mono text-slate-600">
              ${Number(lga.apc_votes).toLocaleString()}
            </td>
            <td class="p-3.5 sm:p-4 text-right font-mono text-slate-600">
              ${Number(lga.pdp_votes).toLocaleString()}
            </td>
            <td class="p-3.5 sm:p-4 text-right font-mono font-bold ${passed25 ? 'text-emerald-700' : 'text-slate-700'}">
              ${lga.lp_percentage}%
            </td>
            <td class="p-3.5 sm:p-4 text-center">
              ${spreadBadge}
            </td>
            <td class="p-3.5 sm:p-4 text-center">
              <button onclick="openWardModal(${lga.id}, '${escapeHtml(lga.name)}')" 
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-emerald-600 text-slate-700 hover:text-white transition-all border border-slate-200">
                <span class="material-symbols-outlined text-sm">visibility</span>
                <span>Inspect</span>
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    async function loadCoordinators() {
      const grid = document.getElementById('coordinatorsGrid');
      grid.innerHTML = '<div class="p-8 text-center text-slate-400 col-span-3">Loading LGA coordinators...</div>';

      try {
        const res = await fetch(`api.php?action=management_coordinators&district=${currentDistrict}`).then(r => r.json());
        if (!res.success || !res.coordinators || res.coordinators.length === 0) {
          grid.innerHTML = '<div class="p-8 text-center text-slate-400 col-span-3">No coordinators mapped for this district.</div>';
          return;
        }

        document.getElementById('coordCountBadge').textContent = `${res.coordinators.length} LGAs mapped`;

        grid.innerHTML = res.coordinators.map(c => `
          <div class="bg-white border border-slate-200/90 rounded-xl p-4 flex flex-col justify-between space-y-3 shadow-2xs">
            <div>
              <div class="flex items-center justify-between">
                <span class="text-xs font-mono font-bold text-emerald-700 uppercase">${escapeHtml(c.lga_name)}</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
              </div>
              <h3 class="font-bold text-slate-900 text-sm mt-1.5 m-0">${escapeHtml(c.coordinator_name)}</h3>
              <p class="text-xs text-slate-500 font-mono mt-0.5 m-0">${escapeHtml(c.coordinator_email)}</p>
            </div>
            <div class="border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-slate-500">
              <span>Collation Progress:</span>
              <strong class="text-slate-900 font-mono font-bold">${c.reported_pus}/${c.total_pus} (${c.reporting_percentage}%)</strong>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-1">
              <a href="mailto:${escapeHtml(c.coordinator_email)}" class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors">
                <span class="material-symbols-outlined text-sm">mail</span>
                <span>Email</span>
              </a>
              <button onclick="alert('LGA Coordinator: ${escapeHtml(c.coordinator_name)}\\nLGA: ${escapeHtml(c.lga_name)}\\nContact: ${escapeHtml(c.coordinator_email)}')" class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200 transition-colors">
                <span class="material-symbols-outlined text-sm">contact_phone</span>
                <span>Contact</span>
              </button>
            </div>
          </div>
        `).join('');

      } catch (err) {
        grid.innerHTML = '<div class="p-8 text-center text-rose-500 col-span-3">Failed to load coordinator roster.</div>';
      }
    }

    async function loadResultSheets() {
      const grid = document.getElementById('ec8aGrid');
      grid.innerHTML = '<div class="p-8 text-center text-slate-400 col-span-3">Fetching Form EC8A ground sheets...</div>';

      try {
        const res = await fetch(`api.php?action=management_result_sheets&district=${currentDistrict}&limit=30`).then(r => r.json());
        if (!res.success || !res.sheets || res.sheets.length === 0) {
          grid.innerHTML = '<div class="p-8 text-center text-slate-400 col-span-3">No Form EC8A result sheets submitted for this district yet.</div>';
          return;
        }

        document.getElementById('ec8aCountBadge').textContent = `${res.sheets.length} sheets audited`;

        grid.innerHTML = res.sheets.map(s => {
          const hasImage = !!s.ec8a_image;
          const imgSrc = hasImage ? s.ec8a_image : null;
          
          return `
            <div class="bg-white border border-slate-200/90 rounded-xl p-4 flex flex-col justify-between space-y-3 shadow-2xs">
              <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-slate-900 truncate">${escapeHtml(s.lga)} &bull; ${escapeHtml(s.ward)}</span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold ${s.verification_status === 'verified' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'} uppercase">
                    ${escapeHtml(s.verification_status)}
                  </span>
                </div>
                <div class="text-xs text-slate-500 line-clamp-1 font-mono">${escapeHtml(s.polling_unit)}</div>
                
                ${hasImage ? `
                  <div onclick="openImageModal('${escapeHtml(imgSrc)}', '${escapeHtml(s.polling_unit)}')" class="w-full h-32 bg-slate-100 rounded-lg overflow-hidden border border-slate-200 cursor-pointer relative group">
                    <img src="${escapeHtml(imgSrc)}" alt="EC8A" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                    <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1 text-white text-xs font-bold">
                      <span class="material-symbols-outlined text-sm">zoom_in</span>
                      <span>Enlarge</span>
                    </div>
                  </div>
                ` : `
                  <div class="w-full h-20 bg-slate-50 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-400 text-xs">
                    No image attached
                  </div>
                `}

                <div class="bg-slate-50 rounded-lg p-2.5 flex items-center justify-between text-xs border border-slate-100">
                  <div>
                    <span class="text-slate-400 text-[10px] uppercase block">LP</span>
                    <strong class="text-emerald-700 font-mono text-sm">${Number(s.lp_votes).toLocaleString()}</strong>
                  </div>
                  <div>
                    <span class="text-slate-400 text-[10px] uppercase block">APC</span>
                    <strong class="text-slate-700 font-mono text-sm">${Number(s.apc_votes).toLocaleString()}</strong>
                  </div>
                  <div>
                    <span class="text-slate-400 text-[10px] uppercase block">PDP</span>
                    <strong class="text-slate-700 font-mono text-sm">${Number(s.pdp_votes).toLocaleString()}</strong>
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-2 flex items-center justify-between text-[11px] text-slate-500">
                <span class="truncate">Agent: ${escapeHtml(s.agent_name || s.agent_code || 'Accredited')}</span>
                <span class="text-emerald-700 font-medium">${escapeHtml(s.transmission_status || 'PU Ground')}</span>
              </div>
            </div>
          `;
        }).join('');

      } catch (err) {
        grid.innerHTML = '<div class="p-8 text-center text-rose-500 col-span-3">Failed to load Form EC8A records.</div>';
      }
    }

    async function loadIncidents() {
      const container = document.getElementById('managementIncidentsList');
      container.innerHTML = '<div class="p-8 text-center text-slate-400">Loading incident tickets...</div>';

      try {
        const res = await fetch(`api.php?action=senatorial_incidents&district=${currentDistrict}`).then(r => r.json());
        if (!res.success || !res.incidents || res.incidents.length === 0) {
          container.innerHTML = '<div class="p-8 text-center text-slate-400">Zero active incidents recorded in this Senatorial District.</div>';
          document.getElementById('incidentTotalBadge').textContent = '0 logged';
          return;
        }

        document.getElementById('incidentTotalBadge').textContent = `${res.incidents.length} recorded`;

        container.innerHTML = res.incidents.map(inc => {
          const isCritical = inc.severity === 'Critical';
          return `
            <div class="bg-white border ${isCritical ? 'border-rose-300 bg-rose-50/20' : 'border-slate-200/90'} rounded-xl p-4 space-y-3 shadow-2xs">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ${isCritical ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-700'}">
                      ${escapeHtml(inc.severity)}
                    </span>
                    <h3 class="text-slate-900 font-bold text-sm m-0">${escapeHtml(inc.title || 'Incident Report')}</h3>
                  </div>
                  <p class="text-xs text-slate-600 mt-1 m-0">${escapeHtml(inc.description)}</p>
                </div>
                <div>
                  <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${inc.status === 'resolved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'}">
                    ${escapeHtml(inc.status || 'Received')}
                  </span>
                </div>
              </div>

              <div class="flex flex-wrap items-center justify-between border-t border-slate-100 pt-3 gap-2">
                <span class="text-xs text-slate-500 font-mono">Location: ${escapeHtml(inc.lga)} &bull; ${escapeHtml(inc.ward || 'Ward')} &bull; ${escapeHtml(inc.polling_unit || 'PU')}</span>
                <button onclick="promptIncidentUpdate(${inc.id}, '${escapeHtml(inc.status)}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-all">
                  Update Resolution &rarr;
                </button>
              </div>
            </div>
          `;
        }).join('');

      } catch (err) {
        container.innerHTML = '<div class="p-8 text-center text-rose-500">Failed to load incident feed.</div>';
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
      grid.innerHTML = '<div class="p-8 text-center text-slate-400 col-span-3">Loading accredited field agents...</div>';

      try {
        const res = await fetch(`api.php?action=senatorial_agents&district=${currentDistrict}`).then(r => r.json());
        if (!res.success || !res.agents || res.agents.length === 0) {
          grid.innerHTML = '<div class="p-8 text-center text-slate-400 col-span-3">No field agents registered for this district yet.</div>';
          document.getElementById('agentsTotalBadge').textContent = '0 active';
          return;
        }

        document.getElementById('agentsTotalBadge').textContent = `${res.agents.length} active`;

        grid.innerHTML = res.agents.map(ag => {
          const photoSrc = ag.photo ? (ag.photo.startsWith('http') ? ag.photo : ag.photo) : null;
          return `
            <div class="bg-white border border-slate-200/90 rounded-xl p-3.5 flex items-center gap-3 shadow-2xs">
              <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                ${photoSrc 
                  ? `<img src="${escapeHtml(photoSrc)}" alt="${escapeHtml(ag.name)}" class="w-full h-full object-cover">`
                  : `<span class="material-symbols-outlined text-slate-400 text-2xl">person</span>`
                }
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-900 truncate">${escapeHtml(ag.name)}</span>
                  <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">${escapeHtml(ag.agent_code || 'SIA-AGENT')}</span>
                </div>
                <div class="text-[11px] text-slate-500 truncate mt-0.5">${escapeHtml(ag.lga)} &bull; ${escapeHtml(ag.ward || 'Ward')}</div>
                <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  <span>${escapeHtml(ag.latest_transmission || 'On PU Ground')}</span>
                </div>
              </div>
            </div>
          `;
        }).join('');

      } catch (err) {
        grid.innerHTML = '<div class="p-8 text-center text-rose-500 col-span-3">Failed to load field agents.</div>';
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
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                  <th class="p-2.5">Ward Name</th>
                  <th class="p-2.5 text-center">PUs</th>
                  <th class="p-2.5 text-right">Reg. Voters</th>
                  <th class="p-2.5 text-right font-bold text-emerald-700">LP Votes</th>
                  <th class="p-2.5 text-right">APC</th>
                  <th class="p-2.5 text-right">PDP</th>
                  <th class="p-2.5 text-right">LP %</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${res.wards.map(w => `
                  <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-bold text-slate-900">${escapeHtml(w.name)}</td>
                    <td class="p-2.5 text-center font-mono text-slate-600">${w.reported_pus}/${w.total_pus}</td>
                    <td class="p-2.5 text-right font-mono text-slate-500">${Number(w.total_registered).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-bold text-emerald-700">${Number(w.lp_votes).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-mono text-slate-600">${Number(w.apc_votes).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-mono text-slate-600">${Number(w.pdp_votes).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-mono font-bold ${w.passed_25_pct ? 'text-emerald-700' : 'text-slate-700'}">${w.lp_percentage}%</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      } catch (err) {
        body.innerHTML = '<div class="p-8 text-center text-rose-500">Failed to load ward data.</div>';
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

</body>
</html>
