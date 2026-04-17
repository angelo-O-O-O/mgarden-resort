<?php
// ============================================================
// MGarden Beach Resort — Receptionist Configuration
// ============================================================

// Error reporting - hide file paths for security
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ob_start();

// Define constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'asia_mgarden');
define('SITE_NAME', 'MGarden Beach Resort');

// Auto-detect site URL
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$folderName = basename(dirname(__DIR__, 2));
define('SITE_URL', $protocol . '://' . $host . '/' . $folderName);

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
function isReceptionistLoggedIn() {
    return isset($_SESSION['recpst_id']);
}

function currentReceptionist() {
    if (!isReceptionistLoggedIn()) return null;
    $db   = getDB();
    $id   = (int) $_SESSION['recpst_id'];
    $stmt = $db->prepare("SELECT recpst_id, recpst_fname, recpst_lname, recpst_email, recpst_cnum, role, picture FROM receptionist WHERE recpst_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ── UTILITY FUNCTIONS ──
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function requireReceptionistLogin() {
    if (!isReceptionistLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(SITE_URL . '/receptionist/index.php');
    }
}
?>
