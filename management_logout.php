<?php
/**
 * SIA 2027 Senatorial District Management Sign Out
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();

if (isset($_SESSION['district_manager'])) {
    unset($_SESSION['district_manager']);
}

header('Location: management_login.php');
exit;

