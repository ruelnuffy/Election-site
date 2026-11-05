<?php
/**
 * SIA 2027 Senatorial District Management Dashboard
 * Open Executive Management Command for Adamawa North, Central, and South
 * Zero Login Required - Direct Campaign Leadership Access
 */
require_once __DIR__ . '/config.php';
$districts = get_senatorial_districts();
$selected_district = strtolower(trim($_GET['district'] ?? 'north'));
if (!isset($districts[$selected_district])) {
    $selected_district = 'north';
}
$active_district_info = $districts[$selected_district];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900 scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIA 2027 | Senatorial Districts Management Command &bull; Adamawa State</title>
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
      50% { opacity: .4; }
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
<body class="min-h-full flex flex-col bg-slate-50 text-slate-800 antialiased selection:bg-emerald-600 selection:text-white">

  <!-- =========================================================================
       1. TOP NAVIGATION HEADER
       ========================================================================= -->
  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 sm:h-20 gap-3">
        
        <!-- Left: Branding -->
        <a href="index.php" class="flex items-center gap-3 text-decoration-none group">
          <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 shadow-xs p-1 flex items-center justify-center overflow-hidden transition-transform group-hover:scale-105">
            <img src="./assets/images/logos/lp_logo.png" alt="Labour Party" class="w-full h-full object-contain">
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-slate-900 font-extrabold text-base sm:text-lg tracking-tight group-hover:text-emerald-700 transition-colors">
                SIA 2027
              </span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                Management Command
              </span>
            </div>
            <span class="text-xs text-slate-500 hidden sm:block">
              Adamawa Senatorial Districts Command &bull; Section 179(2)(b) Spread
            </span>
          </div>
        </a>

        <!-- Right: Actions & Links -->
        <div class="flex items-center gap-2 sm:gap-3">
          
          <!-- Live Telemetry Badge -->
          <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
            <span class="w-2 h-2 rounded-full bg-emerald-600 pulse-subtle"></span>
            <span class="hidden sm:inline">LIVE TELEMETRY</span>
            <span class="sm:hidden">LIVE</span>
          </div>

          <!-- Return to Public Situation Room -->
          <a href="index.php" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3.5 py-2 rounded-xl transition-colors">
            <span class="material-symbols-outlined text-sm">home</span>
            <span class="hidden md:inline">Situation Room</span>
          </a>

          <!-- Party Agent Portal -->
          <a href="login.php" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3.5 py-2 rounded-xl transition-colors">
            <span class="material-symbols-outlined text-sm">badge</span>
            <span class="hidden sm:inline">Agent Portal</span>
          </a>

          <!-- Manual Refresh Button -->
          <button onclick="refreshDashboardData()" id="refreshBtn" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-colors border border-slate-200" title="Refresh Live Data">
            <span class="material-symbols-outlined text-lg leading-none" id="refreshIcon">refresh</span>
          </button>

        </div>

      </div>
    </div>
  </header>

  <!-- =========================================================================
       2. HERO HEADER & DISTRICT SWITCHER TABS
       ========================================================================= -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- District Selection Bar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600">hub</span>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight" id="heroDistrictTitle">
              <?= htmlspecialchars($active_district_info['name']) ?>
            </h1>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-1" id="heroDistrictSub">
            Zonal HQ: <strong class="text-slate-800"><?= htmlspecialchars($active_district_info['hq']) ?></strong> &bull; 
            Strategic Focus: <span class="font-semibold text-emerald-700"><?= htmlspecialchars($active_district_info['strategic_role']) ?></span>
          </p>
        </div>

        <!-- Export CSV Button -->
        <div class="flex items-center gap-2">
          <button onclick="exportDistrictCsv()" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm transition-all cursor-pointer">
            <span class="material-symbols-outlined text-sm">download</span>
            <span>Export District CSV</span>
          </button>
        </div>
      </div>

      <!-- District Switcher Pills -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 border-t border-slate-100">
        <?php foreach ($districts as $key => $d): 
          $is_active = ($key === $selected_district);
        ?>
          <button onclick="switchDistrict('<?= $key ?>')" 
                  id="tab-<?= $key ?>"
                  class="district-tab flex items-center justify-between p-3.5 rounded-xl border text-left transition-all <?= $is_active ? 'bg-emerald-50 border-emerald-500 text-emerald-900 shadow-sm ring-1 ring-emerald-500' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white hover:border-slate-300' ?>">
            <div class="min-w-0 pr-2">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-mono font-bold <?= $is_active ? 'text-emerald-700' : 'text-slate-500' ?>"><?= htmlspecialchars($d['code']) ?></span>
                <span class="text-sm font-black truncate"><?= htmlspecialchars($d['short_name']) ?></span>
              </div>
              <span class="text-[11px] text-slate-500 block truncate mt-0.5">HQ: <?= htmlspecialchars($d['hq']) ?> &bull; <?= count($d['lgas']) ?> LGAs</span>
            </div>
            <span class="material-symbols-outlined text-base <?= $is_active ? 'text-emerald-600' : 'text-slate-400' ?>">arrow_forward</span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- =========================================================================
         3. TOP KPI CARDS
         ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="kpiGrid">
      
      <!-- Card 1: LP Priority Candidate Performance -->
      <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
          <span>Labour Party (LP)</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Priority</span>
        </div>
        <div>
          <div class="text-2xl sm:text-3xl font-black text-slate-900" id="kpiLpVotes">--</div>
          <div class="text-xs font-semibold text-emerald-700 flex items-center gap-1 mt-0.5" id="kpiLpPct">
            <span class="material-symbols-outlined text-sm">trending_up</span>
            <span>--% of Valid District Votes</span>
          </div>
        </div>
        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
          <span>Margin of Lead:</span>
          <strong class="text-slate-800 font-mono" id="kpiLpMargin">--</strong>
        </div>
      </div>

      <!-- Card 2: 25% Constitutional Spread Compliance -->
      <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
          <span>25% Spread Compliance</span>
          <span class="material-symbols-outlined text-slate-400 text-base" title="Section 179(2)(b) Constitution">gavel</span>
        </div>
        <div>
          <div class="text-2xl sm:text-3xl font-black text-slate-900" id="kpiSpreadCount">-- / --</div>
          <div class="text-xs font-bold mt-1" id="kpiSpreadBadge">
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">Calculating...</span>
          </div>
        </div>
        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
          <span>Constitutional Rule:</span>
          <span class="text-slate-700 font-medium">Sec. 179(2)(b)</span>
        </div>
      </div>

      <!-- Card 3: Polling Units Collation Progress -->
      <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
          <span>PU Collation Progress</span>
          <span class="material-symbols-outlined text-slate-400 text-base">how_to_vote</span>
        </div>
        <div>
          <div class="text-2xl sm:text-3xl font-black text-slate-900" id="kpiPuCount">-- / --</div>
          <div class="text-xs font-semibold text-slate-600 mt-1 flex items-center gap-1.5" id="kpiPuPct">
            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden flex-1">
              <div id="kpiPuBar" class="bg-emerald-600 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
            <span class="text-xs font-mono font-bold text-slate-700" id="kpiPuPctText">0%</span>
          </div>
        </div>
        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-2 flex items-center justify-between">
          <span>Reg. Voters:</span>
          <strong class="text-slate-800 font-mono" id="kpiRegVoters">--</strong>
        </div>
      </div>

      <!-- Card 4: Ground Telemetry Distribution -->
      <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
          <span>Ground Transmission</span>
          <span class="material-symbols-outlined text-slate-400 text-base">cell_tower</span>
        </div>
        <div class="space-y-1 text-xs">
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-slate-600">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>PU Ground</span>
            </span>
            <strong class="font-mono text-slate-900" id="teleGround">0</strong>
          </div>
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-slate-600">
              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
              <span>10m Transit</span>
            </span>
            <strong class="font-mono text-slate-900" id="tele10m">0</strong>
          </div>
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-slate-600">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              <span>30m Remote</span>
            </span>
            <strong class="font-mono text-slate-900" id="tele30m">0</strong>
          </div>
        </div>
        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-1.5 flex items-center justify-between">
          <span>Audit Confidence:</span>
          <span class="text-emerald-700 font-bold" id="teleAuditConf">High</span>
        </div>
      </div>

    </div>

    <!-- =========================================================================
         4. COMPARATIVE LGA COLLATION LEAGUE TABLE
         ========================================================================= -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-base sm:text-lg font-bold text-slate-900 flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600">table_chart</span>
            <span>Comparative LGA Collation League Table</span>
          </h2>
          <p class="text-xs text-slate-500 mt-0.5">
            Real-time tallies, Section 179(2)(b) constitutional spread quota, and interactive ward drill-downs.
          </p>
        </div>
        <div class="text-xs text-slate-400 font-mono" id="lastUpdatedNotice">
          Updating...
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs sm:text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
              <th class="p-3 sm:p-4">Local Government Area</th>
              <th class="p-3 sm:p-4">Collation Progress</th>
              <th class="p-3 sm:p-4 text-right">LP (SIA)</th>
              <th class="p-3 sm:p-4 text-right">APC</th>
              <th class="p-3 sm:p-4 text-right">PDP</th>
              <th class="p-3 sm:p-4 text-right">LP Share</th>
              <th class="p-3 sm:p-4 text-center">25% Quota</th>
              <th class="p-3 sm:p-4 text-center">Action</th>
            </tr>
          </thead>
          <tbody id="lgaTableBody" class="divide-y divide-slate-100">
            <tr>
              <td colspan="8" class="p-8 text-center text-slate-400 font-medium">
                Loading LGA collation data...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- =========================================================================
         5. ZONAL THREAT RADAR & AGENT ACTIVITY STREAM
         ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Left: Zonal Threat & Incident Radar -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 sm:p-5 flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-rose-600">crisis_alert</span>
            <h3 class="font-bold text-slate-900 text-sm sm:text-base">Zonal Incident & Security Radar</h3>
          </div>
          <span class="text-xs text-slate-500 font-mono" id="incidentCountBadge">0 logged</span>
        </div>

        <div class="flex-1 overflow-y-auto max-h-80 space-y-3 no-scrollbar" id="incidentsList">
          <div class="p-6 text-center text-slate-400 text-xs font-medium">
            No active incidents reported in this district.
          </div>
        </div>
      </div>

      <!-- Right: Field Agent Verification & Transmission Photo Stream -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 sm:p-5 flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600">verified_user</span>
            <h3 class="font-bold text-slate-900 text-sm sm:text-base">Field Agent Photo Telemetry</h3>
          </div>
          <span class="text-xs text-slate-500 font-mono" id="agentCountBadge">0 active</span>
        </div>

        <div class="flex-1 overflow-y-auto max-h-80 space-y-3 no-scrollbar" id="agentsList">
          <div class="p-6 text-center text-slate-400 text-xs font-medium">
            Loading accredited field agents...
          </div>
        </div>
      </div>

    </div>

  </main>

  <!-- =========================================================================
       6. WARD DRILL-DOWN MODAL
       ========================================================================= -->
  <div id="wardModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200">
      
      <!-- Modal Header -->
      <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
        <div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600">account_tree</span>
            <h3 class="text-base sm:text-lg font-black text-slate-900" id="wardModalTitle">Ward Drill-Down</h3>
          </div>
          <p class="text-xs text-slate-500 mt-0.5" id="wardModalSub">Detailed collation across all electoral wards</p>
        </div>
        <button onclick="closeWardModal()" class="p-2 text-slate-400 hover:text-slate-800 rounded-xl hover:bg-slate-200 transition-colors">
          <span class="material-symbols-outlined text-xl">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3" id="wardModalBody">
        <div class="p-8 text-center text-slate-400">Loading wards...</div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
        <span>Source: Ground EC8A Result Sheets</span>
        <button onclick="closeWardModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold rounded-xl transition-colors">
          Close
        </button>
      </div>

    </div>
  </div>

  <!-- =========================================================================
       7. JAVASCRIPT CONTROLLER (Connects to public api.php)
       ========================================================================= -->
  <script>
    let currentDistrict = '<?= $selected_district ?>';
    let autoRefreshTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
      loadDashboardData();
      // Auto-refresh every 30 seconds
      autoRefreshTimer = setInterval(() => {
        loadDashboardData(true);
      }, 30000);
    });

    function switchDistrict(districtKey) {
      currentDistrict = districtKey;
      
      // Update Tab Styles
      document.querySelectorAll('.district-tab').forEach(tab => {
        tab.className = 'district-tab flex items-center justify-between p-3.5 rounded-xl border text-left transition-all bg-slate-50 border-slate-200 text-slate-700 hover:bg-white hover:border-slate-300';
        tab.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-base text-slate-400';
      });
      const activeTab = document.getElementById(`tab-${districtKey}`);
      if (activeTab) {
        activeTab.className = 'district-tab flex items-center justify-between p-3.5 rounded-xl border text-left transition-all bg-emerald-50 border-emerald-500 text-emerald-900 shadow-sm ring-1 ring-emerald-500';
        activeTab.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-base text-emerald-600';
      }

      // Update URL without full reload
      const url = new URL(window.location);
      url.searchParams.set('district', districtKey);
      window.history.pushState({}, '', url);

      loadDashboardData();
    }

    async function refreshDashboardData() {
      const icon = document.getElementById('refreshIcon');
      icon.classList.add('animate-spin');
      await loadDashboardData();
      setTimeout(() => {
        icon.classList.remove('animate-spin');
      }, 600);
    }

    async function loadDashboardData(silent = false) {
      try {
        if (!silent) {
          document.getElementById('lastUpdatedNotice').textContent = 'Fetching latest tallies...';
        }

        // Parallel Fetch
        const [overviewRes, lgaRes, incidentRes, agentRes] = await Promise.all([
          fetch(`api.php?action=senatorial_overview&district=${currentDistrict}`).then(r => r.json()),
          fetch(`api.php?action=senatorial_lga_breakdown&district=${currentDistrict}`).then(r => r.json()),
          fetch(`api.php?action=senatorial_incidents&district=${currentDistrict}`).then(r => r.json()),
          fetch(`api.php?action=senatorial_agents&district=${currentDistrict}`).then(r => r.json())
        ]);

        if (overviewRes.success) {
          renderOverview(overviewRes);
        }

        if (lgaRes.success) {
          renderLgaTable(lgaRes.lgas);
        }

        if (incidentRes.success) {
          renderIncidents(incidentRes.incidents);
        }

        if (agentRes.success) {
          renderAgents(agentRes.agents);
        }

        document.getElementById('lastUpdatedNotice').textContent = `Updated: ${new Date().toLocaleTimeString()}`;

      } catch (err) {
        console.error('Failed to load senatorial data:', err);
        document.getElementById('lastUpdatedNotice').textContent = 'Error refreshing data';
      }
    }

    function renderOverview(data) {
      const kpis = data.kpis;
      const district = data.district;

      document.getElementById('heroDistrictTitle').textContent = district.name;
      document.getElementById('heroDistrictSub').innerHTML = `
        Zonal HQ: <strong class="text-slate-800">${district.hq}</strong> &bull; 
        Strategic Focus: <span class="font-semibold text-emerald-700">${district.strategic_role}</span>
      `;

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
        ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' 
        : 'bg-amber-100 text-amber-800 border border-amber-200';
      const badgeText = isPassed 
        ? '✓ Constitutional Quota Satisfied' 
        : `${kpis.passed_25_total - kpis.passed_25_count} LGA(s) Below 25% Threshold`;
      document.getElementById('kpiSpreadBadge').innerHTML = `
        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold ${badgeClass}">${badgeText}</span>
      `;

      // 3. PU Collation Progress
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
      document.getElementById('teleAuditConf').textContent = groundRatio >= 80 ? 'High Confidence (Direct PU)' : 'Medium (Transit Uploads)';
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
          ? `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
              <span class="material-symbols-outlined text-xs">check_circle</span>
              <span>Passed</span>
             </span>`
          : `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
              <span class="material-symbols-outlined text-xs">warning</span>
              <span>${lga.total_valid > 0 ? 'Deficit' : 'Pending'}</span>
             </span>`;

        return `
          <tr class="hover:bg-slate-50/80 transition-colors">
            <td class="p-3 sm:p-4">
              <div class="font-bold text-slate-900">${escapeHtml(lga.name)}</div>
              <div class="text-[11px] text-slate-500 font-mono">${Number(lga.total_registered).toLocaleString()} registered voters</div>
            </td>
            <td class="p-3 sm:p-4">
              <div class="flex items-center gap-2">
                <div class="w-16 sm:w-24 bg-slate-200 rounded-full h-1.5 overflow-hidden">
                  <div class="bg-emerald-600 h-full rounded-full" style="width: ${lga.reporting_percentage}%"></div>
                </div>
                <span class="text-xs font-mono font-semibold text-slate-700">${lga.reported_pus}/${lga.total_pus} (${lga.reporting_percentage}%)</span>
              </div>
            </td>
            <td class="p-3 sm:p-4 text-right font-black text-emerald-800">
              ${Number(lga.lp_votes).toLocaleString()}
            </td>
            <td class="p-3 sm:p-4 text-right font-mono text-slate-600">
              ${Number(lga.apc_votes).toLocaleString()}
            </td>
            <td class="p-3 sm:p-4 text-right font-mono text-slate-600">
              ${Number(lga.pdp_votes).toLocaleString()}
            </td>
            <td class="p-3 sm:p-4 text-right font-mono font-bold ${passed25 ? 'text-emerald-700' : 'text-slate-700'}">
              ${lga.lp_percentage}%
            </td>
            <td class="p-3 sm:p-4 text-center">
              ${spreadBadge}
            </td>
            <td class="p-3 sm:p-4 text-center">
              <button onclick="openWardModal(${lga.id}, '${escapeHtml(lga.name)}')" 
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 border border-slate-200 transition-all">
                <span class="material-symbols-outlined text-sm">visibility</span>
                <span>Inspect Wards</span>
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function renderIncidents(incidents) {
      const container = document.getElementById('incidentsList');
      document.getElementById('incidentCountBadge').textContent = `${incidents ? incidents.length : 0} logged`;

      if (!incidents || incidents.length === 0) {
        container.innerHTML = `
          <div class="p-8 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-2xl text-emerald-500 mb-1">security</span>
            <p>No active incidents or security threats in this district.</p>
          </div>
        `;
        return;
      }

      container.innerHTML = incidents.map(inc => {
        const isCritical = inc.severity === 'Critical';
        return `
          <div class="p-3.5 rounded-xl border ${isCritical ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-200'} space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-900">${escapeHtml(inc.title || 'Electoral Incident')}</span>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ${isCritical ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-800'}">
                ${escapeHtml(inc.severity)}
              </span>
            </div>
            <p class="text-xs text-slate-600 line-clamp-2">${escapeHtml(inc.description || 'No description provided')}</p>
            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
              <span>${escapeHtml(inc.lga)} &bull; ${escapeHtml(inc.ward || 'Ward')}</span>
              <span>${inc.created_at || ''}</span>
            </div>
          </div>
        `;
      }).join('');
    }

    function renderAgents(agents) {
      const container = document.getElementById('agentsList');
      document.getElementById('agentCountBadge').textContent = `${agents ? agents.length : 0} active`;

      if (!agents || agents.length === 0) {
        container.innerHTML = `
          <div class="p-8 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-2xl text-slate-300 mb-1">person_off</span>
            <p>No agent records available for this district.</p>
          </div>
        `;
        return;
      }

      container.innerHTML = agents.map(ag => {
        const photoSrc = ag.photo 
          ? (ag.photo.startsWith('http') ? ag.photo : ag.photo)
          : null;
        
        return `
          <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
            <div class="w-11 h-11 rounded-xl bg-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center border border-slate-300">
              ${photoSrc 
                ? `<img src="${escapeHtml(photoSrc)}" alt="${escapeHtml(ag.name)}" class="w-full h-full object-cover">`
                : `<span class="material-symbols-outlined text-slate-400 text-xl">person</span>`
              }
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-900 truncate">${escapeHtml(ag.name)}</span>
                <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">${escapeHtml(ag.agent_code || 'SIA-AGENT')}</span>
              </div>
              <div class="text-[11px] text-slate-500 truncate">${escapeHtml(ag.lga)} &bull; ${escapeHtml(ag.ward || 'Ward')}</div>
              <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>${escapeHtml(ag.latest_transmission || 'Accredited on PU ground')}</span>
              </div>
            </div>
          </div>
        `;
      }).join('');
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
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                  <th class="p-2.5">Ward Name</th>
                  <th class="p-2.5 text-center">PUs</th>
                  <th class="p-2.5 text-right">Reg. Voters</th>
                  <th class="p-2.5 text-right font-bold text-emerald-800">LP Votes</th>
                  <th class="p-2.5 text-right">APC</th>
                  <th class="p-2.5 text-right">PDP</th>
                  <th class="p-2.5 text-right">LP %</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${res.wards.map(w => `
                  <tr class="hover:bg-slate-50/80">
                    <td class="p-2.5 font-bold text-slate-800">${escapeHtml(w.name)}</td>
                    <td class="p-2.5 text-center font-mono">${w.reported_pus}/${w.total_pus}</td>
                    <td class="p-2.5 text-right font-mono">${Number(w.total_registered).toLocaleString()}</td>
                    <td class="p-2.5 text-right font-black text-emerald-700">${Number(w.lp_votes).toLocaleString()}</td>
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

    function exportDistrictCsv() {
      window.location.href = `api.php?action=senatorial_lga_breakdown&district=${currentDistrict}&export=csv`;
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

