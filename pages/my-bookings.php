<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$db  = getDB();
$uid = (int)$_SESSION['user_id'];

// Handle cancel
if (isset($_GET['cancel'])) {
    $bid = (int)$_GET['cancel'];
    $db->query("UPDATE bookings SET status='cancelled', cancelled_at=NOW(), cancellation_reason='Cancelled by guest', updated_at=NOW() WHERE id=$bid AND user_id=$uid AND status IN ('pending','confirmed')");
    setFlash('success', 'Booking cancelled successfully.');
    redirect(SITE_URL . '/pages/my-bookings.php');
}

$filter = $_GET['status'] ?? 'all';
$statusClause = $filter !== 'all' ? "AND b.status='" . $db->real_escape_string($filter) . "'" : '';

$bookings = $db->query("SELECT b.*, r.name as room_name, rc.name as category_name
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN room_categories rc ON r.category_id = rc.id
    WHERE b.user_id=$uid $statusClause
    ORDER BY b.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch services per booking
foreach ($bookings as &$bk) {
    $bk['services'] = $db->query("SELECT s.name FROM booking_services bs JOIN services s ON bs.service_id=s.id WHERE bs.booking_id={$bk['id']}")->fetch_all(MYSQLI_ASSOC);
}
unset($bk);

// Count per status
$counts = $db->query("SELECT status, COUNT(*) as cnt FROM bookings WHERE user_id=$uid GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$statusCounts = ['all' => 0];
foreach ($counts as $c) { $statusCounts[$c['status']] = $c['cnt']; $statusCounts['all'] += $c['cnt']; }

$badges = ['pending'=>'badge-pending','confirmed'=>'badge-confirmed','completed'=>'badge-completed','cancelled'=>'badge-cancelled','checked_in'=>'badge-checked_in'];
$labels = ['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled','checked_in'=>'Checked In'];

$pageTitle = 'My Bookings';
require_once __DIR__ . '/../includes/header.php';
?>
<script>const siteUrl='<?= SITE_URL ?>';</script>

<div class="container page-wrap">
  <div class="page-header flex justify-between items-center" style="flex-wrap:wrap;gap:10px;">
    <div>
      <h1>My Bookings</h1>
      <p><?= $statusCounts['all'] ?? 0 ?> total reservation<?= ($statusCounts['all'] ?? 0) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= SITE_URL ?>/index.php#rooms" class="btn btn-primary btn-sm">+ New Booking</a>
  </div>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <?php foreach (['all','pending','confirmed','completed','cancelled'] as $s): ?>
    <a href="?status=<?= $s ?>" class="filter-tab <?= $filter===$s?'active':'' ?>">
      <?= ucfirst($s) ?> (<?= $statusCounts[$s] ?? 0 ?>)
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($bookings)): ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <h2 class="empty-title">No <?= $filter !== 'all' ? $filter : '' ?> bookings found</h2>
    <p class="empty-desc">When you make a reservation, it will appear here.</p>
    <a href="<?= SITE_URL ?>/index.php#rooms" class="btn btn-primary">Browse Rooms</a>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:16px;">
    <?php foreach ($bookings as $b):
      $nights = max(1, (int)((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400));
      $placeholder = "https://placehold.co/300x200/d1fae5/065f46?text=" . urlencode($b['room_name']) . "&font=quicksand";
      $canCancel = in_array($b['status'], ['pending','confirmed']);
    ?>
    <div class="card" style="padding:0;overflow:hidden;">
      <div class="booking-card-wrap">
        <img src="<?= $placeholder ?>" alt="<?= e($b['room_name']) ?>" class="booking-img"/>
        <div class="booking-body">
          <div class="booking-ref">Ref: <?= e($b['booking_reference']) ?></div>
          <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:8px;margin-bottom:6px;">
            <h3 class="booking-name"><?= e($b['room_name']) ?></h3>
            <span class="badge <?= $badges[$b['status']] ?? 'badge-pending' ?>"><?= $labels[$b['status']] ?? $b['status'] ?></span>
          </div>
          <p class="booking-meta">
            📅 <?= date('M d', strtotime($b['check_in'])) ?> → <?= date('M d, Y', strtotime($b['check_out'])) ?>
            &nbsp;·&nbsp; 👤 <?= $b['num_guests'] ?> guest<?= $b['num_guests'] > 1 ? 's' : '' ?>
            &nbsp;·&nbsp; <?= $nights ?> night<?= $nights > 1 ? 's' : '' ?>
            &nbsp;·&nbsp; <?= e($b['category_name']) ?>
          </p>

          <?php if (!empty($b['services'])): ?>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
            <?php foreach ($b['services'] as $svc): ?>
              <span class="tag">+ <?= e($svc['name']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if ($b['special_requests']): ?>
          <p style="font-size:0.82rem;color:var(--gray-400);margin-bottom:10px;font-style:italic;">"<?= e($b['special_requests']) ?>"</p>
          <?php endif; ?>

          <div class="booking-footer">
            <div>
              <span class="booking-total"><?= peso($b['total_amount']) ?></span>
              <span class="badge <?= $b['payment_status']==='paid'?'badge-completed':'badge-pending' ?>" style="margin-left:10px;">
                <?= $b['payment_status']==='paid' ? 'Paid' : 'Pay on arrival' ?>
              </span>
            </div>
            <?php if ($canCancel): ?>
            <a href="?cancel=<?= $b['id'] ?>" onclick="return confirm('Are you sure you want to cancel this booking?')" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red);">✕ Cancel</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
