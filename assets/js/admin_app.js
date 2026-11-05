/**
 * SIA 2027 Admin Command Center - Single Page Architecture Controller
 * Vanilla JS • No Frameworks • High Density Collation Engine
 */

let currentTab = 'overview';
let currentSheetId = null;
let currentSheetsList = [];
let currentSheetIndex = -1;
let imageZoom = 1;
let imageRotation = 0;
let audioContext = null;

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function showToast(msg, type = 'info') {
  const toast = document.getElementById('adminToast');
  const txt = document.getElementById('toastText');
  const icon = document.getElementById('toastIcon');
  if (toast && txt) {
    let cleanMsg = String(msg || '');
    if (cleanMsg.toLowerCase().includes('error') || cleanMsg.toLowerCase().includes('fail') || type === 'error') {
      type = 'error';
    } else if (cleanMsg.toLowerCase().includes('warn') || type === 'warning') {
      type = 'warning';
    } else if (cleanMsg.toLowerCase().includes('success') || cleanMsg.toLowerCase().includes('synchroniz') || cleanMsg.toLowerCase().includes('verified') || cleanMsg.toLowerCase().includes('saved') || type === 'success') {
      type = 'success';
    }

    if (icon) {
      if (type === 'success') {
        icon.textContent = 'check_circle';
        icon.className = 'material-symbols-outlined text-emerald-400 text-lg';
      } else if (type === 'error') {
        icon.textContent = 'cancel';
        icon.className = 'material-symbols-outlined text-red-400 text-lg';
      } else if (type === 'warning') {
        icon.textContent = 'warning';
        icon.className = 'material-symbols-outlined text-amber-400 text-lg';
      } else {
        icon.textContent = 'info';
        icon.className = 'material-symbols-outlined text-sky-400 text-lg';
      }
    }
    txt.textContent = cleanMsg;
    toast.classList.remove('translate-y-32', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');
    setTimeout(() => {
      toast.classList.remove('translate-y-0', 'opacity-100');
      toast.classList.add('translate-y-32', 'opacity-0');
    }, 3200);
  }
}

// Sound chime for critical incidents
function playCriticalAlertSound() {
  try {
    if (!audioContext) {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    const osc = audioContext.createOscillator();
    const gain = audioContext.createGain();
    osc.connect(gain);
    gain.connect(audioContext.destination);
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(880, audioContext.currentTime); // A5
    osc.frequency.exponentialRampToValueAtTime(440, audioContext.currentTime + 0.3);
    gain.gain.setValueAtTime(0.3, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    osc.start();
    osc.stop(audioContext.currentTime + 0.3);
  } catch (e) {
    // Audio autoplay blocked or not supported
  }
}

// Tab Switching
function switchTab(tab) {
  currentTab = tab;

  // Update active sidebar style for White Theme
  document.querySelectorAll('aside nav a, aside nav button').forEach(btn => {
    btn.classList.remove('bg-emerald-50', 'text-emerald-800', 'font-bold', 'border-l-4', 'border-emerald-600', 'shadow-xs', 'bg-emerald-600', 'text-white');
    btn.classList.add('text-slate-600');
  });
  const activeBtn = document.getElementById('nav-' + tab);
  if (activeBtn) {
    activeBtn.classList.add('bg-emerald-50', 'text-emerald-800', 'font-bold', 'border-l-4', 'border-emerald-600', 'shadow-xs');
    activeBtn.classList.remove('text-slate-600');
  }

  // Update Top Title (crisp, uncluttered labels)
  const titles = {
    'overview': 'Overview',
    'senatorial': 'Senatorial Districts Command',
    'review-queue': 'Results Review Queue',
    'discrepancies': 'Discrepancy Audit',
    'agents': 'Field Agents',
    'coverage': 'Coverage Tracker',
    'incidents': 'Field Incidents',
    'announcements': 'Broadcast Directives',
    'reports': 'Reports & Exports',
    'geography': 'Geography & Polling Units',
    'settings': 'System Settings',
    'admin-users': 'Admin Users',
    'backup': 'Database & Backups',
    'audit-log': 'Audit Log'
  };
  const titleEl = document.getElementById('topPageTitle');
  if (titleEl) titleEl.textContent = titles[tab] || 'Command Center';

  loadModuleContent(tab);
}

function refreshCurrentModule(btn) {
  if (btn) btn.classList.add('spinning');
  loadModuleContent(currentTab);
  setTimeout(() => {
    if (btn) btn.classList.remove('spinning');
    showToast('Module synchronized with database', 'success');
  }, 400);
}

// Module Content Loader
async function loadModuleContent(tab) {
  const container = document.getElementById('moduleView');
  container.innerHTML = `
    <div class="text-center py-20 text-slate-500">
      <div class="w-8 h-8 mx-auto border-2 border-emerald-500 border-t-transparent rounded-full animate-spin mb-3"></div>
      <span>Loading ${tab}...</span>
    </div>
  `;

  try {
    switch (tab) {
      case 'overview':
        await renderOverviewModule(container);
        break;
      case 'senatorial':
        await renderSenatorialModule(container);
        break;
      case 'review-queue':
        await renderReviewQueueModule(container);
        break;
      case 'discrepancies':
        await renderDiscrepanciesModule(container);
        break;
      case 'agents':
        await renderAgentsModule(container);
        break;
      case 'coverage':
        await renderCoverageModule(container);
        break;
      case 'incidents':
        await renderIncidentsModule(container);
        break;
      case 'announcements':
        await renderAnnouncementsModule(container);
        break;
      case 'reports':
        await renderReportsModule(container);
        break;
      case 'geography':
        await renderGeographyModule(container);
        break;
      case 'settings':
        await renderSettingsModule(container);
        break;
      case 'admin-users':
        await renderAdminUsersModule(container);
        break;
      case 'backup':
        await renderBackupModule(container);
        break;
      case 'audit-log':
        await renderAuditLogModule(container);
        break;
      default:
        container.innerHTML = '<div class="text-slate-400 p-8 text-center">Module ready</div>';
    }
  } catch (err) {
    container.innerHTML = `
      <div class="p-6 rounded-2xl bg-red-950/40 border border-red-800 text-red-300 text-sm">
        <strong>Error loading module:</strong> ${escapeHtml(err.message)}
      </div>
    `;
  }
}

// =========================================================================
// 1. MODULE: OVERVIEW (Spacious, Executive, Easy-to-Understand)
// =========================================================================
async function renderOverviewModule(container) {
  const res = await fetch('admin_api.php?action=overview');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  const k = data.kpis;

  // Update sidebar badges
  const badgeQueue = document.getElementById('badgePendingQueue');
  if (badgeQueue) badgeQueue.textContent = k.sheets_pending;
  const badgeDisc = document.getElementById('badgeDiscrepancies');
  if (badgeDisc) badgeDisc.textContent = k.sheets_flagged;
  const badgeCrit = document.getElementById('badgeCriticalIncidents');
  if (badgeCrit) {
    badgeCrit.textContent = k.incidents_critical;
    if (k.incidents_critical > 0) {
      badgeCrit.classList.remove('hidden');
      playCriticalAlertSound();
    } else {
      badgeCrit.classList.add('hidden');
    }
  }

  // Calculate percentages and margins
  const totalValid = Math.max(1, k.total_valid_votes);
  const lpPct = Number((k.lp_votes / totalValid * 100).toFixed(1));
  const apcPct = Number((k.apc_votes / totalValid * 100).toFixed(1));
  const pdpPct = Number((k.pdp_votes / totalValid * 100).toFixed(1));
  const otherVotes = Math.max(0, k.total_valid_votes - k.lp_votes - k.apc_votes - k.pdp_votes);
  const otherPct = Math.max(0, Number((otherVotes / totalValid * 100).toFixed(1)));

  const runnerUpVotes = Math.max(k.apc_votes, k.pdp_votes);
  const runnerUpName = k.apc_votes >= k.pdp_votes ? 'APC' : 'PDP';
  const leadMargin = Math.max(0, k.lp_votes - runnerUpVotes);

  container.innerHTML = `
    <div class="space-y-6">

      <!-- Top 4 Clean Metric Cards (Quiet, spacious, easy to scan) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- 1. Total Votes Counted -->
        <div class="top-glass-card p-6 flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Votes Counted</span>
            <span class="material-symbols-outlined text-slate-400 text-xl">how_to_vote</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
            ${k.total_valid_votes.toLocaleString()}
          </div>
          <div class="text-xs text-slate-500 mt-2 font-medium">
            ${k.total_accredited.toLocaleString()} accredited voters
          </div>
        </div>

        <!-- 2. Polling Units Counted -->
        <div class="top-glass-card p-6 flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Polling Units Counted</span>
            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">${k.reporting_pct}%</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
            ${k.reported_polling_units.toLocaleString()} <span class="text-base font-normal text-slate-400">/ ${k.total_polling_units.toLocaleString()}</span>
          </div>
          <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden mt-3">
            <div class="h-full bg-emerald-600 rounded-full transition-all duration-500" style="width: ${Math.min(100, Math.max(2, k.reporting_pct))}%"></div>
          </div>
        </div>

        <!-- 3. Results Verification Queue -->
        <div class="top-glass-card p-6 flex flex-col justify-between cursor-pointer hover:border-slate-300 transition-all group" onclick="switchTab('review-queue')">
          <div class="flex items-center justify-between text-slate-500 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Verification Queue</span>
            <span class="material-symbols-outlined text-slate-400 group-hover:text-slate-700 text-xl transition-colors">fact_check</span>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-extrabold ${k.sheets_pending > 0 ? 'text-amber-600' : 'text-slate-900'} tracking-tight">
              ${k.sheets_pending}
            </span>
            <span class="text-xs font-medium text-slate-500">pending review</span>
          </div>
          <div class="text-xs text-slate-500 mt-2 flex items-center justify-between">
            <span>${k.sheets_verified} verified</span>
            ${k.sheets_flagged > 0 ? `<span class="text-rose-600 font-bold">${k.sheets_flagged} flagged</span>` : `<span class="text-emerald-600 font-medium">0 flagged</span>`}
          </div>
        </div>

        <!-- 4. Field Network -->
        <div class="top-glass-card p-6 flex flex-col justify-between cursor-pointer hover:border-slate-300 transition-all group" onclick="switchTab('agents')">
          <div class="flex items-center justify-between text-slate-500 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Field Agents</span>
            <span class="material-symbols-outlined text-slate-400 group-hover:text-slate-700 text-xl transition-colors">badge</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
            ${k.agents_total} <span class="text-base font-normal text-slate-400">on ground</span>
          </div>
          <div class="text-xs text-slate-500 mt-2 flex items-center justify-between">
            <span class="text-emerald-700 font-semibold">${k.agents_accredited} accredited</span>
            <span>${k.agents_pending} pending</span>
          </div>
        </div>

      </div>

      <!-- Election Results Section (Intuitive, spacious, zero clutter) -->
      <div class="top-glass-card p-6 sm:p-7 space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-5">
          <div>
            <h3 class="text-lg font-bold text-slate-900 tracking-tight m-0">Election Results</h3>
            <p class="text-xs text-slate-500 mt-0.5">Adamawa Gubernatorial Live Returns</p>
          </div>
          <div class="flex flex-wrap items-center gap-3 text-xs">
            <div class="px-3.5 py-1.5 rounded-xl bg-slate-50 border border-slate-200/90 text-slate-600">
              Total Valid: <strong class="text-slate-900 font-bold">${k.total_valid_votes.toLocaleString()}</strong>
            </div>
            <div class="px-3.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold">
              LP Lead: <strong class="font-extrabold">+${leadMargin.toLocaleString()}</strong> vs ${runnerUpName}
            </div>
          </div>
        </div>

        <!-- Visual Proportional Progress Bar across parties -->
        <div class="space-y-2">
          <div class="w-full h-3.5 rounded-full bg-slate-100 overflow-hidden flex shadow-inner">
            <div class="h-full bg-emerald-600 transition-all duration-700" style="width: ${lpPct}%" title="Labour Party: ${lpPct}%"></div>
            <div class="h-full bg-sky-500 transition-all duration-700" style="width: ${apcPct}%" title="APC: ${apcPct}%"></div>
            <div class="h-full bg-rose-500 transition-all duration-700" style="width: ${pdpPct}%" title="PDP: ${pdpPct}%"></div>
            <div class="h-full bg-slate-300 transition-all duration-700" style="width: ${otherPct}%" title="Others: ${otherPct}%"></div>
          </div>
        </div>

        <!-- Party Cards (Spacious, easy to read, clear winner) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
          
          <!-- Labour Party (Leading) -->
          <div class="p-5 rounded-2xl bg-emerald-50/70 border-2 border-emerald-500/80 flex flex-col justify-between space-y-3 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-900">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                Labour Party (LP)
              </span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white">
                Leading
              </span>
            </div>
            <div>
              <div class="text-3xl font-black text-slate-900 tracking-tight">${k.lp_votes.toLocaleString()}</div>
              <div class="text-xs font-extrabold text-emerald-700 mt-1">${lpPct}% of valid votes</div>
            </div>
          </div>

          <!-- APC -->
          <div class="p-5 rounded-2xl bg-slate-50/80 border border-slate-200 flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                APC
              </span>
              <span class="text-xs font-bold text-slate-500">${apcPct}%</span>
            </div>
            <div>
              <div class="text-2xl font-bold text-slate-900 tracking-tight">${k.apc_votes.toLocaleString()}</div>
              <div class="text-xs text-slate-500 mt-1">Second place</div>
            </div>
          </div>

          <!-- PDP -->
          <div class="p-5 rounded-2xl bg-slate-50/80 border border-slate-200 flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                PDP
              </span>
              <span class="text-xs font-bold text-slate-500">${pdpPct}%</span>
            </div>
            <div>
              <div class="text-2xl font-bold text-slate-900 tracking-tight">${k.pdp_votes.toLocaleString()}</div>
              <div class="text-xs text-slate-500 mt-1">Third place</div>
            </div>
          </div>

          <!-- Other Parties (APM, NDC, etc) -->
          <div class="p-5 rounded-2xl bg-slate-50/80 border border-slate-200 flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                Other Parties
              </span>
              <span class="text-xs font-bold text-slate-500">${otherPct}%</span>
            </div>
            <div>
              <div class="text-2xl font-bold text-slate-900 tracking-tight">${(k.apm_votes + k.ndc_votes).toLocaleString()}</div>
              <div class="text-xs text-slate-500 mt-1">NDC (${k.ndc_votes.toLocaleString()}) &bull; APM (${k.apm_votes.toLocaleString()})</div>
            </div>
          </div>

        </div>

      </div>

      <!-- LGA Leaderboard & Operational Status (Balanced 2-column layout) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- LGA Leaderboard (7 cols) -->
        <div class="lg:col-span-7 top-glass-card p-6 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <h4 class="font-bold text-base text-slate-900 m-0">LGA Vote Leaderboard</h4>
              <p class="text-xs text-slate-500 mt-0.5">Top reporting Local Government Areas</p>
            </div>
            <button onclick="switchTab('coverage')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 transition-colors">
              All 21 LGAs &rarr;
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
              <thead class="text-slate-500 border-b border-slate-200 bg-slate-50/60 font-semibold">
                <tr>
                  <th class="py-3 px-3.5">LGA</th>
                  <th class="py-3 px-3 text-right">PUs</th>
                  <th class="py-3 px-3.5 text-right">LP Votes</th>
                  <th class="py-3 px-3.5 text-right">LP Share</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${data.leaderboard.length === 0 ? `
                  <tr><td colspan="4" class="py-8 text-center text-slate-400">No LGA returns submitted yet.</td></tr>
                ` : data.leaderboard.map(lb => `
                  <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-3.5 font-semibold text-slate-800">${escapeHtml(lb.lga)}</td>
                    <td class="py-3 px-3 text-right font-mono text-slate-500">${lb.reported_pus}</td>
                    <td class="py-3 px-3.5 text-right font-mono text-emerald-700 font-bold">${Number(lb.lp_votes).toLocaleString()}</td>
                    <td class="py-3 px-3.5 text-right">
                      <div class="inline-flex items-center justify-end gap-2">
                        <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden hidden sm:block">
                          <div class="h-full bg-emerald-600 rounded-full" style="width: ${Math.min(100, lb.lp_pct)}%"></div>
                        </div>
                        <span class="font-bold text-slate-900">${lb.lp_pct}%</span>
                      </div>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        <!-- Operational Status & Actions (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
          
          <!-- Verification Status -->
          <div class="top-glass-card p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h4 class="font-bold text-base text-slate-900 m-0">Verification Status</h4>
                <p class="text-xs text-slate-500 mt-0.5">EC8A result sheet audit status</p>
              </div>
              <button onclick="switchTab('review-queue')" class="px-3 py-1 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 transition-colors">
                Open Queue
              </button>
            </div>

            <div class="grid grid-cols-3 gap-3 text-center">
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                <span class="block text-2xl font-black text-emerald-700">${k.sheets_verified}</span>
                <span class="text-[11px] font-semibold text-slate-500">Verified</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                <span class="block text-2xl font-black ${k.sheets_pending > 0 ? 'text-amber-600' : 'text-slate-800'}">${k.sheets_pending}</span>
                <span class="text-[11px] font-semibold text-slate-500">Pending</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                <span class="block text-2xl font-black ${k.sheets_flagged > 0 ? 'text-rose-600' : 'text-slate-800'}">${k.sheets_flagged}</span>
                <span class="text-[11px] font-semibold text-slate-500">Flagged</span>
              </div>
            </div>

            <!-- Quick Integrity Indicator -->
            <div class="p-3.5 rounded-xl ${k.sheets_flagged > 0 ? 'bg-amber-50 border border-amber-200 text-amber-900' : 'bg-emerald-50 border border-emerald-200 text-emerald-900'} flex items-center gap-3 text-xs">
              <span class="material-symbols-outlined text-lg ${k.sheets_flagged > 0 ? 'text-amber-600' : 'text-emerald-600'}">
                ${k.sheets_flagged > 0 ? 'warning' : 'verified_user'}
              </span>
              <div class="flex-1">
                ${k.sheets_flagged > 0 ? `<strong>${k.sheets_flagged} sheets flagged</strong> for audit inspection` : `<strong>Mathematical Integrity:</strong> All counted sheets balanced`}
              </div>
            </div>
          </div>

          <!-- Transmission Rate / Activity Card -->
          <div class="top-glass-card p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h4 class="font-bold text-base text-slate-900 m-0">Transmission Activity</h4>
                <p class="text-xs text-slate-500 mt-0.5">Hourly field submissions (24h)</p>
              </div>
              <span class="flex items-center gap-1.5 text-xs text-emerald-700 font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Live
              </span>
            </div>

            ${renderTrendContent(data.trend)}
          </div>

        </div>

      </div>

    </div>
  `;
}

function renderTrendContent(trend) {
  if (!trend || trend.length === 0) {
    return `
      <div class="py-6 px-4 rounded-xl bg-slate-50 border border-slate-200/80 text-center space-y-1">
        <span class="material-symbols-outlined text-slate-400 text-2xl block">sensors</span>
        <span class="text-xs font-semibold text-slate-700 block">Transmission Network Ready</span>
        <span class="text-[11px] text-slate-500 block">No new batch submissions in the last 24 hours</span>
      </div>
    `;
  }
  const max = Math.max(1, ...trend.map(t => parseInt(t.submissions_count)));
  return `
    <div class="h-32 flex items-end justify-between gap-1 pt-4 px-2">
      ${trend.map(t => {
        const count = parseInt(t.submissions_count);
        const heightPct = Math.max(10, Math.round((count / max) * 100));
        const label = t.hour_slot.split(' ')[1] || '';
        return `
          <div class="flex-1 flex flex-col items-center gap-1.5 group relative">
            <div class="w-full bg-emerald-500/80 hover:bg-emerald-600 rounded-t transition-all" style="height: ${heightPct}%"></div>
            <span class="text-[9px] text-slate-400 truncate w-full text-center">${escapeHtml(label)}</span>
            <div class="absolute -top-7 hidden group-hover:block bg-slate-900 border border-slate-800 px-2 py-0.5 rounded text-[10px] font-mono text-white whitespace-nowrap z-20 shadow-md">
              ${count} sheets (${escapeHtml(t.hour_slot)})
            </div>
          </div>
        `;
      }).join('')}
    </div>
  `;
}

// =========================================================================
// 2. MODULE: RESULTS REVIEW QUEUE (Simple, Clean, Human-Friendly)
// =========================================================================
let queuePage = 1;
let queueStatus = 'all';
let queueLga = '';
let queueSearch = '';

async function renderReviewQueueModule(container) {
  const url = `admin_api.php?action=review_queue&page=${queuePage}&status=${queueStatus}&lga=${encodeURIComponent(queueLga)}&search=${encodeURIComponent(queueSearch)}`;
  const res = await fetch(url);
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  currentSheetsList = data.sheets;

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-5">
      
      <!-- Header & Top Action -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900 tracking-tight m-0">Review Queue</h3>
          <p class="text-xs text-slate-500 mt-0.5">Polling unit result sheets awaiting verification or audit</p>
        </div>

        <button type="button" onclick="bulkVerifyBalanced()" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs inline-flex items-center gap-1.5 self-start md:self-auto cursor-pointer">
          <span class="material-symbols-outlined text-sm">bolt</span>
          <span>Verify All Balanced Sheets</span>
        </button>
      </div>

      <!-- Clean Filter Pills & Search -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <button onclick="setQueueFilter('status', 'all')" class="px-3.5 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors ${queueStatus === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">All (${data.total})</button>
          <button onclick="setQueueFilter('status', 'pending')" class="px-3.5 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors ${queueStatus === 'pending' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">Pending</button>
          <button onclick="setQueueFilter('status', 'flagged')" class="px-3.5 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors ${queueStatus === 'flagged' ? 'bg-rose-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">Flagged Issues</button>
          <button onclick="setQueueFilter('status', 'verified')" class="px-3.5 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors ${queueStatus === 'verified' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">Verified</button>
        </div>

        <div class="relative w-full sm:w-72">
          <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-base">search</span>
          <input type="text" placeholder="Search PU, Ward, or LGA..." value="${escapeHtml(queueSearch)}" onkeyup="if(event.key==='Enter') setQueueFilter('search', this.value)" class="w-full bg-white border border-slate-200 rounded-xl pl-9 pr-3.5 py-1.5 text-xs text-slate-900 outline-none focus:border-emerald-600">
        </div>
      </div>

      <!-- Simplified Human-Friendly Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
          <thead class="text-slate-500 border-b border-slate-200 bg-slate-50/60 font-semibold">
            <tr>
              <th class="py-3 px-4">Polling Unit & Location</th>
              <th class="py-3 px-4">Votes & Turnout</th>
              <th class="py-3 px-4">Collation Status</th>
              <th class="py-3 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100" id="queueTableBody">
            ${data.sheets.length === 0 ? `
              <tr><td colspan="4" class="py-12 text-center text-slate-400">No result sheets matching this filter.</td></tr>
            ` : data.sheets.map((s, index) => {
              const totalCast = Number(s.total_valid_votes) + Number(s.rejected_votes);
              const acc = Number(s.accredited_count);
              const isOvervote = (acc > 0 && totalCast > acc);

              let statusBadge = '';
              if (isOvervote) {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Over-voting (+${totalCast - acc})</span>`;
              } else if (s.verification_status === 'verified') {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Verified</span>`;
              } else if (s.verification_status === 'flagged') {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Flagged</span>`;
              } else if (s.verification_status === 'rejected') {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Rejected</span>`;
              } else {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Pending Review</span>`;
              }

              return `
                <tr class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-4 px-4">
                    <span class="font-bold text-slate-900 block text-sm">${escapeHtml(s.polling_unit)}</span>
                    <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                      <span class="text-xs text-slate-500">${escapeHtml(s.ward)}, ${escapeHtml(s.lga)}</span>
                      <span class="inline-flex items-center gap-1 text-[10px] font-semibold ${s.transmission_status && (s.transmission_status.includes('5 km') || s.transmission_status.includes('5km') || s.transmission_status.includes('30 min')) ? 'text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200' : (s.transmission_status && (s.transmission_status.includes('2km') || s.transmission_status.includes('OFFSITE') || s.transmission_status.includes('10 min')) ? 'text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200' : 'text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200')}">
                        <span class="material-symbols-outlined text-[11px] leading-none">cell_tower</span>
                        <span>${escapeHtml(s.transmission_status || 'ON-SITE')}</span>
                      </span>
                    </div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-extrabold text-sm text-emerald-700 font-mono">LP: ${Number(s.lp_votes).toLocaleString()}</div>
                    <div class="text-xs text-slate-500 mt-0.5">${Number(s.total_valid_votes).toLocaleString()} valid &bull; ${acc.toLocaleString()} accredited</div>
                  </td>
                  <td class="py-4 px-4">
                    ${statusBadge}
                  </td>
                  <td class="py-4 px-4 text-right">
                    <button type="button" onclick="openReviewModalByIndex(${index})" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-emerald-600 text-white font-semibold text-xs transition-colors shadow-2xs cursor-pointer">
                      Review &rarr;
                    </button>
                  </td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      ${data.total_pages > 1 ? `
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs text-slate-500">
          <span>Showing page ${data.page} of ${data.total_pages}</span>
          <div class="flex items-center gap-2">
            <button onclick="setQueuePage(${data.page - 1})" ${data.page <= 1 ? 'disabled class="opacity-40 cursor-not-allowed"' : 'class="hover:text-slate-900 font-semibold cursor-pointer"'}>&larr; Prev</button>
            <button onclick="setQueuePage(${data.page + 1})" ${data.page >= data.total_pages ? 'disabled class="opacity-40 cursor-not-allowed"' : 'class="hover:text-slate-900 font-semibold cursor-pointer"'}>Next &rarr;</button>
          </div>
        </div>
      ` : ''}

    </div>
  `;
}

function setQueueFilter(type, val) {
  if (type === 'status') queueStatus = val;
  if (type === 'lga') queueLga = val;
  if (type === 'search') queueSearch = val;
  queuePage = 1;
  loadModuleContent('review-queue');
}

function setQueuePage(p) {
  queuePage = p;
  loadModuleContent('review-queue');
}

async function bulkVerifyBalanced() {
  if (!confirm("Bulk-verify all mathematically balanced result sheets (cast == accredited) that have zero audit flags?")) {
    return;
  }
  const fd = new FormData();
  fd.append('action', 'bulk_verify_balanced');
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    loadModuleContent('review-queue');
  } else {
    showToast(data.message, 'error');
  }
}

// =========================================================================
// REVIEW DETAIL MODAL CONTROLLER (Keyboard shortcuts V/F/R/J/K, Image Zoom)
// =========================================================================
function openReviewModalByIndex(index) {
  if (index < 0 || index >= currentSheetsList.length) return;
  currentSheetIndex = index;
  openReviewModal(currentSheetsList[index].id);
}

async function openReviewModal(sheetId) {
  currentSheetId = sheetId;
  imageZoom = 1;
  imageRotation = 0;

  const modal = document.getElementById('reviewDetailModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');

  // Load detail
  const res = await fetch(`admin_api.php?action=sheet_detail&id=${sheetId}`);
  const data = await res.json();
  if (!data.success) {
    alert(data.message);
    closeReviewModal();
    return;
  }

  const s = data.sheet;

  // Header
  document.getElementById('modalPuTitle').textContent = s.polling_unit;
  document.getElementById('modalPuSub').textContent = `${s.ward} • ${s.lga} • Submitted ${s.created_at}`;

  // Image stage
  const img = document.getElementById('modalEc8aImage');
  const noImgBox = document.getElementById('modalNoImagePlaceholder');
  const fullLink = document.getElementById('modalImageFullLink');
  const shaLabel = document.getElementById('modalImageSha');
  const zoomControls = document.getElementById('modalZoomControls');

  let imgPath = null;
  if (s.ec8a_image && s.ec8a_image.trim() !== '') {
    imgPath = s.ec8a_image.startsWith('uploads/') ? s.ec8a_image : `uploads/${s.ec8a_image}`;
  }

  if (imgPath) {
    if (img) {
      img.src = imgPath;
      img.classList.remove('hidden');
    }
    if (noImgBox) noImgBox.classList.add('hidden');
    if (fullLink) {
      fullLink.href = imgPath;
      fullLink.classList.remove('hidden');
    }
    if (zoomControls) zoomControls.classList.remove('hidden');
    if (shaLabel) shaLabel.textContent = s.image_sha256 ? `SHA256: ${s.image_sha256.substring(0, 16)}...` : 'Image scan attached';
  } else {
    if (img) {
      img.src = '';
      img.classList.add('hidden');
    }
    if (noImgBox) noImgBox.classList.remove('hidden');
    if (fullLink) fullLink.classList.add('hidden');
    if (zoomControls) zoomControls.classList.add('hidden');
    if (shaLabel) shaLabel.textContent = 'Numerical Telemetry • No Image Scan';
  }
  resetImageTransform();

// 17 Official Registered Political Parties
const ADMIN_OFFICIAL_PARTIES = [
  'a', 'aa', 'aac', 'adc', 'adp', 'apc', 'apga', 'app', 'dlp', 'lp', 'ndc', 'nnpp', 'nrm', 'pdp', 'sdp', 'ypp', 'zlp'
];

  // Populate tallies across all 17 parties
  ADMIN_OFFICIAL_PARTIES.forEach(code => {
    const el = document.getElementById('detail_' + code);
    if (el) el.value = s[code + '_votes'] ?? 0;
  });
  document.getElementById('detail_rejected').value = s.rejected_votes ?? 0;
  document.getElementById('detail_accredited').value = s.accredited_count ?? 0;
  document.getElementById('detail_total_valid').textContent = Number(s.total_valid_votes).toLocaleString();

  // Disable inputs initially
  lockNumberInputs(true);

  // Agent and notes
  document.getElementById('detail_agent_name').textContent = s.agent_name ? `${s.agent_name} (${s.agent_code || ''})` : 'Unassigned Agent';
  document.getElementById('detail_agent_phone').textContent = s.agent_phone || '';
  document.getElementById('detail_notes').textContent = s.notes ? `Observation: "${s.notes}"` : 'No presiding officer observations noted.';

  const transStatusEl = document.getElementById('detail_transmission_status');
  if (transStatusEl) {
    const statusVal = s.transmission_status || 'ON-SITE';
    transStatusEl.textContent = statusVal;
    if (statusVal.includes('5 km') || statusVal.includes('5km') || statusVal.includes('30 min')) {
      transStatusEl.className = 'text-[11px] font-bold text-rose-800 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200';
    } else if (statusVal.includes('2km') || statusVal.includes('OFFSITE') || statusVal.includes('10 min')) {
      transStatusEl.className = 'text-[11px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200';
    } else {
      transStatusEl.className = 'text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200';
    }
  }

  // Integrity calculation
  const totalCast = Number(s.total_valid_votes) + Number(s.rejected_votes);
  const acc = Number(s.accredited_count);
  const integBanner = document.getElementById('modalIntegrityBanner');

  if (acc > 0 && totalCast === acc) {
    integBanner.className = 'p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs';
    integBanner.innerHTML = `<span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-sm">check_circle</span><span>MATHEMATICALLY BALANCED</span></span><span>${totalCast} Cast == ${acc} Accredited</span>`;
  } else if (acc > 0 && totalCast > acc) {
    integBanner.className = 'p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center justify-between shadow-2xs';
    integBanner.innerHTML = `<span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-sm">warning</span><span>OVER-VOTING DETECTED</span></span><span>${totalCast} Cast > ${acc} Accredited (+${totalCast - acc})</span>`;
  } else {
    integBanner.className = 'p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold flex items-center justify-between shadow-2xs';
    integBanner.innerHTML = `<span>UNDER-VOTING / UNRECONCILED</span><span>${totalCast} Cast / ${acc} Accredited</span>`;
  }

  // Status banner
  const statBanner = document.getElementById('modalStatusBanner');
  statBanner.innerHTML = `
    <div class="flex items-center justify-between">
      <span>Status: <strong class="text-slate-900 uppercase font-bold">${escapeHtml(s.verification_status)}</strong></span>
      <span class="text-slate-500">${s.reviewer_name ? `Reviewed by ${escapeHtml(s.reviewer_name)}` : 'Unreviewed'}</span>
    </div>
    ${s.review_note ? `<div class="mt-1 text-amber-800 font-medium"><strong>Note:</strong> ${escapeHtml(s.review_note)}</div>` : ''}
  `;

  // Revisions history
  const revBox = document.getElementById('revisionHistoryBox');
  const revList = document.getElementById('revisionsList');
  if (data.revisions && data.revisions.length > 0) {
    revBox.classList.remove('hidden');
    revList.innerHTML = data.revisions.map(r => `
      <div class="p-2 rounded-lg bg-slate-50 text-[10px] text-slate-600 border border-slate-200">
        <span class="text-slate-900 font-bold">${r.created_at}</span> &bull; 
        <span>${escapeHtml(r.reason)}</span>
      </div>
    `).join('');
  } else {
    revBox.classList.add('hidden');
  }
}

function closeReviewModal() {
  document.getElementById('reviewDetailModal').classList.add('hidden');
  document.getElementById('reviewDetailModal').classList.remove('flex');
}

// Image Zoom & Rotate
function zoomImage(factor) {
  imageZoom *= factor;
  applyImageTransform();
}
function rotateImage() {
  imageRotation = (imageRotation + 90) % 360;
  applyImageTransform();
}
function resetImageTransform() {
  imageZoom = 1;
  imageRotation = 0;
  applyImageTransform();
}
function applyImageTransform() {
  const img = document.getElementById('modalEc8aImage');
  if (img) {
    img.style.transform = `scale(${imageZoom}) rotate(${imageRotation}deg)`;
  }
}

// Inline Number Editing
function toggleEditNumbers() {
  const isEditing = !document.getElementById('detail_lp').disabled;
  lockNumberInputs(isEditing);
}

function lockNumberInputs(lock) {
  const fields = ADMIN_OFFICIAL_PARTIES.map(c => 'detail_' + c).concat(['detail_rejected', 'detail_accredited']);
  fields.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.disabled = lock;
  });
  const box = document.getElementById('editReasonBox');
  const btn = document.getElementById('btnToggleEdit');
  if (lock) {
    box.classList.add('hidden');
    btn.innerHTML = '<span class="material-symbols-outlined text-xs">edit</span><span>Edit Numbers</span>';
  } else {
    box.classList.remove('hidden');
    btn.innerHTML = '<span class="material-symbols-outlined text-xs">close</span><span>Cancel Edit</span>';
  }
}

