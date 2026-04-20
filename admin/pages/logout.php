<?php
require_once __DIR__ . '/../includes/config.php';
session_unset();
session_destroy();
header('Location: ' . ADMIN_URL . '/pages/login.php');
exit;
?>