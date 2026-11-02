# Election-site &bull; SIA 2027 Command Portal
### Adamawa State Governorship & General Election Collation System

An enterprise-hardened, dependency-free, vanilla **PHP 8.4 + SQLite / MySQL + Tailwind CSS (CDN) + Vanilla JS** election intelligence and party agent Form EC8A collation system designed for the Labour Party (LP) electoral command in Adamawa State, Nigeria.

Zero Node.js build steps. Zero Composer dependencies. Deploys instantaneously via standard drag-and-drop / zip upload to any cPanel or Apache shared hosting environment.

---

## 1. System Architecture & Tech Stack

- **Backend Runtime:** Native PHP 8.0+ (Tested on PHP 8.4.1). No frameworks, no external package bloat.
- **Database Engine:** Multi-driver PDO:
  - **SQLite (Default):** Configured with Write-Ahead Logging (`PRAGMA journal_mode = WAL;`), foreign key integrity (`PRAGMA foreign_keys = ON;`), and `PRAGMA busy_timeout = 5000;` to handle concurrent writes without locking.
  - **MySQL (Optional):** Toggleable in `config.php` via `DB_DRIVER = 'mysql'`.
- **Database Migrations:** Idempotent, automatic schema migration on startup via `db.php` (`init_database_schema`). Never drops existing tables or alters user data destructively.
- **Frontend Architecture:** Clean HTML5, Tailwind CSS via CDN, and vanilla ES6+ JavaScript. High-density design system utilizing **Plus Jakarta Sans** and **Outfit** typography, dark command sidebar, and glass-card architecture.
- **Security Hardening:**
  - Synchronizer Token Pattern CSRF protection on every state-changing POST request.
  - Session Fixation protection via `session_regenerate_id(true)` upon successful authentication.
  - Session cookie flags: `HttpOnly`, `SameSite=Lax`, and `Secure` when served over HTTPS.
  - Brute-force rate limiting: 5 failed attempts per 15 minutes per IP + email with exponential backoff.
  - Strict upload validation: MIME-type verification via `finfo`, extension allowlist (`jpg`, `jpeg`, `png`, `webp`, `pdf`), 15MB file cap, randomized hex filenames, and script execution disabled in `uploads/`.
  - Immutable chronological audit trail in `audit_log` recording every admin action, write, status change, and login attempt.
  - Direct HTTP downloads of `database/*.sqlite`, `tests/`, and sensitive files blocked via Apache `.htaccess`.

---

## 2. Electoral Geography (Adamawa State)

- **21 Local Government Areas (LGAs):** Demsa, Fufore, Ganye, Girei, Gombi, Guyuk, Hong, Jada, Lamurde, Madagali, Maiha, Mayo-Belwa, Michika, Mubi North, Mubi South, Numan, Shelleng, Song, Toungo, Yola North, Yola South.
- **226 Electoral Wards (Registration Areas):** Official INEC electoral wards spanning all 21 LGAs.
- **4,101 Polling Units:** Official INEC post-expansion polling units directory seeded directly into `polling_units` from `database/seed/polling_units.csv` with standard delimitation codes (`02/XX/YY/ZZZ`).
- **Dynamic Database Counts:** All figures, coverage percentages, and voter statistics are dynamically queried from `COUNT(*)` in the database layer—never hardcoded in PHP or JavaScript.
- **Interactive Geography Manager:** Admin dashboard includes full CSV import (with dry-run validation preview), master seed reload, and single-click CSV export of all 4,101 polling units.

---

## 3. Role-Based Access Control (RBAC)

Role boundaries are strictly enforced server-side in `admin_api.php`:

| Role | Scope | Permitted Actions |
| :--- | :--- | :--- |
| **Superadmin** | State-wide (All 21 LGAs) | Full access: View/review all sheets, bulk verify, manage all agents, configure global election settings, manage admin users, download raw SQLite snapshots (`VACUUM INTO`) and uploads ZIP archives. |
| **LGA Coordinator** | Assigned LGA Only | Strictly scoped to their designated `lga_id`. Can review/verify/flag/reject result sheets for their LGA, approve/suspend/reassign agents in their LGA, and dispatch announcements to their LGA agents. Cannot access foreign LGAs, manage admins, or modify global settings. |
| **Electoral Analyst** | State-wide (Read-Only) | Read-only telemetry access: Overview KPIs, review queue inspector, discrepancy reports, coverage tracker, incident monitor, audit log, and CSV exports. All mutating requests are rejected with `403 Forbidden`. |
| **Party Agent** | Assigned Polling Unit | Self-service field portal (`dashboard.php`): Transmit Form EC8A tally sheets and photos for assigned PU, report field incidents, view announcements, and queue submissions offline. Cannot submit for any other PU. |

---

## 4. Key Collation & Integrity Features

### A. Results Review Queue & Detail Auditor
- Side-by-side view: Form EC8A photographic document on the left (with Zoom In/Out, Rotate 90°, and Full-Screen preview) and entered tallies on the right.
- Real-time mathematical reconciliation:
  - $\text{Balanced: } \text{Total Cast} (\text{Valid} + \text{Rejected}) = \text{Accredited Voters}$
  - $\text{Over-Voting: } \text{Total Cast} > \text{Accredited Voters}$ (Automatically flagged for audit)
  - $\text{Under-Voting: } \text{Total Cast} < \text{Accredited Voters}$
- Actions: **Verify [V]**, **Flag [F]** (mandatory reason), **Reject [R]** (mandatory reason), **Edit Numbers** (recalculates valid votes, prompts mandatory audit reason, logs revision history in `result_sheet_revisions`), and **Request Resubmission** (flags sheet and sends targeted high-priority announcement to agent).
- Keyboard shortcuts: `J` (Next Sheet), `K` (Previous Sheet), `V` (Verify), `F` (Flag), `R` (Reject).