async function submitNumberEdits() {
  const reason = document.getElementById('detail_edit_reason').value.trim();
  if (!reason) {
    alert("Please provide an audit reason for editing the vote counts.");
    return;
  }

  const fd = new FormData();
  fd.append('action', 'edit_sheet_numbers');
  fd.append('id', currentSheetId);
  fd.append('reason', reason);
  ADMIN_OFFICIAL_PARTIES.forEach(code => {
    const el = document.getElementById('detail_' + code);
    if (el) fd.append(code + '_votes', el.value);
  });
  fd.append('rejected_votes', document.getElementById('detail_rejected').value);
  fd.append('total_accredited', document.getElementById('detail_accredited').value);
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
    openReviewModal(currentSheetId); // reload detail
  } else {
    alert('Error: ' + data.message);
  }
}

// Review Actions: Verify, Flag, Reject, Resubmission
async function actionVerifyCurrent() {
  const fd = new FormData();
  fd.append('action', 'verify_sheet');
  fd.append('id', currentSheetId);
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
    closeReviewModal();
    loadModuleContent(currentTab);
  } else {
    alert('Error: ' + data.message);
  }
}

async function actionFlagCurrent() {
  const reason = prompt("Enter specific reason for flagging this sheet for audit:");
  if (!reason) return;

  const fd = new FormData();
  fd.append('action', 'flag_sheet');
  fd.append('id', currentSheetId);
  fd.append('reason', reason);
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
    closeReviewModal();
    loadModuleContent(currentTab);
  } else {
    alert('Error: ' + data.message);
  }
}

