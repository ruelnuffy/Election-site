<?php
/**
 * SIA 2027 Admin Command Center - Backend API Engine
 * Strict Server-Side Role Scoping: Superadmin | Coordinator | Analyst
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/validators.php';
require_once __DIR__ . '/lib/settings.php';

start_secure_session();
$pdo = get_db_connection();

// 1. Authenticate Admin
$admin = current_admin();
if (!$admin || empty($admin['id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in to Admin Command Center.']);
    exit;
}

// Refresh admin details from DB to catch status/role changes immediately
$adm_stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$adm_stmt->execute([$admin['id']]);
$db_admin = $adm_stmt->fetch();
if (!$db_admin || $db_admin['status'] !== 'active') {
    unset($_SESSION['admin']);
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Administrator account is deactivated or suspended.']);
    exit;
}
$admin = $db_admin;
$_SESSION['admin'] = $admin;

$role = $admin['role']; // 'superadmin' | 'coordinator' | 'analyst'
$coordinator_lga_id = $admin['lga_id'] ? (int)$admin['lga_id'] : null;
$coordinator_lga_name = null;
if ($coordinator_lga_id) {
    $lga_row = $pdo->prepare("SELECT name FROM lgas WHERE id = ?");
    $lga_row->execute([$coordinator_lga_id]);
    $coordinator_lga_name = $lga_row->fetchColumn() ?: null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$is_post = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');

// Verify CSRF on any state-changing POST/DELETE request
if ($is_post && $action !== 'export_csv' && $action !== 'backup_database' && $action !== 'backup_uploads') {
    try {
        require_csrf();
    } catch (Exception $e) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token invalid or expired (CSRF). Refresh page.']);
        exit;
    }
}

// Enforce read-only for Analyst role
$modifying_actions = [
    'verify_sheet', 'flag_sheet', 'reject_sheet', 'edit_sheet_numbers', 'request_resubmission',
    'bulk_verify_balanced', 'update_agent_status', 'generate_reset_link', 'reassign_agent_pu',
    'add_agent', 'delete_agent',
    'agents_csv_import', 'generate_agent_codes', 'update_incident', 'create_announcement',
    'geography_csv_import', 'update_geography_pu', 'update_settings', 'create_admin_user',
    'update_admin_user'
];

if ($role === 'analyst' && in_array($action, $modifying_actions, true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access Denied: Analyst role is strictly read-only.']);
    exit;
}

// Helper: Scoping condition for Coordinator
function get_coordinator_pu_condition(PDO $pdo, ?int $coordinator_lga_id, ?string $coordinator_lga_name, string $table_alias = 'rs'): array {
    if (!$coordinator_lga_id && !$coordinator_lga_name) {
        return ['where' => '', 'params' => []];
    }
    // Match either by lga_id or text lga name
    return [
        'where' => " AND ({$table_alias}.lga_id = ? OR {$table_alias}.lga = ?) ",
        'params' => [$coordinator_lga_id, $coordinator_lga_name]
    ];
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {

        // =================================================================
        // 1. OVERVIEW TELEMETRY & KPIS
        // =================================================================
        case 'overview': {
            $scope = get_coordinator_pu_condition($pdo, $coordinator_lga_id, $coordinator_lga_name, 'rs');

            // 1. Polling Units Stats
            $pu_scope = $coordinator_lga_id ? "WHERE w.lga_id = {$coordinator_lga_id}" : "";
            $total_pus = (int)$pdo->query("
                SELECT COUNT(pu.id)
                FROM polling_units pu
                JOIN wards w ON pu.ward_id = w.id
                {$pu_scope}
            ")->fetchColumn();
            if ($total_pus === 0) {
                $total_pus = 42; // seed fallback
            }

            // Reported PUs (verified or pending is_current=1)
            $rep_stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT polling_unit_id)
                FROM result_sheets rs
                WHERE is_current = 1 AND verification_status IN ('verified', 'pending', 'flagged')
                {$scope['where']}
            ");
            $rep_stmt->execute($scope['params']);
            $reported_pus = (int)$rep_stmt->fetchColumn();

            // 2. Sheets Breakdown
            $party_sum_parts = [];
            foreach (get_official_parties() as $code => $p) {
                $col = $p['column'];
                $party_sum_parts[] = "COALESCE(SUM({$col}), 0) as total_{$col}";
            }
            $party_sums_sql = implode(",\n                    ", $party_sum_parts);

            $sheet_stat_stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_current_sheets,
                    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_count,
                    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN verification_status = 'flagged' THEN 1 ELSE 0 END) as flagged_count,
                    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    {$party_sums_sql},
                    COALESCE(SUM(total_valid_votes), 0) as total_valid,
                    COALESCE(SUM(rejected_votes), 0) as total_rejected,
                    COALESCE(SUM(COALESCE(total_accredited, total_accredited_voters, 0)), 0) as total_accredited
                FROM result_sheets rs
                WHERE is_current = 1
                {$scope['where']}
            ");
            $sheet_stat_stmt->execute($scope['params']);
            $sheet_stats = $sheet_stat_stmt->fetch(PDO::FETCH_ASSOC);

            // 3. Agents Breakdown
            $agent_scope = $coordinator_lga_id ? "WHERE lga_id = {$coordinator_lga_id} OR lga = " . $pdo->quote($coordinator_lga_name ?? '') : "";
            $agent_stats = $pdo->query("
                SELECT 
                    COUNT(*) as total_agents,
                    SUM(CASE WHEN status = 'accredited' THEN 1 ELSE 0 END) as accredited_agents,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_agents,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_agents
                FROM agents
                {$agent_scope}
            ")->fetch(PDO::FETCH_ASSOC);

            // 4. Incidents Breakdown
            $inc_scope = $coordinator_lga_id ? "WHERE lga_id = {$coordinator_lga_id} OR lga = " . $pdo->quote($coordinator_lga_name ?? '') : "";
            $inc_stats = $pdo->query("
                SELECT 
                    COUNT(*) as total_incidents,
                    SUM(CASE WHEN severity = 'critical' AND status != 'resolved' THEN 1 ELSE 0 END) as critical_open,
                    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as unassigned_count,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
                FROM incidents
                {$inc_scope}
            ")->fetch(PDO::FETCH_ASSOC);

            // 5. Hourly reporting rate trend (last 12 hours)
            $trend_stmt = $pdo->prepare("
                SELECT 
                    strftime('%Y-%m-%d %H:00', created_at) as hour_slot,
                    COUNT(*) as submissions_count
                FROM result_sheets rs
                WHERE created_at >= datetime('now', '-24 hours')
                {$scope['where']}
                GROUP BY hour_slot
                ORDER BY hour_slot ASC
            ");
            $trend_stmt->execute($scope['params']);
            $trend_rows = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 6. LGA Leaderboard
            $leaderboard_sql = "
                SELECT 
                    lga,
                    COUNT(DISTINCT polling_unit) as reported_pus,
                    SUM(lp_votes) as lp_votes,
                    SUM(total_valid_votes) as valid_votes,
                    ROUND(CAST(SUM(lp_votes) AS REAL) / MAX(1, SUM(total_valid_votes)) * 100, 1) as lp_pct
                FROM result_sheets rs
                WHERE is_current = 1
                {$scope['where']}
                GROUP BY lga
                ORDER BY lp_votes DESC
                LIMIT 10
            ";
            $lb_stmt = $pdo->prepare($leaderboard_sql);
            $lb_stmt->execute($scope['params']);
            $leaderboard = $lb_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'role' => $role,
                'coordinator_lga' => $coordinator_lga_name,
                'kpis' => [
                    'total_polling_units' => $total_pus,
                    'reported_polling_units' => $reported_pus,
                    'reporting_pct' => $total_pus > 0 ? round(($reported_pus / $total_pus) * 100, 1) : 0,
                    'total_valid_votes' => (int)($sheet_stats['total_valid'] ?? 0),
                    'total_accredited' => (int)($sheet_stats['total_accredited'] ?? 0),
                    'total_rejected' => (int)($sheet_stats['total_rejected'] ?? 0),
                    'lp_votes' => (int)($sheet_stats['total_lp_votes'] ?? $sheet_stats['total_lp'] ?? 0),
                    'apc_votes' => (int)($sheet_stats['total_apc_votes'] ?? $sheet_stats['total_apc'] ?? 0),
                    'pdp_votes' => (int)($sheet_stats['total_pdp_votes'] ?? $sheet_stats['total_pdp'] ?? 0),
                    'apm_votes' => (int)($sheet_stats['total_apm_votes'] ?? $sheet_stats['total_apm'] ?? 0),
                    'ndc_votes' => (int)($sheet_stats['total_ndc_votes'] ?? $sheet_stats['total_ndc'] ?? 0),
                    'lp_pct' => (int)($sheet_stats['total_valid'] ?? 0) > 0 ? round(((int)($sheet_stats['total_lp_votes'] ?? $sheet_stats['total_lp'] ?? 0) / (int)$sheet_stats['total_valid']) * 100, 1) : 0,
                    'sheets_pending' => (int)($sheet_stats['pending_count'] ?? 0),
                    'sheets_verified' => (int)($sheet_stats['verified_count'] ?? 0),
                    'sheets_flagged' => (int)($sheet_stats['flagged_count'] ?? 0),
                    'sheets_rejected' => (int)($sheet_stats['rejected_count'] ?? 0),
                    'agents_total' => (int)($agent_stats['total_agents'] ?? 0),
                    'agents_accredited' => (int)($agent_stats['accredited_agents'] ?? 0),
                    'agents_pending' => (int)($agent_stats['pending_agents'] ?? 0),
                    'agents_suspended' => (int)($agent_stats['suspended_agents'] ?? 0),
                    'incidents_critical' => (int)($inc_stats['critical_open'] ?? 0),
                    'incidents_total' => (int)($inc_stats['total_incidents'] ?? 0)
                ],
                'leaderboard' => $leaderboard,
                'trend' => $trend_rows
            ]);
            break;
        }

        // =================================================================
        // 2. RESULTS REVIEW QUEUE (The Core Collation Feature)
        // =================================================================
        case 'review_queue': {
            $status_filter = $_GET['status'] ?? 'all';
            $lga_filter = $_GET['lga'] ?? '';
            $ward_filter = $_GET['ward'] ?? '';
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
            $offset = ($page - 1) * $per_page;

            $where = ["rs.is_current = 1"];
            $params = [];

            // Coordinator restriction
            if ($coordinator_lga_id || $coordinator_lga_name) {
                $where[] = "(rs.lga_id = ? OR rs.lga = ?)";
                $params[] = $coordinator_lga_id;
                $params[] = $coordinator_lga_name;
            } elseif (!empty($lga_filter)) {
                $where[] = "rs.lga = ?";
                $params[] = $lga_filter;
            }

            if (!empty($ward_filter)) {
                $where[] = "rs.ward = ?";
                $params[] = $ward_filter;
            }

            if ($status_filter !== 'all') {
                $where[] = "rs.verification_status = ?";
                $params[] = $status_filter;
            }

            if (!empty($search)) {
                $where[] = "(rs.polling_unit LIKE ? OR a.name LIKE ? OR a.agent_code LIKE ? OR rs.review_note LIKE ?)";
                $s = "%{$search}%";
                $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
            }

            $where_sql = implode(' AND ', $where);

            // Count total
            $count_stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM result_sheets rs
                LEFT JOIN agents a ON rs.agent_id = a.id
                WHERE {$where_sql}
            ");
            $count_stmt->execute($params);
            $total_records = (int)$count_stmt->fetchColumn();

            // Fetch records
            $data_stmt = $pdo->prepare("
                SELECT 
                    rs.*,
                    COALESCE(rs.total_accredited, rs.total_accredited_voters, 0) as accredited_count,
                    a.name as agent_name,
                    a.phone as agent_phone,
                    a.agent_code,
                    adm.name as reviewer_name
                FROM result_sheets rs
                LEFT JOIN agents a ON rs.agent_id = a.id
                LEFT JOIN admins adm ON rs.reviewed_by = adm.id
                WHERE {$where_sql}
                ORDER BY 
                    CASE rs.verification_status 
                        WHEN 'flagged' THEN 1 
                        WHEN 'pending' THEN 2 
                        WHEN 'verified' THEN 3 
                        ELSE 4 
                    END,
                    rs.id DESC
                LIMIT ? OFFSET ?
            ");
            $params_with_limit = array_merge($params, [$per_page, $offset]);
            $data_stmt->execute($params_with_limit);
            $sheets = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute integrity check per row
            foreach ($sheets as &$s) {
                $total_cast = (int)$s['total_valid_votes'] + (int)$s['rejected_votes'];
                $acc = (int)$s['accredited_count'];
                if ($acc > 0) {
                    if ($total_cast === $acc) {
                        $s['integrity'] = 'balanced';
                    } elseif ($total_cast > $acc) {
                        $s['integrity'] = 'overvoting';
                    } else {
                        $s['integrity'] = 'undervoting';
                    }
                } else {
                    $s['integrity'] = 'unreconciled';
                }
            }

            echo json_encode([
                'success' => true,
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_records,
                'total_pages' => ceil($total_records / $per_page),
                'sheets' => $sheets
            ]);
            break;
        }

        // =================================================================
        // 3. SHEET DETAIL & REVISION HISTORY
        // =================================================================
        case 'sheet_detail': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT 
                    rs.*,
                    COALESCE(rs.total_accredited, rs.total_accredited_voters, 0) as accredited_count,
                    a.name as agent_name,
                    a.phone as agent_phone,
                    a.agent_code,
                    adm.name as reviewer_name,
                    pu.registered_voters
                FROM result_sheets rs
                LEFT JOIN agents a ON rs.agent_id = a.id
                LEFT JOIN admins adm ON rs.reviewed_by = adm.id
                LEFT JOIN polling_units pu ON rs.polling_unit_id = pu.id
                WHERE rs.id = ?
            ");
            $stmt->execute([$id]);
            $sheet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sheet) {
                throw new Exception("Result sheet #{$id} not found.");
            }

            // Coordinator scope check
            if ($coordinator_lga_id && (int)$sheet['lga_id'] !== $coordinator_lga_id && $sheet['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Access denied: This polling unit belongs to another LGA outside your coordinator scope.");
            }

            // Revisions
            $rev_stmt = $pdo->prepare("
                SELECT r.*, a.name as changed_by_name
                FROM result_sheet_revisions r
                LEFT JOIN admins a ON (r.changed_by_type = 'admin' AND r.changed_by_id = a.id)
                WHERE r.sheet_id = ?
                ORDER BY r.id DESC
            ");
            $rev_stmt->execute([$id]);
            $revisions = $rev_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'sheet' => $sheet,
                'revisions' => $revisions
            ]);
            break;
        }

        // =================================================================
        // 4. VERIFY / FLAG / REJECT ACTIONS
        // =================================================================
        case 'verify_sheet': {
            $id = (int)($_POST['id'] ?? 0);
            $note = trim($_POST['note'] ?? '');

            $chk = $pdo->prepare("SELECT * FROM result_sheets WHERE id = ?");
            $chk->execute([$id]);
            $sheet = $chk->fetch();
            if (!$sheet) throw new Exception("Result sheet not found.");

            if ($coordinator_lga_id && (int)$sheet['lga_id'] !== $coordinator_lga_id && $sheet['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            $stmt = $pdo->prepare("
                UPDATE result_sheets 
                SET verification_status = 'verified', reviewed_by = ?, reviewed_at = datetime('now'), review_note = ?
                WHERE id = ?
            ");
            $stmt->execute([$admin['id'], $note ?: 'Verified by administrator', $id]);

            audit_log($pdo, 'admin', $admin['id'], 'verify_sheet', 'result_sheets', $id, [
                'polling_unit' => $sheet['polling_unit'],
                'lga' => $sheet['lga'],
                'note' => $note
            ]);

            echo json_encode(['success' => true, 'message' => "Result Sheet #{$id} verified successfully."]);
            break;
        }

        case 'flag_sheet': {
            $id = (int)($_POST['id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if (empty($reason)) throw new Exception("A specific reason is required to flag a result sheet for audit.");

            $chk = $pdo->prepare("SELECT * FROM result_sheets WHERE id = ?");
            $chk->execute([$id]);
            $sheet = $chk->fetch();
            if (!$sheet) throw new Exception("Result sheet not found.");

            if ($coordinator_lga_id && (int)$sheet['lga_id'] !== $coordinator_lga_id && $sheet['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            $stmt = $pdo->prepare("
                UPDATE result_sheets 
                SET verification_status = 'flagged', reviewed_by = ?, reviewed_at = datetime('now'), review_note = ?
                WHERE id = ?
            ");
            $stmt->execute([$admin['id'], $reason, $id]);

            audit_log($pdo, 'admin', $admin['id'], 'flag_sheet', 'result_sheets', $id, [
                'polling_unit' => $sheet['polling_unit'],
                'reason' => $reason
            ]);

            echo json_encode(['success' => true, 'message' => "Result Sheet #{$id} flagged for audit."]);
            break;
        }

        case 'reject_sheet': {
            $id = (int)($_POST['id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if (empty($reason)) throw new Exception("A specific reason is required to reject an EC8A result sheet.");

            $chk = $pdo->prepare("SELECT * FROM result_sheets WHERE id = ?");
            $chk->execute([$id]);
            $sheet = $chk->fetch();
            if (!$sheet) throw new Exception("Result sheet not found.");

            if ($coordinator_lga_id && (int)$sheet['lga_id'] !== $coordinator_lga_id && $sheet['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            $stmt = $pdo->prepare("
                UPDATE result_sheets 
                SET verification_status = 'rejected', reviewed_by = ?, reviewed_at = datetime('now'), review_note = ?
                WHERE id = ?
            ");
            $stmt->execute([$admin['id'], $reason, $id]);

            audit_log($pdo, 'admin', $admin['id'], 'reject_sheet', 'result_sheets', $id, [
                'polling_unit' => $sheet['polling_unit'],
                'reason' => $reason
            ]);

            echo json_encode(['success' => true, 'message' => "Result Sheet #{$id} rejected."]);
            break;
        }

        case 'edit_sheet_numbers': {
            $id = (int)($_POST['id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if (empty($reason)) throw new Exception("A mandatory audit reason is required when editing vote counts.");

            $official_parties = get_official_parties();
            $party_votes = [];
            foreach ($official_parties as $code => $p) {
                $col = $p['column'];
                $val = $_POST[$col] ?? $_POST[strtolower($code)] ?? $_POST[$code] ?? 0;
                $party_votes[$code] = max(0, (int)$val);
            }
            $rejected = max(0, (int)($_POST['rejected_votes'] ?? 0));
            $accredited = max(0, (int)($_POST['total_accredited'] ?? 0));

            $chk = $pdo->prepare("SELECT * FROM result_sheets WHERE id = ?");
            $chk->execute([$id]);
            $sheet = $chk->fetch();
            if (!$sheet) throw new Exception("Result sheet not found.");

            if ($coordinator_lga_id && (int)$sheet['lga_id'] !== $coordinator_lga_id && $sheet['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            $tally_data = $party_votes;
            $tally_data['rejected_votes'] = $rejected;
            $tally_data['total_accredited'] = $accredited;
            $tally = validate_ec8a_tallies($tally_data);
            if (!$tally['valid']) {
                throw new Exception($tally['error']);
            }
            $total_valid = $tally['total_valid'];

            $old_data = [];
            $new_data = [];
            foreach ($official_parties as $code => $p) {
                $col = $p['column'];
                $old_data[$col] = (int)($sheet[$col] ?? 0);
                $new_data[$col] = $party_votes[$code];
            }
            $old_data['rejected_votes'] = (int)($sheet['rejected_votes'] ?? 0);
            $old_data['total_valid_votes'] = (int)($sheet['total_valid_votes'] ?? 0);
            $old_data['total_accredited'] = (int)($sheet['total_accredited'] ?? $sheet['total_accredited_voters'] ?? 0);

            $new_data['rejected_votes'] = $rejected;
            $new_data['total_valid_votes'] = $total_valid;
            $new_data['total_accredited'] = $accredited;

            $old_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);
            $new_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            $pdo->beginTransaction();

            // Log revision
            $rev_stmt = $pdo->prepare("
                INSERT INTO result_sheet_revisions (sheet_id, changed_by_type, changed_by_id, old_json, new_json, reason, created_at)
                VALUES (?, 'admin', ?, ?, ?, ?, datetime('now'))
            ");
            $rev_stmt->execute([$id, $admin['id'], $old_json, $new_json, $reason]);

            // Update sheet dynamically across all 17 parties
            $upd_set_parts = [];
            $upd_params = [];
            foreach ($official_parties as $code => $p) {
                $col = $p['column'];
                $upd_set_parts[] = "{$col} = ?";
                $upd_params[] = $party_votes[$code];
            }
            $upd_set_parts[] = "rejected_votes = ?";
            $upd_params[] = $rejected;
            $upd_set_parts[] = "total_valid_votes = ?";
            $upd_params[] = $total_valid;
            $upd_set_parts[] = "total_accredited = ?";
            $upd_params[] = $accredited;
            $upd_set_parts[] = "total_accredited_voters = ?";
            $upd_params[] = $accredited;
            $upd_set_parts[] = "reviewed_by = ?";
            $upd_params[] = $admin['id'];
            $upd_set_parts[] = "reviewed_at = datetime('now')";
            $upd_set_parts[] = "review_note = ?";
            $upd_params[] = "Numbers modified by admin: {$reason}";

            $upd_params[] = $id;
            $upd_sql = "UPDATE result_sheets SET " . implode(', ', $upd_set_parts) . " WHERE id = ?";
            $upd = $pdo->prepare($upd_sql);
            $upd->execute($upd_params);

            audit_log($pdo, 'admin', $admin['id'], 'edit_sheet_numbers', 'result_sheets', $id, [
                'reason' => $reason,
                'old' => $old_data,
                'new' => $new_data
            ]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => "Result sheet #{$id} tallies updated with revision record."]);
            break;
        }

        case 'request_resubmission': {
            $id = (int)($_POST['id'] ?? 0);
            $instructions = trim($_POST['instructions'] ?? '');
            if (empty($instructions)) throw new Exception("Please provide clear correction instructions for the party agent.");

            $chk = $pdo->prepare("SELECT * FROM result_sheets WHERE id = ?");
            $chk->execute([$id]);
            $sheet = $chk->fetch();
            if (!$sheet) throw new Exception("Result sheet not found.");

            $pdo->beginTransaction();

            // Update sheet to flagged
            $upd = $pdo->prepare("
                UPDATE result_sheets 
                SET verification_status = 'flagged', reviewed_by = ?, reviewed_at = datetime('now'),
                    review_note = ?
                WHERE id = ?
            ");
            $upd->execute([$admin['id'], "Resubmission requested: {$instructions}", $id]);

            // Dispatch targeted announcement to the agent
            if (!empty($sheet['agent_id'])) {
                $ann = $pdo->prepare("
                    INSERT INTO announcements (title, body, audience, priority, created_by, created_at)
                    VALUES (?, ?, ?, 'urgent', ?, datetime('now'))
                ");
                $ann->execute([
                    "Resubmission Required: " . $sheet['polling_unit'],
                    "HQ Reviewer Note: {$instructions}\n\nPlease take a clear photo of the official Form EC8A and re-transmit using your agent dashboard.",
                    "agent:" . $sheet['agent_id'],
                    $admin['id']
                ]);
            }

            audit_log($pdo, 'admin', $admin['id'], 'request_resubmission', 'result_sheets', $id, [
                'agent_id' => $sheet['agent_id'],
                'instructions' => $instructions
            ]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => "Resubmission request dispatched to agent with high priority."]);
            break;
        }

        case 'bulk_verify_balanced': {
            $scope = get_coordinator_pu_condition($pdo, $coordinator_lga_id, $coordinator_lga_name, 'rs');
            $party_cols_sum = implode(' + ', get_party_column_names());
            
            // Only verify pending sheets where cast == accredited and party sum matches total valid
            $sql = "
                UPDATE result_sheets 
                SET verification_status = 'verified', reviewed_by = ?, reviewed_at = datetime('now'), review_note = 'Bulk verified: Passed automated mathematical reconciliation'
                WHERE is_current = 1 AND verification_status = 'pending'
                AND (total_valid_votes + rejected_votes) = COALESCE(total_accredited, total_accredited_voters, 0)
                AND (total_valid_votes + rejected_votes) > 0
                AND ({$party_cols_sum}) = total_valid_votes
                {$scope['where']}
            ";
            $params = array_merge([$admin['id']], $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->rowCount();

            audit_log($pdo, 'admin', $admin['id'], 'bulk_verify_balanced', 'result_sheets', null, [
                'verified_count' => $count
            ]);

            echo json_encode(['success' => true, 'message' => "Successfully bulk verified {$count} mathematically balanced result sheets."]);
            break;
        }

        // =================================================================
        // 5. DISCREPANCY DETECTION REPORT
        // =================================================================
        case 'discrepancies': {
            $scope = get_coordinator_pu_condition($pdo, $coordinator_lga_id, $coordinator_lga_name, 'rs');
            $party_cols_rs = array_map(fn($c) => "rs.{$c}", get_party_column_names());
            $party_sum_rs_expr = implode(' + ', $party_cols_rs);

            // 1. Over-voting: Valid + Rejected > Accredited
            $stmt_over = $pdo->prepare("
                SELECT rs.*, COALESCE(rs.total_accredited, rs.total_accredited_voters, 0) as acc, a.name as agent_name, a.phone as agent_phone,
                       'Over-voting (Cast > Accredited)' as discrepancy_type
                FROM result_sheets rs
                LEFT JOIN agents a ON rs.agent_id = a.id
                WHERE rs.is_current = 1
                AND (rs.total_valid_votes + rs.rejected_votes) > COALESCE(rs.total_accredited, rs.total_accredited_voters, 0)
                AND COALESCE(rs.total_accredited, rs.total_accredited_voters, 0) > 0
                {$scope['where']}
            ");
            $stmt_over->execute($scope['params']);
            $overvoting_rows = $stmt_over->fetchAll(PDO::FETCH_ASSOC);

            // 2. Party sum mismatch: sum of all 17 parties != total_valid
            $stmt_sum = $pdo->prepare("
                SELECT rs.*, COALESCE(rs.total_accredited, rs.total_accredited_voters, 0) as acc, a.name as agent_name, a.phone as agent_phone,
                       'Party Votes != Total Valid' as discrepancy_type
                FROM result_sheets rs
                LEFT JOIN agents a ON rs.agent_id = a.id
                WHERE rs.is_current = 1
                AND ({$party_sum_rs_expr}) != rs.total_valid_votes
                {$scope['where']}
            ");
            $stmt_sum->execute($scope['params']);
            $sum_mismatches = $stmt_sum->fetchAll(PDO::FETCH_ASSOC);

            // 3. Duplicate Image Hashes
            $stmt_dup = $pdo->prepare("
                SELECT rs.*, COALESCE(rs.total_accredited, rs.total_accredited_voters, 0) as acc, a.name as agent_name, a.phone as agent_phone,
                       'Duplicate EC8A Photo Hash' as discrepancy_type
                FROM result_sheets rs
                JOIN (
                    SELECT image_sha256 FROM result_sheets 
                    WHERE image_sha256 IS NOT NULL AND image_sha256 != ''
                    GROUP BY image_sha256 HAVING COUNT(*) > 1
                ) dup ON rs.image_sha256 = dup.image_sha256
                LEFT JOIN agents a ON rs.agent_id = a.id
                WHERE rs.is_current = 1
                {$scope['where']}
            ");
            $stmt_dup->execute($scope['params']);
            $duplicate_hashes = $stmt_dup->fetchAll(PDO::FETCH_ASSOC);

            // 4. Same PU submitted by two agents
            $stmt_double_agent = $pdo->prepare("
                SELECT rs.*, a.name as agent_name, 'Duplicate PU Submissions by Multiple Agents' as discrepancy_type
                FROM result_sheets rs
                JOIN (
                    SELECT polling_unit FROM result_sheets
                    GROUP BY polling_unit HAVING COUNT(DISTINCT agent_id) > 1
                ) multi ON rs.polling_unit = multi.polling_unit
                LEFT JOIN agents a ON rs.agent_id = a.id
                WHERE rs.is_current = 1
                {$scope['where']}
            ");
            $stmt_double_agent->execute($scope['params']);
            $double_agent_rows = $stmt_double_agent->fetchAll(PDO::FETCH_ASSOC);

            // 5. Turnout > Registered Voters (where registered_voters > 0)
            $stmt_reg = $pdo->prepare("
                SELECT rs.*, pu.registered_voters, 'Votes Exceed Registered Voters' as discrepancy_type, a.name as agent_name
                FROM result_sheets rs
                JOIN polling_units pu ON rs.polling_unit_id = pu.id
                LEFT JOIN agents a ON rs.agent_id = a.id
                WHERE rs.is_current = 1
                AND pu.registered_voters > 0
                AND (rs.total_valid_votes + rs.rejected_votes) > pu.registered_voters
                {$scope['where']}
            ");
            $stmt_reg->execute($scope['params']);
            $reg_exceeded = $stmt_reg->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'overvoting' => $overvoting_rows,
                'sum_mismatches' => $sum_mismatches,
                'duplicate_hashes' => $duplicate_hashes,
                'double_agent_submissions' => $double_agent_rows,
                'registered_voters_exceeded' => $reg_exceeded,
                'total_discrepancies' => count($overvoting_rows) + count($sum_mismatches) + count($duplicate_hashes) + count($double_agent_rows) + count($reg_exceeded)
            ]);
            break;
        }

        // =================================================================
        // 6. AGENT MANAGEMENT
        // =================================================================
        case 'agents': {
            $status = $_GET['status'] ?? 'all';
            $lga = $_GET['lga'] ?? '';
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
            $offset = ($page - 1) * $per_page;

            $where = ["1=1"];
            $params = [];

            if ($coordinator_lga_id || $coordinator_lga_name) {
                $where[] = "(lga_id = ? OR lga = ?)";
                $params[] = $coordinator_lga_id;
                $params[] = $coordinator_lga_name;
            } elseif (!empty($lga)) {
                $where[] = "lga = ?";
                $params[] = $lga;
            }

            if ($status !== 'all') {
                $where[] = "status = ?";
                $params[] = $status;
            }

            if (!empty($search)) {
                $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR agent_code LIKE ? OR polling_unit LIKE ?)";
                $s = "%{$search}%";
                $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
            }

            $where_sql = implode(' AND ', $where);

            $count = (int)$pdo->prepare("SELECT COUNT(*) FROM agents WHERE {$where_sql}")->execute($params) ? $pdo->query("SELECT COUNT(*) FROM agents WHERE {$where_sql}")->fetchColumn() : 0;
            // Proper count execute:
            $c_stmt = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE {$where_sql}");
            $c_stmt->execute($params);
            $total_records = (int)$c_stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT id, name, email, phone, photo, role, lga, ward, polling_unit, agent_code, status, last_seen_at, created_at, password_reset_token
                FROM agents
                WHERE {$where_sql}
                ORDER BY id DESC
                LIMIT ? OFFSET ?
            ");
            $params_limit = array_merge($params, [$per_page, $offset]);
            $stmt->execute($params_limit);
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_records,
                'agents' => $agents
            ]);
            break;
        }

        case 'update_agent_status': {
            $agent_id = (int)($_POST['id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            if (!in_array($new_status, ['accredited', 'pending', 'suspended'], true)) {
                throw new Exception("Invalid agent status.");
            }

            $chk = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
            $chk->execute([$agent_id]);
            $agent_row = $chk->fetch();
            if (!$agent_row) throw new Exception("Agent not found.");

            if ($coordinator_lga_id && (int)$agent_row['lga_id'] !== $coordinator_lga_id && $agent_row['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            $stmt = $pdo->prepare("UPDATE agents SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $agent_id]);

            audit_log($pdo, 'admin', $admin['id'], 'update_agent_status', 'agents', $agent_id, [
                'old_status' => $agent_row['status'],
                'new_status' => $new_status,
                'agent_name' => $agent_row['name']
            ]);

            echo json_encode(['success' => true, 'message' => "Agent status updated to " . ucfirst($new_status)]);
            break;
        }

        case 'generate_reset_link': {
            $agent_id = (int)($_POST['id'] ?? 0);
            $chk = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
            $chk->execute([$agent_id]);
            $agent_row = $chk->fetch();
            if (!$agent_row) throw new Exception("Agent not found.");

            if ($coordinator_lga_id && (int)$agent_row['lga_id'] !== $coordinator_lga_id && $agent_row['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            $token = bin2hex(random_bytes(32));
            $upd = $pdo->prepare("
                UPDATE agents 
                SET password_reset_token = ?, password_reset_expiry = datetime('now', '+24 hours')
                WHERE id = ?
            ");
            $upd->execute([$token, $agent_id]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
            $link = "{$scheme}://{$host}/reset_password.php?token={$token}&email=" . urlencode($agent_row['email']);

            audit_log($pdo, 'admin', $admin['id'], 'generate_reset_link', 'agents', $agent_id, [
                'agent_email' => $agent_row['email']
            ]);

            echo json_encode([
                'success' => true,
                'reset_link' => $link,
                'expires' => 'Valid for 24 hours'
            ]);
            break;
        }

        case 'reassign_agent_pu': {
            $agent_id = (int)($_POST['id'] ?? 0);
            $pu_id = (int)($_POST['polling_unit_id'] ?? 0);
            $pu_name = trim($_POST['polling_unit'] ?? '');

            $chk = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
            $chk->execute([$agent_id]);
            $agent_row = $chk->fetch();
            if (!$agent_row) throw new Exception("Agent not found.");

            if ($coordinator_lga_id && (int)$agent_row['lga_id'] !== $coordinator_lga_id && $agent_row['lga'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Permission denied for other LGA.");
            }

            if ($pu_id > 0) {
                $pu_stmt = $pdo->prepare("
                    SELECT p.id as pu_id, p.name as pu_name,
                           w.id as ward_id, w.name as ward_name,
                           l.id as lga_id, l.name as lga_name
                    FROM polling_units p
                    JOIN wards w ON p.ward_id = w.id
                    JOIN lgas l ON w.lga_id = l.id
                    WHERE p.id = ?
                ");
                $pu_stmt->execute([$pu_id]);
                $geo = $pu_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$geo) throw new Exception("Selected polling unit not found in master directory.");

                $stmt = $pdo->prepare("
                    UPDATE agents 
                    SET polling_unit = ?, ward = ?, lga = ?, polling_unit_id = ?, ward_id = ?, lga_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$geo['pu_name'], $geo['ward_name'], $geo['lga_name'], $geo['pu_id'], $geo['ward_id'], $geo['lga_id'], $agent_id]);
                $pu_name = $geo['pu_name'];
            } else {
                if (empty($pu_name)) throw new Exception("Polling unit name or ID is required.");
                $ward_name = trim($_POST['ward'] ?? '');
                $lga_name = trim($_POST['lga'] ?? '');
                $stmt = $pdo->prepare("
                    UPDATE agents 
                    SET polling_unit = ?, ward = COALESCE(NULLIF(?, ''), ward), lga = COALESCE(NULLIF(?, ''), lga)
                    WHERE id = ?
                ");
                $stmt->execute([$pu_name, $ward_name, $lga_name, $agent_id]);
            }

            audit_log($pdo, 'admin', $admin['id'], 'reassign_agent_pu', 'agents', $agent_id, [
                'old_pu' => $agent_row['polling_unit'],
                'new_pu' => $pu_name
            ]);

            echo json_encode(['success' => true, 'message' => "Agent successfully reassigned to {$pu_name}."]);
            break;
        }

        case 'add_agent': {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['accredited', 'pending'], true) ? $_POST['status'] : 'accredited';
            $pu_id = (int)($_POST['polling_unit_id'] ?? 0);

            if (empty($name)) throw new Exception("Agent full name is required.");
            if (empty($phone)) throw new Exception("Agent phone number is required.");
            if ($pu_id <= 0) throw new Exception("Please select an official Polling Unit from the dropdown directory.");

            // Fetch official PU, Ward, and LGA
            $pu_stmt = $pdo->prepare("
                SELECT p.id as pu_id, p.name as pu_name, p.code as pu_code,
                       w.id as ward_id, w.name as ward_name,
                       l.id as lga_id, l.name as lga_name
                FROM polling_units p
                JOIN wards w ON p.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                WHERE p.id = ?
            ");
            $pu_stmt->execute([$pu_id]);
            $geo = $pu_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$geo) throw new Exception("Selected polling unit was not found in directory.");

            // Coordinator scope check
            if ($coordinator_lga_id && (int)$geo['lga_id'] !== $coordinator_lga_id && $geo['lga_name'] !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Access Denied: You may only assign agents within your designated LGA.");
            }

            // Generate unique agent code
            $code_prefix = 'AGT-';
            $agent_code = '';
            for ($attempt = 0; $attempt < 15; $attempt++) {
                $candidate = $code_prefix . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                $chk_code = $pdo->prepare("SELECT id FROM agents WHERE agent_code = ?");
                $chk_code->execute([$candidate]);
                if (!$chk_code->fetchColumn()) {
                    $agent_code = $candidate;
                    break;
                }
            }
            if (!$agent_code) {
                $agent_code = $code_prefix . time();
            }

            // Default email if none provided
            if (empty($email)) {
                $email = strtolower($agent_code) . '@sia2027.ng';
            }

            // Check duplicate phone or email
            $chk_dup = $pdo->prepare("SELECT id FROM agents WHERE phone = ? OR email = ?");
            $chk_dup->execute([$phone, $email]);
            if ($chk_dup->fetchColumn()) {
                throw new Exception("An agent with this phone number or email is already registered.");
            }

            // Password
            if (empty($password)) {
                $password = 'Agent2027!';
            }
            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert into agents
            $ins = $pdo->prepare("
                INSERT INTO agents (
                    name, email, phone, password_hash, role, state,
                    lga, lga_id, ward, ward_id, polling_unit, polling_unit_id,
                    agent_code, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, 'agent', 'Adamawa',
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, datetime('now')
                )
            ");
            $ins->execute([
                $name, $email, $phone, $pwd_hash,
                $geo['lga_name'], $geo['lga_id'],
                $geo['ward_name'], $geo['ward_id'],
                $geo['pu_name'], $geo['pu_id'],
                $agent_code, $status
            ]);
            $new_agent_id = (int)$pdo->lastInsertId();

            audit_log($pdo, 'admin', $admin['id'], 'add_agent', 'agents', $new_agent_id, [
                'name' => $name,
                'agent_code' => $agent_code,
                'polling_unit' => $geo['pu_name'],
                'lga' => $geo['lga_name']
            ]);

            echo json_encode([
                'success' => true,
                'message' => "Field Agent {$name} ({$agent_code}) successfully created and assigned to {$geo['pu_name']}."
            ]);
            break;
        }

        case 'delete_agent': {
            $agent_id = (int)($_POST['id'] ?? 0);
            if ($agent_id <= 0) throw new Exception("Invalid agent ID.");

            $chk = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
            $chk->execute([$agent_id]);
            $agent = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$agent) throw new Exception("Agent not found.");

            // Coordinator scope check
            if ($coordinator_lga_id && (int)($agent['lga_id'] ?? 0) !== $coordinator_lga_id && ($agent['lga'] ?? '') !== $coordinator_lga_name) {
                http_response_code(403);
                throw new Exception("Access Denied: You may only delete agents within your designated LGA.");
            }

            $del = $pdo->prepare("DELETE FROM agents WHERE id = ?");
            $del->execute([$agent_id]);

            audit_log($pdo, 'admin', $admin['id'], 'delete_agent', 'agents', $agent_id, [
                'name' => $agent['name'],
                'agent_code' => $agent['agent_code'] ?? '',
                'polling_unit' => $agent['polling_unit'] ?? ''
            ]);

            echo json_encode([
                'success' => true,
                'message' => "Agent {$agent['name']} has been permanently deleted."
            ]);
            break;
        }

        case 'get_lgas': {
            if ($coordinator_lga_id) {
                $stmt = $pdo->prepare("SELECT id, name, code FROM lgas WHERE id = ? ORDER BY name ASC");
                $stmt->execute([$coordinator_lga_id]);
            } else {
                $stmt = $pdo->query("SELECT id, name, code FROM lgas ORDER BY name ASC");
            }
            echo json_encode(['success' => true, 'lgas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'get_wards': {
            $lga_id = (int)($_GET['lga_id'] ?? 0);
            if ($coordinator_lga_id && $lga_id !== $coordinator_lga_id) {
                $lga_id = $coordinator_lga_id;
            }
            if ($lga_id > 0) {
                $stmt = $pdo->prepare("SELECT id, name, code FROM wards WHERE lga_id = ? ORDER BY name ASC");
                $stmt->execute([$lga_id]);
            } else {
                $stmt = $pdo->query("SELECT id, name, code, lga_id FROM wards ORDER BY name ASC");
            }
            echo json_encode(['success' => true, 'wards' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'get_pus': {
            $ward_id = (int)($_GET['ward_id'] ?? 0);
            if ($ward_id > 0) {
                $stmt = $pdo->prepare("SELECT id, name, code, registered_voters FROM polling_units WHERE ward_id = ? ORDER BY name ASC");
                $stmt->execute([$ward_id]);
            } else {
                $stmt = $pdo->query("SELECT id, name, code, ward_id, registered_voters FROM polling_units ORDER BY name ASC LIMIT 100");
            }
            echo json_encode(['success' => true, 'polling_units' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'agents_csv_import': {
            $dry_run = !empty($_POST['dry_run']);
            $csv_data = '';

            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $csv_data = file_get_contents($_FILES['csv_file']['tmp_name']);
            } elseif (!empty($_POST['csv_text'])) {
                $csv_data = trim($_POST['csv_text']);
            } else {
                throw new Exception("Please upload a CSV file or paste CSV text.");
            }

            $lines = preg_split('/[\r\n]+/', trim($csv_data));
            if (empty($lines)) throw new Exception("Empty CSV data provided.");

            $header = str_getcsv(array_shift($lines));
            $map = [];
            foreach ($header as $idx => $col) {
                $clean = strtolower(trim($col));
                if (str_contains($clean, 'name')) $map['name'] = $idx;
                if (str_contains($clean, 'email')) $map['email'] = $idx;
                if (str_contains($clean, 'phone')) $map['phone'] = $idx;
                if (str_contains($clean, 'lga')) $map['lga'] = $idx;
                if (str_contains($clean, 'ward')) $map['ward'] = $idx;
                if (str_contains($clean, 'unit') || str_contains($clean, 'pu')) $map['pu'] = $idx;
            }

            if (!isset($map['name']) || !isset($map['email']) || !isset($map['pu'])) {
                throw new Exception("CSV header must contain at least 'name', 'email', and 'polling_unit' columns.");
            }

            $valid_rows = [];
            $errors = [];
            $row_num = 1;

            foreach ($lines as $line) {
                $row_num++;
                if (empty(trim($line))) continue;
                $row = str_getcsv($line);

                $name = trim($row[$map['name']] ?? '');
                $email = strtolower(trim($row[$map['email']] ?? ''));
                $phone = trim($row[$map['phone'] ?? -1] ?? '');
                $lga = trim($row[$map['lga'] ?? -1] ?? ($coordinator_lga_name ?? 'Adamawa Central'));
                $ward = trim($row[$map['ward'] ?? -1] ?? 'Collation Sector 1');
                $pu = trim($row[$map['pu']] ?? '');

                if (empty($name) || empty($email) || empty($pu)) {
                    $errors[] = "Row {$row_num}: Name, email, and polling unit cannot be blank.";
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$row_num}: Invalid email '{$email}'.";
                    continue;
                }

                if ($coordinator_lga_name && strcasecmp($lga, $coordinator_lga_name) !== 0) {
                    $errors[] = "Row {$row_num}: Cannot import agent for '{$lga}' outside your LGA scope ({$coordinator_lga_name}).";
                    continue;
                }

                $valid_rows[] = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'lga' => $lga,
                    'ward' => $ward,
                    'pu' => $pu
                ];
            }

            if ($dry_run) {
                echo json_encode([
                    'success' => true,
                    'dry_run' => true,
                    'total_rows_parsed' => count($valid_rows) + count($errors),
                    'valid_count' => count($valid_rows),
                    'error_count' => count($errors),
                    'preview' => array_slice($valid_rows, 0, 5),
                    'errors' => $errors
                ]);
                break;
            }

            // Commit import
            $pdo->beginTransaction();
            $imported_count = 0;
            $ins_stmt = $pdo->prepare("
                INSERT INTO agents (name, email, phone, lga, ward, polling_unit, agent_code, password_hash, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'accredited', datetime('now'))
            ");

            $default_hash = password_hash('SiaAdamawa2027!', PASSWORD_BCRYPT);

            foreach ($valid_rows as $vr) {
                // Check if email already exists
                $exist_chk = $pdo->prepare("SELECT id FROM agents WHERE LOWER(email) = ?");
                $exist_chk->execute([$vr['email']]);
                if ($exist_chk->fetch()) {
                    continue; // Skip duplicates
                }

                $code = 'SIA-LP-AD-27-' . mt_rand(1000, 9999);
                $ins_stmt->execute([
                    $vr['name'], $vr['email'], $vr['phone'], $vr['lga'], $vr['ward'], $vr['pu'], $code, $default_hash
                ]);
                $imported_count++;
            }

            $pdo->commit();

            audit_log($pdo, 'admin', $admin['id'], 'agents_csv_import', 'agents', null, [
                'imported_count' => $imported_count
            ]);

            echo json_encode([
                'success' => true,
                'dry_run' => false,
                'imported_count' => $imported_count,
                'errors' => $errors,
                'message' => "Successfully imported {$imported_count} agents."
            ]);
            break;
        }

        // =================================================================
        // 7. COVERAGE TRACKER
        // =================================================================
        case 'coverage_tracker': {
            $scope = get_coordinator_pu_condition($pdo, $coordinator_lga_id, $coordinator_lga_name, 'rs');
            $lga_where = $coordinator_lga_id ? "WHERE l.id = {$coordinator_lga_id}" : "";

            // LGA summary drill-down
            $lgas_stmt = $pdo->query("
                SELECT 
                    l.id as lga_id,
                    l.name as lga_name,
                    l.code as lga_code,
                    COUNT(DISTINCT pu.id) as total_pus,
                    COUNT(DISTINCT a.id) as assigned_agents,
                    COUNT(DISTINCT rs.polling_unit_id) as reported_pus
                FROM lgas l
                LEFT JOIN wards w ON l.id = w.lga_id
                LEFT JOIN polling_units pu ON w.id = pu.ward_id
                LEFT JOIN agents a ON pu.id = a.polling_unit_id AND a.status = 'accredited'
                LEFT JOIN result_sheets rs ON pu.id = rs.polling_unit_id AND rs.is_current = 1 AND rs.verification_status = 'verified'
                {$lga_where}
                GROUP BY l.id, l.name, l.code
                ORDER BY l.name ASC
            ");
            $lgas = $lgas_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Unreported PUs list (has agent or no agent, but no verified result sheet)
            $unreported_stmt = $pdo->query("
                SELECT 
                    pu.id,
                    pu.code as pu_code,
                    pu.name as pu_name,
                    w.name as ward_name,
                    l.name as lga_name,
                    a.id as agent_id,
                    a.name as agent_name,
                    a.phone as agent_phone,
                    a.agent_code
                FROM polling_units pu
                JOIN wards w ON pu.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                LEFT JOIN agents a ON pu.id = a.polling_unit_id AND a.status = 'accredited'
                LEFT JOIN result_sheets rs ON pu.id = rs.polling_unit_id AND rs.is_current = 1 AND rs.verification_status = 'verified'
                WHERE rs.id IS NULL
                {$lga_where}
                ORDER BY l.name, w.name, pu.name
                LIMIT 150
            ");
            $unreported_pus = $unreported_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'lgas' => $lgas,
                'unreported_pus' => $unreported_pus
            ]);
            break;
        }

        // =================================================================
        // 8. INCIDENT CENTER
        // =================================================================
        case 'incidents': {
            $status = $_GET['status'] ?? 'all';
            $severity = $_GET['severity'] ?? 'all';
            $scope = get_coordinator_pu_condition($pdo, $coordinator_lga_id, $coordinator_lga_name, 'i');

            $where = ["1=1"];
            $params = [];

            if ($coordinator_lga_id || $coordinator_lga_name) {
                $where[] = "(i.lga_id = ? OR i.lga = ?)";
                $params[] = $coordinator_lga_id;
                $params[] = $coordinator_lga_name;
            }

            if ($status !== 'all') {
                $where[] = "i.status = ?";
                $params[] = $status;
            }

            if ($severity !== 'all') {
                $where[] = "i.severity = ?";
                $params[] = $severity;
            }

            $where_sql = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT 
                    i.*,
                    a.name as agent_name,
                    a.phone as agent_phone,
                    a.agent_code,
                    adm.name as assigned_admin_name
                FROM incidents i
                LEFT JOIN agents a ON i.agent_id = a.id
                LEFT JOIN admins adm ON i.assigned_to = adm.id
                WHERE {$where_sql}
                ORDER BY 
                    CASE i.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'urgent' THEN 2 
                        ELSE 3 
                    END,
                    i.id DESC
            ");
            $stmt->execute($params);
            $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'incidents' => $incidents
            ]);
            break;
        }

        case 'update_incident': {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'in_progress';
            $severity = $_POST['severity'] ?? 'normal';
            $resolution_note = trim($_POST['resolution_note'] ?? '');
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $admin['id'];

            $chk = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
            $chk->execute([$id]);
            $inc = $chk->fetch();
            if (!$inc) throw new Exception("Incident not found.");

            $resolved_at = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("
                UPDATE incidents 
                SET status = ?, severity = ?, assigned_to = ?, resolution_note = ?, resolved_at = COALESCE(?, resolved_at)
                WHERE id = ?
            ");
            $stmt->execute([$status, $severity, $assigned_to, $resolution_note, $resolved_at, $id]);

            audit_log($pdo, 'admin', $admin['id'], 'update_incident', 'incidents', $id, [
                'status' => $status,
                'severity' => $severity,
                'assigned_to' => $assigned_to
            ]);

            echo json_encode(['success' => true, 'message' => "Incident #{$id} status updated to {$status}."]);
            break;
        }

        // =================================================================
        // 9. BROADCAST ANNOUNCEMENTS
        // =================================================================
        case 'announcements': {
            $stmt = $pdo->query("
                SELECT 
                    a.*,
                    adm.name as author_name,
                    COUNT(r.agent_id) as read_count
                FROM announcements a
                LEFT JOIN admins adm ON a.created_by = adm.id
                LEFT JOIN announcement_reads r ON a.id = r.announcement_id
                GROUP BY a.id
                ORDER BY a.id DESC
                LIMIT 50
            ");
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'announcements' => $announcements
            ]);
            break;
        }

        case 'create_announcement': {
            $title = trim($_POST['title'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $audience = trim($_POST['audience'] ?? 'all');
            $priority = $_POST['priority'] ?? 'normal';
            $expires_in_hours = (int)($_POST['expires_in_hours'] ?? 48);

            if (empty($title) || empty($body)) {
                throw new Exception("Title and broadcast body are required.");
            }

            // Coordinator restriction: cannot send global 'all' announcements
            if ($coordinator_lga_id && $audience === 'all') {
                $audience = "lga:{$coordinator_lga_id}";
            }

            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in_hours} hours"));

            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, body, audience, priority, created_by, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$title, $body, $audience, $priority, $admin['id'], $expires_at]);
            $ann_id = (int)$pdo->lastInsertId();

            audit_log($pdo, 'admin', $admin['id'], 'create_announcement', 'announcements', $ann_id, [
                'title' => $title,
                'audience' => $audience,
                'priority' => $priority
            ]);

            echo json_encode(['success' => true, 'message' => "Broadcast announcement dispatched successfully."]);
            break;
        }

        case 'delete_announcement': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("Invalid broadcast directive ID.");
            }

            if ($coordinator_lga_id) {
                $check = $pdo->prepare("SELECT created_by, audience FROM announcements WHERE id = ?");
                $check->execute([$id]);
                $row = $check->fetch();
                if (!$row || ($row['created_by'] != $admin['id'] && $row['audience'] !== "lga:{$coordinator_lga_id}")) {
                    throw new Exception("You do not have permission to delete this broadcast.");
                }
            }

            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);

            audit_log($pdo, 'admin', $admin['id'], 'delete_announcement', 'announcements', $id);
            echo json_encode(['success' => true, 'message' => "Broadcast directive deleted successfully."]);
            break;
        }

        // =================================================================
        // 9. GEOGRAPHY & POLLING UNITS MANAGEMENT
        // =================================================================
        case 'geography_summary': {
            $total_lgas = (int)$pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn();
            $total_wards = (int)$pdo->query("SELECT COUNT(*) FROM wards")->fetchColumn();
            $total_pus = (int)$pdo->query("SELECT COUNT(*) FROM polling_units")->fetchColumn();
            $total_voters = (int)$pdo->query("SELECT COALESCE(SUM(registered_voters), 0) FROM polling_units")->fetchColumn();

            $search = trim($_GET['search'] ?? '');
            $lga_filter = !empty($_GET['lga_id']) ? (int)$_GET['lga_id'] : null;

            // LGA breakdown
            $lgas_stmt = $pdo->query("
                SELECT 
                    l.id, l.name, l.code,
                    COUNT(DISTINCT w.id) as ward_count,
                    COUNT(pu.id) as pu_count,
                    COALESCE(SUM(pu.registered_voters), 0) as registered_voters
                FROM lgas l
                LEFT JOIN wards w ON l.id = w.lga_id
                LEFT JOIN polling_units pu ON w.id = pu.ward_id
                GROUP BY l.id, l.name, l.code
                ORDER BY l.name ASC
            ");
            $lgas = $lgas_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sample / queryable polling units list (paginated, max 100)
            $where = ["1=1"];
            $params = [];
            if ($lga_filter) {
                $where[] = "l.id = ?";
                $params[] = $lga_filter;
            }
            if ($search !== '') {
                $where[] = "(pu.name LIKE ? OR pu.code LIKE ? OR w.name LIKE ? OR l.name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            $where_sql = implode(' AND ', $where);

            $stmt_pu = $pdo->prepare("
                SELECT pu.id, pu.code, pu.name, pu.registered_voters, w.name as ward_name, w.code as ward_code, l.name as lga_name, l.code as lga_code
                FROM polling_units pu
                JOIN wards w ON pu.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                WHERE {$where_sql}
                ORDER BY l.name ASC, w.name ASC, pu.code ASC
                LIMIT 100
            ");
            $stmt_pu->execute($params);
            $pus = $stmt_pu->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'summary' => [
                    'total_lgas' => $total_lgas,
                    'total_wards' => $total_wards,
                    'total_polling_units' => $total_pus,
                    'total_registered_voters' => $total_voters
                ],
                'lgas' => $lgas,
                'polling_units' => $pus
            ]);
            break;
        }

        case 'geography_csv_import': {
            $dry_run = !empty($_POST['dry_run']);
            $reload_seed = !empty($_POST['reload_seed']);
            $csv_file_path = null;
            $tmp_created = false;

            if ($reload_seed) {
                $csv_file_path = __DIR__ . '/database/seed/polling_units.csv';
                if (!file_exists($csv_file_path)) {
                    throw new Exception("Master seed file database/seed/polling_units.csv not found.");
                }
            } elseif (!empty($_FILES['csv_file']['tmp_name'])) {
                $csv_file_path = $_FILES['csv_file']['tmp_name'];
            } elseif (!empty($_POST['csv_text'])) {
                $tmp_path = sys_get_temp_dir() . '/pu_import_' . uniqid() . '.csv';
                file_put_contents($tmp_path, trim($_POST['csv_text']));
                $csv_file_path = $tmp_path;
                $tmp_created = true;
            } else {
                throw new Exception("Please upload a CSV file, paste CSV text, or select reload master seed.");
            }

            if ($dry_run) {
                $handle = fopen($csv_file_path, 'r');
                if (!$handle) throw new Exception("Unable to open CSV for inspection.");
                $header = fgetcsv($handle, 0, ',', '"', '\\');
                $valid = 0;
                $errors = [];
                $row_i = 1;
                while (($r = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                    $row_i++;
                    if (count($r) < 6 || empty(trim($r[0])) || empty(trim($r[5]))) {
                        if (count($errors) < 15) $errors[] = "Row {$row_i}: Missing LGA name or PU code.";
                    } else {
                        $valid++;
                    }
                }
                fclose($handle);
                if ($tmp_created && file_exists($csv_file_path)) @unlink($csv_file_path);

                echo json_encode([
                    'success' => true,
                    'dry_run' => true,
                    'valid_count' => $valid,
                    'error_count' => count($errors),
                    'errors' => $errors,
                    'message' => "Dry-run preview: {$valid} valid rows ready for import."
                ]);
                break;
            }

            $res = import_polling_units_csv($pdo, $csv_file_path);
            if ($tmp_created && file_exists($csv_file_path)) @unlink($csv_file_path);

            if (!$res['success']) {
                throw new Exception($res['error'] ?? 'Geography import failed.');
            }

            try {
                $pdo->exec("DELETE FROM polling_units WHERE name LIKE '[Placeholder]%'");
                $pdo->exec("DELETE FROM wards WHERE name LIKE '[Placeholder]%'");
            } catch (Exception $e) {}

            audit_log($pdo, 'admin', $admin['id'], 'geography_csv_import', 'polling_units', null, [
                'rows_imported' => $res['rows_imported'],
                'reload_seed' => $reload_seed
            ]);

            echo json_encode([
                'success' => true,
                'dry_run' => false,
                'rows_imported' => $res['rows_imported'],
                'total_lgas' => (int)$pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn(),
                'total_wards' => (int)$pdo->query("SELECT COUNT(*) FROM wards")->fetchColumn(),
                'total_polling_units' => (int)$pdo->query("SELECT COUNT(*) FROM polling_units")->fetchColumn(),
                'message' => "Successfully imported {$res['rows_imported']} polling units into official database."
            ]);
            break;
        }

        // =================================================================
        // 10. AUDIT LOG VIEWER
        // =================================================================
        case 'audit_log': {
            $actor_type = $_GET['actor_type'] ?? 'all';
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = min(100, max(15, (int)($_GET['per_page'] ?? 30)));
            $offset = ($page - 1) * $per_page;

            $where = ["1=1"];
            $params = [];

            if ($actor_type !== 'all') {
                $where[] = "actor_type = ?";
                $params[] = $actor_type;
            }

            if (!empty($search)) {
                $where[] = "(action LIKE ? OR entity LIKE ? OR ip LIKE ? OR meta_json LIKE ?)";
                $s = "%{$search}%";
                $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
            }

            $where_sql = implode(' AND ', $where);

            $c_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE {$where_sql}");
            $c_stmt->execute($params);
            $total_records = (int)$c_stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT * FROM audit_log
                WHERE {$where_sql}
                ORDER BY id DESC
                LIMIT ? OFFSET ?
            ");
            $params_limit = array_merge($params, [$per_page, $offset]);
            $stmt->execute($params_limit);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_records,
                'logs' => $logs
            ]);
            break;
        }

        // =================================================================
        // 11. SETTINGS & SYSTEM SWITCHES
        // =================================================================
        case 'settings': {
            if ($is_post) {
                if ($role !== 'superadmin') {
                    http_response_code(403);
                    throw new Exception("Only Superadministrator can modify global electoral settings.");
                }

                $keys = [
                    'election_name', 'election_date', 'submissions_open',
                    'public_results_visible', 'public_label_text',
                    'require_review_before_public', 'max_upload_mb'
                ];

                foreach ($keys as $k) {
                    if (isset($_POST[$k])) {
                        set_setting($pdo, $k, trim((string)$_POST[$k]));
                    }
                }

                audit_log($pdo, 'admin', $admin['id'], 'update_settings', 'settings', null, $_POST);
                echo json_encode(['success' => true, 'message' => "Settings updated successfully."]);
                break;
            }

            // GET settings
            $settings = get_all_settings($pdo);
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
        }

        // =================================================================
        // 12. ADMIN USER MANAGEMENT (Superadmin Only)
        // =================================================================
        case 'admin_users': {
            if ($role !== 'superadmin') {
                http_response_code(403);
                throw new Exception("Superadmin privilege required to manage admin users.");
            }

            $users = $pdo->query("
                SELECT a.id, a.name, a.email, a.role, a.lga_id, a.status, a.last_login_at, a.created_at, l.name as lga_name
                FROM admins a
                LEFT JOIN lgas l ON a.lga_id = l.id
                ORDER BY a.id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'admins' => $users]);
            break;
        }

        case 'create_admin_user': {
            if ($role !== 'superadmin') {
                http_response_code(403);
                throw new Exception("Superadmin privilege required.");
            }

            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $new_role = $_POST['role'] ?? 'analyst';
            $lga_id = !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null;
            $password = $_POST['password'] ?? '';

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception("Name, email, and initial password are required.");
            }

            if (!in_array($new_role, ['superadmin', 'coordinator', 'analyst'], true)) {
                throw new Exception("Invalid role specified.");
            }

            $chk = $pdo->prepare("SELECT id FROM admins WHERE LOWER(email) = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) throw new Exception("An administrator with email '{$email}' already exists.");

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO admins (name, email, password_hash, role, lga_id, status, must_change_password, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', 1, datetime('now'))
            ");
            $stmt->execute([$name, $email, $hash, $new_role, $lga_id]);
            $new_id = (int)$pdo->lastInsertId();

            audit_log($pdo, 'admin', $admin['id'], 'create_admin_user', 'admins', $new_id, [
                'name' => $name, 'email' => $email, 'role' => $new_role, 'lga_id' => $lga_id
            ]);

            echo json_encode(['success' => true, 'message' => "Administrator user '{$name}' created successfully."]);
            break;
        }

        case 'update_admin_user': {
            if ($role !== 'superadmin') {
                http_response_code(403);
                throw new Exception("Superadmin privilege required.");
            }

            $target_id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            $target_role = $_POST['role'] ?? 'analyst';
            $lga_id = !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null;

            if ($target_id === $admin['id'] && $status !== 'active') {
                throw new Exception("You cannot disable your own active Superadmin session.");
            }

            $stmt = $pdo->prepare("UPDATE admins SET status = ?, role = ?, lga_id = ? WHERE id = ?");
            $stmt->execute([$status, $target_role, $lga_id, $target_id]);

            audit_log($pdo, 'admin', $admin['id'], 'update_admin_user', 'admins', $target_id, [
                'status' => $status, 'role' => $target_role, 'lga_id' => $lga_id
            ]);

            echo json_encode(['success' => true, 'message' => "Administrator user settings updated."]);
            break;
        }

        // =================================================================
        // 13. CHANGE OWN PASSWORD
        // =================================================================
        case 'change_password': {
            $curr = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($curr) || empty($new)) {
                throw new Exception("Current and new password are required.");
            }
            if ($new !== $confirm) {
                throw new Exception("New passwords do not match.");
            }
            if (strlen($new) < 8) {
                throw new Exception("New password must be at least 8 characters.");
            }

            $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
            $stmt->execute([$admin['id']]);
            $current_hash = $stmt->fetchColumn();

            if (!password_verify($curr, $current_hash)) {
                throw new Exception("Current password is incorrect.");
            }

            $new_hash = password_hash($new, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?");
            $upd->execute([$new_hash, $admin['id']]);

            $_SESSION['admin']['must_change_password'] = 0;

            audit_log($pdo, 'admin', $admin['id'], 'change_own_password', 'admins', $admin['id'], []);

            echo json_encode(['success' => true, 'message' => "Password changed successfully."]);
            break;
        }

        // =================================================================
        // 14. EXPORT CSV ENGINE
        // =================================================================
        case 'export_csv': {
            $type = $_GET['type'] ?? 'pu_results';
            $filename = "sia2027_{$type}_" . date('Ymd_His') . ".csv";

            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");

            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            if ($type === 'pu_results') {
                $party_headers = [];
                $party_cols = [];
                foreach (get_official_parties() as $code => $p) {
                    $party_headers[] = $code . ' Votes';
                    $party_cols[] = $p['column'];
                }
                $headers = array_merge(
                    ['Sheet ID', 'LGA', 'Ward', 'Polling Unit'],
                    $party_headers,
                    ['Total Valid', 'Rejected', 'Accredited', 'Status', 'Image Filename', 'Date']
                );
                fputcsv($out, $headers, ',', '"', '\\');

                $cols_sql = implode(', ', $party_cols);
                $rows = $pdo->query("SELECT id, lga, ward, polling_unit, {$cols_sql}, total_valid_votes, rejected_votes, COALESCE(total_accredited, total_accredited_voters, 0), verification_status, ec8a_image, created_at FROM result_sheets WHERE is_current = 1 ORDER BY lga, ward, polling_unit")->fetchAll(PDO::FETCH_NUM);
                foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            } elseif ($type === 'lga_results') {
                fputcsv($out, ['LGA', 'Reported PUs', 'LP Votes', 'APC Votes', 'PDP Votes', 'Total Valid', 'Rejected', 'LP Margin Pct'], ',', '"', '\\');
                $rows = $pdo->query("
                    SELECT 
                        lga, COUNT(DISTINCT polling_unit) as pus, SUM(lp_votes), SUM(apc_votes), SUM(pdp_votes),
                        SUM(total_valid_votes), SUM(rejected_votes),
                        ROUND(CAST(SUM(lp_votes) AS REAL) / MAX(1, SUM(total_valid_votes)) * 100, 1)
                    FROM result_sheets WHERE is_current = 1 GROUP BY lga ORDER BY lga
                ")->fetchAll(PDO::FETCH_NUM);
                foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            } elseif ($type === 'agents') {
                fputcsv($out, ['Agent ID', 'Name', 'Email', 'Phone', 'LGA', 'Ward', 'Polling Unit', 'Agent Code', 'Status', 'Registered At'], ',', '"', '\\');
                $rows = $pdo->query("SELECT id, name, email, phone, lga, ward, polling_unit, agent_code, status, created_at FROM agents ORDER BY lga, name")->fetchAll(PDO::FETCH_NUM);
                foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            } elseif ($type === 'incidents') {
                fputcsv($out, ['ID', 'LGA', 'Ward', 'Polling Unit', 'Title', 'Category', 'Severity', 'Status', 'Resolution Note', 'Reported At'], ',', '"', '\\');
                $rows = $pdo->query("SELECT id, lga, ward, polling_unit, title, category, severity, status, resolution_note, created_at FROM incidents ORDER BY id DESC")->fetchAll(PDO::FETCH_NUM);
                foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            } elseif ($type === 'audit_log') {
                fputcsv($out, ['Log ID', 'Actor Type', 'Actor ID', 'Action', 'Entity', 'Entity ID', 'IP Address', 'Meta JSON', 'Timestamp'], ',', '"', '\\');
                $rows = $pdo->query("SELECT id, actor_type, actor_id, action, entity, entity_id, ip, meta_json, created_at FROM audit_log ORDER BY id DESC LIMIT 5000")->fetchAll(PDO::FETCH_NUM);
                foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            } elseif ($type === 'geography' || $type === 'polling_units') {
                fputcsv($out, ['LGA Name', 'LGA Code', 'Ward Name', 'Ward Code', 'Polling Unit Name', 'PU Code', 'Registered Voters'], ',', '"', '\\');
                $rows = $pdo->query("
                    SELECT l.name, l.code, w.name, w.code, pu.name, pu.code, pu.registered_voters
                    FROM polling_units pu
                    JOIN wards w ON pu.ward_id = w.id
                    JOIN lgas l ON w.lga_id = l.id
                    ORDER BY l.name ASC, w.name ASC, pu.code ASC
                ")->fetchAll(PDO::FETCH_NUM);
                foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            }

            fclose($out);
            audit_log($pdo, 'admin', $admin['id'], 'export_csv', 'reports', null, ['type' => $type]);
            exit;
        }

        // =================================================================
        // 15. BACKUP DATABASE (SQLite VACUUM INTO)
        // =================================================================
        case 'backup_database': {
            if ($role !== 'superadmin') {
                http_response_code(403);
                throw new Exception("Superadmin privilege required to export raw database backup.");
            }

            $db_path = __DIR__ . '/database/sia2027.sqlite';
            if (!file_exists($db_path)) {
                throw new Exception("Database file not found on disk.");
            }

            $backup_filename = "sia2027_sqlite_backup_" . date('Ymd_His') . ".sqlite";
            $tmp_target = sys_get_temp_dir() . '/' . $backup_filename;

            // Use SQLite VACUUM INTO for a consistent point-in-time snapshot
            try {
                if (file_exists($tmp_target)) @unlink($tmp_target);
                $escaped = str_replace("'", "''", $tmp_target);
                $pdo->exec("VACUUM INTO '{$escaped}'");
                $source_to_send = $tmp_target;
            } catch (Throwable $e) {
                // Fallback copy
                $source_to_send = $db_path;
            }

            audit_log($pdo, 'admin', $admin['id'], 'backup_database', 'system', null, [
                'filename' => $backup_filename
            ]);

            header('Content-Type: application/x-sqlite3');
            header("Content-Disposition: attachment; filename=\"{$backup_filename}\"");
            header('Content-Length: ' . filesize($source_to_send));
            readfile($source_to_send);

            if ($source_to_send === $tmp_target && file_exists($tmp_target)) {
                @unlink($tmp_target);
            }
            exit;
        }

        // =================================================================
        // 16. BACKUP UPLOADS ARCHIVE
        // =================================================================
        case 'backup_uploads': {
            if ($role !== 'superadmin') {
                http_response_code(403);
                throw new Exception("Superadmin privilege required to export uploads archive.");
            }

            $zip_filename = "sia2027_uploads_" . date('Ymd_His') . ".zip";
            $tmp_zip = sys_get_temp_dir() . '/' . $zip_filename;
            if (file_exists($tmp_zip)) @unlink($tmp_zip);

            $zip = new ZipArchive();
            if ($zip->open($tmp_zip, ZipArchive::CREATE) !== true) {
                throw new Exception("Could not create ZIP archive on server.");
            }

            $uploads_dir = __DIR__ . '/uploads';
            if (is_dir($uploads_dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($uploads_dir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();

            audit_log($pdo, 'admin', $admin['id'], 'backup_uploads', 'system', null, [
                'filename' => $zip_filename
            ]);

            header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename=\"{$zip_filename}\"");
            header('Content-Length: ' . filesize($tmp_zip));
            readfile($tmp_zip);
            @unlink($tmp_zip);
            exit;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Unknown action '{$action}'."]);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
