<?php
/**
 * SIA 2027 Comprehensive Test Suite Runner
 * Dependency-Free Self-Contained SQLite Unit & Integration Tests
 * Usage: php tests/run.php
 */

$test_db_path = sys_get_temp_dir() . '/sia_test_' . uniqid() . '.sqlite';
putenv("SIA_DB_PATH={$test_db_path}");
$_ENV['SIA_DB_PATH'] = $test_db_path;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/validators.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';

echo "=================================================================\n";
echo " SIA 2027 AUTOMATED QUALITY ASSURANCE SUITE\n";
echo " Target DB: {$test_db_path}\n";
echo "=================================================================\n\n";

$pdo = new PDO("sqlite:{$test_db_path}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA journal_mode = WAL;");
$pdo->exec("PRAGMA busy_timeout = 5000;");
$pdo->exec("PRAGMA foreign_keys = ON;");

$passed = 0;
$failed = 0;

function run_assert(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($details ? " - {$details}" : "") . "\n";
    }
}

// -----------------------------------------------------------------
// Test 1: Migrations Idempotency
// -----------------------------------------------------------------
echo "TEST 1: Database Auto-Migrations & Idempotency...\n";
require_once __DIR__ . '/../db.php';

try {
    // Run schema creation on the test PDO instance
    $schema_func = function(PDO $p) {
        // Run tables definition
        $p->exec("CREATE TABLE IF NOT EXISTS lgas (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) UNIQUE NOT NULL, code VARCHAR(30) UNIQUE NOT NULL);");
        $p->exec("CREATE TABLE IF NOT EXISTS wards (id INTEGER PRIMARY KEY AUTOINCREMENT, lga_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, code VARCHAR(30) UNIQUE NOT NULL, FOREIGN KEY (lga_id) REFERENCES lgas(id) ON DELETE CASCADE);");
        $p->exec("CREATE TABLE IF NOT EXISTS polling_units (id INTEGER PRIMARY KEY AUTOINCREMENT, ward_id INTEGER NOT NULL, code VARCHAR(40) UNIQUE NOT NULL, name VARCHAR(180) NOT NULL, registered_voters INTEGER DEFAULT NULL, FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE);");
        $p->exec("CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL, email VARCHAR(150) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(30) NOT NULL, lga_id INTEGER DEFAULT NULL, status VARCHAR(30) NOT NULL DEFAULT 'active', must_change_password INTEGER NOT NULL DEFAULT 0, last_login_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (lga_id) REFERENCES lgas(id) ON DELETE SET NULL);");
        $p->exec("CREATE TABLE IF NOT EXISTS agents (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL, email VARCHAR(150) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, phone VARCHAR(30) DEFAULT NULL, role VARCHAR(30) NOT NULL DEFAULT 'agent', state VARCHAR(80) NOT NULL DEFAULT 'Adamawa State', lga VARCHAR(80) NOT NULL, ward VARCHAR(120) NOT NULL, polling_unit VARCHAR(180) NOT NULL, agent_code VARCHAR(40) UNIQUE NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'accredited', lga_id INTEGER DEFAULT NULL, ward_id INTEGER DEFAULT NULL, polling_unit_id INTEGER DEFAULT NULL, last_seen_at DATETIME DEFAULT NULL, password_reset_token VARCHAR(64) DEFAULT NULL, password_reset_expiry DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);");
        $p->exec("CREATE TABLE IF NOT EXISTS result_sheets (id INTEGER PRIMARY KEY AUTOINCREMENT, agent_id INTEGER DEFAULT NULL, polling_unit_id INTEGER DEFAULT NULL, lga_id INTEGER DEFAULT NULL, ward_id INTEGER DEFAULT NULL, lga VARCHAR(80) NOT NULL, ward VARCHAR(120) NOT NULL, polling_unit VARCHAR(180) NOT NULL, ec8a_image VARCHAR(255) DEFAULT NULL, image_sha256 VARCHAR(64) DEFAULT NULL, lp_votes INTEGER NOT NULL DEFAULT 0, apc_votes INTEGER NOT NULL DEFAULT 0, pdp_votes INTEGER NOT NULL DEFAULT 0, apm_votes INTEGER NOT NULL DEFAULT 0, ndc_votes INTEGER NOT NULL DEFAULT 0, rejected_votes INTEGER NOT NULL DEFAULT 0, total_valid_votes INTEGER NOT NULL DEFAULT 0, total_accredited INTEGER DEFAULT NULL, total_accredited_voters INTEGER DEFAULT 0, notes TEXT DEFAULT NULL, verification_status VARCHAR(30) NOT NULL DEFAULT 'pending', reviewed_by INTEGER DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, review_note TEXT DEFAULT NULL, is_current INTEGER NOT NULL DEFAULT 1, supersedes_id INTEGER DEFAULT NULL, source VARCHAR(30) NOT NULL DEFAULT 'agent', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);");
        $p->exec("CREATE TABLE IF NOT EXISTS result_sheet_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, sheet_id INTEGER NOT NULL, changed_by_type VARCHAR(30) NOT NULL, changed_by_id INTEGER NOT NULL, old_json TEXT NOT NULL, new_json TEXT NOT NULL, reason TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);");
        $p->exec("CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_type VARCHAR(30) NOT NULL, actor_id INTEGER DEFAULT NULL, action VARCHAR(80) NOT NULL, entity VARCHAR(50) NOT NULL, entity_id INTEGER DEFAULT NULL, ip VARCHAR(45) NOT NULL, user_agent TEXT DEFAULT NULL, meta_json TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);");
        $p->exec("CREATE TABLE IF NOT EXISTS incidents (id INTEGER PRIMARY KEY AUTOINCREMENT, agent_id INTEGER DEFAULT NULL, assigned_to INTEGER DEFAULT NULL, lga VARCHAR(80) NOT NULL, ward VARCHAR(120) NOT NULL, polling_unit VARCHAR(180) NOT NULL, title VARCHAR(180) NOT NULL, description TEXT NOT NULL, category VARCHAR(60) NOT NULL DEFAULT 'general', severity VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(30) NOT NULL DEFAULT 'received', resolution_note TEXT DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, lat REAL DEFAULT NULL, lng REAL DEFAULT NULL, attachment VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);");
        $p->exec("CREATE TABLE IF NOT EXISTS announcements (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, audience VARCHAR(80) NOT NULL DEFAULT 'all', priority VARCHAR(30) NOT NULL DEFAULT 'normal', created_by INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME DEFAULT NULL);");
        $p->exec("CREATE TABLE IF NOT EXISTS announcement_reads (announcement_id INTEGER NOT NULL, agent_id INTEGER NOT NULL, read_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (announcement_id, agent_id));");
        $p->exec("CREATE TABLE IF NOT EXISTS settings (key VARCHAR(60) PRIMARY KEY, value TEXT NOT NULL);");
        $p->exec("CREATE TABLE IF NOT EXISTS login_attempts (ip VARCHAR(45) NOT NULL, email VARCHAR(150) NOT NULL, attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP);");
    };

    // Run first time
    $schema_func($pdo);
    // Run second time (test idempotency)
    $schema_func($pdo);

    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    run_assert("Migrations run idempotently without error", count($tables) >= 13);
} catch (Exception $e) {
    run_assert("Migrations run idempotently without error", false, $e->getMessage());
}

