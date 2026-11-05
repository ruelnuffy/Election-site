<?php
/**
 * SIA 2027 Senatorial District Data Engine
 * Zonal Collation, Spread Tracking, Ground Telemetry, and Ward Drill-Downs
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Overview statistics and KPIs for a Senatorial District
 */
function get_senatorial_overview_data(PDO $pdo, string $district_key): array {
    $district = get_senatorial_district($district_key) ?: get_senatorial_district('north');
    $lgas = $district['lgas'];
    $lga_lowers = array_map('strtolower', $lgas);
    $placeholders = !empty($lgas) ? implode(',', array_fill(0, count($lgas), '?')) : "''";

    // 1. Total PUs & Registered Voters
    $stmt_pus = $pdo->prepare("
        SELECT COUNT(p.id) as total_pus, COALESCE(SUM(p.registered_voters), 0) as total_registered
        FROM polling_units p
        JOIN wards w ON p.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE LOWER(l.name) IN ($placeholders)
    ");
    $stmt_pus->execute($lga_lowers);
    $pu_meta = $stmt_pus->fetch(PDO::FETCH_ASSOC);

    // 2. Reported Results Aggregation
    $party_sums = [];
    foreach (get_official_parties() as $code => $p) {
        $col = $p['column'];
        $party_sums[] = "COALESCE(SUM({$col}), 0) as total_{$col}";
    }
    $party_sums_sql = implode(",\n        ", $party_sums);

    $stmt_results = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sheets,
            COUNT(DISTINCT polling_unit) as reported_pus,
            {$party_sums_sql},
            COALESCE(SUM(rejected_votes), 0) as total_rejected,
            COALESCE(SUM(total_valid_votes), 0) as total_valid,
            COALESCE(SUM(total_accredited_voters), 0) as total_accredited
        FROM result_sheets
        WHERE is_current = 1 AND LOWER(lga) IN ($placeholders)
    ");
    $stmt_results->execute($lga_lowers);
    $res_meta = $stmt_results->fetch(PDO::FETCH_ASSOC);

    // 3. Ground Transmission Telemetry
    $stmt_trans = $pdo->prepare("
        SELECT 
            COALESCE(transmission_status, 'Reporting on PU ground') as status,
            COUNT(*) as count
        FROM result_sheets
        WHERE is_current = 1 AND LOWER(lga) IN ($placeholders)
        GROUP BY status
    ");
    $stmt_trans->execute($lga_lowers);
    $trans_rows = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);

    $transmission_stats = [
        'ground' => 0,
        'transit_10m' => 0,
        'transit_30m' => 0,
    ];
    foreach ($trans_rows as $tr) {
        $st = strtolower($tr['status'] ?? '');
        if (str_contains($st, 'ground')) {
            $transmission_stats['ground'] += (int)$tr['count'];
        } elseif (str_contains($st, '10 min')) {
            $transmission_stats['transit_10m'] += (int)$tr['count'];
        } elseif (str_contains($st, '30 min')) {
            $transmission_stats['transit_30m'] += (int)$tr['count'];
        } else {
            $transmission_stats['ground'] += (int)$tr['count'];
        }
    }

    // 4. Verification Status Distribution
    $stmt_status = $pdo->prepare("
        SELECT verification_status, COUNT(*) as cnt
        FROM result_sheets
        WHERE is_current = 1 AND LOWER(lga) IN ($placeholders)
        GROUP BY verification_status
    ");
    $stmt_status->execute($lga_lowers);
    $status_counts = [
        'verified' => 0,
        'pending' => 0,
        'flagged' => 0,
        'rejected' => 0
    ];
    foreach ($stmt_status->fetchAll(PDO::FETCH_ASSOC) as $s_row) {
        $status_counts[$s_row['verification_status']] = (int)$s_row['cnt'];
    }

    // 5. 25% Constitutional Spread per LGA
    $stmt_lga_spread = $pdo->prepare("
        SELECT 
            lga,
            COALESCE(SUM(total_valid_votes), 0) as valid_votes,
            COALESCE(SUM(lp_votes), 0) as lp_votes,
            COALESCE(SUM(apc_votes), 0) as apc_votes,
            COALESCE(SUM(pdp_votes), 0) as pdp_votes
        FROM result_sheets
        WHERE is_current = 1 AND LOWER(lga) IN ($placeholders)
        GROUP BY lga
    ");
    $stmt_lga_spread->execute($lga_lowers);
    $lga_spread_rows = $stmt_lga_spread->fetchAll(PDO::FETCH_ASSOC);

    $lga_spread_map = [];
    foreach ($lga_spread_rows as $ls) {
        $lga_spread_map[strtolower($ls['lga'])] = $ls;
    }

    $spread_summary = [];
    $passed_25_count = 0;
    foreach ($lgas as $l_name) {
        $ls_data = $lga_spread_map[strtolower($l_name)] ?? null;
        $valid = $ls_data ? (int)$ls_data['valid_votes'] : 0;
        $lp = $ls_data ? (int)$ls_data['lp_votes'] : 0;
        $pct = $valid > 0 ? round(($lp / $valid) * 100, 1) : 0;
        $passed = ($pct >= 25.0);
        if ($passed) $passed_25_count++;

        $spread_summary[] = [
            'lga' => $l_name,
            'valid_votes' => $valid,
            'lp_votes' => $lp,
            'lp_percentage' => $pct,
            'passed_25_pct' => $passed
        ];
    }

    // 6. Party Totals & Lead Calculation
    $total_valid = (int)($res_meta['total_valid'] ?? 0);
    $calc_valid = max(1, $total_valid);
    $parties_data = [];
    $max_votes = -1;
    $leading_code = null;

    foreach (get_official_parties() as $code => $p) {
        $votes = (int)($res_meta['total_' . $p['column']] ?? 0);
        $pct = round(($votes / $calc_valid) * 100, 1);
        if ($votes > $max_votes) {
            $max_votes = $votes;
            $leading_code = $code;
        }
        $parties_data[] = [
            'code' => $code,
            'name' => $p['name'],
            'candidate' => $p['candidate'],
            'color' => $p['color'],
            'bg_class' => $p['bg_class'],
            'votes' => $votes,
            'percentage' => $pct,
            'is_priority' => ($code === 'LP')
        ];
    }

    usort($parties_data, fn($a, $b) => $b['votes'] <=> $a['votes']);

    $lp_votes = (int)($res_meta['total_lp_votes'] ?? 0);
    $apc_votes = (int)($res_meta['total_apc_votes'] ?? 0);
    $pdp_votes = (int)($res_meta['total_pdp_votes'] ?? 0);
    $second_place_votes = max($apc_votes, $pdp_votes);
    $net_margin = $lp_votes - $second_place_votes;

    // 7. Incidents & Agents summary for this district
    $stmt_inc = $pdo->prepare("
        SELECT COUNT(*) as total_incidents,
               COALESCE(SUM(CASE WHEN severity = 'Critical' THEN 1 ELSE 0 END), 0) as critical_count
        FROM incidents
        WHERE LOWER(lga) IN ($placeholders)
    ");
    $stmt_inc->execute($lga_lowers);
    $inc_meta = $stmt_inc->fetch(PDO::FETCH_ASSOC);

    $stmt_ag = $pdo->prepare("
        SELECT COUNT(*) as total_agents,
               COALESCE(SUM(CASE WHEN photo IS NOT NULL AND photo != '' THEN 1 ELSE 0 END), 0) as agents_with_photo
        FROM agents
        WHERE LOWER(lga) IN ($placeholders)
    ");
    $stmt_ag->execute($lga_lowers);
    $ag_meta = $stmt_ag->fetch(PDO::FETCH_ASSOC);

    $total_pus = (int)($pu_meta['total_pus'] ?? 0);
    $reported_pus = (int)($res_meta['reported_pus'] ?? 0);
    $reporting_pct = $total_pus > 0 ? round(($reported_pus / $total_pus) * 100, 1) : 0;

    return [
        'success' => true,
        'district' => $district,
        'all_districts' => array_values(get_senatorial_districts()),
        'kpis' => [
            'total_lgas' => count($lgas),
            'total_pus' => $total_pus,
            'reported_pus' => $reported_pus,
            'reporting_percentage' => $reporting_pct,
            'total_registered' => (int)($pu_meta['total_registered'] ?? 0),
            'total_accredited' => (int)($res_meta['total_accredited'] ?? 0),
            'total_valid_votes' => $total_valid,
            'total_rejected' => (int)($res_meta['total_rejected'] ?? 0),
            'lp_votes' => $lp_votes,
            'lp_percentage' => round(($lp_votes / $calc_valid) * 100, 1),
            'margin_lead' => $net_margin,
            'is_leading' => ($leading_code === 'LP' && $lp_votes > 0),
            'leading_party' => $leading_code,
            'passed_25_count' => $passed_25_count,
            'passed_25_total' => count($lgas),
            'passed_25_all' => ($passed_25_count === count($lgas)),
            'total_incidents' => (int)($inc_meta['total_incidents'] ?? 0),
            'critical_incidents' => (int)($inc_meta['critical_count'] ?? 0),
            'total_agents' => (int)($ag_meta['total_agents'] ?? 0),
            'agents_with_photo' => (int)($ag_meta['agents_with_photo'] ?? 0),
        ],
        'parties' => $parties_data,
        'transmission_health' => $transmission_stats,
        'status_distribution' => $status_counts,
        'spread_compliance' => $spread_summary
    ];
}

/**
 * Breakdown of all LGAs within a Senatorial District
 */
function get_senatorial_lga_breakdown_data(PDO $pdo, string $district_key): array {
    $district = get_senatorial_district($district_key) ?: get_senatorial_district('north');
    $lgas = $district['lgas'];
    $lga_breakdown = [];

    foreach ($lgas as $lga_name) {
        $stmt_lga = $pdo->prepare("SELECT id, name, code FROM lgas WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt_lga->execute([$lga_name]);
        $lga_row = $stmt_lga->fetch(PDO::FETCH_ASSOC);
        $lga_id = $lga_row ? (int)$lga_row['id'] : 0;

        $stmt_pus = $pdo->prepare("
            SELECT COUNT(p.id) as total_pus, COALESCE(SUM(p.registered_voters), 0) as total_registered
            FROM polling_units p
            JOIN wards w ON p.ward_id = w.id
            WHERE w.lga_id = ?
        ");
        $stmt_pus->execute([$lga_id]);
        $pu_row = $stmt_pus->fetch(PDO::FETCH_ASSOC);
        $total_pus = (int)($pu_row['total_pus'] ?? 0);
        $total_registered = (int)($pu_row['total_registered'] ?? 0);

        $stmt_res = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sheets,
                COUNT(DISTINCT polling_unit) as reported_pus,
                COALESCE(SUM(lp_votes), 0) as lp_votes,
                COALESCE(SUM(apc_votes), 0) as apc_votes,
                COALESCE(SUM(pdp_votes), 0) as pdp_votes,
                COALESCE(SUM(nnpp_votes), 0) as nnpp_votes,
                COALESCE(SUM(sdp_votes), 0) as sdp_votes,
                COALESCE(SUM(total_valid_votes), 0) as total_valid,
                COALESCE(SUM(total_accredited_voters), 0) as total_accredited,
                COALESCE(SUM(rejected_votes), 0) as total_rejected,
                COALESCE(SUM(CASE WHEN (total_valid_votes + rejected_votes) > total_accredited_voters AND total_accredited_voters > 0 THEN 1 ELSE 0 END), 0) as overvoting_count
            FROM result_sheets
            WHERE is_current = 1 AND LOWER(lga) = LOWER(?)
        ");
        $stmt_res->execute([$lga_name]);
        $res_row = $stmt_res->fetch(PDO::FETCH_ASSOC);

        $reported_pus = (int)($res_row['reported_pus'] ?? 0);
        $total_valid = (int)($res_row['total_valid'] ?? 0);
        $calc_valid = max(1, $total_valid);
        $lp_votes = (int)($res_row['lp_votes'] ?? 0);
        $apc_votes = (int)($res_row['apc_votes'] ?? 0);
        $pdp_votes = (int)($res_row['pdp_votes'] ?? 0);
        $lp_pct = round(($lp_votes / $calc_valid) * 100, 1);
        $second_highest = max($apc_votes, $pdp_votes);
        $margin = $lp_votes - $second_highest;

        $stmt_coord = $pdo->prepare("SELECT name, email FROM admins WHERE lga_id = ? AND role = 'coordinator' LIMIT 1");
        $stmt_coord->execute([$lga_id]);
        $coord = $stmt_coord->fetch(PDO::FETCH_ASSOC);

        $stmt_inc = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE LOWER(lga) = LOWER(?)");
        $stmt_inc->execute([$lga_name]);
        $inc_cnt = (int)$stmt_inc->fetchColumn();

        $lga_breakdown[] = [
            'id' => $lga_id,
            'name' => $lga_name,
            'code' => $lga_row['code'] ?? '',
            'total_pus' => $total_pus,
            'reported_pus' => $reported_pus,
            'reporting_percentage' => $total_pus > 0 ? round(($reported_pus / $total_pus) * 100, 1) : 0,
            'total_registered' => $total_registered,
            'total_valid' => $total_valid,
            'total_accredited' => (int)($res_row['total_accredited'] ?? 0),
            'total_rejected' => (int)($res_row['total_rejected'] ?? 0),
            'lp_votes' => $lp_votes,
            'apc_votes' => $apc_votes,
            'pdp_votes' => $pdp_votes,
            'nnpp_votes' => (int)($res_row['nnpp_votes'] ?? 0),
            'sdp_votes' => (int)($res_row['sdp_votes'] ?? 0),
            'lp_percentage' => $lp_pct,
            'passed_25_pct' => ($lp_pct >= 25.0 && $total_valid > 0),
            'margin_lead' => $margin,
            'is_leading' => ($lp_votes > $second_highest && $lp_votes > 0),
            'overvoting_count' => (int)($res_row['overvoting_count'] ?? 0),
            'incident_count' => $inc_cnt,
            'coordinator_name' => $coord['name'] ?? 'Unassigned',
            'coordinator_email' => $coord['email'] ?? null
        ];
    }

    return [
        'success' => true,
        'district' => $district,
        'lgas' => $lga_breakdown
    ];
}

/**
 * Incidents recorded in a Senatorial District
 */
function get_senatorial_incidents_data(PDO $pdo, string $district_key): array {
    $district = get_senatorial_district($district_key) ?: get_senatorial_district('north');
    $lgas = $district ? $district['lgas'] : [];
    $lga_lowers = array_map('strtolower', $lgas);
    $placeholders = !empty($lgas) ? implode(',', array_fill(0, count($lgas), '?')) : "''";

    $stmt = $pdo->prepare("
        SELECT id, lga, ward, polling_unit, title, category, severity, status, description, attachment, created_at
        FROM incidents
        WHERE LOWER(lga) IN ($placeholders)
        ORDER BY id DESC
        LIMIT 30
    ");
    $stmt->execute($lga_lowers);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'incidents' => $incidents
    ];
}

/**
 * Field Agents active in a Senatorial District
 */
function get_senatorial_agents_data(PDO $pdo, string $district_key, bool $mask_pii = false): array {
    $district = get_senatorial_district($district_key) ?: get_senatorial_district('north');
    $lgas = $district ? $district['lgas'] : [];
    $lga_lowers = array_map('strtolower', $lgas);
    $placeholders = !empty($lgas) ? implode(',', array_fill(0, count($lgas), '?')) : "''";

    $stmt = $pdo->prepare("
        SELECT a.id, a.name, a.email, a.phone, a.photo, a.agent_code, a.lga, a.ward, a.polling_unit, a.status, a.last_seen_at,
               (SELECT r.transmission_status FROM result_sheets r WHERE r.agent_id = a.id AND r.is_current = 1 ORDER BY r.id DESC LIMIT 1) as latest_transmission
        FROM agents a
        WHERE LOWER(a.lga) IN ($placeholders)
        ORDER BY a.id DESC
        LIMIT 60
    ");
    $stmt->execute($lga_lowers);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($mask_pii) {
        foreach ($agents as &$ag) {
            if (!empty($ag['phone'])) {
                $p = trim($ag['phone']);
                $len = strlen($p);
                $ag['phone'] = $len > 6 ? substr($p, 0, 4) . '***' . substr($p, -3) : 'Protected';
            }
            if (!empty($ag['email'])) {
                $parts = explode('@', $ag['email']);
                $ag['email'] = substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? 'sia2027.org');
            }
        }
        unset($ag);
    }

    return [
        'success' => true,
        'agents' => $agents
    ];
}

/**
 * Detailed Ward drill-down within an LGA
 */
function get_senatorial_ward_breakdown_data(PDO $pdo, int $lga_id, string $lga_name = ''): array {
    if ($lga_id <= 0 && !empty($lga_name)) {
        $stmt_f = $pdo->prepare("SELECT id FROM lgas WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt_f->execute([$lga_name]);
        $lga_id = (int)$stmt_f->fetchColumn();
    }

    if ($lga_id <= 0) {
        return ['success' => false, 'message' => 'LGA not found or not specified.'];
    }

    $lga_info = $pdo->prepare("SELECT id, name, code, senatorial_district FROM lgas WHERE id = ?");
    $lga_info->execute([$lga_id]);
    $lga_data = $lga_info->fetch(PDO::FETCH_ASSOC);

    if (!$lga_data) {
        return ['success' => false, 'message' => 'LGA record does not exist.'];
    }

    $stmt_w = $pdo->prepare("
        SELECT 
            w.id,
            w.name,
            w.code,
            COUNT(p.id) as total_pus,
            COALESCE(SUM(p.registered_voters), 0) as total_registered
        FROM wards w
        LEFT JOIN polling_units p ON p.ward_id = w.id
        WHERE w.lga_id = ?
        GROUP BY w.id, w.name, w.code
        ORDER BY w.name ASC
    ");
    $stmt_w->execute([$lga_id]);
    $wards = $stmt_w->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ward_res = $pdo->prepare("
        SELECT 
            ward,
            COUNT(*) as total_sheets,
            COUNT(DISTINCT polling_unit) as reported_pus,
            COALESCE(SUM(lp_votes), 0) as lp_votes,
            COALESCE(SUM(apc_votes), 0) as apc_votes,
            COALESCE(SUM(pdp_votes), 0) as pdp_votes,
            COALESCE(SUM(total_valid_votes), 0) as total_valid,
            COALESCE(SUM(total_accredited_voters), 0) as total_accredited,
            COALESCE(SUM(rejected_votes), 0) as total_rejected
        FROM result_sheets
        WHERE is_current = 1 AND LOWER(lga) = LOWER(?)
        GROUP BY ward
    ");
    $stmt_ward_res->execute([$lga_data['name']]);
    $w_res_rows = $stmt_ward_res->fetchAll(PDO::FETCH_ASSOC);

    $ward_res_map = [];
    foreach ($w_res_rows as $wr) {
        $ward_res_map[strtolower(trim($wr['ward']))] = $wr;
    }

    $enriched_wards = [];
    foreach ($wards as $w) {
        $w_stat = $ward_res_map[strtolower(trim($w['name']))] ?? null;
        $t_pus = (int)($w['total_pus'] ?? 0);
        $r_pus = $w_stat ? (int)$w_stat['reported_pus'] : 0;
        $lp_v = $w_stat ? (int)$w_stat['lp_votes'] : 0;
        $apc_v = $w_stat ? (int)$w_stat['apc_votes'] : 0;
        $pdp_v = $w_stat ? (int)$w_stat['pdp_votes'] : 0;
        $val_v = $w_stat ? (int)$w_stat['total_valid'] : 0;
        $calc_v = max(1, $val_v);

        $enriched_wards[] = [
            'id' => (int)$w['id'],
            'name' => $w['name'],
            'code' => $w['code'],
            'total_pus' => $t_pus,
            'reported_pus' => $r_pus,
            'reporting_percentage' => $t_pus > 0 ? round(($r_pus / $t_pus) * 100, 1) : 0,
            'total_registered' => (int)($w['total_registered'] ?? 0),
            'lp_votes' => $lp_v,
            'apc_votes' => $apc_v,
            'pdp_votes' => $pdp_v,
            'total_valid' => $val_v,
            'lp_percentage' => round(($lp_v / $calc_v) * 100, 1),
            'passed_25_pct' => (round(($lp_v / $calc_v) * 100, 1) >= 25.0 && $val_v > 0)
        ];
    }

    return [
        'success' => true,
        'lga' => $lga_data,
        'wards' => $enriched_wards
    ];
}

