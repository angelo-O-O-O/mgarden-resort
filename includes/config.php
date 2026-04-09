<?php
// ============================================================
// MGarden Beach Resort — Database Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mgarden_resort');
define('SITE_NAME', 'MGarden Beach Resort');
// Auto-detect site URL based on actual folder name
$folderName = basename(dirname(__DIR__));
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/' . $folderName);

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="padding:20px;background:#fee;color:#c00;font-family:sans-serif;">
                Database connection failed: ' . $conn->connect_error . '<br>
                Make sure XAMPP MySQL is running and the database is imported.
                </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// Start session on every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: is user logged in?
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper: get current user
function currentUser() {
    if (!isLoggedIn()) return null;
    $db   = getDB();
    $id   = (int) $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id, name, email, phone, role FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Helper: redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Helper: require login
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(SITE_URL . '/pages/login.php');
    }
}

// Helper: sanitize output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper: format currency
function peso($amount) {
    return '₱' . number_format((float)$amount, 0, '.', ',');
}

// Helper: cart count
function getCartCount() {
    if (!isLoggedIn()) return 0;
    $db   = getDB();
    $id   = (int) $_SESSION['user_id'];
    $res  = $db->query("SELECT COUNT(*) as cnt FROM carts WHERE user_id = $id");
    return $res->fetch_assoc()['cnt'] ?? 0;
}

// Flash messages
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
