<?php
/**
 * SIA 2027 Party Agent Portal - Central API Controller
 * Hardened with Role Scoping, Server-side Tally Verification, Duplicate Hash Flagging,
 * Superseding Resubmissions, and Complete Audit Logging.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$pdo = get_db_connection();
$action = $_POST['action'] ?? $_GET['action'] ?? 'live_results';
$ip = get_client_ip();

// =============================================================
// PUBLIC ENDPOINTS (Zero Authentication Required)
// =============================================================

// 1. LIVE RESULTS & AGGREGATION
if ($action === 'live_results' || $action === 'stats') {
    $require_review = get_setting($pdo, 'require_review_before_public', '1');
    $status_filter = ($require_review === '1') ? "verification_status = 'verified'" : "verification_status != 'rejected'";

    // Cumulative tallies from active current sheets dynamically for all registered parties
    $party_sum_parts = [];
    foreach (get_official_parties() as $code => $p) {
        $col = $p['column'];
        $party_sum_parts[] = "COALESCE(SUM({$col}), 0) as total_{$col}";
    }
    $party_sums_sql = implode(",\n            ", $party_sum_parts);

    $totals_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_sheets,
            {$party_sums_sql},
            COALESCE(SUM(rejected_votes), 0) as total_rejected,
            COALESCE(SUM(total_valid_votes), 0) as total_valid,
            COALESCE(SUM(total_accredited_voters), 0) as total_accredited
        FROM result_sheets
        WHERE is_current = 1 AND {$status_filter}
    ");
    $totals = $totals_stmt->fetch();

    $valid = max(0, (int)$totals['total_valid']);
    $total_accredited = (int)$totals['total_accredited'];
    $total_rejected = (int)$totals['total_rejected'];
    $calc_valid = max(1, $valid);

    $max_votes = -1;
    $leading_code = null;
    foreach (get_official_parties() as $code => $p) {
        $col = $p['column'];
        $votes = (int)($totals['total_' . $col] ?? 0);
        if ($votes > $max_votes) {
            $max_votes = $votes;
            $leading_code = $code;
        }
    }

    $lp_votes = (int)($totals['total_lp_votes'] ?? 0);
    $apc_votes = (int)($totals['total_apc_votes'] ?? 0);

    // Real Polling Unit Count from DB
    $total_pus = (int)$pdo->query("SELECT COUNT(*) FROM polling_units")->fetchColumn();
    if ($total_pus === 0) {
        $total_pus = 4104; // Baseline fallback
    }

    $reported_pus_count = (int)$pdo->query("
        SELECT COUNT(DISTINCT polling_unit) FROM result_sheets
        WHERE is_current = 1 AND {$status_filter}
    ")->fetchColumn();

    $reporting_percentage = $total_pus > 0 ? round(($reported_pus_count / $total_pus) * 100, 1) : 0;

    $parties = [];
    foreach (get_official_parties() as $code => $p) {
        $col = $p['column'];
        $votes = (int)($totals['total_' . $col] ?? 0);
        $pct = round(($votes / $calc_valid) * 100, 1);

        $parties[] = [
            'code' => $code,
            'candidate' => $p['candidate'],
            'party_full' => $p['name'],
            'votes' => $votes,
            'percentage' => $pct,
            'color' => $p['color'],
            'bg_class' => $p['bg_class'],
            'is_leading' => ($code === $leading_code && $max_votes > 0)
        ];
    }

    // LGA breakdown computed live from SQLite
    $lga_stmt = $pdo->query("
        SELECT 
            lga,
            COUNT(*) as submissions_count,
            SUM(lp_votes) as lp,
            SUM(apc_votes) as apc,
            SUM(pdp_votes) as pdp,
            SUM(total_valid_votes) as valid_total
        FROM result_sheets
        WHERE is_current = 1 AND {$status_filter}
        GROUP BY lga
        ORDER BY valid_total DESC
    ");
    $lgas = $lga_stmt->fetchAll();

    // Latest verified EC8A result sheets
    $recent_stmt = $pdo->query("
        SELECT 
            r.id, r.lga, r.ward, r.polling_unit, r.lp_votes, r.apc_votes, r.pdp_votes, 
            r.total_valid_votes, r.ec8a_image, r.created_at, r.verification_status,
            a.name as agent_name, a.agent_code
        FROM result_sheets r
        LEFT JOIN agents a ON r.agent_id = a.id
        WHERE r.is_current = 1 AND r.{$status_filter}
        ORDER BY r.id DESC
        LIMIT 8
    ");
    $recent_submissions = $recent_stmt->fetchAll();

    // Latest verified field incidents
    $incidents_stmt = $pdo->query("
        SELECT id, lga, ward, polling_unit, title, category, severity, status, description, created_at
        FROM incidents
        WHERE status IN ('verified', 'in-progress', 'resolved')
        ORDER BY id DESC
        LIMIT 6
    ");
    $recent_incidents = $incidents_stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'csrf_token' => csrf_token(),
        'notice' => get_setting($pdo, 'public_label_text', 'Party agent tallies, unofficial. Not INEC results.'),
        'summary' => [
            'total_valid_votes' => $valid,
            'total_accredited' => $total_accredited,
            'total_rejected' => $total_rejected,
            'total_polling_units' => $total_pus,
            'reported_polling_units' => $reported_pus_count,
            'reporting_percentage' => $reporting_percentage,
            'total_lgas' => (int)$pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn(),
            'total_wards' => (int)$pdo->query("SELECT COUNT(*) FROM wards")->fetchColumn(),
            'margin_lead' => $lp_votes - $apc_votes,
            'public_results_visible' => get_setting($pdo, 'public_results_visible', '1') === '1'
        ],
        'parties' => $parties,
        'lgas' => $lgas,
        'recent_submissions' => $recent_submissions,
        'recent_incidents' => $recent_incidents
    ], JSON_PRETTY_PRINT);
    exit;
}

// 2. COVERAGE REPORT (Public: reporting percentage per LGA and Ward)
if ($action === 'coverage') {
    $require_review = get_setting($pdo, 'require_review_before_public', '1');
    $status_filter = ($require_review === '1') ? "r.verification_status = 'verified'" : "r.verification_status != 'rejected'";

    $lgas = $pdo->query("SELECT id, name, code FROM lgas ORDER BY name ASC")->fetchAll();
    $coverage_data = [];

    $stmt_pu_count = $pdo->prepare("
        SELECT COUNT(p.id) FROM polling_units p
        JOIN wards w ON p.ward_id = w.id
        WHERE w.lga_id = ?
    ");

    $stmt_rep_count = $pdo->prepare("
        SELECT COUNT(DISTINCT r.polling_unit) FROM result_sheets r
        WHERE r.lga = ? AND r.is_current = 1 AND {$status_filter}
    ");

    foreach ($lgas as $lga) {
        $stmt_pu_count->execute([$lga['id']]);
        $total_pus = (int)$stmt_pu_count->fetchColumn();

        $stmt_rep_count->execute([$lga['name']]);
        $rep_pus = (int)$stmt_rep_count->fetchColumn();

        $coverage_data[] = [
            'lga_id' => $lga['id'],
            'lga_name' => $lga['name'],
            'lga_code' => $lga['code'],
            'total_polling_units' => $total_pus,
            'reported_polling_units' => $rep_pus,
            'coverage_pct' => $total_pus > 0 ? round(($rep_pus / $total_pus) * 100, 1) : 0
        ];
    }

    echo json_encode([
        'success' => true,
        'coverage' => $coverage_data
    ]);
    exit;
}

// 2B. GEOGRAPHY MASTER DIRECTORY (LGAs, Wards, Polling Units)
if ($action === 'get_lgas') {
    $lgas = $pdo->query("SELECT id, name, code FROM lgas ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'lgas' => $lgas]);
    exit;
}

if ($action === 'get_wards') {
    $lga_id = (int)($_GET['lga_id'] ?? 0);
    $lga_name = trim($_GET['lga'] ?? '');
    if ($lga_id > 0) {
        $stmt = $pdo->prepare("SELECT id, name, code FROM wards WHERE lga_id = ? ORDER BY name ASC");
        $stmt->execute([$lga_id]);
    } elseif (!empty($lga_name)) {
        $stmt = $pdo->prepare("SELECT w.id, w.name, w.code FROM wards w JOIN lgas l ON w.lga_id = l.id WHERE l.name = ? ORDER BY w.name ASC");
        $stmt->execute([$lga_name]);
    } else {
        $stmt = $pdo->query("SELECT id, name, code, lga_id FROM wards ORDER BY name ASC");
    }
    echo json_encode(['success' => true, 'wards' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'get_pus') {
    $ward_id = (int)($_GET['ward_id'] ?? 0);
    $ward_name = trim($_GET['ward'] ?? '');
    if ($ward_id > 0) {
        $stmt = $pdo->prepare("SELECT id, name, code, registered_voters FROM polling_units WHERE ward_id = ? ORDER BY name ASC");
        $stmt->execute([$ward_id]);
    } elseif (!empty($ward_name)) {
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.code, p.registered_voters FROM polling_units p JOIN wards w ON p.ward_id = w.id WHERE w.name = ? ORDER BY p.name ASC");
        $stmt->execute([$ward_name]);
    } else {
        $stmt = $pdo->query("SELECT id, name, code, ward_id, registered_voters FROM polling_units ORDER BY name ASC LIMIT 100");
    }
    echo json_encode(['success' => true, 'polling_units' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// 3. GET INCIDENTS STREAM (Public)
if ($action === 'get_incidents') {
    $stmt = $pdo->query("
        SELECT id, lga, ward, polling_unit, title, category, severity, status, description, created_at
        FROM incidents
        ORDER BY id DESC
        LIMIT 25
    ");
    echo json_encode([
        'success' => true,
        'incidents' => $stmt->fetchAll()
    ]);
    exit;
}

// 4. CHECK AUTH STATUS
if ($action === 'check_auth' || $action === 'check') {
    if (is_authenticated()) {
        $agent = current_agent();
        echo json_encode([
            'authenticated' => true,
            'csrf_token' => csrf_token(),
            'status' => $agent['status'] ?? 'accredited',
            'agent' => [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'email' => $agent['email'],
                'lga' => $agent['lga'],
                'ward' => $agent['ward'],
                'polling_unit' => $agent['polling_unit'],
                'agent_code' => $agent['agent_code']
            ]
        ]);
    } else {
        echo json_encode([
            'authenticated' => false,
            'csrf_token' => csrf_token()
        ]);
    }
    exit;
}

// =============================================================
// AUTHENTICATED AGENT ENDPOINTS (Requires Active Session)
// =============================================================

if (!is_agent_authenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please sign in as an accredited Party Agent.',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

$agent = current_agent();

// Refresh agent record from DB to verify active status
$stmt_cur = $pdo->prepare("SELECT * FROM agents WHERE id = ? LIMIT 1");
$stmt_cur->execute([$agent['id']]);
$agent_db = $stmt_cur->fetch();

if (!$agent_db || ($agent_db['status'] ?? '') === 'suspended') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your agent accreditation has been suspended. Please contact your coordinator.',
        'error_code' => 'AGENT_SUSPENDED'
    ]);
    exit;
}

// 4B. UPDATE AGENT FACIAL PHOTO
if ($action === 'update_agent_photo') {
    require_csrf();
    if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please snap or select a photo file.']);
        exit;
    }

    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error uploading photo. Please try again.']);
        exit;
    }

    if ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Photo size must not exceed 10MB.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed_mimes[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid photo format. Please provide a JPG, PNG, or WEBP image.']);
        exit;
    }

    $ext = $allowed_mimes[$mime];
    $upload_dir = __DIR__ . '/uploads/agents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $safe_filename = 'agent_face_' . $agent['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $upload_dir . $safe_filename;

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save photograph to server disk.']);
        exit;
    }

    $photo_path = 'uploads/agents/' . $safe_filename;

    $stmt_up = $pdo->prepare("UPDATE agents SET photo = ? WHERE id = ?");
    $stmt_up->execute([$photo_path, $agent['id']]);

    $_SESSION['agent']['photo'] = $photo_path;

    audit_log($pdo, 'agent', (int)$agent['id'], 'agent_photo_updated', 'agents', (int)$agent['id'], [
        'photo' => $photo_path
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Agent facial photograph updated successfully!',
        'photo_url' => $photo_path
    ]);
    exit;
}

// 5. SUBMIT FORM EC8A RESULTS
if ($action === 'submit_result') {
    require_csrf();

    // Check if submissions are open
    if (get_setting($pdo, 'submissions_open', '1') !== '1') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Result transmission has been frozen by Command HQ administrators.',
            'error_code' => 'SUBMISSIONS_FROZEN'
        ]);
        exit;
    }

    // Agent must be accredited (not pending)
    if ($agent_db['status'] !== 'accredited') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your agent account is currently pending accreditation approval by Command HQ or your LGA coordinator.',
            'error_code' => 'AGENT_PENDING_APPROVAL'
        ]);
        exit;
    }

    // 1. Agent can only submit for their assigned polling unit
    $assigned_pu = trim($agent_db['polling_unit']);
    $submitted_pu = trim($_POST['polling_unit'] ?? $assigned_pu);

    if ($submitted_pu !== $assigned_pu) {
        http_response_code(403);
        audit_log($pdo, 'agent', (int)$agent['id'], 'result_submission_pu_mismatch_rejected', 'result_sheets', null, [
            'assigned' => $assigned_pu,
            'submitted' => $submitted_pu
        ]);
        echo json_encode([
            'success' => false,
            'message' => "Scope violation: You are accredited exclusively for {$assigned_pu}. You cannot submit for another polling station.",
            'error_code' => 'PU_SCOPE_VIOLATION'
        ]);
        exit;
    }

    // 2. Validate Vote Tallies Server-Side across all 17 official political parties
    $official_parties = get_official_parties();
    $party_votes = [];
    foreach ($official_parties as $code => $p) {
        $col = $p['column'];
        $val = $_POST[$col] ?? $_POST[strtolower($code)] ?? $_POST[$code] ?? 0;
        $party_votes[$code] = max(0, intval($val));
    }
    $rejected_votes = max(0, intval($_POST['rejected_votes'] ?? 0));
    $total_accredited = max(0, intval($_POST['total_accredited'] ?? 0));
    $notes = sanitize($_POST['notes'] ?? '');

    $transmission_status = sanitize($_POST['transmission_status'] ?? 'Reporting on PU ground');
    $valid_statuses = [
        'Reporting on PU ground',
        'Took me 10 mins to get internet',
        'I have to travel for 30 mins to get internet'
    ];
    if (!in_array($transmission_status, $valid_statuses, true)) {
        $transmission_status = 'Reporting on PU ground';
    }

    $tally_data = $party_votes;
    $tally_data['rejected_votes'] = $rejected_votes;
    $tally_data['total_accredited'] = $total_accredited;
    $tally_check = validate_ec8a_tallies($tally_data);

    if (!$tally_check['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $tally_check['error']]);
        exit;
    }

    $total_valid = $tally_check['total_valid'];
    $lp_votes = $party_votes['LP'] ?? 0;
    $auto_flag_reasons = [];

    if ($tally_check['is_overvoting']) {
        $auto_flag_reasons[] = $tally_check['flag_reason'];
    }

    // 3. File Upload & Cryptographic SHA256 Verification
    $max_mb = (int)get_setting($pdo, 'max_upload_mb', '15');
    $image_filename = null;
    $image_sha256 = null;

    if (!empty($_FILES['ec8a_photo']['name']) && $_FILES['ec8a_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_check = validate_uploaded_ec8a_file($_FILES['ec8a_photo'], $max_mb);
        if (!$upload_check['valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $upload_check['error']]);
            exit;
        }

        if ($upload_check['has_file']) {
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0777, true);
            }
            $image_sha256 = $upload_check['sha256'];

            // Check for duplicate image hash across the system
            $stmt_dup = $pdo->prepare("
                SELECT id, polling_unit FROM result_sheets
                WHERE image_sha256 = ? AND image_sha256 IS NOT NULL
                LIMIT 1
            ");
            $stmt_dup->execute([$image_sha256]);
            $dup_match = $stmt_dup->fetch();
            if ($dup_match) {
                $auto_flag_reasons[] = "Duplicate EC8A photo hash detected: Identical image was already submitted in Sheet #{$dup_match['id']} ({$dup_match['polling_unit']}).";
            }

            // Secure randomized filename
            $safe_filename = 'EC8A_' . preg_replace('/[^a-zA-Z0-9]/', '_', $agent_db['lga']) . '_' . bin2hex(random_bytes(10)) . '.' . $upload_check['ext'];
            $dest = UPLOAD_DIR . $safe_filename;
            if (move_uploaded_file($upload_check['tmp_name'], $dest)) {
                $image_filename = $safe_filename;
            }
        }
    }

    // Determine initial verification status
    $initial_status = empty($auto_flag_reasons) ? 'pending' : 'flagged';
    $review_note = !empty($auto_flag_reasons) ? implode(' | ', $auto_flag_reasons) : null;

    // 4. Enforce One Current Sheet Per Polling Unit (Superseding Logic)
    $stmt_prev = $pdo->prepare("
        SELECT * FROM result_sheets
        WHERE polling_unit = ? AND is_current = 1
        LIMIT 1
    ");
    $stmt_prev->execute([$assigned_pu]);
    $prev_sheet = $stmt_prev->fetch();

    $supersedes_id = null;
    if ($prev_sheet) {
        $supersedes_id = (int)$prev_sheet['id'];
        // Mark previous sheet as superseded
        $pdo->prepare("UPDATE result_sheets SET is_current = 0 WHERE id = ?")->execute([$supersedes_id]);

        $new_revision_data = [];
        foreach ($official_parties as $code => $p) {
            $new_revision_data[$p['column']] = $party_votes[$code];
        }
        $new_revision_data['rejected_votes'] = $rejected_votes;
        $new_revision_data['total_valid_votes'] = $total_valid;
        $new_revision_data['total_accredited'] = $total_accredited;
        $new_revision_data['total_accredited_voters'] = $total_accredited;
        $new_revision_data['image'] = $image_filename;

        // Log Revision
        $stmt_rev = $pdo->prepare("
            INSERT INTO result_sheet_revisions (sheet_id, changed_by_type, changed_by_id, old_json, new_json, reason, created_at)
            VALUES (?, 'agent', ?, ?, ?, ?, datetime('now'))
        ");
        $stmt_rev->execute([
            $supersedes_id,
            $agent['id'],
            json_encode($prev_sheet, JSON_UNESCAPED_UNICODE),
            json_encode($new_revision_data, JSON_UNESCAPED_UNICODE),
            'Agent submitted revised Form EC8A transmission'
        ]);
    }

    // Find polling_unit_id if linked
    $pu_id = null;
    try {
        $stmt_puid = $pdo->prepare("SELECT id FROM polling_units WHERE name = ? OR code = ? LIMIT 1");
        $stmt_puid->execute([$assigned_pu, $assigned_pu]);
        $pu_id = $stmt_puid->fetchColumn() ?: null;
    } catch (Throwable) {}

    $party_cols = [];
    $party_placeholders = [];
    $party_insert_vals = [];
    foreach ($official_parties as $code => $p) {
        $party_cols[] = $p['column'];
        $party_placeholders[] = '?';
        $party_insert_vals[] = $party_votes[$code];
    }
    $party_cols_str = implode(', ', $party_cols);
    $party_placeholders_str = implode(', ', $party_placeholders);

    // Insert new transmission sheet
    $stmt_ins = $pdo->prepare("
        INSERT INTO result_sheets (
            agent_id, lga, ward, polling_unit, polling_unit_id, ec8a_image, image_sha256,
            transmission_status,
            {$party_cols_str}, rejected_votes,
            total_valid_votes, total_accredited, total_accredited_voters, notes, verification_status,
            review_note, is_current, supersedes_id, source, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?,
            {$party_placeholders_str}, ?,
            ?, ?, ?, ?, ?,
            ?, 1, ?, 'agent', datetime('now')
        )
    ");

    $insert_params = array_merge(
        [
            $agent['id'],
            $agent_db['lga'],
            $agent_db['ward'],
            $assigned_pu,
            $pu_id,
            $image_filename,
            $image_sha256,
            $transmission_status
        ],
        $party_insert_vals,
        [
            $rejected_votes,
            $total_valid,
            $total_accredited,
            $total_accredited,
            $notes,
            $initial_status,
            $review_note,
            $supersedes_id
        ]
    );

    $stmt_ins->execute($insert_params);

    $new_id = (int)$pdo->lastInsertId();

    audit_log($pdo, 'agent', (int)$agent['id'], 'result_transmitted', 'result_sheets', $new_id, [
        'pu' => $assigned_pu,
        'valid' => $total_valid,
        'status' => $initial_status,
        'supersedes' => $supersedes_id,
        'transmission_status' => $transmission_status,
        'flag_reasons' => $auto_flag_reasons
    ]);

    $response_msg = ($initial_status === 'flagged')
        ? 'Results submitted and automatically flagged for administrator review: ' . implode(' ', $auto_flag_reasons)
        : 'Official Form EC8A results successfully transmitted to Command HQ! Awaiting reviewer verification.';

    echo json_encode([
        'success' => true,
        'message' => $response_msg,
        'submission_id' => $new_id,
        'status' => $initial_status,
        'supersedes_id' => $supersedes_id,
        'total_valid' => $total_valid,
        'lp_votes' => $lp_votes,
        'lp_percentage' => $total_valid > 0 ? round(($lp_votes / $total_valid) * 100, 1) : 0
    ]);
    exit;
}

// 6. SUBMIT FIELD INCIDENT
if ($action === 'submit_incident') {
    require_csrf();

    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? 'General Incident');
    $severity = sanitize($_POST['severity'] ?? 'normal');
    $description = sanitize($_POST['description'] ?? '');

    if (empty($title) || empty($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please provide an incident title and clear description.']);
        exit;
    }

    $attachment = null;
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_check = validate_uploaded_ec8a_file($_FILES['attachment'], 10);
        if ($upload_check['valid'] && $upload_check['has_file']) {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
            $safe_filename = 'INC_' . bin2hex(random_bytes(10)) . '.' . $upload_check['ext'];
            if (move_uploaded_file($upload_check['tmp_name'], UPLOAD_DIR . $safe_filename)) {
                $attachment = $safe_filename;
            }
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO incidents (agent_id, lga, ward, polling_unit, title, category, severity, description, attachment, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'received', datetime('now'))
    ");
    $stmt->execute([
        $agent['id'],
        $agent_db['lga'],
        $agent_db['ward'],
        $agent_db['polling_unit'],
        $title,
        $category,
        $severity,
        $description,
        $attachment
    ]);

    $inc_id = (int)$pdo->lastInsertId();
    audit_log($pdo, 'agent', (int)$agent['id'], 'incident_reported', 'incidents', $inc_id, [
        'title' => $title,
        'category' => $category,
        'severity' => $severity
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Urgent incident dispatch logged with Central Legal & Security Command.'
    ]);
    exit;
}

// 7. CHANGE AGENT PASSWORD
if ($action === 'change_password') {
    require_csrf();

    $current_pass = trim($_POST['current_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');

    if (empty($current_pass) || empty($new_pass)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in both current and new passwords.']);
        exit;
    }

    if (strlen($new_pass) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
        exit;
    }

    $stmt_chk = $pdo->prepare("SELECT password_hash FROM agents WHERE id = ?");
    $stmt_chk->execute([$agent['id']]);
    $hash = $stmt_chk->fetchColumn();

    if (!$hash || !password_verify($current_pass, $hash)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE agents SET password_hash = ? WHERE id = ?")->execute([$new_hash, $agent['id']]);

    audit_log($pdo, 'agent', (int)$agent['id'], 'password_changed', 'agents', (int)$agent['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully!'
    ]);
    exit;
}

// 8. GET ANNOUNCEMENTS FOR AGENT
if ($action === 'announcements') {
    $lga_id = null;
    try {
        $stmt_lga = $pdo->prepare("SELECT id FROM lgas WHERE name = ? LIMIT 1");
        $stmt_lga->execute([$agent_db['lga']]);
        $lga_id = $stmt_lga->fetchColumn() ?: null;
    } catch (Throwable) {}

    $stmt_ann = $pdo->prepare("
        SELECT a.*, 
            (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id AND r.agent_id = ?) as is_read
        FROM announcements a
        WHERE (a.audience = 'all' 
            OR a.audience = 'lga:' || ? 
            OR a.audience = 'agent:' || ?)
          AND (a.expires_at IS NULL OR a.expires_at > datetime('now'))
        ORDER BY a.priority = 'critical' DESC, a.priority = 'urgent' DESC, a.id DESC
    ");
    $stmt_ann->execute([$agent['id'], $lga_id, $agent['id']]);
    $announcements = $stmt_ann->fetchAll();

    $unread_count = 0;
    foreach ($announcements as $ann) {
        if (empty($ann['is_read'])) $unread_count++;
    }

    echo json_encode([
        'success' => true,
        'announcements' => $announcements,
        'unread_count' => $unread_count
    ]);
    exit;
}

// 9. MARK ANNOUNCEMENT AS READ
if ($action === 'mark_announcement_read') {
    require_csrf();
    $ann_id = (int)($_POST['announcement_id'] ?? $_POST['id'] ?? 0);
    if ($ann_id > 0) {
        $stmt = $pdo->prepare("
            INSERT OR IGNORE INTO announcement_reads (announcement_id, agent_id, read_at)
            VALUES (?, ?, datetime('now'))
        ");
        $stmt->execute([$ann_id, $agent['id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// 10. GET AGENT SUMMARY & SUBMISSION HISTORY
if ($action === 'get_summary') {
    $stmt = $pdo->prepare("
        SELECT r.*, 
            (SELECT COUNT(*) FROM result_sheet_revisions rev WHERE rev.sheet_id = r.id) as revisions_count
        FROM result_sheets r
        WHERE r.agent_id = ? 
        ORDER BY r.id DESC 
        LIMIT 15
    ");
    $stmt->execute([$agent['id']]);
    $results = $stmt->fetchAll();

    $stmt_incidents = $pdo->prepare("SELECT * FROM incidents WHERE agent_id = ? ORDER BY id DESC LIMIT 10");
    $stmt_incidents->execute([$agent['id']]);
    $incidents = $stmt_incidents->fetchAll();

    echo json_encode([
        'success' => true,
        'csrf_token' => csrf_token(),
        'agent_status' => $agent_db['status'],
        'results' => $results,
        'incidents' => $incidents
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown API action.']);
