<?php
require_once __DIR__ . '/config.php';
// Auth guard
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin Panel') ?> — MGarden Beach Resort</title>
    <link rel="stylesheet" href="<?= ADMIN_URL ?>/css/style.css?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="topbar-title"><?= htmlspecialchars($page_title ?? '') ?></h1>
            </div>
        </div>
        <span class="topbar-date" id="topbarDate"></span>
    </header>
    <main class="page-body">