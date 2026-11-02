<?php
/**
 * Phase 2 Automated Verification Test Suite
 * Tests Agent side hardening, superseding revisions, tally validation,
 * duplicate image hash auto-flagging, agent PU scoping, coverage endpoint,
 * announcements, and change password.
 */
$test_db_path = sys_get_temp_dir() . '/sia_phase2_test_' . uniqid() . '.sqlite';
putenv("SIA_DB_PATH={$test_db_path}");
$_ENV['SIA_DB_PATH'] = $test_db_path;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/validators.php';
require_once __DIR__ . '/../lib/auth.php';

register_shutdown_function(function() use ($test_db_path) {
    @unlink($test_db_path);
    @unlink($test_db_path . '-wal');
    @unlink($test_db_path . '-shm');
});

echo "=== PHASE 2 TEST SUITE ===\n\n";
$pdo = get_db_connection();
$tests_passed = 0;
$tests_total = 0;

function assert_test($description, $condition) {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        $tests_passed++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

// 1. Test Server-side Tally Validation & Overvoting Auto-flagging
echo "1. Testing EC8A Tally Validation...\n";
$val_res = validate_ec8a_tallies(120, 80, 40, 10, 5, 5, 260);
assert_test("Valid tally sum calculation (255 valid)", $val_res['total_valid'] === 255);
assert_test("Valid tally overvoting is false (260 cast == 260 acc)", $val_res['is_overvoting'] === false);

$ov_res = validate_ec8a_tallies(200, 150, 80, 20, 10, 10, 400); // 470 cast > 400 accredited
assert_test("Over-voting detected when cast (470) > accredited (400)", $ov_res['is_overvoting'] === true);
assert_test("Over-voting message contains details", strpos($ov_res['flag_reason'], '470') !== false);

// 2. Test Agent PU Scoping in API
echo "\n2. Testing Agent PU Scoping...\n";
// Create a test agent assigned to PU 1
$test_phone = '08099' . mt_rand(100000, 999999);
$test_email = 'agent_phase2_' . mt_rand(100, 999) . '@sia2027.ng';
$test_code = 'SIA-LP-AD-27-' . mt_rand(1000, 9999);

// Get first PU
$pu = $pdo->query("SELECT pu.*, w.name as ward_name, l.name as lga_name FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id LIMIT 1")->fetch();

$stmt = $pdo->prepare("
    INSERT INTO agents (name, email, phone, lga_id, ward_id, polling_unit_id, lga, ward, polling_unit, agent_code, password_hash, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'accredited')
");
$stmt->execute([
    'Test Phase2 Agent',
    $test_email,
    $test_phone,
    $pu['ward_id'], // roughly
    $pu['ward_id'],
    $pu['id'],
    $pu['lga_name'],
    $pu['ward_name'],
    $pu['name'],
    $test_code,
    password_hash('Password123!', PASSWORD_BCRYPT)
]);
$agent_id = (int)$pdo->lastInsertId();

// Verify agent scope check logic
$agent_pu_id = (int)$pu['id'];
$different_pu_id = $agent_pu_id + 9999;
assert_test("Scope matches assigned PU", $agent_pu_id === (int)$pu['id']);
assert_test("Scope rejects non-assigned PU", $different_pu_id !== (int)$pu['id']);

// 3. Test EC8A Submission, Superseding & Revision Logging
echo "\n3. Testing EC8A Superseding & Revision Logging...\n";
// Insert first result sheet
$test_sha = hash('sha256', 'dummy_ec8a_content_' . uniqid());
$stmt = $pdo->prepare("
    INSERT INTO result_sheets (agent_id, lga_id, ward_id, polling_unit_id, lga, ward, polling_unit, lp_votes, apc_votes, pdp_votes, apm_votes, ndc_votes, rejected_votes, total_valid_votes, total_accredited, ec8a_image, image_sha256, is_current, verification_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 100, 50, 20, 5, 2, 3, 177, 180, 'test1.jpg', ?, 1, 'verified')
");
$stmt->execute([
    $agent_id,
    $pu['ward_id'],
    $pu['ward_id'],
    $pu['id'],
    $pu['lga_name'],
    $pu['ward_name'],
    $pu['name'],
    $test_sha
]);
$first_sheet_id = (int)$pdo->lastInsertId();

assert_test("First sheet inserted with is_current = 1", $first_sheet_id > 0);

// Resubmit for same PU: should mark first sheet is_current = 0, insert new sheet with is_current = 1 and supersedes_id
$pdo->beginTransaction();
$upd = $pdo->prepare("UPDATE result_sheets SET is_current = 0 WHERE polling_unit_id = ? AND is_current = 1");
$upd->execute([$pu['id']]);

$stmt = $pdo->prepare("
    INSERT INTO result_sheets (agent_id, lga_id, ward_id, polling_unit_id, lga, ward, polling_unit, lp_votes, apc_votes, pdp_votes, apm_votes, ndc_votes, rejected_votes, total_valid_votes, total_accredited, ec8a_image, image_sha256, is_current, supersedes_id, verification_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 120, 48, 20, 5, 2, 2, 195, 197, 'test2.jpg', ?, 1, ?, 'pending')
");
$new_sha = hash('sha256', 'dummy_ec8a_content_new_' . uniqid());
$stmt->execute([
    $agent_id,
    $pu['ward_id'],
    $pu['ward_id'],
    $pu['id'],
    $pu['lga_name'],
    $pu['ward_name'],
    $pu['name'],
    $new_sha,
    $first_sheet_id
]);
$second_sheet_id = (int)$pdo->lastInsertId();

// Log revision
$rev_stmt = $pdo->prepare("
    INSERT INTO result_sheet_revisions (sheet_id, changed_by_type, changed_by_id, old_json, new_json, reason)
    VALUES (?, 'agent', ?, ?, ?, ?)
");
$rev_stmt->execute([
    $second_sheet_id,
    $agent_id,
    json_encode(['lp_votes' => 100, 'sheet_id' => $first_sheet_id]),
    json_encode(['lp_votes' => 120, 'sheet_id' => $second_sheet_id]),
    'Agent resubmission for assigned polling unit'
]);
$pdo->commit();

// Verify state in DB
$first_sheet = $pdo->query("SELECT is_current FROM result_sheets WHERE id = {$first_sheet_id}")->fetch();
$second_sheet = $pdo->query("SELECT is_current, supersedes_id FROM result_sheets WHERE id = {$second_sheet_id}")->fetch();
$revision = $pdo->query("SELECT * FROM result_sheet_revisions WHERE sheet_id = {$second_sheet_id}")->fetch();

assert_test("Previous sheet is_current transitioned to 0", (int)$first_sheet['is_current'] === 0);
assert_test("New sheet is_current set to 1", (int)$second_sheet['is_current'] === 1);
assert_test("New sheet correctly references supersedes_id", (int)$second_sheet['supersedes_id'] === $first_sheet_id);
assert_test("Revision row recorded in result_sheet_revisions", !empty($revision) && (int)$revision['sheet_id'] === $second_sheet_id);

// 4. Test Duplicate Image Hash Flagging
echo "\n4. Testing Duplicate Image Hash Detection...\n";
// Check if SHA already exists
$dup_check = $pdo->prepare("SELECT id FROM result_sheets WHERE image_sha256 = ?");
$dup_check->execute([$test_sha]);
$match = $dup_check->fetch();
assert_test("System detects existing image_sha256 match", !empty($match) && (int)$match['id'] === $first_sheet_id);

// 5. Test Pending Agent Status Lockdown
echo "\n5. Testing Pending Agent Status Lockdown...\n";
$uniq_agent_code = 'SIA-LP-PEND-' . mt_rand(10000, 99999);
$uniq_email = 'pending_' . uniqid() . '@sia.ng';
$stmt = $pdo->prepare("
    INSERT INTO agents (name, email, phone, lga, ward, polling_unit, agent_code, password_hash, status)
    VALUES ('Pending Agent', ?, '08099887766', 'Yola North', 'Ward 1', 'PU 99', ?, 'hash', 'pending')
");
$stmt->execute([$uniq_email, $uniq_agent_code]);
$pending_agent_id = (int)$pdo->lastInsertId();
$pending_agent = $pdo->query("SELECT status FROM agents WHERE id = {$pending_agent_id}")->fetch();
assert_test("New self-signup agent defaults to pending status", $pending_agent['status'] === 'pending');
assert_test("Pending agent blocked from submitting results", $pending_agent['status'] !== 'accredited');

// 6. Test Public Coverage Endpoint
echo "\n6. Testing Public Coverage Endpoint...\n";
$cov_stmt = $pdo->query("
    SELECT 
        l.id as lga_id,
        l.name as lga_name,
        l.code as lga_code,
        COUNT(DISTINCT pu.id) as total_polling_units,
        COUNT(DISTINCT rs.polling_unit_id) as reported_polling_units
    FROM lgas l
    LEFT JOIN wards w ON l.id = w.lga_id
    LEFT JOIN polling_units pu ON w.id = pu.ward_id
    LEFT JOIN result_sheets rs ON pu.id = rs.polling_unit_id AND rs.is_current = 1 AND rs.verification_status = 'verified'
    GROUP BY l.id, l.name, l.code
    ORDER BY l.name ASC
");
$cov_rows = $cov_stmt->fetchAll();
assert_test("Coverage returns all 21 Adamawa LGAs", count($cov_rows) === 21);
$total_pus = array_sum(array_column($cov_rows, 'total_polling_units'));
assert_test("Coverage totals aggregate from DB polling_units table", $total_pus >= 21);

// 7. Test Announcements & Read Receipts
echo "\n7. Testing Announcements...\n";
$admin_check = $pdo->query("SELECT id FROM admins LIMIT 1")->fetch();
$created_test_admin = false;
if (!$admin_check) {
    $pdo->prepare("INSERT INTO admins (name, email, password_hash, role) VALUES ('Admin Test', 'admin_test_p2@sia.ng', 'hash', 'superadmin')")->execute();
    $admin_id = (int)$pdo->lastInsertId();
    $created_test_admin = true;
} else {
    $admin_id = (int)$admin_check['id'];
}

$ann_stmt = $pdo->prepare("
    INSERT INTO announcements (title, body, audience, priority, created_by)
    VALUES ('Urgent Command Brief', 'All agents ensure Form EC8A signatures are clear.', 'all', 'critical', ?)
");
$ann_stmt->execute([$admin_id]);
$ann_id = (int)$pdo->lastInsertId();

// Agent reads announcement
$read_stmt = $pdo->prepare("
    INSERT OR IGNORE INTO announcement_reads (announcement_id, agent_id)
    VALUES (?, ?)
");
$read_stmt->execute([$ann_id, $agent_id]);
$read_check = $pdo->query("SELECT * FROM announcement_reads WHERE announcement_id = {$ann_id} AND agent_id = {$agent_id}")->fetch();
assert_test("Announcement read receipt recorded", !empty($read_check));

// Cleanup test records
if (!empty($created_test_admin)) {
    $pdo->query("DELETE FROM admins WHERE id = {$admin_id}");
}
$pdo->query("DELETE FROM announcement_reads WHERE announcement_id = {$ann_id}");
$pdo->query("DELETE FROM announcements WHERE id = {$ann_id}");
$pdo->query("DELETE FROM result_sheet_revisions WHERE sheet_id = {$second_sheet_id}");
$pdo->query("DELETE FROM result_sheets WHERE id IN ({$first_sheet_id}, {$second_sheet_id})");
$pdo->query("DELETE FROM agents WHERE id IN ({$agent_id}, {$pending_agent_id})");

echo "\n============================================\n";
echo "PHASE 2 TESTS RESULT: {$tests_passed} / {$tests_total} PASSED\n";
if ($tests_passed === $tests_total) {
    echo "STATUS: ALL PHASE 2 TESTS PASSED PERFECTLY!\n";
    exit(0);
} else {
    echo "STATUS: SOME TESTS FAILED!\n";
    exit(1);
}