async function actionRejectCurrent() {
  const reason = prompt("Enter specific reason for rejecting this EC8A sheet:");
  if (!reason) return;

  const fd = new FormData();
  fd.append('action', 'reject_sheet');
  fd.append('id', currentSheetId);
  fd.append('reason', reason);
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
    closeReviewModal();
    loadModuleContent(currentTab);
  } else {
    alert('Error: ' + data.message);
  }
}

async function actionRequestResubmissionCurrent() {
  const instructions = prompt("Enter instructions for the agent to correct and re-transmit Form EC8A:");
  if (!instructions) return;

  const fd = new FormData();
  fd.append('action', 'request_resubmission');
  fd.append('id', currentSheetId);
  fd.append('instructions', instructions);
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
    closeReviewModal();
    loadModuleContent(currentTab);
  } else {
    alert('Error: ' + data.message);
  }
}

// Global Keyboard Shortcuts (J/K Next/Prev, V Verify, F Flag, R Reject)
document.addEventListener('keydown', (e) => {
  // If user is typing in an input or textarea, don't intercept
  if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;

  const modalOpen = !document.getElementById('reviewDetailModal').classList.contains('hidden');
  if (!modalOpen) return;

  if (e.key === 'v' || e.key === 'V') {
    e.preventDefault();
    actionVerifyCurrent();
  } else if (e.key === 'f' || e.key === 'F') {
    e.preventDefault();
    actionFlagCurrent();
  } else if (e.key === 'r' || e.key === 'R') {
    e.preventDefault();
    actionRejectCurrent();
  } else if (e.key === 'j' || e.key === 'J') {
    e.preventDefault();
    if (currentSheetIndex < currentSheetsList.length - 1) {
      openReviewModalByIndex(currentSheetIndex + 1);
    }
  } else if (e.key === 'k' || e.key === 'K') {
    e.preventDefault();
    if (currentSheetIndex > 0) {
      openReviewModalByIndex(currentSheetIndex - 1);
    }
  } else if (e.key === 'Escape') {
    closeReviewModal();
  }
});

// =========================================================================
// 3. MODULE: DISCREPANCIES AUDIT (Simple, Human-Friendly, Clean)
// =========================================================================
let discrepancyCategory = 'overvoting';

