<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['agent']);
session_destroy();
session_start();
set_flash('info', 'You have been signed out safely.');
header('Location: index.php');
exit;