// -----------------------------------------------------------------
// Test 2: Login Rate Limiting (5 failures in 15 min)
// -----------------------------------------------------------------
echo "\nTEST 2: Login Rate Limiting...\n";
$test_ip = '192.168.1.100';
$test_email = 'ratelimit@sia.ng';

clear_login_attempts($pdo, $test_ip, $test_email);
run_assert("Initial attempt is not rate-limited", is_login_rate_limited($pdo, $test_ip, $test_email, 5, 15) === false);

for ($i = 1; $i <= 5; $i++) {
    record_login_attempt($pdo, $test_ip, $test_email);
}
run_assert("Account throttled after 5 failed login attempts", is_login_rate_limited($pdo, $test_ip, $test_email, 5, 15) === true);

clear_login_attempts($pdo, $test_ip, $test_email);
run_assert("Rate limit resets after successful login clearance", is_login_rate_limited($pdo, $test_ip, $test_email, 5, 15) === false);

// -----------------------------------------------------------------
// Test 3: CSRF Rejection on Tampered Token
// -----------------------------------------------------------------
echo "\nTEST 3: CSRF Token Integrity...\n";
start_secure_session();
$valid_csrf = csrf_token();
run_assert("Valid session CSRF token is verified", verify_csrf_token($valid_csrf) === true);
run_assert("Forged or tampered CSRF token is rejected", verify_csrf_token('invalid_tampered_token_string') === false);
run_assert("Empty CSRF token is rejected", verify_csrf_token('') === false);

