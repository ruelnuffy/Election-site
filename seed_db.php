<?php
/**
 * SIA 2027 Database Initialization Script
 * Safe, idempotent initializer that sets up the database schema
 * and synchronizes the official 4,101 Adamawa polling units.
 * Never drops active tables or existing data.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
migrate_database($pdo);

$total_lgas = (int)$pdo->query("SELECT COUNT(*) FROM lgas")->fetchColumn();
$total_wards = (int)$pdo->query("SELECT COUNT(*) FROM wards")->fetchColumn();
$total_pus = (int)$pdo->query("SELECT COUNT(*) FROM polling_units")->fetchColumn();
$total_agents = (int)$pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
$total_sheets = (int)$pdo->query("SELECT COUNT(*) FROM result_sheets")->fetchColumn();

echo "=================================================================\n";
echo " SIA 2027 DATABASE STATE\n";
echo "=================================================================\n";
echo "  - Local Government Areas: {$total_lgas} / 21 LGAs\n";
echo "  - Electoral Wards:        {$total_wards} / 226 Wards\n";
echo "  - Polling Units:          {$total_pus} / 4,101 Units\n";
echo "  - Party Agents:           {$total_agents}\n";
echo "  - Result Sheets:          {$total_sheets}\n";
echo "=================================================================\n";
echo "To generate UI test simulation data (demo admins, agents, sheets), run:\n";
echo "  php seed_demo.php --demo\n\n";
