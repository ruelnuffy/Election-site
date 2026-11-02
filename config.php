<?php
/**
 * SIA 2027 Party Agent Portal - Global Configuration
 * Adamawa State Gubernatorial & General Election
 */

// Load Core Libraries
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/validators.php';
require_once __DIR__ . '/lib/settings.php';

// Start hardened secure session
start_secure_session();

// Application Constants
define('APP_NAME', 'SIA 2027 Party Agent Command');
define('APP_STATE', 'Adamawa State');

// Database Configuration
// By default, uses SQLite for 100% zero-configuration cPanel deployment.
// To use MySQL instead, set DB_DRIVER to 'mysql' and fill credentials.
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite'); // 'sqlite' or 'mysql'

// SQLite Path
define('SQLITE_FILE', getenv('SIA_DB_PATH') ?: (__DIR__ . '/database/sia2027.sqlite'));

// MySQL Credentials (optional)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sia2027_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// Uploads Directory
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

// Official Registered Political Parties (17 Parties)
const OFFICIAL_PARTIES = [
    'A' => [
        'code' => 'A',
        'name' => 'Accord',
        'candidate' => 'Accord Candidate',
        'color' => '#0284c7',
        'bg_class' => 'bg-sky-600',
        'column' => 'a_votes'
    ],
    'AA' => [
        'code' => 'AA',
        'name' => 'Action Alliance',
        'candidate' => 'Action Alliance Candidate',
        'color' => '#9333ea',
        'bg_class' => 'bg-purple-600',
        'column' => 'aa_votes'
    ],
    'AAC' => [
        'code' => 'AAC',
        'name' => 'African Action Congress',
        'candidate' => 'AAC Candidate',
        'color' => '#ea580c',
        'bg_class' => 'bg-orange-600',
        'column' => 'aac_votes'
    ],
    'ADC' => [
        'code' => 'ADC',
        'name' => 'African Democratic Congress',
        'candidate' => 'ADC Candidate',
        'color' => '#0d9488',
        'bg_class' => 'bg-teal-600',
        'column' => 'adc_votes'
    ],
    'ADP' => [
        'code' => 'ADP',
        'name' => 'Action Democratic Party',
        'candidate' => 'ADP Candidate',
        'color' => '#d97706',
        'bg_class' => 'bg-amber-600',
        'column' => 'adp_votes'
    ],
    'APC' => [
        'code' => 'APC',
        'name' => 'All Progressives Congress',
        'candidate' => 'Aishatu Dahiru Binani',
        'color' => '#2563eb',
        'bg_class' => 'bg-blue-600',
        'column' => 'apc_votes'
    ],
    'APGA' => [
        'code' => 'APGA',
        'name' => 'All Progressives Grand Alliance',
        'candidate' => 'APGA Candidate',
        'color' => '#ca8a04',
        'bg_class' => 'bg-yellow-600',
        'column' => 'apga_votes'
    ],
    'APP' => [
        'code' => 'APP',
        'name' => 'Action Peoples Party',
        'candidate' => 'APP Candidate',
        'color' => '#4f46e5',
        'bg_class' => 'bg-indigo-600',
        'column' => 'app_votes'
    ],
    'DLP' => [
        'code' => 'DLP',
        'name' => 'Democratic Leadership Party',
        'candidate' => 'DLP Candidate',
        'color' => '#0891b2',
        'bg_class' => 'bg-cyan-600',
        'column' => 'dlp_votes'
    ],
    'LP' => [
        'code' => 'LP',
        'name' => 'Labour Party',
        'candidate' => 'Senator Ishaku Abbo (SIA)',
        'color' => '#059669',
        'bg_class' => 'bg-emerald-600',
        'column' => 'lp_votes',
        'is_lead_campaign' => true
    ],
    'NDC' => [
        'code' => 'NDC',
        'name' => 'National Democratic Coalition',
        'candidate' => 'NDC Candidate',
        'color' => '#4b5563',
        'bg_class' => 'bg-slate-600',
        'column' => 'ndc_votes'
    ],
    'NNPP' => [
        'code' => 'NNPP',
        'name' => 'New Nigeria Peoples Party',
        'candidate' => 'NNPP Candidate',
        'color' => '#dc2626',
        'bg_class' => 'bg-red-600',
        'column' => 'nnpp_votes'
    ],
    'NRM' => [
        'code' => 'NRM',
        'name' => 'National Rescue Movement',
        'candidate' => 'NRM Candidate',
        'color' => '#65a30d',
        'bg_class' => 'bg-lime-600',
        'column' => 'nrm_votes'
    ],
    'PDP' => [
        'code' => 'PDP',
        'name' => 'Peoples Democratic Party',
        'candidate' => 'Ahmadu Umaru Fintiri',
        'color' => '#16a34a',
        'bg_class' => 'bg-green-600',
        'column' => 'pdp_votes'
    ],
    'SDP' => [
        'code' => 'SDP',
        'name' => 'Social Democratic Party',
        'candidate' => 'SDP Candidate',
        'color' => '#e11d48',
        'bg_class' => 'bg-rose-600',
        'column' => 'sdp_votes'
    ],
    'YPP' => [
        'code' => 'YPP',
        'name' => 'Young Progressives Party',
        'candidate' => 'YPP Candidate',
        'color' => '#7c3aed',
        'bg_class' => 'bg-violet-600',
        'column' => 'ypp_votes'
    ],
    'ZLP' => [
        'code' => 'ZLP',
        'name' => 'Zenith Labour Party',
        'candidate' => 'ZLP Candidate',
        'color' => '#047857',
        'bg_class' => 'bg-emerald-700',
        'column' => 'zlp_votes'
    ],
];

function get_official_parties(): array {
    return OFFICIAL_PARTIES;
}

function get_party_column_names(): array {
    return array_column(OFFICIAL_PARTIES, 'column');
}

// Flash Message Helpers
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Output Sanitization Helper
function sanitize(mixed $data): string {
    return htmlspecialchars(trim((string)($data ?? '')), ENT_QUOTES, 'UTF-8');
}

// Authentication Helper Alias
function is_authenticated(): bool {
    return is_agent_authenticated();
}
