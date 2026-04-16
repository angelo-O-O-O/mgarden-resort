<?php
require_once __DIR__ . '/../includes/config.php';

// Destroy session cleanly
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// Start fresh session for flash message
session_start();
setFlash('success', 'You have been signed out. See you again soon!');
redirect(SITE_URL . '/guest/index.php');