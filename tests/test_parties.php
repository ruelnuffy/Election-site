<?php
/**
 * SIA 2027 Political Parties Test Suite
 * Validates full support for all 17 registered political parties:
 * A, AA, AAC, ADC, ADP, APC, APGA, APP, DLP, LP, NDC, NNPP, NRM, PDP, SDP, YPP, ZLP
 */

$test_db_path = sys_get_temp_dir() . '/sia_parties_test_' . uniqid() . '.sqlite';
putenv("SIA_DB_PATH={$test_db_path}");
$_ENV['SIA_DB_PATH'] = $test_db_path;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/validators.php';

register_shutdown_function(function() use ($test_db_path) {
    @unlink($test_db_path);
    @unlink($test_db_path . '-wal');
    @unlink($test_db_path . '-shm');
});

$expected_parties = [
    'A', 'AA', 'AAC', 'ADC', 'ADP', 'APC', 'APGA', 'APP', 'DLP', 'LP',
    'NDC', 'NNPP', 'NRM', 'PDP', 'SDP', 'YPP', 'ZLP'
];

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $label, string &$msg = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label} - {$msg}\n";
    }
}

echo "=== 17 POLITICAL PARTIES INTEGRATION TEST ===\n\n";

// 1. Registry validation
echo "1. Testing Party Registry Configuration...\n";
$official = get_official_parties();
$party_keys = array_keys($official);
sort($party_keys);
$expected_sorted = $expected_parties;
sort($expected_sorted);

assert_test(
    $party_keys === $expected_sorted,
    "Official registry has exact 17 parties (" . count($official) . " found)"
);

$columns = get_party_column_names();
assert_test(
    count($columns) === 17,
    "Column names registry contains 17 columns"
);

assert_test(
    in_array('lp_votes', $columns, true) && in_array('a_votes', $columns, true) && in_array('zlp_votes', $columns, true),
    "Column names match expected lowercase convention"
);

// 2. Server-side Tally Validator with 17 parties
echo "\n2. Testing EC8A Tally Validator across 17 Parties...\n";
$vote_inputs = [];
$expected_valid = 0;
foreach ($expected_parties as $idx => $code) {
    $votes = ($idx + 1) * 10;
    $vote_inputs[$code] = $votes;
    $expected_valid += $votes;
}

$vote_inputs['rejected_votes'] = 15;
$vote_inputs['total_accredited'] = $expected_valid + 15;

$result = validate_ec8a_tallies($vote_inputs);
assert_test(
    $result['valid'] === true,
    "Validator accepts associative array of all 17 parties"
);
assert_test(
    $result['total_valid'] === $expected_valid,
    "Total valid is mathematically recomputed: {$result['total_valid']} == {$expected_valid}"
);
assert_test(
    $result['is_overvoting'] === false,
    "Balanced cast == accredited passes without over-voting flag"
);

// Test Overvoting detection across 17 parties
$vote_inputs['total_accredited'] = $expected_valid; // cast is expected_valid + 15, so cast > accredited
$ov_result = validate_ec8a_tallies($vote_inputs);
assert_test(
    $ov_result['is_overvoting'] === true,
    "Over-voting accurately detected across 17 parties when Cast > Accredited"
);

// 3. Database Schema Verification in Memory / Live SQLite
echo "\n3. Testing Database Columns in schema...\n";
$pdo = get_db_connection();
$table_info = $pdo->query("PRAGMA table_info(result_sheets)")->fetchAll(PDO::FETCH_ASSOC);
$existing_cols = array_column($table_info, 'name');

$missing_cols = [];
foreach ($columns as $c) {
    if (!in_array($c, $existing_cols, true)) {
        $missing_cols[] = $c;
    }
}
$missing_str = implode(', ', $missing_cols);
assert_test(
    empty($missing_cols),
    "All 17 party columns exist in result_sheets SQLite table",
    $missing_str
);

// 4. Test Live Results API Cumulative Sum Query
echo "\n4. Testing Dynamic Cumulation across all 17 parties...\n";
$party_sum_parts = [];
foreach (get_official_parties() as $code => $p) {
    $col = $p['column'];
    $party_sum_parts[] = "COALESCE(SUM({$col}), 0) as total_{$col}";
}
$party_sums_sql = implode(", ", $party_sum_parts);
$totals = $pdo->query("SELECT {$party_sums_sql} FROM result_sheets WHERE is_current = 1")->fetch(PDO::FETCH_ASSOC);

$all_present = true;
foreach ($columns as $c) {
    if (!isset($totals['total_' . $c])) {
        $all_present = false;
        break;
    }
}
assert_test(
    $all_present,
    "Cumulative SQL aggregates all 17 party columns seamlessly"
);

echo "\n============================================\n";
echo "17-PARTY TESTS: {$passed} PASSED, {$failed} FAILED\n";
if ($failed === 0) {
    echo "STATUS: ALL 17 POLITICAL PARTIES FULLY INTEGRATED & VERIFIED!\n";
} else {
    echo "STATUS: ISSUES DETECTED\n";
    exit(1);
}

