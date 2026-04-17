<?php
require_once __DIR__ . '/config.php';
$receptionist = currentReceptionist();
$flash        = getFlash();
$currentPage  = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?> - Receptionist</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link rel="stylesheet" href="<?= SITE_URL ?>/receptionist/css/style.css?v=1"/>
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar">
  <div class="container navbar-inner">

    <!-- Brand -->
    <a href="<?= SITE_URL ?>/receptionist/pages/dashboard.php" class="navbar-brand">
      <img
        src="<?= SITE_URL ?>/images/mgardenlogo.jpg"
        alt="MGarden Logo"
        class="brand-logo-img"
        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
      />
      <div class="brand-icon" style="display:none;">M</div>
      <div class="brand-text">
        <span class="brand-name">MGarden</span>
        <span class="brand-sub">Receptionist</span>
      </div>
    </a>

    <!-- Nav Links -->
    <nav class="nav-links" id="navLinks">
      <a href="<?= SITE_URL ?>/receptionist/pages/dashboard.php"
         class="nav-link" id="navDashboard">
        Dashboard
      </a>
    </nav>

    <!-- Auth Actions -->
    <div class="nav-actions">
      <?php if ($receptionist): ?>
        <!-- User Dropdown -->
        <div class="user-dropdown">
          <button class="user-btn" onclick="toggleDropdown()">
            <?php if (!empty($receptionist['picture'])): ?>
              <img
                src="data:image/jpeg;base64,<?= base64_encode($receptionist['picture']) ?>"
                alt="Profile"
                class="user-avatar-img"
              />
            <?php else: ?>
              <div class="user-avatar">
                <?= strtoupper(substr($receptionist['recpst_fname'] ?? 'R', 0, 1)) ?>
              </div>
            <?php endif; ?>
            <span class="user-name"><?= e($receptionist['recpst_fname'] ?? 'Receptionist') ?></span>
            <i class="fa-solid fa-chevron-down"></i>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <div class="dropdown-divider"></div>
            <a href="<?= SITE_URL ?>/receptionist/pages/logout.php" class="dropdown-item text-red"><i class="fa-solid fa-sign-out-alt"></i> Sign Out</a>
          </div>
        </div>
      <?php endif; ?>
      <button class="hamburger" onclick="toggleNav()" id="hamburger"><i class="fa-solid fa-bars"></i></button>
    </div>

  </div>
</header>

<!-- ── FLASH MESSAGE ── -->
<?php if ($flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" id="flashMsg">
  <?= e($flash['message']) ?>
  <button onclick="document.getElementById('flashMsg').remove()" class="flash-close"><i class="fa-solid fa-times"></i></button>
</div>
<?php endif; ?>

<main>
