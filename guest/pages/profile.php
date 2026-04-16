<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$guest = currentGuest();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
  <div class="container" style="max-width:720px;">

    <!-- Page Header -->
    <div style="margin-bottom:32px;">
      <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-bottom:4px;">My Profile</h1>
      <p style="color:var(--gray-400);">View your account information</p>
    </div>

    <!-- Profile Card -->
    <div class="card" style="padding:36px;box-shadow:var(--shadow-hover);">

      <!-- Avatar + Name -->
      <div style="display:flex;align-items:center;gap:24px;margin-bottom:36px;padding-bottom:28px;border-bottom:1px solid var(--green-100);">
        <?php if (!empty($guest['profile_pic'])): ?>
          <img
            src="<?= SITE_URL ?>/guest/pages/profile_pic.php?id=<?= $guest['guest_id'] ?>"
            alt="Profile Photo"
            style="width:88px;height:88px;border-radius:50%;object-fit:cover;box-shadow:var(--shadow-hover);border:3px solid var(--green-100);"
          />
        <?php else: ?>
          <div style="width:88px;height:88px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;flex-shrink:0;box-shadow:var(--shadow-hover);">
            <?= strtoupper(substr($guest['guest_name'] ?? 'G', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div>
          <h2 style="font-size:1.4rem;font-weight:700;color:var(--gray-800);margin-bottom:4px;">
            <?= e($guest['guest_name'] ?? '—') ?>
          </h2>
          <p style="color:var(--gray-400);font-size:0.88rem;">MGarden Guest Account</p>
        </div>
      </div>

      <!-- Info Grid -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <div class="profile-info-item">
          <p class="profile-info-label"><i class="fa-solid fa-envelope"></i> Email Address</p>
          <p class="profile-info-val"><?= e($guest['email'] ?? '—') ?></p>
        </div>

        <div class="profile-info-item">
          <p class="profile-info-label"><i class="fa-solid fa-phone"></i> Contact Number</p>
          <p class="profile-info-val"><?= e($guest['contact_num'] ?? '—') ?></p>
        </div>

        <div class="profile-info-item" style="grid-column:1/-1;">
          <p class="profile-info-label"><i class="fa-solid fa-map-marker-alt"></i> Address</p>
          <p class="profile-info-val"><?= e($guest['address'] ?? '—') ?></p>
        </div>

      </div>

      <!-- Actions -->
      <div style="margin-top:32px;padding-top:24px;border-top:1px solid var(--green-100);display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?= SITE_URL ?>/guest/index.php" class="btn btn-outline">← Back to Home</a>
        <a href="<?= SITE_URL ?>/guest/pages/logout.php" class="btn btn-red btn-sm" style="margin-left:auto;"><i class="fa-solid fa-sign-out-alt"></i> Sign Out</a>
      </div>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>