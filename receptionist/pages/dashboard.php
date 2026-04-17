<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// Fetch reservation statistics
$stats = $db->query("
    SELECT
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
        COUNT(*) as total_count
    FROM reservations
")->fetch_assoc();

// Fetch recent reservations (last 10)
$recentReservations = $db->query("
    SELECT r.reservation_id, r.guest_id, r.facility_id, r.checkin_date, r.checkout_date,
           r.rate_type, r.status, r.num_guests, r.total_amount, r.reserved_at,
           f.facility_name, f.category,
           g.guest_name, g.email, g.contact_num
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    JOIN guests g ON r.guest_id = g.guest_id
    ORDER BY r.reserved_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Fetch pending reservations for management
$pendingReservations = $db->query("
    SELECT r.reservation_id, r.guest_id, r.facility_id, r.checkin_date, r.checkout_date,
           r.rate_type, r.status, r.num_guests, r.total_amount, r.reserved_at,
           f.facility_name, f.category,
           g.guest_name, g.email, g.contact_num
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    JOIN guests g ON r.guest_id = g.guest_id
    WHERE r.status = 'pending'
    ORDER BY r.reserved_at DESC
")->fetch_all(MYSQLI_ASSOC);

function catIcon($category) {
    $map = [
        'pool'=>'fa-solid fa-person-swimming',
        'beach'=>'fa-solid fa-umbrella-beach',
        'accommodation'=>'fa-solid fa-bed',
        'dining'=>'fa-solid fa-utensils',
        'spa'=>'fa-solid fa-spa',
        'sports'=>'fa-solid fa-person-running',
        'event'=>'fa-solid fa-calendar-days',
        'activity'=>'fa-solid fa-bullseye',
        'resort'=>'fa-solid fa-hotel'
    ];
    $cat = strtolower(trim($category ?? ''));
    foreach ($map as $key => $icon) {
        if (str_contains($cat, $key)) {
            return "<i class=\"{$icon}\"></i>";
        }
    }
    return '<i class="fa-solid fa-star"></i>';
}

function statusBadge($status) {
    $colors = [
        'pending' => 'badge-yellow',
        'approved' => 'badge-green',
        'cancelled' => 'badge-red',
        'completed' => 'badge-blue'
    ];
    return '<span class="status-badge ' . ($colors[$status] ?? 'badge-gray') . '">' . ucfirst($status) . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- DASHBOARD HEADER -->
<section class="dashboard-header">
  <div class="container">
    <h1>Receptionist Dashboard</h1>
    <p>Welcome back! Here's an overview of all reservations.</p>
  </div>
</section>

<!-- STATS CARDS -->
<section class="stats-section">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-content">
          <h3><?= $stats['pending_count'] ?></h3>
          <p>Pending Reservations</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="stat-content">
          <h3><?= $stats['approved_count'] ?></h3>
          <p>Approved Reservations</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-times-circle"></i></div>
        <div class="stat-content">
          <h3><?= $stats['cancelled_count'] ?></h3>
          <p>Cancelled Reservations</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-calendar-alt"></i></div>
        <div class="stat-content">
          <h3><?= $stats['total_count'] ?></h3>
          <p>Total Reservations</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PENDING RESERVATIONS -->
<section class="reservations-section">
  <div class="container">
    <div class="section-header">
      <h2>Pending Reservations</h2>
      <p>Reservations awaiting approval</p>
    </div>

    <?php if (empty($pendingReservations)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-inbox"></i>
        <h3>No pending reservations</h3>
        <p>All reservations have been processed.</p>
      </div>
    <?php else: ?>
      <div class="reservations-table">
        <table>
          <thead>
            <tr>
              <th>Guest</th>
              <th>Facility</th>
              <th>Dates</th>
              <th>Guests</th>
              <th>Amount</th>
              <th>Status</th>
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
                  </div>
                </td>
                <td>
                  <div class="facility-info">
                    <?= catIcon($res['category']) ?>
                    <span><?= e($res['facility_name']) ?></span>
                  </div>
                </td>
                <td>
                  <div class="date-info">
                    <strong><?= date('M d', strtotime($res['checkin_date'])) ?> - <?= date('M d, Y', strtotime($res['checkout_date'])) ?></strong>
                    <small><?= ucfirst($res['rate_type']) ?></small>
                  </div>
                </td>
                <td><?= $res['num_guests'] ?> guest<?= $res['num_guests'] != 1 ? 's' : '' ?></td>
                <td><strong>₱<?= number_format($res['total_amount'], 2) ?></strong></td>
                <td><?= statusBadge($res['status']) ?></td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="approveReservation(<?= $res['reservation_id'] ?>)">
                      <i class="fa-solid fa-check"></i> Approve
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="cancelReservation(<?= $res['reservation_id'] ?>)">
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
  </div>
</section>

<!-- RECENT RESERVATIONS -->
<section class="reservations-section">
  <div class="container">
    <div class="section-header">
      <h2>Recent Reservations</h2>
      <p>Latest reservation activity</p>
    </div>

    <div class="reservations-table">
      <table>
        <thead>
          <tr>
            <th>Guest</th>
            <th>Facility</th>
            <th>Dates</th>
            <th>Guests</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Reserved</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentReservations as $res): ?>
            <tr>
              <td>
                <div class="guest-info">
                  <strong><?= e($res['guest_name']) ?></strong>
                  <small><?= e($res['email']) ?></small>
                </div>
              </td>
              <td>
                <div class="facility-info">
                  <?= catIcon($res['category']) ?>
                  <span><?= e($res['facility_name']) ?></span>
                </div>
              </td>
              <td>
                <div class="date-info">
                  <strong><?= date('M d', strtotime($res['checkin_date'])) ?> - <?= date('M d, Y', strtotime($res['checkout_date'])) ?></strong>
                  <small><?= ucfirst($res['rate_type']) ?></small>
                </div>
              </td>
              <td><?= $res['num_guests'] ?> guest<?= $res['num_guests'] != 1 ? 's' : '' ?></td>
              <td><strong>₱<?= number_format($res['total_amount'], 2) ?></strong></td>
              <td><?= statusBadge($res['status']) ?></td>
              <td><small><?= date('M d, H:i', strtotime($res['reserved_at'])) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<script>
  window.RECEPTIONIST_API = {
    updateReservationUrl: '<?= SITE_URL ?>/receptionist/pages/update_reservation.php'
  };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>