async function renderDiscrepanciesModule(container) {
  const res = await fetch('admin_api.php?action=discrepancies');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  const overCount = data.overvoting.length;
  const sumCount = data.sum_mismatches.length;
  const dupCount = data.duplicate_hashes.length;

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-5">
      
      <!-- Clean Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900 tracking-tight m-0">Collation Audit</h3>
          <p class="text-xs text-slate-500 mt-0.5">Automated mathematical and photo integrity checks across all polling units</p>
        </div>
        <div class="flex items-center gap-2">
          ${overCount === 0 && sumCount === 0 && dupCount === 0 ? `
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              All Audits Clear
            </span>
          ` : `
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
              ${overCount + sumCount + dupCount} Issues Identified
            </span>
          `}
        </div>
      </div>

      <!-- Segmented Category Switcher -->
      <div class="flex flex-wrap items-center gap-2">
        <button onclick="setDiscrepancyCategory('overvoting')" class="px-4 py-2 rounded-full text-xs font-semibold transition-colors cursor-pointer ${discrepancyCategory === 'overvoting' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">
          Over-Voting (${overCount})
        </button>
        <button onclick="setDiscrepancyCategory('sum')" class="px-4 py-2 rounded-full text-xs font-semibold transition-colors cursor-pointer ${discrepancyCategory === 'sum' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">
          Tally Mismatches (${sumCount})
        </button>
        <button onclick="setDiscrepancyCategory('duplicates')" class="px-4 py-2 rounded-full text-xs font-semibold transition-colors cursor-pointer ${discrepancyCategory === 'duplicates' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">
          Duplicate Photos (${dupCount})
        </button>
      </div>

      <!-- Items List -->
      <div id="discrepancyListContainer">
        ${renderDiscrepancyList(data)}
      </div>

    </div>
  `;
}

function setDiscrepancyCategory(cat) {
  discrepancyCategory = cat;
  renderDiscrepanciesModule(document.getElementById('moduleView'));
}

function renderDiscrepancyList(data) {
  if (discrepancyCategory === 'overvoting') {
    if (data.overvoting.length === 0) {
      return `
        <div class="py-12 text-center text-slate-400 space-y-1">
          <span class="material-symbols-outlined text-emerald-600 text-3xl block">check_circle</span>
          <span class="text-sm font-semibold text-slate-800 block">No Over-Voting Detected</span>
          <span class="text-xs text-slate-500 block">All submitted polling units have ballots cast &le; accredited voters.</span>
        </div>
      `;
    }
    return `
      <div class="divide-y divide-slate-100">
        ${data.overvoting.map(s => {
          const cast = Number(s.total_valid_votes) + Number(s.rejected_votes);
          const acc = Number(s.acc);
          const excess = cast - acc;
          return `
            <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/60 transition-colors px-2 rounded-xl">
              <div class="space-y-1">
                <span class="font-bold text-slate-900 text-sm block">${escapeHtml(s.polling_unit)}</span>
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                  <span class="font-medium text-slate-700">${escapeHtml(s.lga || '')}</span>
                  <span>&bull;</span>
                  <span>${cast.toLocaleString()} ballots cast vs ${acc.toLocaleString()} accredited</span>
                </div>
              </div>
              <div class="flex items-center gap-3 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                  +${excess} excess ${excess === 1 ? 'vote' : 'votes'}
                </span>
                <button onclick="openReviewModal(${s.id})" class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-600 text-white font-semibold text-xs transition-colors shadow-2xs cursor-pointer whitespace-nowrap">
                  Inspect Sheet &rarr;
                </button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  if (discrepancyCategory === 'sum') {
    if (data.sum_mismatches.length === 0) {
      return `
        <div class="py-12 text-center text-slate-400 space-y-1">
          <span class="material-symbols-outlined text-emerald-600 text-3xl block">check_circle</span>
          <span class="text-sm font-semibold text-slate-800 block">All Party Tallies Mathematically Balanced</span>
          <span class="text-xs text-slate-500 block">Sum of all party votes perfectly matches recorded valid total across all sheets.</span>
        </div>
      `;
    }
    return `
      <div class="divide-y divide-slate-100">
        ${data.sum_mismatches.map(s => {
          const partySum = ADMIN_OFFICIAL_PARTIES.reduce((acc, c) => acc + (Number(s[c + '_votes']) || 0), 0);
          const diff = Math.abs(Number(s.total_valid_votes) - partySum);
          return `
            <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/60 transition-colors px-2 rounded-xl">
              <div class="space-y-1">
                <span class="font-bold text-slate-900 text-sm block">${escapeHtml(s.polling_unit)}</span>
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                  <span class="font-medium text-slate-700">${escapeHtml(s.lga || '')}</span>
                  <span>&bull;</span>
                  <span>Parties sum to ${partySum.toLocaleString()} &bull; Sheet recorded ${Number(s.total_valid_votes).toLocaleString()}</span>
                </div>
              </div>
              <div class="flex items-center gap-3 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                  ${diff} vote mismatch
                </span>
                <button onclick="openReviewModal(${s.id})" class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-600 text-white font-semibold text-xs transition-colors shadow-2xs cursor-pointer whitespace-nowrap">
                  Inspect Sheet &rarr;
                </button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  if (discrepancyCategory === 'duplicates') {
    if (data.duplicate_hashes.length === 0) {
      return `
        <div class="py-12 text-center text-slate-400 space-y-1">
          <span class="material-symbols-outlined text-emerald-600 text-3xl block">check_circle</span>
          <span class="text-sm font-semibold text-slate-800 block">No Duplicate Sheet Photos Detected</span>
          <span class="text-xs text-slate-500 block">Every result sheet contains a distinct, verified image.</span>
        </div>
      `;
    }
    return `
      <div class="divide-y divide-slate-100">
        ${data.duplicate_hashes.map(s => `
          <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/60 transition-colors px-2 rounded-xl">
            <div class="space-y-1">
              <span class="font-bold text-slate-900 text-sm block">${escapeHtml(s.polling_unit)}</span>
              <div class="text-xs text-slate-500">
                <span>${escapeHtml(s.lga || '')}</span> &bull; <span>Duplicate image detected across multiple units</span>
              </div>
            </div>
            <div class="flex items-center gap-3 self-start sm:self-auto">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                Duplicate Image
              </span>
              <button onclick="openReviewModal(${s.id})" class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-600 text-white font-semibold text-xs transition-colors shadow-2xs cursor-pointer whitespace-nowrap">
                Inspect Sheet &rarr;
              </button>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  return '';
}

// =========================================================================
// 4. MODULE: AGENTS MANAGEMENT (Add, Delete & Real Unit Dropdown)
// =========================================================================
async function renderAgentsModule(container) {
  const res = await fetch('admin_api.php?action=agents&page=1&per_page=50');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-base font-bold text-slate-900 m-0">Field Agents</h3>
          <span class="text-xs text-slate-500">${data.total} registered field agents across Adamawa State</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button type="button" onclick="openAddAgentModal()" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-sm">person_add</span>
            <span>Add Field Agent</span>
          </button>
          <button type="button" onclick="openAgentImportModal()" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 inline-flex items-center gap-1.5 shadow-2xs cursor-pointer">
            <span class="material-symbols-outlined text-xs">download</span>
            <span>Import CSV</span>
          </button>
          <a href="admin_api.php?action=export_csv&type=agents" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-all">
            Export CSV
          </a>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
          <thead class="text-slate-500 border-b border-slate-200 bg-slate-50/70 font-semibold">
            <tr>
              <th class="py-3 px-3.5">Agent Code</th>
              <th class="py-3 px-3.5">Name</th>
              <th class="py-3 px-3.5">Contact</th>
              <th class="py-3 px-3.5">Assigned Polling Unit</th>
              <th class="py-3 px-3.5 text-center">Status</th>
              <th class="py-3 px-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            ${data.agents.length === 0 ? `
              <tr><td colspan="6" class="py-12 text-center text-slate-400">No registered field agents yet. Click "Add Field Agent" to deploy one.</td></tr>
            ` : data.agents.map(a => `
              <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3.5 px-3.5 font-mono font-bold text-slate-600">${escapeHtml(a.agent_code)}</td>
                <td class="py-3.5 px-3.5">
                  <div class="flex items-center gap-2.5">
                    ${a.photo ? `
                      <img src="${escapeHtml(a.photo)}" alt="${escapeHtml(a.name)}" class="w-8 h-8 rounded-full object-cover border border-slate-200 shadow-2xs flex-shrink-0" onerror="this.onerror=null; this.outerHTML='<div class=\\'w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-xs flex-shrink-0\\'>${escapeHtml(a.name.substring(0,2).toUpperCase())}</div>'">
                    ` : `
                      <div class="w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                        ${escapeHtml(a.name.substring(0,2).toUpperCase())}
                      </div>
                    `}
                    <div>
                      <span class="font-bold text-slate-900 block">${escapeHtml(a.name)}</span>
                      <span class="text-[10px] text-slate-400 font-mono sm:hidden">${escapeHtml(a.agent_code)}</span>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-3.5 text-slate-600">
                  <div>${escapeHtml(a.phone || 'No phone')}</div>
                  <div class="text-[10px] text-slate-400">${escapeHtml(a.email)}</div>
                </td>
                <td class="py-3.5 px-3.5">
                  <span class="block text-slate-900 font-bold truncate max-w-xs">${escapeHtml(a.polling_unit)}</span>
                  <span class="text-[11px] text-slate-500">${escapeHtml(a.ward)}, ${escapeHtml(a.lga)}</span>
                </td>
                <td class="py-3.5 px-3.5 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold ${a.status === 'accredited' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (a.status === 'pending' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200')}">
                    ${escapeHtml(a.status)}
                  </span>
                </td>
                <td class="py-3.5 px-3.5 text-right space-x-1.5 whitespace-nowrap">
                  ${a.status !== 'accredited' ? `
                    <button onclick="updateAgentStatus(${a.id}, 'accredited', this)" class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold cursor-pointer shadow-2xs">Approve</button>
                  ` : `
                    <button onclick="updateAgentStatus(${a.id}, 'suspended', this)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-[11px] font-semibold cursor-pointer">Suspend</button>
                  `}
                  <button onclick="openReassignAgentModal(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.polling_unit)}')" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-[11px] font-semibold cursor-pointer">Reassign</button>
                  <button onclick="generateResetLink(${a.id})" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-[11px] font-semibold cursor-pointer">Reset</button>
                  <button onclick="deleteAgent(${a.id}, '${escapeHtml(a.name)}')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[11px] font-bold cursor-pointer" title="Delete Agent">Delete</button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

// -------------------------------------------------------------------------
// ADD AGENT CONTROLLER (Real Units Cascading Dropdown)
// -------------------------------------------------------------------------
async function openAddAgentModal() {
  const modal = document.getElementById('addAgentModal');
  if (!modal) return;

  // Reset form fields
  document.getElementById('addAgentForm').reset();
  document.getElementById('addAgentPassword').value = 'Agent2027!';
  document.getElementById('addAgentStatus').value = 'accredited';

  const lgaSelect = document.getElementById('addAgentLga');
  const wardSelect = document.getElementById('addAgentWard');
  const puSelect = document.getElementById('addAgentPu');

  lgaSelect.innerHTML = '<option value="">Loading LGAs...</option>';
  wardSelect.innerHTML = '<option value="">-- Select LGA first --</option>';
  wardSelect.disabled = true;
  puSelect.innerHTML = '<option value="">-- Select Ward first --</option>';
  puSelect.disabled = true;

  modal.classList.remove('hidden');
  modal.classList.add('flex');

  try {
    const res = await fetch('admin_api.php?action=get_lgas');
    const data = await res.json();
    if (data.success && data.lgas) {
      lgaSelect.innerHTML = '<option value="">-- Choose LGA --</option>' +
        data.lgas.map(l => `<option value="${l.id}">${escapeHtml(l.name)}</option>`).join('');
    } else {
      lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
    }
  } catch (err) {
    lgaSelect.innerHTML = '<option value="">Failed to fetch LGAs</option>';
  }
}

function closeAddAgentModal() {
  const modal = document.getElementById('addAgentModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

async function onAddAgentLgaChange() {
  const lgaId = document.getElementById('addAgentLga').value;
  const wardSelect = document.getElementById('addAgentWard');
  const puSelect = document.getElementById('addAgentPu');

  puSelect.innerHTML = '<option value="">-- Select Ward first --</option>';
  puSelect.disabled = true;

  if (!lgaId) {
    wardSelect.innerHTML = '<option value="">-- Select LGA first --</option>';
    wardSelect.disabled = true;
    return;
  }

  wardSelect.innerHTML = '<option value="">Loading Wards...</option>';
  wardSelect.disabled = true;

  try {
    const res = await fetch(`admin_api.php?action=get_wards&lga_id=${lgaId}`);
    const data = await res.json();
    if (data.success && data.wards) {
      wardSelect.innerHTML = '<option value="">-- Choose Ward --</option>' +
        data.wards.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
      wardSelect.disabled = false;
    } else {
      wardSelect.innerHTML = '<option value="">No wards found</option>';
    }
  } catch (err) {
    wardSelect.innerHTML = '<option value="">Failed to load wards</option>';
  }
}

async function onAddAgentWardChange() {
  const wardId = document.getElementById('addAgentWard').value;
  const puSelect = document.getElementById('addAgentPu');

  if (!wardId) {
    puSelect.innerHTML = '<option value="">-- Select Ward first --</option>';
    puSelect.disabled = true;
    return;
  }

  puSelect.innerHTML = '<option value="">Loading Official Polling Units...</option>';
  puSelect.disabled = true;

  try {
    const res = await fetch(`admin_api.php?action=get_pus&ward_id=${wardId}`);
    const data = await res.json();
    if (data.success && data.polling_units) {
      puSelect.innerHTML = '<option value="">-- Select Official Polling Unit --</option>' +
        data.polling_units.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (${p.code || 'PU'}) - ${p.registered_voters || 0} voters</option>`).join('');
      puSelect.disabled = false;
    } else {
      puSelect.innerHTML = '<option value="">No polling units found</option>';
    }
  } catch (err) {
    puSelect.innerHTML = '<option value="">Failed to load polling units</option>';
  }
}

async function submitAddAgent(e) {
  e.preventDefault();
  const btn = document.getElementById('btnAddAgentSubmit');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span>Creating Agent...</span>';

  const fd = new FormData();
  fd.append('action', 'add_agent');
  fd.append('name', document.getElementById('addAgentName').value.trim());
  fd.append('phone', document.getElementById('addAgentPhone').value.trim());
  fd.append('email', document.getElementById('addAgentEmail').value.trim());
  fd.append('password', document.getElementById('addAgentPassword').value);
  fd.append('status', document.getElementById('addAgentStatus').value);
  fd.append('polling_unit_id', document.getElementById('addAgentPu').value);
  fd.append('csrf_token', getCsrfToken());

  try {
    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeAddAgentModal();
      loadModuleContent('agents');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Network error adding agent: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = origHtml;
  }
}

// -------------------------------------------------------------------------
// REASSIGN AGENT CONTROLLER (Real Units Cascading Dropdown)
// -------------------------------------------------------------------------
async function openReassignAgentModal(agentId, agentName, currentPu) {
  const modal = document.getElementById('reassignAgentModal');
  if (!modal) return;

  document.getElementById('reassignAgentId').value = agentId;
  document.getElementById('reassignAgentName').textContent = agentName;
  document.getElementById('reassignAgentCurrentPu').textContent = currentPu;

  const lgaSelect = document.getElementById('reassignAgentLga');
  const wardSelect = document.getElementById('reassignAgentWard');
  const puSelect = document.getElementById('reassignAgentPu');

  lgaSelect.innerHTML = '<option value="">Loading LGAs...</option>';
  wardSelect.innerHTML = '<option value="">-- Select LGA first --</option>';
  wardSelect.disabled = true;
  puSelect.innerHTML = '<option value="">-- Select Ward first --</option>';
  puSelect.disabled = true;

  modal.classList.remove('hidden');
  modal.classList.add('flex');

  try {
    const res = await fetch('admin_api.php?action=get_lgas');
    const data = await res.json();
    if (data.success && data.lgas) {
      lgaSelect.innerHTML = '<option value="">-- Choose LGA --</option>' +
        data.lgas.map(l => `<option value="${l.id}">${escapeHtml(l.name)}</option>`).join('');
    }
  } catch (err) {
    lgaSelect.innerHTML = '<option value="">Failed to fetch LGAs</option>';
  }
}

function closeReassignAgentModal() {
  const modal = document.getElementById('reassignAgentModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

async function onReassignAgentLgaChange() {
  const lgaId = document.getElementById('reassignAgentLga').value;
  const wardSelect = document.getElementById('reassignAgentWard');
  const puSelect = document.getElementById('reassignAgentPu');

  puSelect.innerHTML = '<option value="">-- Select Ward first --</option>';
  puSelect.disabled = true;

  if (!lgaId) {
    wardSelect.innerHTML = '<option value="">-- Select LGA first --</option>';
    wardSelect.disabled = true;
    return;
  }

  wardSelect.innerHTML = '<option value="">Loading Wards...</option>';
  wardSelect.disabled = true;

  try {
    const res = await fetch(`admin_api.php?action=get_wards&lga_id=${lgaId}`);
    const data = await res.json();
    if (data.success && data.wards) {
      wardSelect.innerHTML = '<option value="">-- Choose Ward --</option>' +
        data.wards.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
      wardSelect.disabled = false;
    }
  } catch (err) {
    wardSelect.innerHTML = '<option value="">Failed to load wards</option>';
  }
}

async function onReassignAgentWardChange() {
  const wardId = document.getElementById('reassignAgentWard').value;
  const puSelect = document.getElementById('reassignAgentPu');

  if (!wardId) {
    puSelect.innerHTML = '<option value="">-- Select Ward first --</option>';
    puSelect.disabled = true;
    return;
  }

  puSelect.innerHTML = '<option value="">Loading Polling Units...</option>';
  puSelect.disabled = true;

  try {
    const res = await fetch(`admin_api.php?action=get_pus&ward_id=${wardId}`);
    const data = await res.json();
    if (data.success && data.polling_units) {
      puSelect.innerHTML = '<option value="">-- Select Official Polling Unit --</option>' +
        data.polling_units.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (${p.code || 'PU'})</option>`).join('');
      puSelect.disabled = false;
    }
  } catch (err) {
    puSelect.innerHTML = '<option value="">Failed to load polling units</option>';
  }
}

async function submitReassignAgent(e) {
  e.preventDefault();
  const btn = document.getElementById('btnReassignSubmit');
  btn.disabled = true;

  const agentId = document.getElementById('reassignAgentId').value;
  const puId = document.getElementById('reassignAgentPu').value;

  const fd = new FormData();
  fd.append('action', 'reassign_agent_pu');
  fd.append('id', agentId);
  fd.append('polling_unit_id', puId);
  fd.append('csrf_token', getCsrfToken());

  try {
    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeReassignAgentModal();
      loadModuleContent('agents');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Error: ' + err.message);
  } finally {
    btn.disabled = false;
  }
}

// -------------------------------------------------------------------------
// DELETE AGENT
// -------------------------------------------------------------------------
async function deleteAgent(agentId, agentName) {
  if (!confirm(`Are you sure you want to permanently delete agent "${agentName}"?\n\nThis cannot be undone.`)) {
    return;
  }

  const fd = new FormData();
  fd.append('action', 'delete_agent');
  fd.append('id', agentId);
  fd.append('csrf_token', getCsrfToken());

  try {
    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      loadModuleContent('agents');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Network error: ' + err.message);
  }
}

// -------------------------------------------------------------------------
// UPDATE AGENT STATUS (Approve / Suspend)
// -------------------------------------------------------------------------
async function updateAgentStatus(agentId, newStatus, btn) {
  const isApprove = (newStatus === 'accredited');
  const actionText = isApprove ? 'approve and accredit' : 'suspend';

  if (!confirm(`Are you sure you want to ${actionText} this field agent?`)) {
    return;
  }

  const originalText = btn ? btn.textContent : '';
  if (btn) {
    btn.disabled = true;
    btn.textContent = isApprove ? 'Approving...' : 'Suspending...';
  }

  const fd = new FormData();
  fd.append('action', 'update_agent_status');
  fd.append('id', agentId);
  fd.append('status', newStatus);
  fd.append('csrf_token', getCsrfToken());

  try {
    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      loadModuleContent('agents');
    } else {
      alert('Error: ' + data.message);
      if (btn) {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    }
  } catch (err) {
    alert('Network error: ' + err.message);
    if (btn) {
      btn.disabled = false;
      btn.textContent = originalText;
    }
  }
}

// -------------------------------------------------------------------------
// GENERATE RESET LINK
// -------------------------------------------------------------------------
async function generateResetLink(agentId) {
  const fd = new FormData();
  fd.append('action', 'generate_reset_link');
  fd.append('id', agentId);
  fd.append('csrf_token', getCsrfToken());

  try {
    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success && data.reset_link) {
      prompt("One-time Password Reset Link (valid 24h) — Copy and share with the agent:", data.reset_link);
      showToast('Reset link generated successfully', 'success');
    } else {
      alert('Error: ' + (data.message || 'Could not generate reset link.'));
    }
  } catch (err) {
    alert('Network error: ' + err.message);
  }
}

function openAgentImportModal() {
  const csv = prompt("Paste CSV text (Columns: name, email, phone, lga, ward, polling_unit):");
  if (!csv) return;

  const dryRun = confirm("Run in DRY-RUN preview mode first? (Click Cancel to import directly to DB)");
  
  const fd = new FormData();
  fd.append('action', 'agents_csv_import');
  fd.append('csv_text', csv);
  if (dryRun) fd.append('dry_run', '1');
  fd.append('csrf_token', getCsrfToken());

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        if (data.dry_run) {
          alert(`Dry Run Complete:\nValid rows: ${data.valid_count}\nErrors: ${data.error_count}\n\n${data.errors.join('\n')}`);
        } else {
          showToast(data.message);
          loadModuleContent('agents');
        }
      } else {
        alert('Error: ' + data.message);
      }
    });
}

// =========================================================================
// 5. MODULE: COVERAGE TRACKER
// =========================================================================
async function renderCoverageModule(container) {
  const res = await fetch('admin_api.php?action=coverage_tracker');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  container.innerHTML = `
    <div class="space-y-6">
      <div class="top-glass-card p-6 space-y-4">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900 m-0">LGA Coverage</h3>
          <span class="text-xs text-slate-500">Real-time reporting progress across all 21 Local Government Areas</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          ${data.lgas.map(l => {
            const pct = l.total_pus > 0 ? Math.round((l.reported_pus / l.total_pus) * 100) : 0;
            return `
              <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-1.5 shadow-2xs">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-slate-900 text-xs">${escapeHtml(l.lga_name)}</span>
                  <span class="text-xs font-mono font-bold text-emerald-700">${pct}%</span>
                </div>
                <div class="w-full h-1.5 bg-slate-200/80 rounded-full overflow-hidden">
                  <div class="h-full bg-emerald-600 rounded-full" style="width: ${pct}%"></div>
                </div>
                <div class="flex items-center justify-between text-[10px] text-slate-500">
                  <span>${l.reported_pus}/${l.total_pus} Reported</span>
                  <span>${l.assigned_agents} Agents</span>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>

      <!-- Unreported PUs List -->
      <div class="top-glass-card p-6 space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">Unreported Polling Units</h4>
            <span class="text-xs text-slate-500">${data.unreported_pus.length} units awaiting returns</span>
          </div>
        </div>
        <div class="overflow-x-auto max-h-96 custom-scrollbar">
          <table class="w-full text-xs text-left">
            <thead class="text-slate-500 border-b border-slate-200 sticky top-0 bg-white font-semibold">
              <tr>
                <th class="py-2.5 px-3">PU Name</th>
                <th class="py-2.5 px-3">Ward / LGA</th>
                <th class="py-2.5 px-3">Assigned Agent</th>
                <th class="py-2.5 px-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              ${data.unreported_pus.map(pu => `
                <tr class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-2.5 px-3 font-semibold text-slate-900">${escapeHtml(pu.pu_name)}</td>
                  <td class="py-2.5 px-3 text-slate-500">${escapeHtml(pu.ward_name)}, ${escapeHtml(pu.lga_name)}</td>
                  <td class="py-2.5 px-3 text-slate-700">
                    ${pu.agent_name ? `${escapeHtml(pu.agent_name)} (${escapeHtml(pu.agent_phone || '')})` : '<span class="text-rose-600 font-medium">Unassigned</span>'}
                  </td>
                  <td class="py-2.5 px-3 text-right">
                    ${pu.agent_id ? `
                      <button onclick="pingAgentPrompt(${pu.agent_id}, '${escapeHtml(pu.pu_name)}')" class="px-2.5 py-1 rounded bg-amber-500 hover:bg-amber-600 text-white font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs cursor-pointer">
                        <span>Ping Agent</span>
                        <span class="material-symbols-outlined text-xs">campaign</span>
                      </button>
                    ` : '<span class="text-slate-400 text-[10px]">Unassigned</span>'}
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `;
}

function pingAgentPrompt(agentId, puName) {
  const msg = prompt(`Send priority announcement to agent at ${puName}:`, "Please transmit verified Form EC8A results sheet immediately.");
  if (!msg) return;

  const fd = new FormData();
  fd.append('action', 'create_announcement');
  fd.append('title', `Urgent Transmission Reminder: ${puName}`);
  fd.append('body', msg);
  fd.append('audience', `agent:${agentId}`);
  fd.append('priority', 'urgent');
  fd.append('csrf_token', getCsrfToken());

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) showToast("Ping dispatched to agent!");
      else alert('Error: ' + data.message);
    });
}

// =========================================================================
// 6. MODULE: INCIDENTS COMMAND
// =========================================================================
async function renderIncidentsModule(container) {
  const res = await fetch('admin_api.php?action=incidents');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-base font-bold text-slate-900 m-0">Field Incidents</h3>
          <span class="text-xs text-slate-500">${data.incidents.length} total logged incident alerts</span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        ${data.incidents.map(i => {
          const sevColors = {
            'critical': 'bg-rose-50 text-rose-800 border-rose-200',
            'urgent': 'bg-amber-50 text-amber-800 border-amber-200',
            'normal': 'bg-slate-100 text-slate-700 border-slate-200'
          }[i.severity] || 'bg-slate-100 text-slate-700 border-slate-200';

          return `
            <div class="p-4 rounded-2xl bg-white border border-slate-200 space-y-2.5 flex flex-col justify-between shadow-2xs">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border ${sevColors}">
                    ${escapeHtml(i.severity)} &bull; ${escapeHtml(i.category)}
                  </span>
                  <span class="text-[10px] text-slate-400">${i.created_at}</span>
                </div>
                <h4 class="font-bold text-sm text-slate-900 m-0">${escapeHtml(i.title)}</h4>
                <p class="text-xs text-slate-600 leading-relaxed m-0">${escapeHtml(i.description)}</p>
                <div class="text-[11px] text-slate-500 pt-1 border-t border-slate-100">
                  <span>${escapeHtml(i.polling_unit)} &bull; ${escapeHtml(i.lga)}</span>
                  ${i.agent_name ? `<div class="text-[10px] text-slate-400">Agent: ${escapeHtml(i.agent_name)} (${escapeHtml(i.agent_phone || '')})</div>` : ''}
                </div>
              </div>

              <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                <span class="text-[11px] font-semibold text-slate-500">Status: <strong class="text-slate-900 font-bold">${escapeHtml(i.status)}</strong></span>
                <button onclick="updateIncidentPrompt(${i.id}, '${i.status}')" class="px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-xs font-semibold text-slate-700 cursor-pointer transition-colors">
                  Update
                </button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    </div>
  `;
}

function updateIncidentPrompt(id, currentStatus) {
  const newStatus = prompt("Update Incident Status (received / in_progress / resolved):", currentStatus === 'resolved' ? 'resolved' : 'resolved');
  if (!newStatus) return;
  const note = prompt("Resolution / status observation notes:", "Resolved by headquarters coordinator.");

  const fd = new FormData();
  fd.append('action', 'update_incident');
  fd.append('id', id);
  fd.append('status', newStatus);
  fd.append('resolution_note', note || '');
  fd.append('csrf_token', getCsrfToken());

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        loadModuleContent('incidents');
      } else {
        alert('Error: ' + data.message);
      }
    });
}

// =========================================================================
// 7. MODULE: ANNOUNCEMENTS & COMMAND BROADCASTS
// =========================================================================
let cachedAnnouncementLgas = null;

async function renderAnnouncementsModule(container) {
  const res = await fetch('admin_api.php?action=announcements');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  const announcements = data.announcements || [];

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-base font-bold text-slate-900 m-0">Command Broadcasts &amp; Directives</h3>
          <span class="text-xs text-slate-500">Dispatch live operational instructions and situation directives to field agents</span>
        </div>
        <button onclick="openCreateAnnouncementModal()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs inline-flex items-center gap-1.5 cursor-pointer transition-all">
          <span class="material-symbols-outlined text-sm">campaign</span>
          <span>+ Compose Broadcast</span>
        </button>
      </div>

      ${announcements.length === 0 ? `
        <div class="p-10 rounded-2xl bg-white border border-dashed border-slate-200 text-center space-y-3">
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center mx-auto">
            <span class="material-symbols-outlined text-2xl">campaign</span>
          </div>
          <div class="space-y-1">
            <h4 class="font-bold text-sm text-slate-800 m-0">No Active Broadcast Directives</h4>
            <p class="text-xs text-slate-500 max-w-md mx-auto m-0 leading-relaxed">
              Dispatched instructions appear instantly on every accredited agent's mobile terminal. Directives can be targeted statewide or isolated to specific Local Government Areas.
            </p>
          </div>
          <button onclick="openCreateAnnouncementModal()" class="mt-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs inline-flex items-center gap-1.5 cursor-pointer transition-all">
            <span class="material-symbols-outlined text-sm">add</span>
            <span>Compose First Broadcast</span>
          </button>
        </div>
      ` : `
        <div class="space-y-3">
          ${announcements.map(a => {
            const priorityBadge = a.priority === 'critical'
              ? 'bg-rose-50 text-rose-700 border-rose-200'
              : (a.priority === 'urgent' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-700 border-slate-200');
            const priorityIcon = a.priority === 'critical' ? 'emergency' : (a.priority === 'urgent' ? 'warning' : 'info');

            let audienceLabel = 'All Field Agents (Statewide)';
            if (a.audience && a.audience.startsWith('lga:')) {
              const lgaId = a.audience.split(':')[1];
              audienceLabel = `Target: LGA #${lgaId}`;
            } else if (a.audience && a.audience.startsWith('agent:')) {
              const agentId = a.audience.split(':')[1];
              audienceLabel = `Target: Agent #${agentId}`;
            }

            return `
              <div class="p-5 rounded-2xl bg-white border border-slate-200/90 space-y-3 shadow-xs hover:border-slate-300 transition-all">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border inline-flex items-center gap-1 ${priorityBadge}">
                      <span class="material-symbols-outlined text-xs">${priorityIcon}</span>
                      <span>${escapeHtml(a.priority)}</span>
                    </span>
                    <h4 class="font-bold text-sm text-slate-900 m-0">${escapeHtml(a.title)}</h4>
                  </div>
                  <span class="text-[11px] text-slate-400 font-mono">${a.created_at}</span>
                </div>
                
                <p class="text-xs text-slate-700 leading-relaxed m-0 whitespace-pre-wrap">${escapeHtml(a.body)}</p>
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-500 pt-2.5 border-t border-slate-100">
                  <div class="flex items-center gap-3 flex-wrap">
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">${escapeHtml(audienceLabel)}</span>
                    <span>Author: <strong>${escapeHtml(a.author_name || 'Admin')}</strong></span>
                    ${a.expires_at ? `<span class="text-slate-400">Expires: ${escapeHtml(a.expires_at)}</span>` : ''}
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="text-emerald-700 font-semibold inline-flex items-center gap-1">
                      <span class="material-symbols-outlined text-xs">done_all</span>
                      <span>Read by ${a.read_count} agent${a.read_count == 1 ? '' : 's'}</span>
                    </span>
                    <button onclick="deleteAnnouncement(${a.id})" class="text-rose-600 hover:text-rose-800 text-[11px] font-semibold hover:underline inline-flex items-center gap-0.5 cursor-pointer">
                      <span class="material-symbols-outlined text-xs">delete</span>
                      <span>Delete</span>
                    </button>
                  </div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      `}
    </div>
  `;
}

async function openCreateAnnouncementModal() {
  const modal = document.getElementById('createAnnouncementModal');
  if (!modal) return;

  const form = document.getElementById('createAnnouncementForm');
  if (form) form.reset();

  const audSelect = document.getElementById('annAudience');
  if (audSelect && !cachedAnnouncementLgas) {
    try {
      const res = await fetch('admin_api.php?action=get_lgas');
      const data = await res.json();
      if (data.success && data.lgas) {
        cachedAnnouncementLgas = data.lgas;
      }
    } catch (e) {
      console.warn('Could not load LGAs for broadcast targeting:', e);
    }
  }

  if (audSelect && cachedAnnouncementLgas) {
    audSelect.innerHTML = `
      <option value="all">All Agents (Statewide)</option>
      <optgroup label="Target Specific LGA">
        ${cachedAnnouncementLgas.map(l => `<option value="lga:${l.id}">${escapeHtml(l.name)} LGA</option>`).join('')}
      </optgroup>
    `;
  }

  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeCreateAnnouncementModal() {
  const modal = document.getElementById('createAnnouncementModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

async function handleCreateAnnouncement(e) {
  e.preventDefault();
  const btn = document.getElementById('btnAnnounceSubmit');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-xs animate-spin">refresh</span><span>Dispatching...</span>';
  }

  try {
    const form = e.target;
    const fd = new FormData(form);
    fd.append('action', 'create_announcement');
    fd.append('csrf_token', getCsrfToken());

    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to dispatch broadcast');

    showToast(data.message || 'Broadcast dispatched successfully!');
    closeCreateAnnouncementModal();
    renderAnnouncementsModule(document.getElementById('moduleStage'));
  } catch (err) {
    alert(err.message);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

async function deleteAnnouncement(id) {
  if (!confirm('Are you sure you want to delete this broadcast directive?')) return;
  try {
    const fd = new FormData();
    fd.append('action', 'delete_announcement');
    fd.append('id', id);
    fd.append('csrf_token', getCsrfToken());

    const res = await fetch('admin_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to delete broadcast');

    showToast(data.message || 'Broadcast deleted.');
    renderAnnouncementsModule(document.getElementById('moduleStage'));
  } catch (err) {
    alert(err.message);
  }
}

// =========================================================================
// 8. MODULE: REPORTS & EXPORTS
// =========================================================================
async function renderReportsModule(container) {
  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-6">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="text-base font-bold text-slate-900 m-0">Reports &amp; Data Exports</h3>
        <p class="text-xs text-slate-500 font-normal m-0 mt-0.5">Export verified collation datasets in UTF-8 CSV or print official summaries.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">receipt_long</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">Polling Unit EC8A Tallies</h4>
            <span class="text-xs text-slate-500">Tally breakdown per polling unit.</span>
          </div>
          <a href="admin_api.php?action=export_csv&type=pu_results" class="inline-block w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow-2xs">
            Download PU CSV &darr;
          </a>
        </div>

        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">bar_chart</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">LGA Collation Summaries</h4>
            <span class="text-xs text-slate-500">Aggregated returns and margins by LGA.</span>
          </div>
          <a href="admin_api.php?action=export_csv&type=lga_results" class="inline-block w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow-2xs">
            Download LGA CSV &darr;
          </a>
        </div>

        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">badge</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">Party Agent Roster</h4>
            <span class="text-xs text-slate-500">Accredited agents, codes, and contact info.</span>
          </div>
          <a href="admin_api.php?action=export_csv&type=agents" class="inline-block w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow-2xs">
            Download Agents CSV &darr;
          </a>
        </div>

        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">emergency</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">Field Incident Ledger</h4>
            <span class="text-xs text-slate-500">Categorized field incidents and resolutions.</span>
          </div>
          <a href="admin_api.php?action=export_csv&type=incidents" class="inline-block w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow-2xs">
            Download Incidents CSV &darr;
          </a>
        </div>

        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">history</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">System Audit Trail</h4>
            <span class="text-xs text-slate-500">Chronological security and collation audit log.</span>
          </div>
          <a href="admin_api.php?action=export_csv&type=audit_log" class="inline-block w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow-2xs">
            Download Audit CSV &darr;
          </a>
        </div>

        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">map</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">Electoral Geography</h4>
            <span class="text-xs text-slate-500">21 LGAs, 226 Wards, and 4,101 Polling Units.</span>
          </div>
          <a href="admin_api.php?action=export_csv&type=geography" class="inline-block w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow-2xs">
            Download Geography CSV &darr;
          </a>
        </div>

        <div class="p-5 rounded-2xl bg-white border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
          <span class="material-symbols-outlined text-3xl text-emerald-700">print</span>
          <div>
            <h4 class="font-bold text-sm text-slate-900 m-0">Printable Collation Sheet</h4>
            <span class="text-xs text-slate-500">Browser-printable summary for command records.</span>
          </div>
          <button onclick="window.print()" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-bold text-xs rounded-xl cursor-pointer">
            Open Print View ↗
          </button>
        </div>

      </div>
    </div>
  `;
}

// =========================================================================
// 9. MODULE: GEOGRAPHY & POLLING UNITS
// =========================================================================
let currentGeoView = 'lgas';
let currentGeoData = null;
let currentGeoLgaFilter = '';
let currentGeoSearch = '';

async function renderGeographyModule(container) {
  currentGeoView = 'lgas';
  currentGeoLgaFilter = '';
  currentGeoSearch = '';

  container.innerHTML = `
    <div class="space-y-6">
      <div class="top-glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900 m-0">Geography &amp; Polling Units</h3>
            <span class="text-xs text-slate-500">21 LGAs &bull; 226 Wards &bull; 4,101 Polling Units</span>
          </div>
          <div class="flex items-center gap-2">
            <a href="admin_api.php?action=export_csv&type=geography" class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-300 inline-flex items-center gap-1.5 transition-all shadow-2xs">
              <span class="material-symbols-outlined text-xs">download</span>
              <span>Export CSV</span>
            </a>
            <button type="button" onclick="openGeographyImportModal()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs inline-flex items-center gap-1.5 transition-all shadow-2xs cursor-pointer">
              <span class="material-symbols-outlined text-xs">upload_file</span>
              <span>Import Dataset</span>
            </button>
          </div>
        </div>

        <!-- 4 KPI Metrics Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-1 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total LGAs</span>
            <div id="geoMetricLgas" class="text-xl font-bold font-mono text-slate-900">...</div>
            <span class="text-[10px] text-slate-400">Local Government Areas</span>
          </div>
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-1 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Electoral Wards</span>
            <div id="geoMetricWards" class="text-xl font-bold font-mono text-emerald-700">...</div>
            <span class="text-[10px] text-slate-400">Registration Areas</span>
          </div>
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-1 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Polling Units</span>
            <div id="geoMetricPus" class="text-xl font-bold font-mono text-emerald-700">...</div>
            <span class="text-[10px] text-slate-400">Voting Points</span>
          </div>
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-1 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Registered Voters</span>
            <div id="geoMetricVoters" class="text-xl font-bold font-mono text-slate-900">...</div>
            <span class="text-[10px] text-slate-400">State Total</span>
          </div>
        </div>

        <!-- Filter & View Controls -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 pt-2">
          <!-- View Switcher Tabs -->
          <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs">
            <button id="btnGeoViewLgas" onclick="switchGeographyView('lgas')" class="px-3.5 py-1.5 rounded-lg font-bold transition-all bg-emerald-600 text-white shadow-2xs cursor-pointer">
              LGA Directory (21 LGAs)
            </button>
            <button id="btnGeoViewPus" onclick="switchGeographyView('polling_units')" class="px-3.5 py-1.5 rounded-lg font-bold transition-all text-slate-600 hover:text-slate-900 cursor-pointer">
              Polling Units
            </button>
          </div>

          <!-- Search & Filter Controls -->
          <div class="flex flex-wrap items-center gap-2">
            <select id="geoLgaSelect" onchange="handleGeoFilterChange()" class="bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-900 outline-none focus:border-emerald-600">
              <option value="">All 21 LGAs</option>
            </select>
            <div class="relative">
              <input type="text" id="geoSearchInput" placeholder="Search PU, code, ward..." onkeyup="handleGeoSearchKey(event)" class="w-48 sm:w-64 bg-white border border-slate-300 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 outline-none focus:border-emerald-600">
              <span class="material-symbols-outlined absolute left-2.5 top-2 text-slate-400 text-xs pointer-events-none">search</span>
            </div>
            <button onclick="handleGeoFilterChange()" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-200 cursor-pointer">Filter</button>
          </div>
        </div>

        <!-- Dynamic Content Stage -->
        <div id="geoContentStage" class="pt-2">
          <div class="p-8 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-2xl animate-spin mb-2 block text-emerald-600">sync</span>
            Loading geography...
          </div>
        </div>
      </div>
    </div>
  `;

  await loadGeographyData('', '');
}

async function loadGeographyData(lgaId = '', search = '') {
  currentGeoLgaFilter = lgaId;
  currentGeoSearch = search;

  const stage = document.getElementById('geoContentStage');
  if (stage) {
    stage.innerHTML = `
      <div class="p-8 text-center text-slate-400 text-xs">
        <span class="material-symbols-outlined text-2xl animate-spin mb-2 block text-emerald-600">sync</span>
        Loading electoral geography...
      </div>
    `;
  }

  try {
    const url = `admin_api.php?action=geography_summary&search=${encodeURIComponent(search)}&lga_id=${encodeURIComponent(lgaId)}`;
    const res = await fetch(url);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load geography.');

    currentGeoData = data;

    // Update KPI Metrics
    const s = data.summary || {};
    const elLgas = document.getElementById('geoMetricLgas');
    const elWards = document.getElementById('geoMetricWards');
    const elPus = document.getElementById('geoMetricPus');
    const elVoters = document.getElementById('geoMetricVoters');

    if (elLgas) elLgas.textContent = (s.total_lgas || 21).toLocaleString();
    if (elWards) elWards.textContent = (s.total_wards || 226).toLocaleString();
    if (elPus) elPus.textContent = (s.total_polling_units || 4101).toLocaleString();
    if (elVoters) elVoters.textContent = (s.total_registered_voters || 0).toLocaleString();

    // Populate LGA selector if not yet populated
    const select = document.getElementById('geoLgaSelect');
    if (select && select.options.length <= 1 && data.lgas) {
      data.lgas.forEach(l => {
        const opt = document.createElement('option');
        opt.value = l.id;
        opt.textContent = `${l.name} (${l.code})`;
        if (l.id == lgaId) opt.selected = true;
        select.appendChild(opt);
      });
    }

    renderCurrentGeoView();
  } catch (err) {
    if (stage) {
      stage.innerHTML = `
        <div class="p-6 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
          <div class="font-bold flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm">error</span>
            <span>Failed to load electoral geography</span>
          </div>
          <div>${escapeHtml(err.message)}</div>
        </div>
      `;
    }
  }
}

function switchGeographyView(viewName) {
  currentGeoView = viewName;

  const btnLgas = document.getElementById('btnGeoViewLgas');
  const btnPus = document.getElementById('btnGeoViewPus');

  if (viewName === 'lgas') {
    if (btnLgas) {
      btnLgas.className = 'px-3.5 py-1.5 rounded-lg font-bold transition-all bg-emerald-600 text-white shadow-2xs cursor-pointer';
    }
    if (btnPus) {
      btnPus.className = 'px-3.5 py-1.5 rounded-lg font-bold transition-all text-slate-600 hover:text-slate-900 cursor-pointer';
    }
  } else {
    if (btnLgas) {
      btnLgas.className = 'px-3.5 py-1.5 rounded-lg font-bold transition-all text-slate-600 hover:text-slate-900 cursor-pointer';
    }
    if (btnPus) {
      btnPus.className = 'px-3.5 py-1.5 rounded-lg font-bold transition-all bg-emerald-600 text-white shadow-2xs cursor-pointer';
    }
  }

  renderCurrentGeoView();
}

function renderCurrentGeoView() {
  const stage = document.getElementById('geoContentStage');
  if (!stage || !currentGeoData) return;

  if (currentGeoView === 'lgas') {
    const lgas = currentGeoData.lgas || [];
    const totalPus = currentGeoData.summary?.total_polling_units || 4101;

    stage.innerHTML = `
      <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-2xs custom-scrollbar">
        <table class="w-full text-xs text-left">
          <thead class="text-slate-500 border-b border-slate-200 bg-slate-50/70 font-semibold">
            <tr>
              <th class="py-3 px-4">Local Government Area (LGA)</th>
              <th class="py-3 px-3">LGA Code</th>
              <th class="py-3 px-3 text-right">Electoral Wards</th>
              <th class="py-3 px-3 text-right">Polling Units</th>
              <th class="py-3 px-3 text-right">Registered Voters</th>
              <th class="py-3 px-3 text-right">State Share</th>
              <th class="py-3 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            ${lgas.map(l => {
              const puPct = totalPus > 0 ? ((l.pu_count / totalPus) * 100).toFixed(1) : 0;
              return `
                <tr class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-3 px-4 font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span>${escapeHtml(l.name)}</span>
                  </td>
                  <td class="py-3 px-3 font-mono text-slate-500">${escapeHtml(l.code)}</td>
                  <td class="py-3 px-3 text-right font-mono text-emerald-700 font-bold">${l.ward_count}</td>
                  <td class="py-3 px-3 text-right font-mono text-slate-900 font-bold">${Number(l.pu_count).toLocaleString()}</td>
                  <td class="py-3 px-3 text-right font-mono text-slate-600">${Number(l.registered_voters).toLocaleString()}</td>
                  <td class="py-3 px-3 text-right font-mono text-slate-500">${puPct}%</td>
                  <td class="py-3 px-4 text-right">
                    <button onclick="drillDownLgaPus(${l.id})" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-[11px] inline-flex items-center gap-1 transition-all border border-slate-200 cursor-pointer">
                      <span>View PUs</span>
                      <span class="material-symbols-outlined text-[12px]">chevron_right</span>
                    </button>
                  </td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  } else {
    const pus = currentGeoData.polling_units || [];
    const search = currentGeoSearch;

    stage.innerHTML = `
      <div class="space-y-3">
        <div class="flex items-center justify-between text-xs text-slate-500 px-1">
          <span>Showing <strong>${pus.length}</strong> polling units ${search ? `matching "<span class="text-slate-900 font-bold">${escapeHtml(search)}</span>"` : '(Filtered)'}</span>
          <span class="text-[11px] text-slate-400 font-mono">Max 100 preview &bull; Use Export CSV for full 4,101 units</span>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-2xs custom-scrollbar max-h-[550px]">
          <table class="w-full text-xs text-left">
            <thead class="text-slate-500 border-b border-slate-200 sticky top-0 bg-slate-50/90 font-semibold z-10">
              <tr>
                <th class="py-3 px-4">INEC Delimitation Code</th>
                <th class="py-3 px-4">Polling Unit Name</th>
                <th class="py-3 px-3">Electoral Ward</th>
                <th class="py-3 px-3">LGA</th>
                <th class="py-3 px-4 text-right">Registered Voters</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              ${pus.length === 0 ? `
                <tr>
                  <td colspan="5" class="py-12 text-center text-slate-400">
                    No polling units found matching your search criteria.
                  </td>
                </tr>
              ` : pus.map(pu => `
                <tr class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-2.5 px-4 font-mono font-bold text-emerald-700">${escapeHtml(pu.code)}</td>
                  <td class="py-2.5 px-4 font-semibold text-slate-900">${escapeHtml(pu.name)}</td>
                  <td class="py-2.5 px-3 text-slate-700">
                    <div>${escapeHtml(pu.ward_name)}</div>
                    <div class="text-[10px] text-slate-400 font-mono">${escapeHtml(pu.ward_code || '')}</div>
                  </td>
                  <td class="py-2.5 px-3 text-slate-500">${escapeHtml(pu.lga_name)}</td>
                  <td class="py-2.5 px-4 text-right font-mono text-slate-700">${Number(pu.registered_voters || 0).toLocaleString()}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }
}

function drillDownLgaPus(lgaId) {
  const select = document.getElementById('geoLgaSelect');
  if (select) select.value = lgaId;
  switchGeographyView('polling_units');
  loadGeographyData(lgaId, currentGeoSearch);
}

function handleGeoFilterChange() {
  const select = document.getElementById('geoLgaSelect');
  const input = document.getElementById('geoSearchInput');
  const lgaId = select ? select.value : '';
  const search = input ? input.value.trim() : '';

  if (search && currentGeoView !== 'polling_units') {
    switchGeographyView('polling_units');
  }

  loadGeographyData(lgaId, search);
}

function handleGeoSearchKey(event) {
  if (event.key === 'Enter') {
    handleGeoFilterChange();
  }
}

function openGeographyImportModal() {
  const modal = document.getElementById('geographyImportModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const resBox = document.getElementById('geographyImportResult');
    if (resBox) resBox.classList.add('hidden');
  }
}

function closeGeographyImportModal() {
  const modal = document.getElementById('geographyImportModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

async function handleReloadMasterSeed(isDryRun = false) {
  const confirmMsg = isDryRun
    ? "Run dry-run inspection on master seed database/seed/polling_units.csv?"
    : "Are you sure you want to reload the official Adamawa master dataset (4,101 polling units)? This will cleanly synchronize the geography catalog.";
  if (!confirm(confirmMsg)) return;

  const resBox = document.getElementById('geographyImportResult');
  if (!resBox) return;

  resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-slate-950 border-slate-800 text-slate-300';
  resBox.classList.remove('hidden');
  resBox.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-xs animate-spin">sync</span> Processing master seed file...</span>';

  try {
    const formData = new FormData();
    formData.append('reload_seed', '1');
    formData.append('csrf_token', getCsrfToken());
    if (isDryRun) formData.append('dry_run', '1');

    const res = await fetch('admin_api.php?action=geography_csv_import', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Import failed.');

    if (data.dry_run) {
      resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-emerald-950/30 border-emerald-500/40 text-emerald-300';
      resBox.innerHTML = `
        <div class="font-bold flex items-center gap-1.5">
          <span class="material-symbols-outlined text-xs">check_circle</span>
          <span>Dry-Run Validation Passed</span>
        </div>
        <div>${escapeHtml(data.message)}</div>
        <div class="text-[11px] text-slate-400">Valid records: ${data.valid_count} &bull; Errors: ${data.error_count}</div>
      `;
    } else {
      resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-emerald-950/40 border-emerald-500/60 text-emerald-200';
      resBox.innerHTML = `
        <div class="font-bold flex items-center gap-1.5">
          <span class="material-symbols-outlined text-xs">verified</span>
          <span>Master Seed Reloaded Successfully</span>
        </div>
        <div>${escapeHtml(data.message)}</div>
        <div class="text-[11px] text-slate-300">Total LGAs: ${data.total_lgas} &bull; Wards: ${data.total_wards} &bull; Polling Units: ${data.total_polling_units}</div>
      `;
      showToast('Master INEC geography seed successfully synchronized!', 'success');
      await loadGeographyData(currentGeoLgaFilter, currentGeoSearch);
    }
  } catch (err) {
    resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-red-950/40 border-red-500/60 text-red-300';
    resBox.innerHTML = `
      <div class="font-bold flex items-center gap-1.5">
        <span class="material-symbols-outlined text-xs">error</span>
        <span>Import Error</span>
      </div>
      <div>${escapeHtml(err.message)}</div>
    `;
  }
}

async function handleGeographyCsvSubmit(event) {
  event.preventDefault();

  const fileInput = document.getElementById('geoCsvFileInput');
  const textInput = document.getElementById('geoCsvTextInput');
  const dryRunBox = document.getElementById('geoDryRunCheckbox');
  const resBox = document.getElementById('geographyImportResult');
  const submitBtn = document.getElementById('btnSubmitGeoImport');
  const submitText = document.getElementById('btnSubmitGeoImportText');

  const file = fileInput?.files?.[0];
  const text = textInput?.value?.trim();
  const isDryRun = dryRunBox?.checked;

  if (!file && !text) {
    alert("Please choose a CSV file or paste CSV content.");
    return;
  }

  resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-slate-950 border-slate-800 text-slate-300';
  resBox.classList.remove('hidden');
  resBox.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-xs animate-spin">sync</span> Uploading and validating geography dataset...</span>';

  if (submitBtn) submitBtn.disabled = true;
  if (submitText) submitText.textContent = 'Processing...';

  try {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    if (file) {
      formData.append('csv_file', file);
    } else if (text) {
      formData.append('csv_text', text);
    }
    if (isDryRun) formData.append('dry_run', '1');

    const res = await fetch('admin_api.php?action=geography_csv_import', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Import failed.');

    if (data.dry_run) {
      resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-emerald-950/30 border-emerald-500/40 text-emerald-300';
      resBox.innerHTML = `
        <div class="font-bold flex items-center gap-1.5">
          <span class="material-symbols-outlined text-xs">check_circle</span>
          <span>Dry-Run Validation Result</span>
        </div>
        <div>${escapeHtml(data.message)}</div>
        <div class="text-[11px] text-slate-400">Valid records: ${data.valid_count} &bull; Errors: ${data.error_count}</div>
        ${data.errors?.length > 0 ? `
          <div class="mt-2 text-red-400 space-y-0.5 text-[10px] max-h-32 overflow-y-auto">
            ${data.errors.map(e => `<div>&bull; ${escapeHtml(e)}</div>`).join('')}
          </div>
        ` : ''}
      `;
    } else {
      resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-emerald-950/40 border-emerald-500/60 text-emerald-200';
      resBox.innerHTML = `
        <div class="font-bold flex items-center gap-1.5">
          <span class="material-symbols-outlined text-xs">verified</span>
          <span>Import Completed Successfully</span>
        </div>
        <div>${escapeHtml(data.message)}</div>
        <div class="text-[11px] text-slate-300">Imported Rows: ${data.rows_imported} &bull; Total LGAs: ${data.total_lgas} &bull; Wards: ${data.total_wards} &bull; PUs: ${data.total_polling_units}</div>
      `;
      showToast('Polling units and geography successfully imported!', 'success');
      await loadGeographyData(currentGeoLgaFilter, currentGeoSearch);
    }
  } catch (err) {
    resBox.className = 'p-3.5 rounded-2xl border text-xs space-y-1.5 bg-red-950/40 border-red-500/60 text-red-300';
    resBox.innerHTML = `
      <div class="font-bold flex items-center gap-1.5">
        <span class="material-symbols-outlined text-sm">error</span>
        <span>Import Failed</span>
      </div>
      <div>${escapeHtml(err.message)}</div>
    `;
  } finally {
    if (submitBtn) submitBtn.disabled = false;
    if (submitText) submitText.textContent = 'Process Import';
  }
}

// =========================================================================
// 10. MODULE: SETTINGS (Superadmin Only)
// =========================================================================
async function renderSettingsModule(container) {
  const res = await fetch('admin_api.php?action=settings');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  const s = data.settings || {};

  container.innerHTML = `
    <div class="top-glass-card p-6 sm:p-8 space-y-6 w-full max-w-4xl">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="text-base font-bold text-slate-900 m-0">System Settings</h3>
        <span class="text-xs text-slate-500 font-normal">Configure election title, disclaimer, and submission controls.</span>
      </div>

      <form id="globalSettingsForm" onsubmit="handleSaveSettings(event)" class="space-y-4 text-xs">
        <div>
          <label class="block font-bold text-slate-700 uppercase mb-1">Election Title</label>
          <input type="text" name="election_name" value="${escapeHtml(s.election_name || 'Adamawa State Governorship Election 2027')}" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-900 outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
        </div>

        <div>
          <label class="block font-bold text-slate-700 uppercase mb-1">Public Disclaimer Banner Text</label>
          <input type="text" name="public_label_text" value="${escapeHtml(s.public_label_text || 'Party agent tallies, unofficial. Not INEC results.')}" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-900 outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex items-center justify-between shadow-2xs">
            <div>
              <span class="font-bold text-slate-900 block">Agent Submissions</span>
              <span class="text-[10px] text-slate-500">Allow field agent EC8A uploads</span>
            </div>
            <select name="submissions_open" class="bg-white border border-slate-300 rounded-lg px-2.5 py-1 text-slate-900 font-bold outline-none focus:border-emerald-600">
              <option value="1" ${s.submissions_open == '1' ? 'selected' : ''}>Active (Open)</option>
              <option value="0" ${s.submissions_open == '0' ? 'selected' : ''}>Frozen (Locked)</option>
            </select>
          </div>

          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex items-center justify-between shadow-2xs">
            <div>
              <span class="font-bold text-slate-900 block">Public Verification Filter</span>
              <span class="text-[10px] text-slate-500">Only verified sheets appear publicly</span>
            </div>
            <select name="require_review_before_public" class="bg-white border border-slate-300 rounded-lg px-2.5 py-1 text-slate-900 font-bold outline-none focus:border-emerald-600">
              <option value="1" ${s.require_review_before_public == '1' ? 'selected' : ''}>Require Review</option>
              <option value="0" ${s.require_review_before_public == '0' ? 'selected' : ''}>Publish All Non-Rejected</option>
            </select>
          </div>

        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
          <button type="submit" class="px-6 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-xs cursor-pointer">
            Save Settings &rarr;
          </button>
        </div>
      </form>
    </div>
  `;
}

async function handleSaveSettings(e) {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  fd.append('action', 'settings');
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
  } else {
    alert('Error: ' + data.message);
  }
}

// =========================================================================
// 11. MODULE: ADMIN USERS (Superadmin Only)
// =========================================================================
async function renderAdminUsersModule(container) {
  const res = await fetch('admin_api.php?action=admin_users');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-base font-bold text-slate-900 m-0">Admin Users</h3>
          <span class="text-xs text-slate-500">Superadmins, LGA Coordinators, and Analysts</span>
        </div>
        <button onclick="openCreateAdminModal()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs cursor-pointer">
          + Add Admin User
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
          <thead class="text-slate-500 border-b border-slate-200 bg-slate-50/70 font-semibold">
            <tr>
              <th class="py-2.5 px-3">Name</th>
              <th class="py-2.5 px-3">Email</th>
              <th class="py-2.5 px-3">Role</th>
              <th class="py-2.5 px-3">LGA Scope</th>
              <th class="py-2.5 px-3 text-center">Status</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            ${data.admins.map(adm => `
              <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-3 font-bold text-slate-900">${escapeHtml(adm.name)}</td>
                <td class="py-3 px-3 text-slate-500">${escapeHtml(adm.email)}</td>
                <td class="py-3 px-3 font-bold uppercase text-[11px] text-emerald-700">${escapeHtml(adm.role)}</td>
                <td class="py-3 px-3 text-slate-700">${escapeHtml(adm.lga_name || 'All LGAs (State)')}</td>
                <td class="py-3 px-3 text-center">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${adm.status === 'active' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}">
                    ${escapeHtml(adm.status)}
                  </span>
                </td>
                <td class="py-3 px-3 text-right">
                  <button onclick="toggleAdminStatus(${adm.id}, '${adm.status}', '${adm.role}')" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-[11px] font-semibold cursor-pointer">
                    ${adm.status === 'active' ? 'Disable' : 'Enable'}
                  </button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function openCreateAdminModal() {
  const name = prompt("Administrator Full Name:");
  if (!name) return;
  const email = prompt("Official Admin Email:");
  if (!email) return;
  const role = prompt("Role (superadmin / coordinator / analyst):", "coordinator");
  const pwd = prompt("Initial Password (min 8 characters):", "Command2027!");

  const fd = new FormData();
  fd.append('action', 'create_admin_user');
  fd.append('name', name);
  fd.append('email', email);
  fd.append('role', role || 'coordinator');
  fd.append('password', pwd);
  fd.append('csrf_token', getCsrfToken());

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        loadModuleContent('admin-users');
      } else {
        alert('Error: ' + data.message);
      }
    });
}

function toggleAdminStatus(id, currentStatus, currentRole) {
  const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
  const fd = new FormData();
  fd.append('action', 'update_admin_user');
  fd.append('id', id);
  fd.append('status', newStatus);
  fd.append('role', currentRole);
  fd.append('csrf_token', getCsrfToken());

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        loadModuleContent('admin-users');
      } else {
        alert('Error: ' + data.message);
      }
    });
}

// =========================================================================
// 12. MODULE: SYSTEM BACKUP & ARCHIVES
// =========================================================================
async function renderBackupModule(container) {
  container.innerHTML = `
    <div class="top-glass-card p-6 sm:p-8 space-y-6 w-full max-w-4xl">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="text-base font-bold text-slate-900 m-0">Database &amp; Backups</h3>
        <p class="text-xs text-slate-500 font-normal m-0 mt-0.5">Generate hot transactional snapshots of the SQLite database and uploads archive.</p>
      </div>

      <div class="space-y-4">
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex items-center justify-between shadow-2xs">
          <div>
            <span class="font-bold text-slate-900 text-sm block">SQLite Database Snapshot</span>
            <span class="text-xs text-slate-500">Atomic point-in-time copy of sia2027.sqlite</span>
          </div>
          <a href="admin_api.php?action=backup_database" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all shadow-2xs">
            Download .sqlite &darr;
          </a>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex items-center justify-between shadow-2xs">
          <div>
            <span class="font-bold text-slate-900 text-sm block">EC8A Uploads Archive (.zip)</span>
            <span class="text-xs text-slate-500">Compressed ZIP archive of all scanned Form EC8A images</span>
          </div>
          <a href="admin_api.php?action=backup_uploads" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all shadow-2xs">
            Download .zip &darr;
          </a>
        </div>
      </div>
    </div>
  `;
}

// =========================================================================
// 13. MODULE: IMMUTABLE AUDIT LOG VIEWER
// =========================================================================
async function renderAuditLogModule(container) {
  const res = await fetch('admin_api.php?action=audit_log&page=1&per_page=30');
  const data = await res.json();
  if (!data.success) throw new Error(data.message);

  container.innerHTML = `
    <div class="top-glass-card p-6 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-base font-bold text-slate-900 m-0">Audit Log</h3>
          <span class="text-xs text-slate-500">${data.total} logged security &amp; collation events</span>
        </div>
      </div>

      <div class="overflow-x-auto max-h-[65vh] custom-scrollbar rounded-2xl border border-slate-200 bg-white shadow-2xs">
        <table class="w-full text-xs text-left">
          <thead class="text-slate-500 border-b border-slate-200 sticky top-0 bg-slate-50/90 font-semibold z-10">
            <tr>
              <th class="py-2.5 px-3">Time</th>
              <th class="py-2.5 px-3">Actor</th>
              <th class="py-2.5 px-3">Action</th>
              <th class="py-2.5 px-3">Entity</th>
              <th class="py-2.5 px-3">IP</th>
              <th class="py-2.5 px-3">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
            ${data.logs.map(l => `
              <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-2.5 px-3 text-slate-400 whitespace-nowrap">${l.created_at}</td>
                <td class="py-2.5 px-3 font-semibold text-slate-700">${escapeHtml(l.actor_type)} #${l.actor_id || '-'}</td>
                <td class="py-2.5 px-3 font-bold text-emerald-700">${escapeHtml(l.action)}</td>
                <td class="py-2.5 px-3 text-slate-600">${escapeHtml(l.entity || '')} #${l.entity_id || '-'}</td>
                <td class="py-2.5 px-3 text-slate-400">${escapeHtml(l.ip || '')}</td>
                <td class="py-2.5 px-3 text-slate-600 truncate max-w-xs font-sans text-xs">${escapeHtml(l.meta_json || '')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

// =========================================================================
// SENATORIAL DISTRICTS COMMAND MODULE
// =========================================================================
let currentSenatorialDistrict = 'north';

async function renderSenatorialModule(container, district = currentSenatorialDistrict) {
  currentSenatorialDistrict = district;

  const [resOverview, resLgas, resIncidents, resAgents] = await Promise.all([
    fetch(`admin_api.php?action=senatorial_overview&district=${district}`).then(r => r.json()),
    fetch(`admin_api.php?action=senatorial_lga_breakdown&district=${district}`).then(r => r.json()),
    fetch(`admin_api.php?action=senatorial_incidents&district=${district}`).then(r => r.json()),
    fetch(`admin_api.php?action=senatorial_agents&district=${district}`).then(r => r.json())
  ]);

  if (!resOverview.success) throw new Error(resOverview.message || 'Failed to load district overview');

  const d = resOverview.district;
  const k = resOverview.kpis;
  const lgas = resLgas.lgas || [];
  const incidents = resIncidents.incidents || [];
  const agents = resAgents.agents || [];
  const trans = resOverview.transmission_health || { ground: 0, transit_10m: 0, transit_30m: 0 };
  const totalTrans = Math.max(1, trans.ground + trans.transit_10m + trans.transit_30m);

  const groundPct = Math.round((trans.ground / totalTrans) * 100);
  const transit10Pct = Math.round((trans.transit_10m / totalTrans) * 100);
  const transit30Pct = Math.round((trans.transit_30m / totalTrans) * 100);

  container.innerHTML = `
    <div class="space-y-6">

      <!-- 1. District Switcher Header -->
      <div class="top-glass-card p-4 sm:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600 text-2xl">hub</span>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">${escapeHtml(d.name)}</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${escapeHtml(d.bg_class)} uppercase">
              ${escapeHtml(d.code)}
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Zonal HQ: <strong class="text-slate-700">${escapeHtml(d.hq)}</strong> &bull; Role: <span class="text-slate-600">${escapeHtml(d.strategic_role)}</span>
          </p>
        </div>

        <!-- 3 Senatorial Pills -->
        <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-2xl border border-slate-200">
          <button onclick="renderSenatorialModule(document.getElementById('moduleView'), 'north')" 
            class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${district === 'north' ? 'bg-white text-emerald-700 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'}">
            North (5 LGAs)
          </button>
          <button onclick="renderSenatorialModule(document.getElementById('moduleView'), 'central')" 
            class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${district === 'central' ? 'bg-white text-blue-700 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'}">
            Central (7 LGAs)
          </button>
          <button onclick="renderSenatorialModule(document.getElementById('moduleView'), 'south')" 
            class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${district === 'south' ? 'bg-white text-amber-700 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'}">
            South (9 LGAs)
          </button>
        </div>
      </div>

      <!-- 2. Four Golden Executive KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Turnout & PUs -->
        <div class="top-glass-card p-5 flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider">Turnout & PUs</span>
            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
              ${k.reporting_percentage}% Reported
            </span>
          </div>
          <div class="text-2xl font-extrabold text-slate-900">
            ${k.reported_pus.toLocaleString()} <span class="text-sm font-normal text-slate-400">/ ${k.total_pus.toLocaleString()} PUs</span>
          </div>
          <div class="text-xs text-slate-500 mt-2">
            <strong>${k.total_accredited.toLocaleString()}</strong> voters accredited (${k.total_registered.toLocaleString()} on PVC roll)
          </div>
          <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden mt-3">
            <div class="h-full bg-emerald-600 rounded-full" style="width: ${Math.min(100, Math.max(3, k.reporting_percentage))}%"></div>
          </div>
        </div>

        <!-- Senator Ishaku Abbo (LP) Votes -->
        <div class="top-glass-card p-5 flex flex-col justify-between border-emerald-200 bg-gradient-to-br from-white to-emerald-50/30">
          <div class="flex items-center justify-between text-slate-500 mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-800">LP &bull; Senator Ishaku Abbo</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-600 text-white">PRIORITY</span>
          </div>
          <div class="text-2xl font-black text-emerald-700">
            ${k.lp_votes.toLocaleString()} <span class="text-sm font-bold text-emerald-600">(${k.lp_percentage}%)</span>
          </div>
          <div class="text-xs text-slate-600 mt-2 flex items-center justify-between">
            <span>Net Lead Margin:</span>
            <strong class="${k.margin_lead >= 0 ? 'text-emerald-700' : 'text-rose-600'} font-bold">
              ${k.margin_lead >= 0 ? '+' : ''}${k.margin_lead.toLocaleString()} votes
            </strong>
          </div>
        </div>

        <!-- 25% Constitutional Spread Compliance Meter -->
        <div class="top-glass-card p-5 flex flex-col justify-between ${k.passed_25_all ? 'border-emerald-200' : 'border-amber-200'}">
          <div class="flex items-center justify-between text-slate-500 mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider">25% Constitutional Spread</span>
            <span class="material-symbols-outlined ${k.passed_25_all ? 'text-emerald-600' : 'text-amber-600'} text-lg">gavel</span>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl font-extrabold ${k.passed_25_all ? 'text-emerald-700' : 'text-amber-700'}">
              ${k.passed_25_count} <span class="text-sm font-normal text-slate-400">/ ${k.passed_25_total} LGAs</span>
            </span>
          </div>
          <div class="text-xs text-slate-500 mt-2 font-medium">
            ${k.passed_25_all ? '✓ 100% of district LGAs secured >25%' : `Need ${k.passed_25_total - k.passed_25_count} more LGA(s) to pass 25% threshold`}
          </div>
          <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden mt-3">
            <div class="h-full ${k.passed_25_all ? 'bg-emerald-600' : 'bg-amber-500'} rounded-full" 
              style="width: ${Math.round((k.passed_25_count / k.passed_25_total) * 100)}%"></div>
          </div>
        </div>

        <!-- Ground Connectivity & Incidents -->
        <div class="top-glass-card p-5 flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider">Ground Signals & Threats</span>
            <span class="material-symbols-outlined text-slate-400 text-lg">cell_tower</span>
          </div>
          <div class="flex items-center justify-between">
            <div>
              <span class="text-xs text-slate-500 block">Threat Incidents</span>
              <span class="text-lg font-black ${k.critical_incidents > 0 ? 'text-rose-600' : 'text-slate-800'}">
                ${k.total_incidents} (${k.critical_incidents} critical)
              </span>
            </div>
            <div class="text-right">
              <span class="text-xs text-slate-500 block">Agent Photos</span>
              <span class="text-lg font-black text-emerald-700">${k.agents_with_photo} / ${k.total_agents}</span>
            </div>
          </div>
          <div class="text-[11px] text-slate-500 mt-2">
            Instant PU Ground Reports: <strong>${groundPct}%</strong>
          </div>
        </div>

      </div>

      <!-- 3. LGA Collation League Table & Performance Matrix -->
      <div class="top-glass-card overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <div>
            <h3 class="text-base font-bold text-slate-900 tracking-tight">LGA Collation Matrix &bull; ${escapeHtml(d.short_name)}</h3>
            <p class="text-xs text-slate-500">Live tally verification, margin comparison, and 25% statutory compliance across all ${lgas.length} LGAs</p>
          </div>
          <div class="flex items-center gap-2">
            <button onclick="exportZonalCsv('${district}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-all cursor-pointer">
              <span class="material-symbols-outlined text-sm">download</span>
              <span>Export Zonal Pack</span>
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                <th class="py-3 px-4">Local Government Area</th>
                <th class="py-3 px-3">Reporting PUs</th>
                <th class="py-3 px-3">Total Valid</th>
                <th class="py-3 px-3 text-emerald-800 font-extrabold bg-emerald-50/50">LP (SIA)</th>
                <th class="py-3 px-3">APC</th>
                <th class="py-3 px-3">PDP</th>
                <th class="py-3 px-3">Lead Margin</th>
                <th class="py-3 px-3 text-center">25% Spread</th>
                <th class="py-3 px-3 text-center">Over-Voting</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              ${lgas.map(l => {
                const passed25Badge = l.passed_25_pct 
                  ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">PASS (${l.lp_percentage}%)</span>`
                  : `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">${l.lp_percentage}%</span>`;

                const overvoteBadge = l.overvoting_count > 0
                  ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200 animate-pulse">${l.overvoting_count} FLAGGED</span>`
                  : `<span class="text-slate-400 font-medium">0</span>`;

                return `
                  <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3.5 px-4">
                      <div class="font-bold text-slate-900">${escapeHtml(l.name)}</div>
                      <div class="text-[10px] text-slate-400 font-mono">${escapeHtml(l.code)} &bull; Coord: ${escapeHtml(l.coordinator_name)}</div>
                    </td>
                    <td class="py-3.5 px-3">
                      <div class="font-bold text-slate-800">${l.reported_pus} / ${l.total_pus}</div>
                      <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden mt-1">
                        <div class="h-full bg-emerald-600 rounded-full" style="width: ${Math.min(100, Math.max(3, l.reporting_percentage))}%"></div>
                      </div>
                    </td>
                    <td class="py-3.5 px-3 font-semibold text-slate-800 font-mono">
                      ${l.total_valid.toLocaleString()}
                    </td>
                    <td class="py-3.5 px-3 font-mono font-bold text-emerald-700 bg-emerald-50/30">
                      ${l.lp_votes.toLocaleString()} <span class="text-[10px] font-normal text-emerald-600">(${l.lp_percentage}%)</span>
                    </td>
                    <td class="py-3.5 px-3 font-mono text-slate-600">
                      ${l.apc_votes.toLocaleString()}
                    </td>
                    <td class="py-3.5 px-3 font-mono text-slate-600">
                      ${l.pdp_votes.toLocaleString()}
                    </td>
                    <td class="py-3.5 px-3 font-mono font-bold ${l.margin_lead >= 0 ? 'text-emerald-700' : 'text-rose-600'}">
                      ${l.margin_lead >= 0 ? '+' : ''}${l.margin_lead.toLocaleString()}
                    </td>
                    <td class="py-3.5 px-3 text-center">
                      ${passed25Badge}
                    </td>
                    <td class="py-3.5 px-3 text-center">
                      ${overvoteBadge}
                    </td>
                    <td class="py-3.5 px-4 text-right">
                      <button onclick="openWardDrillDownModal(${l.id}, '${escapeHtml(l.name)}')" 
                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-all cursor-pointer">
                        Wards Drill-Down
                      </button>
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. Two Columns: Transmission Telemetry & Zonal Incidents -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Ground Transmission Telemetry -->
        <div class="top-glass-card p-5">
          <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div>
              <h3 class="text-sm font-bold text-slate-900 tracking-tight">Ground Transmission Telemetry</h3>
              <p class="text-xs text-slate-500">Physical signal accessibility reported by PU agents in ${escapeHtml(d.short_name)}</p>
            </div>
            <span class="material-symbols-outlined text-slate-400">satellite_alt</span>
          </div>

          <div class="space-y-4">
            <!-- 1. PU Ground -->
            <div class="p-3.5 rounded-xl bg-emerald-50/50 border border-emerald-100">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                  Reporting directly on PU ground
                </span>
                <span class="text-xs font-black text-emerald-800">${trans.ground} sheets (${groundPct}%)</span>
              </div>
              <div class="w-full h-1.5 rounded-full bg-emerald-200/50 overflow-hidden">
                <div class="h-full bg-emerald-600 rounded-full" style="width: ${groundPct}%"></div>
              </div>
            </div>

            <!-- 2. 10 mins transit -->
            <div class="p-3.5 rounded-xl bg-blue-50/50 border border-blue-100">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-bold text-blue-900 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                  Took 10 mins to reach network
                </span>
                <span class="text-xs font-black text-blue-800">${trans.transit_10m} sheets (${transit10Pct}%)</span>
              </div>
              <div class="w-full h-1.5 rounded-full bg-blue-200/50 overflow-hidden">
                <div class="h-full bg-blue-600 rounded-full" style="width: ${transit10Pct}%"></div>
              </div>
            </div>

            <!-- 3. 30 mins remote transit -->
            <div class="p-3.5 rounded-xl bg-amber-50/50 border border-amber-100">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                  Traveled 30+ mins to reach internet (Remote Blindspots)
                </span>
                <span class="text-xs font-black text-amber-800">${trans.transit_30m} sheets (${transit30Pct}%)</span>
              </div>
              <div class="w-full h-1.5 rounded-full bg-amber-200/50 overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full" style="width: ${transit30Pct}%"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Zonal Incident Threat Radar -->
        <div class="top-glass-card p-5">
          <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div>
              <h3 class="text-sm font-bold text-slate-900 tracking-tight">Zonal Incident Threat Radar</h3>
              <p class="text-xs text-slate-500">Active alerts scoped to ${escapeHtml(d.short_name)}</p>
            </div>
            <span class="material-symbols-outlined text-rose-500">emergency</span>
          </div>

          ${incidents.length === 0 ? `
            <div class="py-12 text-center text-slate-400">
              <span class="material-symbols-outlined text-3xl mb-1 text-slate-300">verified_user</span>
              <p class="text-xs">No active incidents reported in ${escapeHtml(d.short_name)}</p>
            </div>
          ` : `
            <div class="space-y-3 max-h-72 overflow-y-auto custom-scrollbar pr-1">
              ${incidents.map(inc => `
                <div class="p-3 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-all">
                  <div class="flex items-center justify-between mb-1">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold ${
                      inc.severity === 'Critical' ? 'bg-rose-100 text-rose-800 border border-rose-200' :
                      inc.severity === 'High' ? 'bg-orange-100 text-orange-800 border border-orange-200' :
                      'bg-slate-100 text-slate-700'
                    }">${escapeHtml(inc.severity)} &bull; ${escapeHtml(inc.category)}</span>
                    <span class="text-[10px] text-slate-400">${escapeHtml(inc.created_at)}</span>
                  </div>
                  <h4 class="text-xs font-bold text-slate-900">${escapeHtml(inc.title)}</h4>
                  <p class="text-[11px] text-slate-600 line-clamp-2 mt-0.5">${escapeHtml(inc.description)}</p>
                  <div class="text-[10px] text-slate-400 mt-1 font-mono">
                    LGA: ${escapeHtml(inc.lga)} &bull; Ward: ${escapeHtml(inc.ward || 'N/A')}
                  </div>
                </div>
              `).join('')}
            </div>
          `}
        </div>

      </div>

      <!-- 5. Agent Facial Verification Gallery (Campaign Chairman Feature) -->
      <div class="top-glass-card p-5">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
          <div>
            <h3 class="text-sm font-bold text-slate-900 tracking-tight">Accredited Agent Identity Stream</h3>
            <p class="text-xs text-slate-500">Live facial snapshots captured by PU agents across ${escapeHtml(d.short_name)}</p>
          </div>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
            ${agents.length} Field Agents
          </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
          ${agents.slice(0, 12).map(ag => {
            const hasPhoto = ag.photo && ag.photo.trim() !== '';
            const photoSrc = hasPhoto 
              ? (ag.photo.startsWith('http') ? ag.photo : ag.photo)
              : '';

            return `
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-center flex flex-col items-center">
                <div class="w-14 h-14 rounded-full border-2 border-emerald-500 overflow-hidden bg-white mb-2 shadow-xs flex items-center justify-center">
                  ${hasPhoto 
                    ? `<img src="${escapeHtml(photoSrc)}" alt="${escapeHtml(ag.name)}" class="w-full h-full object-cover">`
                    : `<span class="material-symbols-outlined text-slate-300 text-2xl">person</span>`
                  }
                </div>
                <div class="text-xs font-bold text-slate-900 truncate w-full" title="${escapeHtml(ag.name)}">
                  ${escapeHtml(ag.name)}
                </div>
                <div class="text-[9px] text-emerald-700 font-mono font-semibold">
                  ${escapeHtml(ag.agent_code)}
                </div>
                <div class="text-[9px] text-slate-400 truncate w-full mt-0.5">
                  ${escapeHtml(ag.lga)}
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>

    </div>

    <!-- Ward Drill-Down Modal Container -->
    <div id="senatorialWardModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
      <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 id="wardModalTitle" class="text-base font-bold text-slate-900">Ward Breakdown</h3>
            <p id="wardModalSubtitle" class="text-xs text-slate-500">Ward collation inspection</p>
          </div>
          <button onclick="closeWardDrillDownModal()" class="w-8 h-8 rounded-full bg-slate-200/80 hover:bg-slate-300 text-slate-600 flex items-center justify-center cursor-pointer">
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>
        <div id="wardModalBody" class="p-5 overflow-y-auto custom-scrollbar flex-1">
          <!-- Wards Table dynamically injected -->
        </div>
      </div>
    </div>
  `;
}

async function openWardDrillDownModal(lgaId, lgaName) {
  const modal = document.getElementById('senatorialWardModal');
  const title = document.getElementById('wardModalTitle');
  const sub = document.getElementById('wardModalSubtitle');
  const body = document.getElementById('wardModalBody');

  if (!modal || !body) return;

  title.textContent = `${lgaName} • Ward Collation Breakdown`;
  sub.textContent = `Inspecting all electoral wards and voting progress in ${lgaName}`;
  body.innerHTML = `
    <div class="text-center py-12 text-slate-400">
      <div class="w-8 h-8 mx-auto border-2 border-emerald-600 border-t-transparent rounded-full animate-spin mb-2"></div>
      <span class="text-xs">Loading ward data for ${lgaName}...</span>
    </div>
  `;

  modal.classList.remove('hidden');
  modal.classList.add('flex');

  try {
    const res = await fetch(`admin_api.php?action=senatorial_ward_breakdown&lga_id=${lgaId}`).then(r => r.json());
    if (!res.success) throw new Error(res.message);

    const wards = res.wards || [];
    body.innerHTML = `
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            <th class="py-2.5 px-3">Electoral Ward</th>
            <th class="py-2.5 px-3">PUs Reported</th>
            <th class="py-2.5 px-3">Registered</th>
            <th class="py-2.5 px-3">Total Valid</th>
            <th class="py-2.5 px-3 text-emerald-800 font-bold bg-emerald-50">LP (SIA)</th>
            <th class="py-2.5 px-3">APC</th>
            <th class="py-2.5 px-3">PDP</th>
            <th class="py-2.5 px-3 text-center">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          ${wards.map(w => `
            <tr class="hover:bg-slate-50">
              <td class="py-3 px-3">
                <span class="font-bold text-slate-900">${escapeHtml(w.name)}</span>
                <span class="block text-[10px] text-slate-400 font-mono">${escapeHtml(w.code)}</span>
              </td>
              <td class="py-3 px-3 font-semibold text-slate-800">
                ${w.reported_pus} / ${w.total_pus} (${w.reporting_percentage}%)
              </td>
              <td class="py-3 px-3 text-slate-600 font-mono">
                ${w.total_registered.toLocaleString()}
              </td>
              <td class="py-3 px-3 text-slate-800 font-mono font-semibold">
                ${w.total_valid.toLocaleString()}
              </td>
              <td class="py-3 px-3 text-emerald-700 font-mono font-bold bg-emerald-50/50">
                ${w.lp_votes.toLocaleString()} (${w.lp_percentage}%)
              </td>
              <td class="py-3 px-3 text-slate-600 font-mono">
                ${w.apc_votes.toLocaleString()}
              </td>
              <td class="py-3 px-3 text-slate-600 font-mono">
                ${w.pdp_votes.toLocaleString()}
              </td>
              <td class="py-3 px-3 text-center">
                ${w.is_leading ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">LP LEADING</span>' : '<span class="text-slate-400 text-[10px]">TALLYING</span>'}
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  } catch (err) {
    body.innerHTML = `<div class="p-6 text-center text-rose-600 text-xs font-semibold">Error: ${escapeHtml(err.message)}</div>`;
  }
}

function closeWardDrillDownModal() {
  const modal = document.getElementById('senatorialWardModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function exportZonalCsv(district) {
  window.open(`admin_api.php?action=export_csv&district=${district}`, '_blank');
}

// Password modal handler
function openPasswordModal() {
  document.getElementById('adminPasswordModal').classList.remove('hidden');
  document.getElementById('adminPasswordModal').classList.add('flex');
}
function closePasswordModal() {
  document.getElementById('adminPasswordModal').classList.add('hidden');
  document.getElementById('adminPasswordModal').classList.remove('flex');
}

async function handleAdminChangePassword(e) {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  fd.append('action', 'change_password');
  fd.append('csrf_token', getCsrfToken());

  const res = await fetch('admin_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message);
    closePasswordModal();
    form.reset();
  } else {
    alert('Error: ' + data.message);
  }
}

// Startup
window.addEventListener('DOMContentLoaded', () => {
  const bodyPage = document.body.dataset.page;
  const hash = window.location.hash.replace('#', '');
  const validTabs = ['overview', 'senatorial', 'review-queue', 'discrepancies', 'agents', 'coverage', 'incidents', 'announcements', 'reports', 'geography', 'settings', 'admin-users', 'backup', 'audit-log'];
  const targetPage = (bodyPage && validTabs.includes(bodyPage)) ? bodyPage : (validTabs.includes(hash) ? hash : 'overview');
  switchTab(targetPage);
});

