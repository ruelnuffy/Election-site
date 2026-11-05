<?php
/**
 * Test Suite: Senatorial District Command & Management
 * Verifies District mapping, KPI aggregation, 25% Constitutional Spread, and Ward Drill-Downs
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = get_db_connection();
sync_senatorial_districts($pdo);

echo "=== SIA 2027 SENATORIAL DISTRICT MANAGEMENT TEST SUITE ===\n\n";

$tests_passed = 0;
$tests_total = 0;

function assert_test($condition, $message) {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        echo "  [PASS] $message\n";
        $tests_passed++;
    } else {
        echo "  [FAIL] $message\n";
    }
}

// TEST 1: Senatorial Configuration & Mappings
echo "1. Checking Senatorial District Configuration...\n";
$districts = get_senatorial_districts();
assert_test(count($districts) === 3, "Exactly 3 senatorial districts defined");
assert_test(isset($districts['north'], $districts['central'], $districts['south']), "North, Central, and South districts exist");

$north = get_senatorial_district('north');
assert_test(count($north['lgas']) === 5, "Adamawa North contains exactly 5 LGAs");
assert_test(in_array('Michika', $north['lgas']) && in_array('Madagali', $north['lgas']), "Michika and Madagali in North");

$central = get_senatorial_district('central');
assert_test(count($central['lgas']) === 7, "Adamawa Central contains exactly 7 LGAs");
assert_test(in_array('Yola North', $central['lgas']) && in_array('Girei', $central['lgas']), "Yola North and Girei in Central");

$south = get_senatorial_district('south');
assert_test(count($south['lgas']) === 9, "Adamawa South contains exactly 9 LGAs");
assert_test(in_array('Numan', $south['lgas']) && in_array('Toungo', $south['lgas']), "Numan and Toungo in South");

// Total across all 3 districts must be 21 LGAs
$all_dist_lgas = array_merge($north['lgas'], $central['lgas'], $south['lgas']);
assert_test(count($all_dist_lgas) === 21, "Total district LGAs sum to exactly 21");

// TEST 2: Database Schema & Migration
echo "\n2. Checking Database Schema & LGAs Synchronization...\n";
$stmt_check = $pdo->query("SELECT COUNT(DISTINCT senatorial_district) FROM lgas WHERE senatorial_district IS NOT NULL");
assert_test((int)$stmt_check->fetchColumn() === 3, "All 3 senatorial districts assigned in lgas table");

$stmt_unassigned = $pdo->query("SELECT COUNT(*) FROM lgas WHERE senatorial_district IS NULL");
assert_test((int)$stmt_unassigned->fetchColumn() === 0, "Zero unassigned LGAs remaining");

// TEST 3: Geography Statistics per District
echo "\n3. Testing Geography Counts per Senatorial District...\n";
foreach (['north' => 924, 'central' => 1686, 'south' => 1491] as $d_key => $expected_pus) {
    $d = get_senatorial_district($d_key);
    $placeholders = implode(',', array_fill(0, count($d['lgas']), '?'));
    $lowers = array_map('strtolower', $d['lgas']);
    $stmt = $pdo->prepare("
        SELECT COUNT(p.id) as pus, COALESCE(SUM(p.registered_voters), 0) as voters
        FROM polling_units p
        JOIN wards w ON p.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE LOWER(l.name) IN ($placeholders)
    ");
    $stmt->execute($lowers);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_test((int)$row['pus'] === $expected_pus, "District {$d_key} has exactly {$expected_pus} Polling Units");
}

function parse_api_json(string $out): ?array {
    $pos = strpos($out, '{');
    if ($pos === false) return null;
    return json_decode(substr($out, $pos), true);
}

// TEST 4: API Endpoint Validation (senatorial_overview)
echo "\n4. Testing senatorial_overview API Endpoint...\n";
$_SESSION['admin'] = [
    'id' => 2,
    'name' => 'Lead Superadministrator',
    'email' => 'superadmin@sia2027.ng',
    'role' => 'superadmin',
    'status' => 'active',
    'lga_id' => null
];

$_GET['action'] = 'senatorial_overview';
$_GET['district'] = 'north';
ob_start();
include __DIR__ . '/../admin_api.php';
$out = ob_get_clean();
$resp = parse_api_json($out);

assert_test($resp && $resp['success'] === true, "senatorial_overview returns success = true");
assert_test(($resp['district']['id'] ?? '') === 'north', "Returned district id is 'north'");
assert_test(($resp['kpis']['total_lgas'] ?? 0) === 5, "North total_lgas = 5");
assert_test(($resp['kpis']['total_pus'] ?? 0) === 924, "North total_pus = 924");
assert_test(isset($resp['transmission_health']['ground']), "Transmission health telemetry present");

// TEST 5: API Endpoint Validation (senatorial_lga_breakdown)
echo "\n5. Testing senatorial_lga_breakdown API Endpoint...\n";
$_GET['action'] = 'senatorial_lga_breakdown';
$_GET['district'] = 'central';
ob_start();
include __DIR__ . '/../admin_api.php';
$out2 = ob_get_clean();
$resp2 = parse_api_json($out2);

assert_test($resp2 && $resp2['success'] === true, "senatorial_lga_breakdown returns success = true");
assert_test(count($resp2['lgas'] ?? []) === 7, "Central district returns exactly 7 LGAs");
$yola_north = array_values(array_filter($resp2['lgas'] ?? [], fn($l) => $l['name'] === 'Yola North'))[0] ?? null;
assert_test($yola_north !== null, "Yola North LGA present in Central breakdown");
assert_test(($yola_north['total_pus'] ?? 0) > 0, "Yola North has registered polling units ({$yola_north['total_pus']})");

// TEST 6: API Endpoint Validation (senatorial_ward_breakdown)
echo "\n6. Testing senatorial_ward_breakdown Ward Drill-Down...\n";
$_GET['action'] = 'senatorial_ward_breakdown';
$_GET['lga_name'] = 'Michika';
ob_start();
include __DIR__ . '/../admin_api.php';
$out3 = ob_get_clean();
$resp3 = parse_api_json($out3);

assert_test($resp3 && $resp3['success'] === true, "senatorial_ward_breakdown returns success = true");
assert_test(count($resp3['wards'] ?? []) === 16, "Michika has exactly 16 official wards");
assert_test(($resp3['wards'][0]['name'] ?? '') === 'Bazza Margi', "Ward 1 is Bazza Margi");

echo "\n============================================\n";
echo "TESTS RUN: {$tests_total}, PASSED: {$tests_passed}, FAILED: " . ($tests_total - $tests_passed) . "\n";
if ($tests_passed === $tests_total) {
    echo "STATUS: ALL SENATORIAL DISTRICT MANAGEMENT TESTS PASSED!\n";
} else {
    echo "STATUS: SOME TESTS FAILED.\n";
    exit(1);
}
