<?php
// sidebar.php — included by header.php
// $receptionist and $currentPage must already be set by header.php

$navItems = [
    'dashboard'    => ['label' => 'Dashboard',    'icon' => 'fa-solid fa-gauge-high'],
    'reservations' => ['label' => 'Reservations', 'icon' => 'fa-solid fa-calendar-check'],
    'calendar'     => ['label' => 'Calendar',     'icon' => 'fa-solid fa-calendar-days'],
    'payments'     => ['label' => 'Payments',     'icon' => 'fa-solid fa-credit-card'],
    'facilities'   => ['label' => 'Facilities',   'icon' => 'fa-solid fa-door-open'],
];

// Pending count for badge
$_pendingCount = 0;
try {
    $db = getDB();
    $r = $db->query("SELECT COUNT(*) as cnt FROM reservations WHERE status = 'pending'");
    if ($r) $_pendingCount = (int)($r->fetch_assoc()['cnt'] ?? 0);
} catch (Exception $e) {}
?>
<aside class="sidebar" id="sidebar">

  <!-- Resort brand -->
  <div class="sidebar-brand">
    <img
      src="<?= SITE_URL ?>/images/mgardenlogo.jpg"
      alt="MGarden"
      class="sidebar-logo-img"
      onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
    />
    <div class="sidebar-logo-fallback" style="display:none;">M</div>
    <div class="sidebar-brand-text">
      <span class="brand-name">MGarden</span>
      <span class="brand-sub">Beach Resort</span>
    </div>
  </div>

  <!-- Profile -->
  <div class="sidebar-profile">
    <div class="profile-avatar-wrap">
      <div class="profile-avatar">
        <?php if (!empty($receptionist['picture'])): ?>
          <img src="data:image/jpeg;base64,<?= base64_encode($receptionist['picture']) ?>" alt="Profile"/>
        <?php else: ?>
          <?= strtoupper(substr($receptionist['recpst_fname'] ?? 'R', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="profile-info">
        <span class="profile-name">
          <?= e(trim(($receptionist['recpst_fname'] ?? '') . ' ' . ($receptionist['recpst_lname'] ?? ''))) ?>
        </span>
        <span class="profile-role"><?= e($receptionist['role'] ?? 'Receptionist') ?></span>
      </div>
    </div>
  </div>

  <!-- Nav -->
  <nav class="sidebar-nav">
    <span class="nav-label">Main Menu</span>

    <?php foreach ($navItems as $pageKey => $item): ?>
      <a
        href="<?= SITE_URL ?>/receptionist/pages/<?= $pageKey ?>.php"
        class="sidebar-link <?= $currentPage === $pageKey ? 'active' : '' ?>"
      >
        <i class="<?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($pageKey === 'dashboard' && $_pendingCount > 0): ?>
          <span class="nav-badge"><?= $_pendingCount ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Logout -->
  <div class="sidebar-footer">
    <a href="<?= SITE_URL ?>/receptionist/pages/logout.php" class="sidebar-logout">
      <i class="fa-solid fa-arrow-right-from-bracket"></i>
      Sign Out
    </a>
  </div>

</aside>