<?php
// ============================================================
// MGarden Beach Resort — Database Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'asia_mgarden');
define('SITE_NAME', 'MGarden Beach Resort');

// Auto-detect site URL
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$folderName = basename(dirname(__DIR__, 2));
define('SITE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/' . $folderName);

// ── DATABASE CONNECTION ──
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="padding:20px;background:#fee2e2;color:#991b1b;font-family:sans-serif;border-left:4px solid #ef4444;">
                <strong>Database connection failed:</strong> ' . $conn->connect_error . '<br><br>
                Make sure XAMPP MySQL is running and the <strong>' . DB_NAME . '</strong> database is imported.
                </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ── SESSION ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── AUTH HELPERS ──
function isLoggedIn() {
    return isset($_SESSION['guest_id']);
}

function currentGuest() {
    if (!isLoggedIn()) return null;
    $db   = getDB();
    $id   = (int) $_SESSION['guest_id'];
    $stmt = $db->prepare("SELECT guest_id, guest_name, email, contact_num, address, profile_pic FROM guests WHERE guest_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(SITE_URL . '/guest/pages/login.php');
    }
}

// ── FLASH MESSAGES ──
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── HELPERS ──
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function peso($amount) {
    return '₱' . number_format((float)$amount, 0, '.', ',');
}

function redirect($url) {
    header("Location: $url");
    exit;
}