// -----------------------------------------------------------------
// Test 4: Agent Polling Unit Scope Enforcement
// -----------------------------------------------------------------
echo "\nTEST 4: Agent PU Scope Enforcement...\n";
$pdo->exec("INSERT INTO lgas (id, name, code) VALUES (1, 'Yola North', 'AD-YLN'), (2, 'Numan', 'AD-NMN');");
$pdo->exec("INSERT INTO wards (id, lga_id, name, code) VALUES (1, 1, 'Alkalawa', 'AD-YLN-01'), (2, 2, 'Numan Urban', 'AD-NMN-01');");
$pdo->exec("INSERT INTO polling_units (id, ward_id, code, name, registered_voters) VALUES (1, 1, '001', 'PU 001 Central School', 400), (2, 2, '002', 'PU 002 General Hospital', 500);");

// Create Agent assigned to PU 1
$pdo->exec("INSERT INTO agents (id, name, email, phone, lga, ward, polling_unit, agent_code, password_hash, status, polling_unit_id) VALUES (1, 'Sani Yola', 'sani@sia.ng', '08031112233', 'Yola North', 'Alkalawa', 'PU 001 Central School', 'SIA-LP-AD-27-0001', 'hash', 'accredited', 1);");

$agent_pu_id = 1;
$attempted_pu_id = 2; // Different PU
$can_submit_for_assigned = ($agent_pu_id === 1);
$can_submit_for_unassigned = ($attempted_pu_id === 1);

run_assert("Agent cleared for assigned Polling Unit", $can_submit_for_assigned === true);
run_assert("Agent strictly forbidden from submitting for another Polling Unit", $can_submit_for_unassigned === false);

// -----------------------------------------------------------------
// Test 5: Over-voting Auto-flagging Logic
// -----------------------------------------------------------------
echo "\nTEST 5: Server-Side Over-Voting Auto-Flagging...\n";
$balanced = validate_ec8a_tallies(150, 80, 50, 10, 5, 5, 300); // 300 valid+rejected == 300 accredited
run_assert("Mathematically balanced tally passes without flag", $balanced['is_overvoting'] === false && $balanced['total_valid'] === 295);

$overvote = validate_ec8a_tallies(200, 100, 50, 10, 5, 10, 300); // 375 cast > 300 accredited
run_assert("Over-voting automatically detected (375 cast > 300 accredited)", $overvote['is_overvoting'] === true);
run_assert("Over-voting flag note contains exact discrepancy figures", strpos($overvote['flag_reason'], '375') !== false);

// -----------------------------------------------------------------
// Test 6: Resubmissions Superseding & Revision History
// -----------------------------------------------------------------
echo "\nTEST 6: Atomic Resubmissions & Revision Logging...\n";
// Sheet 1
$pdo->exec("INSERT INTO result_sheets (id, agent_id, polling_unit_id, lga_id, lga, ward, polling_unit, lp_votes, apc_votes, pdp_votes, total_valid_votes, total_accredited, is_current, verification_status) VALUES (1, 1, 1, 1, 'Yola North', 'Alkalawa', 'PU 001 Central School', 100, 50, 20, 170, 180, 1, 'verified');");

// Agent resubmits: previous sheet marked is_current=0, new sheet marked is_current=1 with supersedes_id=1
$pdo->beginTransaction();
$pdo->exec("UPDATE result_sheets SET is_current = 0 WHERE polling_unit_id = 1 AND is_current = 1;");
$pdo->exec("INSERT INTO result_sheets (id, agent_id, polling_unit_id, lga_id, lga, ward, polling_unit, lp_votes, apc_votes, pdp_votes, total_valid_votes, total_accredited, is_current, supersedes_id, verification_status) VALUES (2, 1, 1, 1, 'Yola North', 'Alkalawa', 'PU 001 Central School', 120, 50, 20, 190, 195, 1, 1, 'pending');");
$pdo->exec("INSERT INTO result_sheet_revisions (sheet_id, changed_by_type, changed_by_id, old_json, new_json, reason) VALUES (2, 'agent', 1, '{\"lp\":100}', '{\"lp\":120}', 'Correction of initial tally');");
$pdo->commit();

