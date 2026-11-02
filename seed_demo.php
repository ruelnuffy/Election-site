<?php
/**
 * SIA 2027 Election Intelligence & Party Agent Command Portal
 * Demo Simulation Generator
 * Usage: php seed_demo.php --demo
 * Creates demo admins, 30 agents, 100 realistic sheets & incidents for UI testing.
 * NEVER RUN AUTOMATICALLY IN PRODUCTION.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be executed from the command line.\n");
}

if (!in_array('--demo', $argv, true)) {
    echo "=================================================================\n";
    echo " SIA 2027 DEMO SEED GENERATOR\n";
    echo "=================================================================\n";
    echo "WARNING: This script populates demo administrators, agents, and\n";
    echo "simulation Form EC8A tally sheets for interface testing.\n\n";
    echo "To run this generator, execute:\n";
    echo "  php seed_demo.php --demo\n\n";
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/audit.php';

$pdo = get_db_connection();

echo "\n=================================================================\n";
echo " SIA 2027 DEMO GENERATOR INITIALIZING\n";
echo "=================================================================\n\n";

// Helper to generate secure random password
function generate_temp_password(int $length = 12): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// 1. Fetch Yola North LGA ID for Coordinator
$yola_north = $pdo->query("SELECT id FROM lgas WHERE name = 'Yola North' LIMIT 1")->fetch();
$yola_north_id = $yola_north ? (int)$yola_north['id'] : 1;

// 2. Generate Demo Admins
echo "Generating Demo Administrators...\n";
$demo_admins = [
    [
        'name' => 'Demo Superadministrator',
        'email' => 'superadmin@sia2027.ng',
        'role' => 'superadmin',
        'lga_id' => null,
        'password' => generate_temp_password(12)
    ],
    [
        'name' => 'Demo LGA Coordinator (Yola North)',
        'email' => 'coordinator.yola@sia2027.ng',
        'role' => 'coordinator',
        'lga_id' => $yola_north_id,
        'password' => generate_temp_password(12)
    ],
    [
        'name' => 'Demo Electoral Analyst',
        'email' => 'analyst@sia2027.ng',
        'role' => 'analyst',
        'lga_id' => null,
        'password' => generate_temp_password(12)
    ]
];

$ins_admin = $pdo->prepare("
    INSERT INTO admins (name, email, password_hash, role, lga_id, status, must_change_password, created_at)
    VALUES (?, ?, ?, ?, ?, 'active', 1, datetime('now'))
    ON CONFLICT(email) DO UPDATE SET 
        password_hash = excluded.password_hash,
        must_change_password = 1,
        status = 'active'
");

foreach ($demo_admins as $adm) {
    $hash = password_hash($adm['password'], PASSWORD_BCRYPT);
    $ins_admin->execute([$adm['name'], $adm['email'], $hash, $adm['role'], $adm['lga_id']]);
}

// 3. Fetch Polling Units for Agent Assignment
$pus = $pdo->query("
    SELECT pu.id as pu_id, pu.name as pu_name, w.id as ward_id, w.name as ward_name, l.id as lga_id, l.name as lga_name
    FROM polling_units pu
    JOIN wards w ON pu.ward_id = w.id
    JOIN lgas l ON w.lga_id = l.id
    ORDER BY l.name, w.name
    LIMIT 42
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($pus)) {
    die("Error: No polling units found in database. Initializing...\n");
}

echo "Generating 30 Demo Party Agents...\n";
$first_names = ['Ibrahim', 'Maryam', 'Abubakar', 'Fatima', 'Emmanuel', 'Grace', 'Usman', 'Bilikisu', 'Musa', 'Deborah', 'Bitrus', 'Amina'];
$last_names = ['Sani', 'Aliyu', 'Dahiru', 'Bwacha', 'Gidado', 'Yaro', 'Modibbo', 'Ribadu', 'Nuhu', 'Tukur', 'Buba', 'Babangida'];

$agent_ids = [];
$ins_agent = $pdo->prepare("
    INSERT INTO agents (name, email, phone, role, lga, ward, polling_unit, agent_code, password_hash, status, lga_id, ward_id, polling_unit_id, created_at)
    VALUES (?, ?, ?, 'agent', ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ON CONFLICT(email) DO NOTHING
");

$default_agent_hash = password_hash('AgentPassword2027!', PASSWORD_BCRYPT);

for ($i = 1; $i <= 30; $i++) {
    $fn = $first_names[array_rand($first_names)];
    $ln = $last_names[array_rand($last_names)];
    $name = "{$fn} {$ln}";
    $email = "agent{$i}.demo@sia2027.ng";
    $phone = "0803" . sprintf('%07d', mt_rand(1000000, 9999999));
    $code = sprintf('SIA-LP-AD-27-%04d', $i);
    $status = ($i <= 24) ? 'accredited' : (($i <= 28) ? 'pending' : 'suspended');

    $assigned_pu = $pus[($i - 1) % count($pus)];

    $ins_agent->execute([
        $name, $email, $phone,
        $assigned_pu['lga_name'], $assigned_pu['ward_name'], $assigned_pu['pu_name'],
        $code, $default_agent_hash, $status,
        $assigned_pu['lga_id'], $assigned_pu['ward_id'], $assigned_pu['pu_id']
    ]);

    $agent_id = (int)$pdo->lastInsertId();
    if ($agent_id === 0) {
        $ex = $pdo->prepare("SELECT id FROM agents WHERE email = ?");
        $ex->execute([$email]);
        $agent_id = (int)$ex->fetchColumn();
    }
    if ($agent_id > 0) $agent_ids[] = $agent_id;
}

// 4. Generate 100 Realistic Form EC8A Result Sheets across all 17 political parties
echo "Generating 100 Form EC8A Result Sheets across Adamawa State (17 Political Parties)...\n";
$official_party_cols = get_party_column_names();
$party_cols_str = implode(', ', $official_party_cols);
$party_q_marks = implode(', ', array_fill(0, count($official_party_cols), '?'));

$ins_sheet = $pdo->prepare("
    INSERT INTO result_sheets (
        agent_id, lga, ward, polling_unit, polling_unit_id, ec8a_image, image_sha256,
        {$party_cols_str}, rejected_votes,
        total_valid_votes, total_accredited, total_accredited_voters, notes, verification_status,
        reviewed_by, reviewed_at, review_note, is_current, source, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, {$party_q_marks}, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'agent', datetime('now', '-' || ? || ' hours'))
");

$created_sheets = 0;
for ($s = 1; $s <= 100; $s++) {
    $pu_index = ($s - 1) % count($pus);
    $target_pu = $pus[$pu_index];
    $agent_id = !empty($agent_ids) ? $agent_ids[($s - 1) % count($agent_ids)] : null;

    // Realistic vote tallies across all 17 registered political parties
    $votes_by_party = [
        'a_votes' => mt_rand(1, 10),
        'aa_votes' => mt_rand(1, 10),
        'aac_votes' => mt_rand(2, 12),
        'adc_votes' => mt_rand(5, 25),
        'adp_votes' => mt_rand(2, 15),
        'apc_votes' => mt_rand(60, 160),
        'apga_votes' => mt_rand(4, 20),
        'app_votes' => mt_rand(1, 10),
        'dlp_votes' => mt_rand(1, 8),
        'lp_votes' => mt_rand(120, 260),
        'ndc_votes' => mt_rand(1, 8),
        'nnpp_votes' => mt_rand(10, 45),
        'nrm_votes' => mt_rand(1, 10),
        'pdp_votes' => mt_rand(35, 110),
        'sdp_votes' => mt_rand(8, 35),
        'ypp_votes' => mt_rand(3, 18),
        'zlp_votes' => mt_rand(1, 8),
    ];

    $valid = array_sum($votes_by_party);
    $rejected = mt_rand(2, 12);
    $cast = $valid + $rejected;

    // Simulate over-voting in ~6 sheets for discrepancy detection testing
    $is_overvote = ($s % 16 === 0);
    if ($is_overvote) {
        $accredited = $cast - mt_rand(5, 25); // Cast exceeds accredited!
        $status = 'flagged';
        $review_note = "Over-voting detected: Valid + Rejected ({$cast}) exceeds Accredited ({$accredited})";
    } else {
        $accredited = $cast; // Balanced
        $status = ($s <= 65) ? 'verified' : (($s <= 88) ? 'pending' : 'flagged');
        $review_note = ($status === 'verified') ? 'Verified by central collation team' : null;
    }

    $img_name = "demo_ec8a_" . (($s % 10) + 1) . ".jpg";
    $sha = hash('sha256', "demo_sha_content_{$s}_{$img_name}");
    $hours_ago = mt_rand(1, 24);

    $party_values = [];
    foreach ($official_party_cols as $c) {
        $party_values[] = $votes_by_party[$c] ?? 0;
    }

    $sheet_params = array_merge(
        [
            $agent_id,
            $target_pu['lga_name'],
            $target_pu['ward_name'],
            $target_pu['pu_name'],
            $target_pu['pu_id'],
            $img_name,
            $sha
        ],
        $party_values,
        [
            $rejected,
            $valid,
            $accredited,
            $accredited,
            "Presiding officer concluded ballot count peacefully.",
            $status,
            ($status === 'verified') ? 1 : null,
            ($status === 'verified') ? date('Y-m-d H:i:s') : null,
            $review_note,
            $hours_ago
        ]
    );

    $ins_sheet->execute($sheet_params);
    $created_sheets++;
}

// 5. Generate 20 Realistic Incidents
echo "Generating 20 Field Incidents...\n";
$categories = [
    'BVAS Malfunction / Failure',
    'Late Arrival of INEC Officials',
    'Voter Turnout & Queue Control',
    'Peaceful Situation Report',
    'Transmission Delay'
];

$ins_inc = $pdo->prepare("
    INSERT INTO incidents (
        agent_id, lga, ward, polling_unit, title, description, category, severity, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-' || ? || ' hours'))
");

for ($j = 1; $j <= 20; $j++) {
    $target_pu = $pus[($j * 2) % count($pus)];
    $agent_id = !empty($agent_ids) ? $agent_ids[($j) % count($agent_ids)] : null;
    $cat = $categories[array_rand($categories)];
    $sev = ($j === 1 || $j === 7) ? 'critical' : (($j % 3 === 0) ? 'urgent' : 'normal');
    $stat = ($j <= 8) ? 'resolved' : (($j <= 15) ? 'in_progress' : 'received');

    $ins_inc->execute([
        $agent_id,
        $target_pu['lga_name'],
        $target_pu['ward_name'],
        $target_pu['pu_name'],
        "{$cat} reported at {$target_pu['pu_name']}",
        "Field agent confirmed {$cat}. Ground situation monitored by LGA collation patrol.",
        $cat,
        $sev,
        $stat,
        mt_rand(1, 20)
    ]);
}

// Print Demo Credentials ONCE to Terminal
echo "\n=================================================================\n";
echo " DEMO SEED COMPLETED SUCCESSFULLY!\n";
echo "=================================================================\n";
echo "THE FOLLOWING DEMO ADMINISTRATOR CREDENTIALS ARE PRINTED ONCE.\n";
echo "THEY ARE NOT STORED IN COMMITTED DOCS AND FORCE PASSWORD CHANGE.\n\n";

printf("%-20s | %-28s | %-16s | %-15s\n", "ROLE", "EMAIL", "PASSWORD", "STATUS");
echo str_repeat("-", 88) . "\n";
foreach ($demo_admins as $adm) {
    printf("%-20s | %-28s | %-16s | %-15s\n", $adm['role'], $adm['email'], $adm['password'], "Must Change");
}
echo str_repeat("-", 88) . "\n\n";

echo "Agent Default Password: AgentPassword2027!\n";
echo "Sample Agent Emails: agent1.demo@sia2027.ng to agent30.demo@sia2027.ng\n\n";
echo "Admin Portal URL: http://127.0.0.1:8080/admin_login.php\n";
echo "Agent Portal URL: http://127.0.0.1:8080/index.php#portal\n";
echo "=================================================================\n\n";

