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

// Badge counts
$_pendingCount      = 0;
$_upcomingCheckins  = 0;
$_pendingPayments   = 0;
try {
    $db = getDB();
    $r = $db->query("SELECT COUNT(*) as cnt FROM reservations WHERE status = 'pending'");
    if ($r) $_pendingCount = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $db->query("SELECT COUNT(*) as cnt FROM reservations WHERE status = 'approved' AND checkin_date >= CURDATE()");
    if ($r) $_upcomingCheckins = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $db->query("SELECT COUNT(*) as cnt FROM payment_records WHERE status = 'pending'");
    if ($r) $_pendingPayments = (int)($r->fetch_assoc()['cnt'] ?? 0);
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
        <?php if ($pageKey === 'calendar' && $_upcomingCheckins > 0): ?>
          <span class="nav-badge"><?= $_upcomingCheckins ?></span>
        <?php endif; ?>
        <?php if ($pageKey === 'payments' && $_pendingPayments > 0): ?>
          <span class="nav-badge"><?= $_pendingPayments ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Logout -->
  <div class="sidebar-footer">
    <button onclick="openSignoutModal()" class="sidebar-logout" style="width:100%;background:none;border:none;text-align:left;cursor:pointer;">
      <i class="fa-solid fa-arrow-right-from-bracket"></i>
      Sign Out
    </button>
  </div>

</aside>

<!-- Signout confirm modal -->
<div class="modal-backdrop" id="signoutBackdrop" onclick="closeSignoutModal()"></div>
<div class="modal" id="signoutModal">
  <div class="modal-header">
    <div>
      <h3 class="modal-title">Sign Out</h3>
      <p class="modal-subtitle">Are you sure you want to sign out?</p>
    </div>
    <button class="modal-close" onclick="closeSignoutModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <div class="approve-confirm-box" style="background:var(--red-light);border-color:#fecaca;">
      <div class="approve-icon" style="background:var(--red-light);color:var(--red);">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
      </div>
      <div class="approve-info">
        <p style="font-size:0.92rem;color:var(--gray-700);line-height:1.5;">
          You will be logged out of the receptionist portal. Any unsaved changes will be lost.
        </p>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
      <button class="btn btn-outline" onclick="closeSignoutModal()">Stay</button>
      <a href="<?= SITE_URL ?>/receptionist/pages/logout.php" class="btn btn-danger">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
      </a>
    </div>
  </div>
</div>