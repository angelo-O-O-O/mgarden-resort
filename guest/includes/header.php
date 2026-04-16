<?php
require_once __DIR__ . '/config.php';
$guest       = currentGuest();
$flash       = getFlash();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= SITE_URL ?>/guest/css/style.css?v=1"/>
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar">
  <div class="container navbar-inner">

    <!-- Brand -->
    <a href="<?= SITE_URL ?>/guest/index.php" class="navbar-brand">
      <img
        src="<?= SITE_URL ?>/images/mgardenlogo.jpg"
        alt="MGarden Logo"
        class="brand-logo-img"
        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
      />
      <div class="brand-icon" style="display:none;">M</div>
      <div class="brand-text">
        <span class="brand-name">MGarden</span>
        <span class="brand-sub">Beach Resort</span>
      </div>
    </a>

    <!-- Nav Links -->
    <nav class="nav-links" id="navLinks">
      <a href="<?= SITE_URL ?>/guest/index.php"
         class="nav-link" id="navHome">
        Home
      </a>
      <a href="<?= SITE_URL ?>/guest/index.php#facilities" class="nav-link">Facilities</a>
      <a href="<?= SITE_URL ?>/guest/index.php#pricing"    class="nav-link">Pricing</a>
      <a href="#" class="nav-link">Schedules</a>
      <a href="<?= SITE_URL ?>/guest/index.php#about"      class="nav-link">About Us</a>
    </nav>

    <!-- Auth Actions -->
    <div class="nav-actions">
      <?php if ($guest): ?>
        <!-- Cart -->
        <a href="<?= SITE_URL ?>/guest/pages/cart.php" class="cart-btn" title="My Cart">
          🛒
        </a>
        <!-- User Dropdown -->
        <div class="user-dropdown">
          <button class="user-btn" onclick="toggleDropdown()">
            <?php if (!empty($guest['profile_pic'])): ?>
              <img
                src="<?= SITE_URL ?>/guest/pages/profile_pic.php?id=<?= $guest['guest_id'] ?>"
                alt="Profile"
                class="user-avatar-img"
              />
            <?php else: ?>
              <div class="user-avatar">
                <?= strtoupper(substr($guest['guest_name'] ?? 'G', 0, 1)) ?>
              </div>
            <?php endif; ?>
            <span class="user-name"><?= e(explode(' ', trim($guest['guest_name'] ?? 'Guest'))[0]) ?></span>
            <span>▾</span>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <a href="<?= SITE_URL ?>/guest/pages/my_bookings.php" class="dropdown-item">📋 My Bookings</a>
            <a href="<?= SITE_URL ?>/guest/pages/profile.php"     class="dropdown-item">👤 My Profile</a>
            <div class="dropdown-divider"></div>
            <a href="<?= SITE_URL ?>/guest/pages/logout.php" class="dropdown-item text-red">🚪 Sign Out</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/guest/pages/login.php"  class="btn btn-outline">Login</a>
        <a href="<?= SITE_URL ?>/guest/pages/signup.php" class="btn btn-primary">Sign Up</a>
      <?php endif; ?>
      <button class="hamburger" onclick="toggleNav()" id="hamburger">☰</button>
    </div>

  </div>
</header>

<!-- ── FLASH MESSAGE ── -->
<?php if ($flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" id="flashMsg">
  <?= e($flash['message']) ?>
  <button onclick="document.getElementById('flashMsg').remove()" class="flash-close">✕</button>
</div>
<?php endif; ?>

<main>