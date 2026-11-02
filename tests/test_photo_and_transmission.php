<?php
/**
 * SIA 2027 QA Test Suite - Agent Facial Photo & Transmission Ground Status
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/validators.php';

$test_db_file = sys_get_temp_dir() . '/sia_test_photo_trans_' . uniqid() . '.sqlite';
$pdo = new PDO('sqlite:' . $test_db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

init_database_schema($pdo);

echo "=== SIA 2027 AGENT PHOTO & TRANSMISSION STATUS TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;

function assert_test($label, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

// 1. Database Column Checks
echo "1. Checking Schema Columns...\n";
$agent_cols = array_column($pdo->query("PRAGMA table_info(agents)")->fetchAll(), 'name');
assert_test("agents table has 'photo' column", in_array('photo', $agent_cols, true));

$sheet_cols = array_column($pdo->query("PRAGMA table_info(result_sheets)")->fetchAll(), 'name');
assert_test("result_sheets table has 'transmission_status' column", in_array('transmission_status', $sheet_cols, true));

// 2. Testing Agent Registration with Photo Path
echo "\n2. Testing Agent Face Photo Storage...\n";
$agent_email = 'photo_agent_' . uniqid() . '@example.com';
$mock_photo_path = 'uploads/agents/agent_face_2027_test.jpg';

$stmt = $pdo->prepare("
    INSERT INTO agents (
        name, email, password_hash, phone, photo, role, state, lga, ward, polling_unit, agent_code, status
    ) VALUES (
        'Test Agent Photo', ?, 'hash123', '08031234567', ?, 'agent', 'Adamawa State', 'Yola North', 'Ajiya', 'PU 001 Test', 'SIA-LP-AD-27-9999', 'accredited'
    )
");
$stmt->execute([$agent_email, $mock_photo_path]);
$agent_id = (int)$pdo->lastInsertId();

$saved_agent = $pdo->query("SELECT * FROM agents WHERE id = {$agent_id}")->fetch();
assert_test("Agent record saved with photo path", $saved_agent['photo'] === $mock_photo_path);
assert_test("Agent name and code properly preserved", $saved_agent['agent_code'] === 'SIA-LP-AD-27-9999');

// 3. Testing Form EC8A Transmission Status Dropdown Options
echo "\n3. Testing Form EC8A Transmission Ground Status Dropdown...\n";
$valid_statuses = [
    'Reporting on PU ground',
    'Took me 10 mins to get internet',
    'I have to travel for 30 mins to get internet'
];

foreach ($valid_statuses as $idx => $status) {
    $pu_name = "PU " . ($idx + 1) . " Ground Test";
    $ins = $pdo->prepare("
        INSERT INTO result_sheets (
            agent_id, lga, ward, polling_unit, transmission_status,
            lp_votes, apc_votes, pdp_votes, total_valid_votes, total_accredited, total_accredited_voters,
            verification_status, is_current, source
        ) VALUES (
            ?, 'Yola North', 'Ajiya', ?, ?,
            120, 80, 50, 250, 250, 250,
            'pending', 1, 'agent'
        )
    ");
    $ins->execute([$agent_id, $pu_name, $status]);
    $sheet_id = (int)$pdo->lastInsertId();

    $saved_sheet = $pdo->query("SELECT * FROM result_sheets WHERE id = {$sheet_id}")->fetch();
    assert_test("Result sheet #{$sheet_id} recorded with status '{$status}'", $saved_sheet['transmission_status'] === $status);
}

// 4. Testing Review Queue Query Includes Transmission Status
echo "\n4. Testing Review Queue Query Telemetry...\n";
$q_stmt = $pdo->query("
    SELECT rs.*, a.name as agent_name, a.photo as agent_photo
    FROM result_sheets rs
    LEFT JOIN agents a ON rs.agent_id = a.id
    WHERE rs.is_current = 1
    ORDER BY rs.id DESC
");
$records = $q_stmt->fetchAll();
assert_test("Review queue retrieves records with agent_photo", !empty($records[0]['agent_photo']));
assert_test("Review queue retrieves transmission_status column", !empty($records[0]['transmission_status']));

@unlink($test_db_file);

echo "\n============================================\n";
echo "TESTS RUN: " . ($passed + $failed) . ", PASSED: {$passed}, FAILED: {$failed}\n";
if ($failed === 0) {
    echo "STATUS: ALL AGENT PHOTO & TRANSMISSION TESTS PASSED!\n";
    exit(0);
} else {
    echo "STATUS: SOME TESTS FAILED!\n";
    exit(1);
}

