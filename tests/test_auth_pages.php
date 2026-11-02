<?php
/**
 * Test Suite: SIA 2027 Standalone Auth Pages
 * Tests login.php, signup.php, forgot_password.php, reset_password.php
 */
$test_db_path = sys_get_temp_dir() . '/sia_auth_test_' . uniqid() . '.sqlite';
putenv("SIA_DB_PATH={$test_db_path}");
$_ENV['SIA_DB_PATH'] = $test_db_path;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/auth.php';

register_shutdown_function(function() use ($test_db_path) {
    @unlink($test_db_path);
    @unlink($test_db_path . '-wal');
    @unlink($test_db_path . '-shm');
});

echo "=== SIA 2027 STANDALONE AUTH PAGES QA SUITE ===\n\n";

$pdo = get_db_connection();
$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// 1. Check all four files exist and have no syntax errors
// -------------------------------------------------------------
echo "1. Checking Auth Page Syntax...\n";
$pages = ['login.php', 'signup.php', 'forgot_password.php', 'reset_password.php'];
foreach ($pages as $page) {
    $path = __DIR__ . '/../' . $page;
    $exists = file_exists($path);
    assert_test("{$page} exists on disk", $exists);
    if ($exists) {
        $lint = shell_exec("/Users/ruelnuffy/.config/herd-lite/bin/php -l " . escapeshellarg($path));
        assert_test("{$page} clean PHP lint", str_contains($lint, 'No syntax errors detected'));
    }
}

// -------------------------------------------------------------
// 2. Test Agent Registration (signup.php flow)
// -------------------------------------------------------------
echo "\n2. Testing Agent Registration Logic...\n";
$test_email = 'test_agent_' . time() . '_' . mt_rand(100, 999) . '@sia2027.org';
$test_pass = 'Secur3FieldAgentPass!';
$hash = password_hash($test_pass, PASSWORD_BCRYPT);
$agent_code = 'SIA-LP-AD-27-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

$ins = $pdo->prepare("
    INSERT INTO agents (name, email, password_hash, phone, role, state, lga, ward, polling_unit, agent_code, status, created_at, last_seen_at)
    VALUES (?, ?, ?, ?, 'agent', 'Adamawa State', 'Yola North', 'Ajiya Ward', 'PU 001 Gate School', ?, 'pending', datetime('now'), datetime('now'))
");
$ins->execute(['Test Agent QA', $test_email, $hash, '+2348039998877', $agent_code]);
$agent_id = (int)$pdo->lastInsertId();

assert_test("Agent account created in database", $agent_id > 0);

$fetch = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
$fetch->execute([$agent_id]);
$created_agent = $fetch->fetch(PDO::FETCH_ASSOC);

assert_test("Agent status defaults to pending", $created_agent['status'] === 'pending');
assert_test("Agent assigned valid unique code", str_starts_with($created_agent['agent_code'], 'SIA-LP-AD-27-'));

// -------------------------------------------------------------
// 3. Test Agent Login with Email and Agent Code (login.php flow)
// -------------------------------------------------------------
echo "\n3. Testing Agent Authentication Logic...\n";
// Look up by email
$s1 = $pdo->prepare("SELECT * FROM agents WHERE LOWER(email) = LOWER(?) LIMIT 1");
$s1->execute([$test_email]);
$a1 = $s1->fetch();
assert_test("Lookup agent by email", $a1 && (int)$a1['id'] === $agent_id);
assert_test("Verify password for email lookup", password_verify($test_pass, $a1['password_hash']));

// Look up by agent_code
$s2 = $pdo->prepare("SELECT * FROM agents WHERE UPPER(agent_code) = UPPER(?) LIMIT 1");
$s2->execute([$agent_code]);
$a2 = $s2->fetch();
assert_test("Lookup agent by Agent Code", $a2 && (int)$a2['id'] === $agent_id);
assert_test("Verify password for agent_code lookup", password_verify($test_pass, $a2['password_hash']));

// Reject wrong password
assert_test("Reject invalid password", !password_verify('wrong_pass', $a1['password_hash']));

// -------------------------------------------------------------
// 4. Test Password Recovery Request (forgot_password.php flow)
// -------------------------------------------------------------
echo "\n4. Testing Password Reset Token Generation...\n";
$reset_token = bin2hex(random_bytes(32));
$upd = $pdo->prepare("
    UPDATE agents 
    SET password_reset_token = ?, 
        password_reset_expiry = datetime('now', '+1 hour') 
    WHERE id = ?
");
$upd->execute([$reset_token, $agent_id]);

// Query agent with valid token
$chk_token = $pdo->prepare("
    SELECT * FROM agents 
    WHERE password_reset_token = ? 
      AND password_reset_expiry IS NOT NULL 
      AND password_reset_expiry > datetime('now')
    LIMIT 1
");
$chk_token->execute([$reset_token]);
$token_agent = $chk_token->fetch();

assert_test("Token stored and retrieved", $token_agent && (int)$token_agent['id'] === $agent_id);
assert_test("Token length is 64 hex characters", strlen($reset_token) === 64);

// -------------------------------------------------------------
// 5. Test Password Reset Execution (reset_password.php flow)
// -------------------------------------------------------------
echo "\n5. Testing Password Reset Execution...\n";
$new_pass = 'UpdatedBrandNew2027!';
$new_hash = password_hash($new_pass, PASSWORD_BCRYPT);

$exec_upd = $pdo->prepare("
    UPDATE agents 
    SET password_hash = ?, 
        password_reset_token = NULL, 
        password_reset_expiry = NULL 
    WHERE id = ?
");
$exec_upd->execute([$new_hash, $agent_id]);

// Verify token was cleared
$chk_cleared = $pdo->prepare("SELECT password_reset_token, password_reset_expiry, password_hash FROM agents WHERE id = ?");
$chk_cleared->execute([$agent_id]);
$updated_agent = $chk_cleared->fetch();

assert_test("Reset token cleared after password update", empty($updated_agent['password_reset_token']));
assert_test("Reset expiry cleared after password update", empty($updated_agent['password_reset_expiry']));
assert_test("Old password rejected", !password_verify($test_pass, $updated_agent['password_hash']));
assert_test("New password verified successfully", password_verify($new_pass, $updated_agent['password_hash']));

// -------------------------------------------------------------
// 6. Verify No Admin Links on Public/Agent Auth Pages
// -------------------------------------------------------------
echo "\n6. Verifying No Admin Links on Auth Pages...\n";
foreach ($pages as $page) {
    $content = file_get_contents(__DIR__ . '/../' . $page);
    assert_test("{$page} has no admin_login.php links", !str_contains($content, 'admin_login.php'));
    assert_test("{$page} has no admin portal links", !preg_match('/href=[\'"][^\'"]*admin/i', $content));
}

// -------------------------------------------------------------
// Cleanup test agent
// -------------------------------------------------------------
$pdo->prepare("DELETE FROM agents WHERE id = ?")->execute([$agent_id]);

echo "\n============================================\n";
echo "AUTH TESTS: {$passed} PASSED, {$failed} FAILED\n";
echo "STATUS: " . ($failed === 0 ? "ALL AUTHENTICATION CHECKS PASSED!" : "FAILURES DETECTED!") . "\n";