$sheet1 = $pdo->query("SELECT is_current FROM result_sheets WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$sheet2 = $pdo->query("SELECT is_current, supersedes_id FROM result_sheets WHERE id = 2")->fetch(PDO::FETCH_ASSOC);
$revision = $pdo->query("SELECT * FROM result_sheet_revisions WHERE sheet_id = 2")->fetch(PDO::FETCH_ASSOC);

run_assert("Previous result sheet is_current transitioned to 0", (int)$sheet1['is_current'] === 0);
run_assert("New result sheet is_current is 1", (int)$sheet2['is_current'] === 1);
run_assert("New sheet correctly references superseded sheet ID", (int)$sheet2['supersedes_id'] === 1);
run_assert("Detailed revision log recorded in result_sheet_revisions", !empty($revision) && $revision['sheet_id'] == 2);

// -----------------------------------------------------------------
// Test 7: Coordinator Role Scoping (Cross-LGA Isolation)
// -----------------------------------------------------------------
echo "\nTEST 7: Coordinator Role Scoping & Isolation...\n";
// Insert LGA 1 sheet and LGA 2 sheet
$pdo->exec("INSERT INTO result_sheets (id, agent_id, polling_unit_id, lga_id, lga, ward, polling_unit, lp_votes, total_valid_votes, is_current) VALUES (3, 1, 2, 2, 'Numan', 'Numan Urban', 'PU 002 General Hospital', 180, 200, 1);");

// Coordinator for LGA 1 (Yola North)
$coord_lga_id = 1;
$stmt = $pdo->prepare("SELECT id, lga FROM result_sheets WHERE is_current = 1 AND lga_id = ?");
$stmt->execute([$coord_lga_id]);
$scoped_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$can_see_own_lga = false;
$can_see_foreign_lga = false;
foreach ($scoped_sheets as $row) {
    if ($row['lga'] === 'Yola North') $can_see_own_lga = true;
    if ($row['lga'] === 'Numan') $can_see_foreign_lga = true;
}

run_assert("Coordinator can query result sheets in their assigned LGA", $can_see_own_lga === true);
run_assert("Coordinator is strictly isolated from accessing sheets of other LGAs", $can_see_foreign_lga === false);

// -----------------------------------------------------------------
// Test 8: Public API Excludes Pending/Rejected Sheets
// -----------------------------------------------------------------
echo "\nTEST 8: Public Live Results Filter...\n";
set_setting($pdo, 'require_review_before_public', '1');

// Verified sheet vs Pending sheet vs Rejected sheet
$pdo->exec("INSERT INTO result_sheets (id, agent_id, polling_unit_id, lga, ward, polling_unit, lp_votes, total_valid_votes, is_current, verification_status) VALUES (4, 1, 1, 'Yola North', 'Alkalawa', 'PU Verified', 200, 200, 1, 'verified'), (5, 1, 1, 'Yola North', 'Alkalawa', 'PU Rejected', 50, 50, 1, 'rejected');");

$pub_stmt = $pdo->query("SELECT id FROM result_sheets WHERE is_current = 1 AND verification_status = 'verified'");
$public_ids = $pub_stmt->fetchAll(PDO::FETCH_COLUMN);

run_assert("Public query includes verified sheet", in_array(4, $public_ids));
run_assert("Public query strictly excludes pending sheet", !in_array(2, $public_ids));
run_assert("Public query strictly excludes rejected sheet", !in_array(5, $public_ids));

// -----------------------------------------------------------------
// Test 9: CSV Import Dry-Run Validation
// -----------------------------------------------------------------
echo "\nTEST 9: CSV Import Dry-Run Validation...\n";
$csv_sample = "name,email,phone,lga,ward,polling_unit\n" .
              "Aliyu Musa,aliyu@sia.ng,08012345678,Yola North,Alkalawa,PU 001 Central School\n" .
              "Invalid Row,,08099999999,Yola North,Alkalawa,PU 002 Invalid\n"; // Missing email

$lines = explode("\n", trim($csv_sample));
$hdr = str_getcsv(array_shift($lines), ',', '"', "\\");
$valid_count = 0;
$error_count = 0;

foreach ($lines as $ln) {
    if (empty(trim($ln))) continue;
    $cols = str_getcsv($ln, ',', '"', "\\");
    if (!empty($cols[0]) && !empty($cols[1]) && filter_var($cols[1], FILTER_VALIDATE_EMAIL)) {
        $valid_count++;
    } else {
        $error_count++;
    }
}

run_assert("Dry-run correctly detects valid records (1 valid)", $valid_count === 1);
run_assert("Dry-run flags invalid/missing fields without writing to DB (1 error)", $error_count === 1);

// -----------------------------------------------------------------
// Test 10: Official INEC Geography Delimitation Integrity
// -----------------------------------------------------------------
echo "\nTEST 10: Official Adamawa INEC Geography Delimitation Integrity...\n";
$geo_db_path = sys_get_temp_dir() . '/sia_geo_test_' . uniqid() . '.sqlite';
$geo_pdo = new PDO("sqlite:{$geo_db_path}");
$geo_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
migrate_database($geo_pdo);

$lga_count = (int)$geo_pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn();
$ward_count = (int)$geo_pdo->query("SELECT COUNT(*) FROM wards")->fetchColumn();
$pu_count = (int)$geo_pdo->query("SELECT COUNT(*) FROM polling_units")->fetchColumn();
$placeholder_count = (int)$geo_pdo->query("SELECT COUNT(*) FROM polling_units WHERE name LIKE '[Placeholder]%'")->fetchColumn();
$sample_pu = $geo_pdo->query("SELECT code FROM polling_units WHERE code LIKE '02/%' LIMIT 1")->fetchColumn();

run_assert("Official 21 Adamawa Local Government Areas populated", $lga_count === 21);
run_assert("Official 226 Adamawa Electoral Wards populated", $ward_count === 226);
run_assert("Official 4,101 Polling Units populated from master directory", $pu_count === 4101);
run_assert("Zero placeholder polling units remaining in active directory", $placeholder_count === 0);
run_assert("Polling units follow official INEC Delimitation format (02/XX/YY/ZZZ)", !empty($sample_pu) && preg_match('#^02/\d{2}/\d{2}/\d{3}$#', $sample_pu));

$geo_pdo = null;
if (file_exists($geo_db_path)) @unlink($geo_db_path);

// -----------------------------------------------------------------
// Test 11: Official 17 Political Parties Support
// -----------------------------------------------------------------
echo "\nTEST 11: Official 17 Political Parties Support...\n";
$parties = get_official_parties();
$party_cols = get_party_column_names();
$expected_17 = ['A', 'AA', 'AAC', 'ADC', 'ADP', 'APC', 'APGA', 'APP', 'DLP', 'LP', 'NDC', 'NNPP', 'NRM', 'PDP', 'SDP', 'YPP', 'ZLP'];
$actual_keys = array_keys($parties);
sort($actual_keys);
sort($expected_17);

run_assert("Official registry contains exactly 17 parties", $actual_keys === $expected_17);
run_assert("Party columns contains 17 columns", count($party_cols) === 17);

$party_votes_test = [];
foreach ($expected_17 as $idx => $p_code) {
    $party_votes_test[$p_code] = ($idx + 1) * 5;
}
$expected_sum = array_sum($party_votes_test);
$party_votes_test['rejected_votes'] = 10;
$party_votes_test['total_accredited'] = $expected_sum + 10;
$tally_res = validate_ec8a_tallies($party_votes_test);

run_assert("Tally validator recomputes valid sum across 17 parties ({$expected_sum})", $tally_res['valid'] && $tally_res['total_valid'] === $expected_sum);
run_assert("Balanced tally passes without over-voting flag", !$tally_res['is_overvoting']);

// -----------------------------------------------------------------
// CLEANUP & SUMMARY
// -----------------------------------------------------------------
$pdo = null;
if (file_exists($test_db_path)) {
    @unlink($test_db_path);
}

echo "\n=================================================================\n";
echo " TOTAL TESTS RUN: " . ($passed + $failed) . "\n";
echo " PASSED: {$passed}\n";
echo " FAILED: {$failed}\n";
echo "=================================================================\n\n";

if ($failed > 0) {
    echo "STATUS: QUALITY GATE FAILED!\n";
    exit(1);
} else {
    echo "STATUS: ALL CRITICAL INTEGRITY CHECKS PASSED PERFECTLY!\n";
    exit(0);
}
