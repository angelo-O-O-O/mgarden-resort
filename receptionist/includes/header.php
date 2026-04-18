<?php
require_once __DIR__ . '/config.php';
$receptionist = currentReceptionist();
$flash        = getFlash();

$currentPage  = basename($_SERVER['PHP_SELF'], '.php');
$tabLabelsMap = [
    'dashboard'    => 'Dashboard',
    'reservations' => 'Reservations',
    'calendar'     => 'Calendar',
    'payments'     => 'Payments',
    'facilities'   => 'Facilities',
];
$pageLabel = $tabLabelsMap[$currentPage] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $pageLabel ?> — <?= SITE_NAME ?> Receptionist</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link rel="stylesheet" href="<?= SITE_URL ?>/receptionist/css/style.css?v=6"/>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="app-shell">

  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <div class="main-content">

    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger-btn" onclick="toggleSidebar()">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div>
          <div class="topbar-title"><?= $pageLabel ?></div>
          <div class="topbar-subtitle">MGarden Beach Resort</div>
        </div>
      </div>
      <div class="topbar-right">
        <span class="topbar-date" id="topbarDate"></span>
      </div>
    </header>

    <?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" id="flashMsg">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
      <?= e($flash['message']) ?>
      <button onclick="this.parentElement.remove()" class="flash-close"><i class="fa-solid fa-times"></i></button>
    </div>
    <?php endif; ?>

    <div class="page-content">