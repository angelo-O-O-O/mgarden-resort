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

    <?php if (!empty($_SESSION['login_welcome'])): ?>
    <?php $welcomeName = $_SESSION['login_welcome']; unset($_SESSION['login_welcome']); ?>
    <div class="welcome-modal-overlay" id="welcomeModal">
      <div class="welcome-modal">
        <div class="welcome-modal-icon">
          <i class="fa-solid fa-hand-wave"></i>
        </div>
        <h2>Welcome back, <?= e($welcomeName) ?>!</h2>
        <p>You're now logged in to the MGarden Receptionist Portal. Have a great shift!</p>
        <button class="btn btn-primary" onclick="closeWelcomeModal()">
          Go to Dashboard <i class="fa-solid fa-arrow-right"></i>
        </button>
      </div>
    </div>
    <style>
      .welcome-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.45);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeInOverlay .25s ease;
      }
      @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }
      .welcome-modal {
        background: #fff;
        border-radius: 18px;
        padding: 48px 40px 40px;
        max-width: 420px;
        width: 90%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
        animation: slideUpModal .3s ease;
      }
      @keyframes slideUpModal { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
      .welcome-modal-icon {
        width: 72px;
        height: 72px;
        background: linear-gradient(135deg, #34d399, #10b981);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 1.8rem;
        color: #fff;
      }
      .welcome-modal h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a1a2e;
        margin: 0 0 10px;
      }
      .welcome-modal p {
        color: #6b7280;
        font-size: .95rem;
        margin: 0 0 28px;
        line-height: 1.6;
      }
      .app-shell.modal-blur {
        filter: blur(4px);
        pointer-events: none;
        user-select: none;
      }
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('welcomeModal');
        if (modal) {
          document.body.appendChild(modal);
          document.querySelector('.app-shell')?.classList.add('modal-blur');
        }
      });
      function closeWelcomeModal() {
        document.getElementById('welcomeModal').remove();
        document.querySelector('.app-shell')?.classList.remove('modal-blur');
      }
    </script>
    <?php endif; ?>

    <div class="page-content">