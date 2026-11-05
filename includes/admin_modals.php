<?php
/**
 * SIA 2027 Admin Command Center - Shared Global Modals
 */
?>
  <!-- EC8A Detail & Review Modal -->
  <div id="reviewDetailModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-6">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-6xl h-[92vh] flex flex-col overflow-hidden shadow-2xl">
      
      <!-- Modal Header -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between flex-shrink-0 bg-white">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center text-sm shadow-xs">
            EC8A
          </div>
          <div>
            <h3 id="modalPuTitle" class="text-base sm:text-lg font-bold text-slate-900 m-0">Form EC8A Result Review</h3>
            <span id="modalPuSub" class="text-xs text-slate-500 font-normal block">Polling Unit &bull; Ward &bull; LGA</span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Keyboard shortcuts pill -->
          <span class="hidden md:inline-flex items-center gap-2 text-[10px] text-slate-600 font-mono bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
            <span>Keys: [V]erify &bull; [F]lag &bull; [R]eject &bull; [J]/[K] Next/Prev</span>
          </span>
          <button onclick="closeReviewModal()" class="text-slate-400 hover:text-slate-700 p-1 text-2xl font-bold cursor-pointer transition-colors">&times;</button>
        </div>
      </div>

      <!-- Modal Body (Two-Column Layout: Left Image Stage, Right Tallies) -->
      <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 overflow-hidden">
        
        <!-- Left: Image Viewer (7 cols) -->
        <div class="lg:col-span-7 bg-slate-950 flex flex-col border-b lg:border-b-0 lg:border-r border-slate-200 relative">
          <!-- Image Controls Toolbar -->
          <div class="p-3 bg-slate-900/95 border-b border-slate-800 flex items-center justify-between text-xs z-10">
            <span class="text-slate-400 font-mono text-[11px]" id="modalImageSha">SHA256: ...</span>
            <div id="modalZoomControls" class="flex items-center gap-2">
              <button onclick="zoomImage(1.2)" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-colors cursor-pointer">+</button>
              <button onclick="zoomImage(0.8)" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-colors cursor-pointer">-</button>
              <button onclick="resetImageTransform()" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors cursor-pointer">Reset</button>
              <button onclick="rotateImage()" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors cursor-pointer">Rotate 90°</button>
              <a id="modalImageFullLink" href="#" target="_blank" class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition-colors">Full Size ↗</a>
            </div>
          </div>

          <!-- Image Stage -->
          <div class="flex-1 overflow-auto flex items-center justify-center p-4 bg-slate-950 relative" id="imageStage">
            <img id="modalEc8aImage" src="" alt="Form EC8A Sheet" class="max-h-full max-w-full object-contain transition-transform duration-200 origin-center rounded-lg shadow-lg">

            <!-- Informative placeholder when no photo was attached -->
            <div id="modalNoImagePlaceholder" class="hidden text-center p-8 space-y-3.5 max-w-sm animate-in fade-in duration-200">
              <div class="w-16 h-16 mx-auto rounded-3xl bg-slate-900 border border-slate-800 text-slate-400 flex items-center justify-center shadow-xl">
                <span class="material-symbols-outlined text-3xl text-amber-500">no_photography</span>
              </div>
              <div class="space-y-1">
                <h4 class="text-white font-bold text-sm m-0">No EC8A Photo Transmitted</h4>
                <p class="text-xs text-slate-400 m-0 leading-relaxed">
                  This submission was transmitted as direct numerical telemetry without an attached Form EC8A photo scan.
                </p>
              </div>
              <div class="pt-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-950/70 text-amber-300 border border-amber-800">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                  <span>Numerical Telemetry Only</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Tally Form & Review Actions (5 cols) -->
        <div class="lg:col-span-5 flex flex-col justify-between overflow-y-auto custom-scrollbar bg-white p-5 space-y-4 text-slate-900 border-l border-slate-200">
          
          <div class="space-y-4">
            <!-- Integrity Banner -->
            <div id="modalIntegrityBanner" class="p-3.5 rounded-2xl border text-xs font-bold flex items-center justify-between">
              <!-- Populated dynamically -->
            </div>

            <!-- Reviewer / Status Banner -->
            <div id="modalStatusBanner" class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700">
              <!-- Status info -->
            </div>

            <!-- Party Vote Numbers Grid -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                <span>Recorded Tallies</span>
                <button type="button" onclick="toggleEditNumbers()" id="btnToggleEdit" class="text-emerald-700 hover:text-emerald-800 text-[11px] font-semibold inline-flex items-center gap-1 cursor-pointer">
                  <span class="material-symbols-outlined text-xs">edit</span>
                  <span>Edit Numbers</span>
                </button>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs max-h-80 overflow-y-auto pr-1 custom-scrollbar">
                <?php
                foreach (get_official_parties() as $code => $p):
                    $isLp = ($code === 'LP');
                    $cardClass = $isLp 
                        ? 'bg-emerald-50 border border-emerald-200 text-emerald-900 font-bold' 
                        : 'bg-slate-50 border border-slate-200/90 text-slate-800 font-semibold';
                    $textColor = $isLp ? 'text-emerald-800' : 'text-slate-700';
                    $inputColor = $isLp ? 'text-emerald-900 font-black' : 'text-slate-900';
                ?>
                  <div class="p-2 rounded-xl <?= $cardClass ?> flex items-center justify-between gap-1 shadow-2xs">
                    <span class="truncate text-[11px] <?= $textColor ?>" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($code) ?></span>
                    <input type="number" id="detail_<?= strtolower($code) ?>" disabled class="w-16 bg-white border border-slate-300 rounded px-1.5 py-0.5 text-right <?= $inputColor ?> font-bold text-xs outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                  </div>
                <?php endforeach; ?>

                <div class="p-2 rounded-xl bg-slate-50 border border-slate-200/90 flex items-center justify-between gap-1 shadow-2xs">
                  <span class="font-bold text-rose-600 text-[11px]">Rejected</span>
                  <input type="number" id="detail_rejected" disabled class="w-16 bg-white border border-slate-300 rounded px-1.5 py-0.5 text-right text-slate-900 font-bold text-xs outline-none focus:border-emerald-600">
                </div>
              </div>

              <!-- Accredited & Valid Total Summary -->
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs text-slate-700">
                <div class="flex items-center justify-between">
                  <span class="font-medium text-slate-600">Total Valid Votes:</span>
                  <strong id="detail_total_valid" class="text-slate-900 font-mono text-sm font-bold">0</strong>
                </div>
                <div class="flex items-center justify-between">
                  <span class="font-medium text-slate-600">Accredited Voters:</span>
                  <input type="number" id="detail_accredited" disabled class="w-24 bg-white border border-slate-300 rounded px-2 py-0.5 text-right text-slate-900 font-bold outline-none">
                </div>
              </div>

              <!-- Reason input for edit numbers -->
              <div id="editReasonBox" class="hidden space-y-1 pt-1">
                <label class="block text-[11px] font-bold text-amber-700">Audit Reason for Editing Numbers (Mandatory)</label>
                <input type="text" id="detail_edit_reason" placeholder="e.g. Obvious transposition corrected against signed sheet." class="w-full bg-white border border-amber-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-amber-500">
                <button type="button" onclick="submitNumberEdits()" class="mt-1 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 rounded-xl text-xs transition-all shadow-xs cursor-pointer">
                  Save Edited Numbers &amp; Record Revision
                </button>
              </div>
            </div>

            <!-- Agent & Observation Notes -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs text-slate-700">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Agent Telemetry</span>
              <div class="flex items-center justify-between text-slate-800">
                <span id="detail_agent_name" class="font-semibold">Agent Name</span>
                <span id="detail_agent_phone" class="font-mono text-[11px] text-slate-500"></span>
              </div>
              <div class="flex items-center gap-1.5 pt-1 border-t border-slate-200">
                <span class="material-symbols-outlined text-xs text-emerald-700">cell_tower</span>
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Ground Status:</span>
                <span id="detail_transmission_status" class="text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">ON-SITE</span>
              </div>
              <p id="detail_notes" class="text-slate-600 text-[11px] italic m-0 pt-1 border-t border-slate-200"></p>
            </div>

            <!-- Revisions History -->
            <div id="revisionHistoryBox" class="hidden space-y-1.5">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Revision History</span>
              <div id="revisionsList" class="space-y-1 max-h-32 overflow-y-auto custom-scrollbar pr-1"></div>
            </div>
          </div>

          <!-- Audit Action Buttons -->
          <?php if ($role !== 'analyst'): ?>
          <div class="pt-3 border-t border-slate-100 space-y-2">
            <div class="grid grid-cols-3 gap-2">
              <button type="button" onclick="actionVerifyCurrent()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-xs transition-all cursor-pointer flex items-center justify-center gap-1 shadow-xs">
                <span class="material-symbols-outlined text-xs">check</span>
                <span>Verify [V]</span>
              </button>
              <button type="button" onclick="actionFlagCurrent()" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 rounded-xl text-xs transition-all cursor-pointer flex items-center justify-center gap-1 shadow-xs">
                <span class="material-symbols-outlined text-xs">flag</span>
                <span>Flag [F]</span>
              </button>
              <button type="button" onclick="actionRejectCurrent()" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 rounded-xl text-xs transition-all cursor-pointer flex items-center justify-center gap-1 shadow-xs">
                <span class="material-symbols-outlined text-xs">close</span>
                <span>Reject [R]</span>
              </button>
            </div>
            <button type="button" onclick="actionRequestResubmissionCurrent()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-semibold py-2 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
              <span class="material-symbols-outlined text-xs">campaign</span>
              <span>Request Resubmission from Agent</span>
            </button>
          </div>
          <?php else: ?>
          <div class="p-3 rounded-xl bg-slate-100 text-slate-600 text-center text-xs border border-slate-200">
            Viewing in Read-Only Mode (Analyst Role)
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

  <!-- Change Password Modal -->
  <div id="adminPasswordModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-5 shadow-2xl text-slate-900">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h3 class="text-lg font-bold text-slate-900 m-0">Change Password</h3>
        <button onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
      </div>

      <form id="adminPasswordForm" onsubmit="handleAdminChangePassword(event)" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Current Password</label>
          <input type="password" name="current_password" required class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">New Password (min 8 chars)</label>
          <input type="password" name="new_password" minlength="8" required class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Confirm New Password</label>
          <input type="password" name="confirm_password" minlength="8" required class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button type="button" onclick="closePasswordModal()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">Cancel</button>
          <button type="submit" id="btnSaveAdminPwd" class="px-6 py-2 rounded-full text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs cursor-pointer">Save New Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Geography & Polling Units Import Modal -->
  <div id="geographyImportModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 space-y-5 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar text-slate-900">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-base sm:text-lg font-bold text-slate-900 m-0">Import Polling Units &amp; Geography</h3>
          <span class="text-xs text-slate-500 font-normal">Load official INEC Delimitation directory or custom CSV</span>
        </div>
        <button onclick="closeGeographyImportModal()" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
      </div>

      <!-- Quick Action: Reload Master INEC Seed -->
      <div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200 space-y-2 text-slate-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-700 text-lg">verified</span>
            <span class="font-bold text-xs text-slate-900">Official Adamawa INEC Dataset</span>
          </div>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-emerald-800 border border-emerald-300">4,101 Units</span>
        </div>
        <p class="text-[11px] text-slate-600 m-0">
          Re-seed the entire electoral hierarchy directly from the built-in master dataset (<code class="text-slate-800 bg-white px-1 py-0.5 rounded border border-slate-200">database/seed/polling_units.csv</code>) with 21 LGAs, 226 Wards, and 4,101 Polling Units.
        </p>
        <div class="pt-1 flex gap-2">
          <button type="button" onclick="handleReloadMasterSeed(true)" class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-200 transition-all inline-flex items-center gap-1 cursor-pointer">
            <span class="material-symbols-outlined text-xs">visibility</span>
            <span>Dry-Run Preview</span>
          </button>
          <button type="button" onclick="handleReloadMasterSeed(false)" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all inline-flex items-center gap-1 shadow-xs cursor-pointer">
            <span class="material-symbols-outlined text-xs">refresh</span>
            <span>Reload Master Seed</span>
          </button>
        </div>
      </div>

      <!-- Custom CSV Upload / Paste Section -->
      <form id="geographyImportForm" onsubmit="handleGeographyCsvSubmit(event)" class="space-y-4">
        <div class="relative flex py-1 items-center">
          <div class="flex-grow border-t border-slate-200"></div>
          <span class="flex-shrink mx-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Or Import Custom CSV</span>
          <div class="flex-grow border-t border-slate-200"></div>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">CSV File Upload</label>
          <input type="file" id="geoCsvFileInput" accept=".csv,text/csv" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-700 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Or Paste CSV Content</label>
          <textarea id="geoCsvTextInput" rows="4" placeholder="lga_name,lga_code,ward_name,ward_code,pu_name,pu_code,registered_voters" class="w-full bg-white border border-slate-300 rounded-xl p-3 text-xs text-slate-900 font-mono outline-none focus:border-emerald-600 custom-scrollbar"></textarea>
          <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
            <span>Columns: lga_name, lga_code, ward_name, ward_code, pu_name, pu_code, registered_voters</span>
            <a href="samples/polling_units_sample.csv" download class="text-emerald-700 hover:underline font-semibold">Sample CSV &darr;</a>
          </div>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <input type="checkbox" id="geoDryRunCheckbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
          <label for="geoDryRunCheckbox" class="text-xs text-slate-600 font-medium">Dry-run validation preview only (do not write to database)</label>
        </div>

        <!-- Result / Feedback Box -->
        <div id="geographyImportResult" class="hidden p-3.5 rounded-2xl border text-xs space-y-1.5"></div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
          <button type="button" onclick="closeGeographyImportModal()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">Close</button>
          <button type="submit" id="btnSubmitGeoImport" class="px-6 py-2 rounded-full text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all inline-flex items-center gap-1.5 shadow-xs cursor-pointer">
            <span class="material-symbols-outlined text-xs">upload</span>
            <span id="btnSubmitGeoImportText">Process Import</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================================
       ADD FIELD AGENT MODAL (Real INEC Polling Units Dropdown)
       ========================================================================= -->
  <div id="addAgentModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 text-slate-900 shadow-2xl space-y-5 animate-in fade-in duration-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="font-bold text-base text-slate-900 m-0">Add Field Agent</h3>
          <p class="text-xs text-slate-500 mt-0.5">Deploy a verified agent to an official INEC polling unit</p>
        </div>
        <button onclick="closeAddAgentModal()" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
      </div>

      <form id="addAgentForm" onsubmit="submitAddAgent(event)" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Full Name *</label>
            <input type="text" id="addAgentName" required placeholder="e.g. Aminu Bello" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number *</label>
            <input type="tel" id="addAgentPhone" required placeholder="e.g. 08012345678" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address (Optional)</label>
            <input type="email" id="addAgentEmail" placeholder="auto-generated if blank" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Initial Status</label>
            <select id="addAgentStatus" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600">
              <option value="accredited">Accredited (Active immediately)</option>
              <option value="pending">Pending Approval</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Portal Password</label>
          <input type="text" id="addAgentPassword" value="Agent2027!" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600 font-mono">
          <span class="text-[10px] text-slate-400 mt-0.5 block">Default password for first-time agent portal sign-in</span>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/90 space-y-3">
          <span class="block text-xs font-bold text-slate-800 uppercase tracking-wider">Official Polling Unit Assignment</span>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">1. Local Government Area (LGA) *</label>
            <select id="addAgentLga" onchange="onAddAgentLgaChange()" required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600">
              <option value="">-- Choose LGA --</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">2. Electoral Ward *</label>
            <select id="addAgentWard" onchange="onAddAgentWardChange()" disabled required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600 disabled:opacity-50">
              <option value="">-- Select LGA first --</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">3. Polling Unit *</label>
            <select id="addAgentPu" disabled required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600 disabled:opacity-50">
              <option value="">-- Select Ward first --</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
          <button type="button" onclick="closeAddAgentModal()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">Cancel</button>
          <button type="submit" id="btnAddAgentSubmit" class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-xs">person_add</span>
            <span>Create Agent</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================================
       REASSIGN FIELD AGENT MODAL (Real INEC Polling Units Dropdown)
       ========================================================================= -->
  <div id="reassignAgentModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 text-slate-900 shadow-2xl space-y-5 animate-in fade-in duration-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="font-bold text-base text-slate-900 m-0">Reassign Polling Unit</h3>
          <p id="reassignAgentSubtitle" class="text-xs text-slate-500 mt-0.5">Select a new official polling unit</p>
        </div>
        <button onclick="closeReassignAgentModal()" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
      </div>

      <form id="reassignAgentForm" onsubmit="submitReassignAgent(event)" class="space-y-4">
        <input type="hidden" id="reassignAgentId" value="">

        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1">
          <span class="text-slate-500 block">Agent: <strong id="reassignAgentName" class="text-slate-900 font-bold"></strong></span>
          <span class="text-slate-500 block">Current Unit: <strong id="reassignAgentCurrentPu" class="text-slate-900 font-medium"></strong></span>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">1. Local Government Area (LGA) *</label>
            <select id="reassignAgentLga" onchange="onReassignAgentLgaChange()" required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600">
              <option value="">-- Choose LGA --</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">2. Electoral Ward *</label>
            <select id="reassignAgentWard" onchange="onReassignAgentWardChange()" disabled required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600 disabled:opacity-50">
              <option value="">-- Select LGA first --</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">3. New Polling Unit *</label>
            <select id="reassignAgentPu" disabled required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 outline-none focus:border-emerald-600 disabled:opacity-50">
              <option value="">-- Select Ward first --</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
          <button type="button" onclick="closeReassignAgentModal()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">Cancel</button>
          <button type="submit" id="btnReassignSubmit" class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-xs">swap_horiz</span>
            <span>Update Assignment</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Compose Broadcast Directive -->
  <div id="createAnnouncementModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-2xl w-full max-w-lg p-6 sm:p-7 space-y-5 animate-in fade-in zoom-in-95 duration-150">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
            <span class="material-symbols-outlined text-xl">campaign</span>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900 m-0">Compose Command Broadcast</h3>
            <span class="text-xs text-slate-500">Dispatch directives and critical alerts to field agents</span>
          </div>
        </div>
        <button onclick="closeCreateAnnouncementModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center cursor-pointer transition-colors">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>

      <form id="createAnnouncementForm" onsubmit="handleCreateAnnouncement(event)" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Directive Title *</label>
          <input type="text" id="annTitle" name="title" required placeholder="e.g. Critical Collation Advisory for Ward 3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition-all">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Priority Level *</label>
            <select id="annPriority" name="priority" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 bg-white">
              <option value="normal">Normal Advisory</option>
              <option value="urgent">Urgent Directive</option>
              <option value="critical">Critical Emergency Alert</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Target Audience *</label>
            <select id="annAudience" name="audience" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 bg-white">
              <option value="all">All Agents (Statewide)</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Directive Expiry</label>
          <select id="annExpiry" name="expires_in_hours" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 bg-white">
            <option value="24">Expires in 24 Hours</option>
            <option value="48" selected>Expires in 48 Hours</option>
            <option value="168">Expires in 7 Days</option>
            <option value="720">Expires in 30 Days</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Message Body *</label>
          <textarea id="annBody" name="body" required rows="4" placeholder="Enter full situation directive, guidelines, or instructions for field agents..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition-all resize-none"></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
          <button type="button" onclick="closeCreateAnnouncementModal()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">Cancel</button>
          <button type="submit" id="btnAnnounceSubmit" class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-xs">send</span>
            <span>Dispatch Broadcast</span>
          </button>
        </div>
      </form>
    </div>
  </div>

