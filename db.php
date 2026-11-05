<?php
/**
 * SIA 2027 Party Agent Portal - Database Connection & Auto-Migrator
 * Supports SQLite (with WAL mode & busy_timeout) and MySQL via PDO
 */
require_once __DIR__ . '/config.php';

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $db_dir = dirname(SQLITE_FILE);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Hardening SQLite concurrency & locks
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        // Initialize / Migrate schema idempotently without dropping data
        init_database_schema($pdo);

        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please check your configuration.");
    }
}

/**
 * Idempotently creates tables, adds missing columns, and seeds initial metadata.
 * Never drops existing tables or rows.
 */
function migrate_database(PDO $pdo): void {
    init_database_schema($pdo);
}

function init_database_schema(PDO $pdo): void {
    // ---------------------------------------------------------
    // 1. Settings Table
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(80) PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");


    $default_settings = [
        'election_name' => 'SIA 2027 Adamawa State Gubernatorial Election',
        'election_date' => '2027-02-06',
        'submissions_open' => '1',
        'public_results_visible' => '1',
        'public_label_text' => 'Party agent tallies, unofficial. Not INEC results.',
        'require_review_before_public' => '1',
        'max_upload_mb' => '15'
    ];

    $stmt_set = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($default_settings as $k => $v) {
        $stmt_set->execute([$k, $v]);
    }

    // ---------------------------------------------------------
    // 2. Geography Tables: LGAs, Wards, Polling Units
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lgas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            code VARCHAR(20) NOT NULL UNIQUE
        );
    ");


    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lga_id INTEGER NOT NULL,
            name VARCHAR(150) NOT NULL,
            code VARCHAR(30) NOT NULL,
            FOREIGN KEY (lga_id) REFERENCES lgas(id) ON DELETE CASCADE,
            UNIQUE(lga_id, code)
        );
    ");


    $pdo->exec("
        CREATE TABLE IF NOT EXISTS polling_units (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ward_id INTEGER NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(200) NOT NULL,
            registered_voters INTEGER DEFAULT NULL,
            FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE
        );
    ");


    // Seed Geography from CSV if present, else placeholder LGAs
    seed_geography_if_empty($pdo);

    // ---------------------------------------------------------
    // 3. Admins Table
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL, -- superadmin | coordinator | analyst
            lga_id INTEGER DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            must_change_password INTEGER NOT NULL DEFAULT 0,
            last_login_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lga_id) REFERENCES lgas(id) ON DELETE SET NULL
        );
    ");

    ensure_column_exists($pdo, 'admins', 'must_change_password', 'INTEGER DEFAULT 0');
    ensure_column_exists($pdo, 'admins', 'last_login_at', 'DATETIME DEFAULT NULL');
    ensure_column_exists($pdo, 'admins', 'lga_id', 'INTEGER DEFAULT NULL');

    // ---------------------------------------------------------
    // 4. Agents Table (Altered for new columns)
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'agent',
            state VARCHAR(80) NOT NULL DEFAULT 'Adamawa State',
            lga VARCHAR(80) NOT NULL,
            ward VARCHAR(120) NOT NULL,
            polling_unit VARCHAR(180) NOT NULL,
            agent_code VARCHAR(40) UNIQUE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'accredited', -- pending | accredited | suspended
            last_seen_at DATETIME DEFAULT NULL,
            password_reset_token VARCHAR(64) DEFAULT NULL,
            password_reset_expiry DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");


    ensure_column_exists($pdo, 'agents', 'photo', 'VARCHAR(255) DEFAULT NULL');
    ensure_column_exists($pdo, 'agents', 'last_seen_at', 'DATETIME DEFAULT NULL');
    ensure_column_exists($pdo, 'agents', 'password_reset_token', 'VARCHAR(64) DEFAULT NULL');
    ensure_column_exists($pdo, 'agents', 'password_reset_expiry', 'DATETIME DEFAULT NULL');
    ensure_column_exists($pdo, 'agents', 'lga_id', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'agents', 'ward_id', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'agents', 'polling_unit_id', 'INTEGER DEFAULT NULL');

    // ---------------------------------------------------------
    // 5. Form EC8A Result Sheets Table
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS result_sheets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id INTEGER NOT NULL,
            lga VARCHAR(80) NOT NULL,
            ward VARCHAR(120) NOT NULL,
            polling_unit VARCHAR(180) NOT NULL,
            polling_unit_id INTEGER DEFAULT NULL,
            ec8a_image VARCHAR(255) DEFAULT NULL,
            image_sha256 VARCHAR(64) DEFAULT NULL,
            transmission_status VARCHAR(100) DEFAULT 'Reporting on PU ground',
            a_votes INTEGER NOT NULL DEFAULT 0,
            aa_votes INTEGER NOT NULL DEFAULT 0,
            aac_votes INTEGER NOT NULL DEFAULT 0,
            adc_votes INTEGER NOT NULL DEFAULT 0,
            adp_votes INTEGER NOT NULL DEFAULT 0,
            apc_votes INTEGER NOT NULL DEFAULT 0,
            apga_votes INTEGER NOT NULL DEFAULT 0,
            app_votes INTEGER NOT NULL DEFAULT 0,
            dlp_votes INTEGER NOT NULL DEFAULT 0,
            lp_votes INTEGER NOT NULL DEFAULT 0,
            ndc_votes INTEGER NOT NULL DEFAULT 0,
            nnpp_votes INTEGER NOT NULL DEFAULT 0,
            nrm_votes INTEGER NOT NULL DEFAULT 0,
            pdp_votes INTEGER NOT NULL DEFAULT 0,
            sdp_votes INTEGER NOT NULL DEFAULT 0,
            ypp_votes INTEGER NOT NULL DEFAULT 0,
            zlp_votes INTEGER NOT NULL DEFAULT 0,
            apm_votes INTEGER NOT NULL DEFAULT 0,
            rejected_votes INTEGER NOT NULL DEFAULT 0,
            total_valid_votes INTEGER NOT NULL DEFAULT 0,
            total_accredited_voters INTEGER NOT NULL DEFAULT 0,
            notes TEXT DEFAULT NULL,
            verification_status VARCHAR(40) NOT NULL DEFAULT 'pending', -- pending | verified | flagged | rejected
            reviewed_by INTEGER DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            review_note TEXT DEFAULT NULL,
            is_current INTEGER NOT NULL DEFAULT 1,
            supersedes_id INTEGER DEFAULT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'agent', -- agent | admin
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
            FOREIGN KEY (polling_unit_id) REFERENCES polling_units(id) ON DELETE SET NULL,
            FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
            FOREIGN KEY (supersedes_id) REFERENCES result_sheets(id) ON DELETE SET NULL
        );
    ");


    // Add columns dynamically to result_sheets if missing
    ensure_column_exists($pdo, 'result_sheets', 'polling_unit_id', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'lga_id', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'ward_id', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'total_accredited', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'image_sha256', 'VARCHAR(64) DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'transmission_status', "VARCHAR(100) DEFAULT 'Reporting on PU ground'");
    ensure_column_exists($pdo, 'result_sheets', 'reviewed_by', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'reviewed_at', 'DATETIME DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'review_note', 'TEXT DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'is_current', 'INTEGER NOT NULL DEFAULT 1');
    ensure_column_exists($pdo, 'result_sheets', 'supersedes_id', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'result_sheets', 'source', "VARCHAR(30) NOT NULL DEFAULT 'agent'");

    // Ensure all 17 registered party vote columns exist
    foreach (get_party_column_names() as $party_col) {
        ensure_column_exists($pdo, 'result_sheets', $party_col, 'INTEGER NOT NULL DEFAULT 0');
    }
    ensure_column_exists($pdo, 'result_sheets', 'apm_votes', 'INTEGER NOT NULL DEFAULT 0');

    // ---------------------------------------------------------
    // 6. Result Sheet Revisions (Auditing Edit Numbers)
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS result_sheet_revisions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sheet_id INTEGER NOT NULL,
            changed_by_type VARCHAR(30) NOT NULL, -- admin | agent
            changed_by_id INTEGER NOT NULL,
            old_json TEXT NOT NULL,
            new_json TEXT NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sheet_id) REFERENCES result_sheets(id) ON DELETE CASCADE
        );
    ");


    // ---------------------------------------------------------
    // 7. Audit Log Table
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_type VARCHAR(30) NOT NULL, -- admin | agent | system | visitor
            actor_id INTEGER DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity VARCHAR(80) DEFAULT NULL,
            entity_id INTEGER DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            meta_json TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");


    // ---------------------------------------------------------
    // 8. Field Incidents Table
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incidents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id INTEGER NOT NULL,
            lga VARCHAR(80) NOT NULL,
            ward VARCHAR(120) NOT NULL,
            polling_unit VARCHAR(180) NOT NULL,
            title VARCHAR(150) NOT NULL,
            category VARCHAR(60) NOT NULL,
            severity VARCHAR(30) NOT NULL DEFAULT 'normal', -- normal | urgent | critical
            description TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'received', -- received | verified | in-progress | resolved
            assigned_to INTEGER DEFAULT NULL,
            resolution_note TEXT DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            lat REAL DEFAULT NULL,
            lng REAL DEFAULT NULL,
            attachment VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES admins(id) ON DELETE SET NULL
        );
    ");


    ensure_column_exists($pdo, 'incidents', 'assigned_to', 'INTEGER DEFAULT NULL');
    ensure_column_exists($pdo, 'incidents', 'resolution_note', 'TEXT DEFAULT NULL');
    ensure_column_exists($pdo, 'incidents', 'resolved_at', 'DATETIME DEFAULT NULL');
    ensure_column_exists($pdo, 'incidents', 'lat', 'REAL DEFAULT NULL');
    ensure_column_exists($pdo, 'incidents', 'lng', 'REAL DEFAULT NULL');
    ensure_column_exists($pdo, 'incidents', 'attachment', 'VARCHAR(255) DEFAULT NULL');

    // ---------------------------------------------------------
    // 9. Announcements & Read Receipts
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            audience VARCHAR(80) NOT NULL DEFAULT 'all', -- all | lga:<id> | agent:<id>
            priority VARCHAR(30) NOT NULL DEFAULT 'normal', -- normal | urgent | critical
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
        );
    ");


    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcement_reads (
            announcement_id INTEGER NOT NULL,
            agent_id INTEGER NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (announcement_id, agent_id),
            FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
            FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
        );
    ");


    // ---------------------------------------------------------
    // 10. Login Rate Limiting Table
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip VARCHAR(45) NOT NULL,
            email VARCHAR(150) NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // ---------------------------------------------------------
    // 11. District Managers Table & Default Accounts
    // ---------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS district_managers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            district VARCHAR(50) NOT NULL, -- north | central | south
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_login_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    seed_district_managers_if_empty($pdo);

    // ---------------------------------------------------------
    // 12. Senatorial Districts Synchronization
    // ---------------------------------------------------------
    sync_senatorial_districts($pdo);

}

