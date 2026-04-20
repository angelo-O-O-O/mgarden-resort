<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Database ──────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'asia_mgarden');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ── Base URL (auto-detected) ──────────────────────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
// Strip everything from /admin/ onward to get project root
$root_path = preg_replace('#/admin(?:/.*)?$#', '', $_SERVER['SCRIPT_NAME']);
define('BASE_URL',  $protocol . '://' . $host . $root_path);          // e.g. http://localhost/mgarden_new
define('ADMIN_URL', BASE_URL . '/admin');                               // e.g. http://localhost/mgarden_new/admin
?>