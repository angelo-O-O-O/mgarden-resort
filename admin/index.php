<?php
require_once __DIR__ . '/includes/config.php';
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . ADMIN_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . ADMIN_URL . '/pages/login.php');
}
exit;
?>