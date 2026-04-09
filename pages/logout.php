<?php
require_once __DIR__ . '/../includes/config.php';
session_destroy();
session_start();
setFlash('success', 'You have been signed out. See you again! 👋');
redirect(SITE_URL . '/index.php');
