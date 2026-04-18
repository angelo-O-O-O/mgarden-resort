<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── FILTER ──
$allowedStatuses = ['all', 'pending', 'approved', 'cancelled', 'completed'];
$filterStatus    = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $allowedStatuses)) $filterStatus = 'all';

$whereClause = $filterStatus !== 'all' ? "WHERE r.status = '$filterStatus'" : '';

// ── COUNTS PER STATUS (for filter tabs) ──
$counts = $db->query("
    SELECT status, COUNT(*) AS cnt
    FROM reservations
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$countMap = ['all' => 0];
foreach ($counts as $row) {
    $countMap[$row['status']] = (int)$row['cnt'];
    $countMap['all'] += (int)$row['cnt'];
}

// ── RESERVATIONS ──
$reservations = $db->query("
    SELECT r.reservation_id, r.checkin_date, r.checkout_date,
           r.rate_type, r.status, r.num_guests, r.total_amount, r.reserved_at,
           f.facility_name, f.category,
           g.guest_name, g.email, g.contact_num
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    JOIN guests g ON r.guest_id = g.guest_id
    $whereClause
    ORDER BY r.reserved_at DESC
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
    <h1>Reservations</h1>
    <p>Complete reservation history and status tracking</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-calendar-check"></i></div>
</div>

<!-- Status filter tabs -->
<div class="filter-tabs">
  <?php
  $filterDefs = [
      'all'       => ['label' => 'All',       'icon' => 'fa-solid fa-list'],
      'pending'   => ['label' => 'Pending',   'icon' => 'fa-solid fa-clock'],
      'approved'  => ['label' => 'Approved',  'icon' => 'fa-solid fa-circle-check'],
      'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-solid fa-circle-xmark'],
      'completed' => ['label' => 'Completed', 'icon' => 'fa-solid fa-flag-checkered'],
  ];
  foreach ($filterDefs as $key => $def):
      $isActive = $filterStatus === $key;
      $cnt      = $countMap[$key] ?? 0;
  ?>
    <a
      href="?status=<?= $key ?>"
      class="filter-tab <?= $isActive ? 'active' : '' ?>"
    >
      <i class="<?= $def['icon'] ?>"></i>
      <?= $def['label'] ?>
      <span class="filter-tab-count"><?= $cnt ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="section-header" style="margin-top: 20px;">
  <div>
    <h2>
      <?= $filterStatus === 'all' ? 'All Reservations' : ucfirst($filterStatus) . ' Reservations' ?>
    </h2>
    <p><?= count($reservations) ?> record<?= count($reservations) != 1 ? 's' : '' ?> found</p>
  </div>
</div>

<div class="table-card">
  <?php if (empty($reservations)): ?>
    <div class="empty-state">
      <i class="fa-solid fa-calendar"></i>
      <h3>No reservations found</h3>
      <p>There are no <?= $filterStatus !== 'all' ? $filterStatus . ' ' : '' ?>reservations at the moment.</p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Guest</th>
          <th>Facility</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Rate</th>
          <th>Pax</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Reserved On</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reservations as $res): ?>
          <tr>
            <td><small style="color:var(--gray-400);">#<?= $res['reservation_id'] ?></small></td>
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
            <td><?= $res['num_guests'] ?></td>
            <td><strong>₱<?= number_format($res['total_amount'], 2) ?></strong></td>
            <td><?= statusBadge($res['status']) ?></td>
            <td><small style="color:var(--gray-400);"><?= date('M d, Y H:i', strtotime($res['reserved_at'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>