/**
 * Seeds official District Management accounts if table is empty
 */
function seed_district_managers_if_empty(PDO $pdo): void {
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM district_managers")->fetchColumn();
        if ($cnt === 0) {
            $default_managers = [
                [
                    'district' => 'north',
                    'name' => 'Hon. Zonal Director (North Command)',
                    'email' => 'north@sia2027.ng',
                    'phone' => '0803 100 2027',
                    'password' => 'North2027!'
                ],
                [
                    'district' => 'central',
                    'name' => 'Hon. Zonal Director (Central Command)',
                    'email' => 'central@sia2027.ng',
                    'phone' => '0803 200 2027',
                    'password' => 'Central2027!'
                ],
                [
                    'district' => 'south',
                    'name' => 'Hon. Zonal Director (South Command)',
                    'email' => 'south@sia2027.ng',
                    'phone' => '0803 300 2027',
                    'password' => 'South2027!'
                ]
            ];

            $stmt = $pdo->prepare("
                INSERT INTO district_managers (district, name, email, phone, password_hash, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', datetime('now'))
            ");

            foreach ($default_managers as $m) {
                $hash = password_hash($m['password'], PASSWORD_DEFAULT);
                $stmt->execute([$m['district'], $m['name'], $m['email'], $m['phone'], $hash]);
            }
        }
    } catch (Throwable $e) {
        error_log("Failed to seed district managers: " . $e->getMessage());
    }
}

/**
 * Syncs and updates the official senatorial district mapping for all 21 Adamawa LGAs
 */
function sync_senatorial_districts(PDO $pdo): void {
    ensure_column_exists($pdo, 'lgas', 'senatorial_district', 'VARCHAR(50) DEFAULT NULL');
    ensure_column_exists($pdo, 'admins', 'senatorial_district', 'VARCHAR(50) DEFAULT NULL');

    $districts = [
        'north' => ['Madagali', 'Maiha', 'Michika', 'Mubi North', 'Mubi South'],
        'central' => ['Fufore', 'Girei', 'Gombi', 'Hong', 'Song', 'Yola North', 'Yola South'],
        'south' => ['Demsa', 'Ganye', 'Guyuk', 'Jada', 'Lamurde', 'Mayo-Belwa', 'Numan', 'Shelleng', 'Toungo']
    ];

    $stmt_upd = $pdo->prepare("UPDATE lgas SET senatorial_district = ? WHERE LOWER(name) = LOWER(?)");
    foreach ($districts as $district_key => $lgas) {
        foreach ($lgas as $lga_name) {
            $stmt_upd->execute([$district_key, $lga_name]);
        }
    }
}

/**
 * Ensures a column exists on a table without failing or dropping data
 */
function ensure_column_exists(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_column($cols, 'name');
            if (!in_array($column, $existing, true)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } else {
            // MySQL / MariaDB
            $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    } catch (Throwable $e) {
        error_log("Failed to alter table {$table} add column {$column}: " . $e->getMessage());
    }
}

/**
 * Seeds official 21 LGAs and placeholder wards/PUs if empty
 */
function seed_geography_if_empty(PDO $pdo): void {
    $seed_file = __DIR__ . '/database/seed/polling_units.csv';
    $pu_count = 0;
    try {
        $pu_count = (int)$pdo->query("SELECT COUNT(*) FROM polling_units WHERE name NOT LIKE '[Placeholder]%'")->fetchColumn();
    } catch (Exception $e) {}

    if ($pu_count === 0 && file_exists($seed_file) && is_readable($seed_file)) {
        import_polling_units_csv($pdo, $seed_file);
        try {
            $pdo->exec("DELETE FROM polling_units WHERE name LIKE '[Placeholder]%'");
            $pdo->exec("DELETE FROM wards WHERE name LIKE '[Placeholder]%'");
        } catch (Exception $e) {}
        return;
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn();
    if ($count > 0) {
        return;
    }

    // Official 21 Local Government Areas of Adamawa State
    $official_lgas = [
        ['name' => 'Demsa',        'code' => 'AD-DMA'],
        ['name' => 'Fufore',       'code' => 'AD-FUF'],
        ['name' => 'Ganye',        'code' => 'AD-GNY'],
        ['name' => 'Girei',        'code' => 'AD-GRI'],
        ['name' => 'Gombi',        'code' => 'AD-GMB'],
        ['name' => 'Guyuk',        'code' => 'AD-GYK'],
        ['name' => 'Hong',         'code' => 'AD-HNG'],
        ['name' => 'Jada',         'code' => 'AD-JDA'],
        ['name' => 'Lamurde',      'code' => 'AD-LMR'],
        ['name' => 'Madagali',     'code' => 'AD-MDG'],
        ['name' => 'Maiha',        'code' => 'AD-MHA'],
        ['name' => 'Mayo-Belwa',   'code' => 'AD-MYB'],
        ['name' => 'Michika',      'code' => 'AD-MCK'],
        ['name' => 'Mubi North',   'code' => 'AD-MBN'],
        ['name' => 'Mubi South',   'code' => 'AD-MBS'],
        ['name' => 'Numan',        'code' => 'AD-NMN'],
        ['name' => 'Shelleng',     'code' => 'AD-SHL'],
        ['name' => 'Song',         'code' => 'AD-SNG'],
        ['name' => 'Toungo',       'code' => 'AD-TNG'],
        ['name' => 'Yola North',   'code' => 'AD-YLN'],
        ['name' => 'Yola South',   'code' => 'AD-YLS'],
    ];

    $stmt_lga = $pdo->prepare("INSERT INTO lgas (name, code) VALUES (?, ?)");
    $stmt_ward = $pdo->prepare("INSERT INTO wards (lga_id, name, code) VALUES (?, ?, ?)");
    $stmt_pu = $pdo->prepare("INSERT INTO polling_units (ward_id, code, name, registered_voters) VALUES (?, ?, ?, ?)");

    $pu_counter = 1;
    foreach ($official_lgas as $lga_item) {
        $stmt_lga->execute([$lga_item['name'], $lga_item['code']]);
        $lga_id = (int)$pdo->lastInsertId();

        // Seed 1 clearly labeled placeholder ward per LGA
        $ward_name = "[Placeholder] Ward 01 ({$lga_item['name']})";
        $ward_code = "{$lga_item['code']}-W01";
        $stmt_ward->execute([$lga_id, $ward_name, $ward_code]);
        $ward_id = (int)$pdo->lastInsertId();

        // Seed 2 clearly labeled placeholder polling units per ward
        for ($p = 1; $p <= 2; $p++) {
            $pu_code = sprintf('%s-PU%03d', $lga_item['code'], $p);
            $pu_name = sprintf('[Placeholder] Polling Unit %02d (%s)', $p, $lga_item['name']);
            $stmt_pu->execute([$ward_id, $pu_code, $pu_name, 500]);
            $pu_counter++;
        }
    }
}

/**
 * Imports geography from CSV file with header: lga_name,lga_code,ward_name,ward_code,pu_name,pu_code,registered_voters
 */
function import_polling_units_csv(PDO $pdo, string $csv_path): array {
    $handle = fopen($csv_path, 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Unable to read CSV file.'];
    }

    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if (!$header) {
        fclose($handle);
        return ['success' => false, 'error' => 'CSV file is empty.'];
    }

    $header = array_map(fn($h) => strtolower(trim($h)), $header);
    $rows_imported = 0;

    $lga_cache = [];
    $ward_cache = [];

    $stmt_find_lga = $pdo->prepare("SELECT id FROM lgas WHERE name = ? OR code = ?");
    $stmt_ins_lga = $pdo->prepare("INSERT INTO lgas (name, code) VALUES (?, ?)");

    $stmt_find_ward = $pdo->prepare("SELECT id FROM wards WHERE lga_id = ? AND (name = ? OR code = ?)");
    $stmt_ins_ward = $pdo->prepare("INSERT INTO wards (lga_id, name, code) VALUES (?, ?, ?)");

    $stmt_ins_pu = $pdo->prepare("
        INSERT INTO polling_units (ward_id, code, name, registered_voters)
        VALUES (?, ?, ?, ?)
        ON CONFLICT(code) DO UPDATE SET name = excluded.name, registered_voters = excluded.registered_voters
    ");

    $in_tx = $pdo->inTransaction();
    if (!$in_tx) {
        $pdo->beginTransaction();
    }

    try {
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) < 6) continue;
            $lga_name = trim($row[0]);
            $lga_code = trim($row[1]);
            $ward_name = trim($row[2]);
            $ward_code = trim($row[3]);
            $pu_name = trim($row[4]);
            $pu_code = trim($row[5]);
            $registered = !empty($row[6]) ? (int)$row[6] : null;

            if (empty($lga_name) || empty($pu_code)) continue;

            // LGA
            if (!isset($lga_cache[$lga_code])) {
                $stmt_find_lga->execute([$lga_name, $lga_code]);
                $lga_id = $stmt_find_lga->fetchColumn();
                if (!$lga_id) {
                    $stmt_ins_lga->execute([$lga_name, $lga_code]);
                    $lga_id = (int)$pdo->lastInsertId();
                }
                $lga_cache[$lga_code] = $lga_id;
            }
            $lga_id = $lga_cache[$lga_code];

            // Ward
            $ward_key = "{$lga_id}_{$ward_code}";
            if (!isset($ward_cache[$ward_key])) {
                $stmt_find_ward->execute([$lga_id, $ward_name, $ward_code]);
                $ward_id = $stmt_find_ward->fetchColumn();
                if (!$ward_id) {
                    $stmt_ins_ward->execute([$lga_id, $ward_name, $ward_code]);
                    $ward_id = (int)$pdo->lastInsertId();
                }
                $ward_cache[$ward_key] = $ward_id;
            }
            $ward_id = $ward_cache[$ward_key];

            // Polling Unit
            $stmt_ins_pu->execute([$ward_id, $pu_code, $pu_name, $registered]);
            $rows_imported++;
        }

        if (!$in_tx) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if (!$in_tx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fclose($handle);
        return ['success' => false, 'error' => $e->getMessage()];
    }

    fclose($handle);
    return ['success' => true, 'rows_imported' => $rows_imported];
}
