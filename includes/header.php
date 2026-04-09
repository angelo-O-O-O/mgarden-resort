<?php
require_once __DIR__ . '/config.php';
$user      = currentUser();
$cartCount = getCartCount();
$flash     = getFlash();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=1"/>
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar">
  <div class="container navbar-inner">

    <a href="<?= SITE_URL ?>/index.php" class="navbar-brand">
      <div class="brand-icon">M</div>
      <div class="brand-text">
        <span class="brand-name">MGarden</span>
        <span class="brand-sub">Beach Resort</span>
      </div>
    </a>

    <nav class="nav-links" id="navLinks">
      <a href="<?= SITE_URL ?>/index.php"              class="nav-link <?= $currentPage==='index.php'?'active':'' ?>">Home</a>
      <a href="<?= SITE_URL ?>/pages/schedules.php"    class="nav-link <?= $currentPage==='schedules.php'?'active':'' ?>">Schedules</a>
      <a href="<?= SITE_URL ?>/pages/about.php"        class="nav-link <?= $currentPage==='about.php'?'active':'' ?>">About Us</a>
    </nav>

    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="<?= SITE_URL ?>/pages/cart.php" class="cart-btn" title="My Cart">
          🛒
          <?php if ($cartCount > 0): ?>
            <span class="cart-badge"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>
        <div class="user-dropdown">
          <button class="user-btn" onclick="toggleDropdown()">
            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <span class="user-name"><?= e(explode(' ', $user['name'])[0]) ?></span>
            <span>▾</span>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <a href="<?= SITE_URL ?>/pages/my-bookings.php" class="dropdown-item">📋 My Bookings</a>
            <a href="<?= SITE_URL ?>/pages/cart.php"        class="dropdown-item">🛒 My Cart <?= $cartCount>0?"($cartCount)":'' ?></a>
            <div class="dropdown-divider"></div>
            <a href="<?= SITE_URL ?>/pages/logout.php"      class="dropdown-item text-red">🚪 Sign Out</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/pages/login.php"    class="btn btn-outline">Sign In</a>
        <a href="<?= SITE_URL ?>/pages/register.php" class="btn btn-primary">Sign Up</a>
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
