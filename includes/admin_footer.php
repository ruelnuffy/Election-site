<?php
/**
 * SIA 2027 Admin Command Center - Shared Footer & Scripts
 */
require_once __DIR__ . '/admin_modals.php';
?>
    </main>
  </div>

  <!-- Toast Notification -->
  <div id="adminToast" class="fixed bottom-6 right-6 z-[100] transform translate-y-32 opacity-0 transition-all duration-300 bg-slate-900 border border-slate-800 text-white px-5 py-3 rounded-full shadow-2xl flex items-center gap-2.5 text-sm font-semibold pointer-events-none">
    <span id="toastIcon" class="material-symbols-outlined text-emerald-400 text-lg">check_circle</span>
    <span id="toastText">Action completed</span>
  </div>

  <!-- Main Controller Script with cache-busting -->
  <script src="./assets/js/admin_app.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin_app.js') ?>"></script>

</body>
</html>

