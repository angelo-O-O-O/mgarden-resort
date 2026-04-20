<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── STATS ──
$stats = $db->query("
    SELECT
        COUNT(CASE WHEN status = 'pending'   THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status = 'approved'  THEN 1 END) AS approved_count,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count,
        COUNT(*) AS total_count
    FROM reservations
")->fetch_assoc();

// ── PENDING RESERVATIONS ──
$pendingReservations = $db->query("
    SELECT r.reservation_id, r.checkin_date, r.checkout_date,
           r.rate_type, r.status, r.num_guests, r.total_amount, r.reserved_at,
           f.facility_name, f.category,
           g.guest_name, g.email, g.contact_num
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    JOIN guests g ON r.guest_id = g.guest_id
    WHERE r.status = 'pending'
    ORDER BY r.reserved_at ASC
")->fetch_all(MYSQLI_ASSOC);

function catIcon($cat) {
    $map = [
        'pool'          => 'fa-solid fa-person-swimming',
        'beach'         => 'fa-solid fa-umbrella-beach',
        'accommodation' => 'fa-solid fa-bed',
        'dining'        => 'fa-solid fa-utensils',
        'spa'           => 'fa-solid fa-spa',
        'sports'        => 'fa-solid fa-person-running',
        'event'         => 'fa-solid fa-calendar-days',
        'activity'      => 'fa-solid fa-bullseye',
        'resort'        => 'fa-solid fa-hotel',
    ];
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $key => $icon) {
        if (str_contains($c, $key)) return "<i class=\"{$icon}\"></i>";
    }
    return '<i class="fa-solid fa-star"></i>';
}

function statusBadge($status) {
    $colors = [
        'pending'   => 'badge-yellow',
        'approved'  => 'badge-green',
        'cancelled' => 'badge-red',
        'completed' => 'badge-blue',
    ];
    return '<span class="status-badge ' . ($colors[$status] ?? 'badge-gray') . '">' . ucfirst($status) . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner -->
<div class="page-banner">
  <div>
    <h1>Good day, <?= e($receptionist['recpst_fname'] ?? 'Receptionist') ?>!</h1>
    <p>Here's an overview of resort reservations.</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-gauge-high"></i></div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-content">
      <h3><?= $stats['pending_count'] ?></h3>
      <p>Pending</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-content">
      <h3><?= $stats['approved_count'] ?></h3>
      <p>Approved</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa-solid fa-circle-xmark"></i></div>
    <div class="stat-content">
      <h3><?= $stats['cancelled_count'] ?></h3>
      <p>Cancelled</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
    <div class="stat-content">
      <h3><?= $stats['total_count'] ?></h3>
      <p>Total Reservations</p>
    </div>
  </div>
</div>

<!-- Pending Reservations -->
<div class="section-header">
  <div>
    <h2><i class="fa-solid fa-clock" style="color:var(--yellow);font-size:1rem;"></i> Pending Approvals</h2>
    <p>Reservations awaiting your action</p>
  </div>
  <?php if (!empty($pendingReservations)): ?>
    <span class="status-badge badge-yellow"><?= count($pendingReservations) ?> pending</span>
  <?php endif; ?>
</div>

<?php if (empty($pendingReservations)): ?>
  <div class="table-card">
    <div class="empty-state">
      <i class="fa-solid fa-inbox"></i>
      <h3>All caught up!</h3>
      <p>No reservations are waiting for approval.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Guest</th>
          <th>Facility</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Rate</th>
          <th>Guests</th>
          <th>Amount</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingReservations as $res): ?>
          <tr>
            <td>
              <div class="guest-info">
                <strong><?= e($res['guest_name']) ?></strong>
                <small><?= e($res['email']) ?></small>
                <small><?= e($res['contact_num']) ?></small>
              </div>
            </td>
            <td>
              <div class="facility-info">
                <?= catIcon($res['category']) ?>
                <span><?= e($res['facility_name']) ?></span>
              </div>
            </td>
            <td><strong><?= date('M d, Y', strtotime($res['checkin_date'])) ?></strong></td>
            <td><?= date('M d, Y', strtotime($res['checkout_date'])) ?></td>
            <td><span class="status-badge badge-gray"><?= ucfirst($res['rate_type']) ?></span></td>
            <td><?= $res['num_guests'] ?> pax</td>
            <td><strong>₱<?= number_format($res['total_amount'], 2) ?></strong></td>
            <td>
              <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="approveReservation(<?= $res['reservation_id'] ?>)">
                  <i class="fa-solid fa-check"></i> Approve
                </button>
                <button class="btn btn-sm btn-danger" onclick="cancelReservation(<?= $res['reservation_id'] ?>)">
                  <i class="fa-solid fa-times"></i> Cancel
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
  window.RECEPTIONIST_API = {
    updateReservationUrl: '<?= SITE_URL ?>/receptionist/pages/update_reservation.php'
  };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>