### B. Discrepancy & Fraud Detection
Dedicated module automatically auditing:
1. Over-voting ($\text{Cast} > \text{Accredited}$).
2. Mathematical mismatch ($\sum \text{Parties} \neq \text{Total Valid}$).
3. Turnout exceeding registered voters ($\text{Votes} > \text{Registered Voters}$).
4. Duplicate image hash (detects identical photographic files uploaded across multiple units).
5. Multiple agents attempting to submit for the same polling unit.

### C. Offline-Tolerant Agent Submissions
- If ground agents lose cellular connectivity in remote areas, submissions are safely queued in `localStorage['sia2027_offline_ec8a_queue']` along with base64 photographic assets.
- A prominent status banner displays queued items. Submissions automatically sync when network is restored (`window.addEventListener('online')`) or via manual trigger.

### D. Public Results Gateway (`index.html`)
- Prominent legal notice: *"Party agent tallies, unofficial. Not INEC results."*
- Polling engine polls `api.php?action=live_results` every 8 seconds, backs off exponentially on connection errors (8s $\to$ 16s $\to$ 32s $\to$ 60s), and automatically pauses when the browser tab is hidden (`document.hidden`).
- Only verified sheets with `is_current = 1` are aggregated into public tallies (configurable in settings via `require_review_before_public`).

---

## 5. One-Click Automated Deployment & Setup

The portal includes an all-in-one deployment engine that handles environment pre-flight diagnostics, permission configuration, database migrations, geography seeding, and administrator account creation in one step.

### Option A: Command Line Runner (`deploy.sh`)
If deploying to a VPS, local terminal, or SSH-enabled cPanel:
```bash
# Make executable and run (configures permissions, runs migrations, seeds initial parameters)
./deploy.sh

# Or to initialize with full demo simulation suite:
./deploy.sh --demo
```

### Option B: Web Browser Wizard (`deploy.php`)
If deploying via standard cPanel File Manager upload:
1. Upload and extract `sia2027_cpanel_deploy.zip` in your `public_html/` directory.
2. Visit `https://your-domain.com/deploy.php` in your web browser.
3. Review the automated pre-flight system diagnostics (PHP version, PDO SQLite, Fileinfo, folder writability).
4. Enter your Superadmin credentials (or leave blank to use defaults) and click **"Run Everything & Initialize Portal"**.
5. The setup engine auto-migrates all 13 tables, populates initial settings, creates the primary party agent (`agent@sia2027.org` / `password123`), and writes `database/installed.lock` to permanently secure the installer.

### Manual / Fallback First-Run Setup:
If using `admin_login.php` on an unconfigured database, the system will automatically redirect to `setup.php`, allowing you to create the initial Superadmin and self-locking upon completion.

---

## 6. Testing & Quality Assurance

A complete, dependency-free test suite is included in `tests/`:

```bash
# Run the automated test runner (spins up a temporary isolated SQLite DB)
php tests/run.php
```

### Verified Test Cases:
- **Test 1:** Database auto-migrations run idempotently without data loss.
- **Test 2:** Login rate limiting blocks brute-force attacks after 5 failed attempts.
- **Test 3:** CSRF token verification and rejection of forged tokens.
- **Test 4:** Agent Polling Unit scope enforcement (rejection of unassigned PUs).
- **Test 5:** Server-side over-voting auto-flagging.
- **Test 6:** Atomic resubmissions superseding previous sheets (`is_current = 0`) and revision logging.
- **Test 7:** Coordinator role scoping and cross-LGA isolation.
- **Test 8:** Public live results filter excluding pending/rejected sheets.
- **Test 9:** CSV import dry-run validation without altering database state.

---

## 7. Demo Simulation Generator

To test the user interface with realistic election returns and scenarios without entering production data:

```bash
# Generate 3 demo admins, 30 agents, 100 realistic sheets, and 20 incidents
php seed_demo.php --demo
```

> [!IMPORTANT]
> The demo credentials are generated randomly with high entropy and printed **once** to your terminal. They force a password change upon first login. Never run this script on an active production election ledger.

---

## 8. Backup & Disaster Recovery

- **Live SQLite Database Snapshot:** Available in Admin Headquarters under **Database & Backups**. Uses SQLite's native `VACUUM INTO` command to generate an atomic, non-blocking point-in-time `.sqlite` copy suitable for immediate download.
- **Uploads Archive:** Compresses all scanned Form EC8A documents in `uploads/` into a downloadable `.zip` file.
- **Emergency Manual Backup (CLI):**
  ```bash
  cp database/sia2027.sqlite database/sia2027_backup_$(date +%Y%m%d_%H%M%S).sqlite
  ```

---

## 9. Election Day Operational Runbook

1. **T-24 Hours:** Verify Superadmin and LGA Coordinator access. Run `php tests/run.php` to confirm all security boundaries. Ensure `submissions_open = 1` in Settings.
2. **06:00 AM (Polls Open):** Ensure LGA Coordinators are monitoring the **Incident Command Hotline** for early BVAS or logistics reports.
3. **02:30 PM (Counting Commences):** Field agents begin uploading signed Form EC8A results and photos.
4. **Collation Review:** LGA Coordinators use the **Results Review Queue** with side-by-side verification and balance audits. Use **Bulk Verify Balanced** for sheets that pass all checks.
5. **Auditing:** Chief Electoral Analyst monitors the **Discrepancy Audit** tab for over-voting or duplicate photo hashes.
6. **Publication:** Verified tallies stream directly to the public gateway (`index.html`) with dynamic reporting percentages and margin leads.

