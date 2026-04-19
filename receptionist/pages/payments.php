<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── STATUS FILTER ──
$allowedStatuses = ['all','pending','paid','refunded'];
$filterStatus    = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $allowedStatuses)) $filterStatus = 'all';

$where = $filterStatus !== 'all' ? "WHERE pr.status = '$filterStatus'" : '';

// ── STATUS COUNTS ──
$counts = $db->query("
    SELECT status, COUNT(*) AS cnt FROM payment_records GROUP BY status
")->fetch_all(MYSQLI_ASSOC);
$countMap = ['all' => 0];
foreach ($counts as $row) {
    $countMap[$row['status']] = (int)$row['cnt'];
    $countMap['all'] += (int)$row['cnt'];
}

// ── SUMMARY STATS ──
$summary = $db->query("
    SELECT
        SUM(CASE WHEN status = 'paid'    THEN total_amount ELSE 0 END) AS total_collected,
        SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) AS total_pending,
        COUNT(CASE WHEN status = 'paid'    THEN 1 END) AS paid_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status = 'refunded' THEN 1 END) AS refunded_count,
        COUNT(*) AS total_count
    FROM payment_records
")->fetch_assoc();

// ── PAYMENT RECORDS ──
$payments = $db->query("
    SELECT pr.payment_id, pr.total_amount, pr.status, pr.payment_method,
           pr.payment_date, pr.payment_time, pr.created_at,
           pr.reservation_id,
           g.guest_name, g.email,
           f.facility_name, f.category
    FROM payment_records pr
    LEFT JOIN guests g ON pr.guest_id = g.guest_id
    LEFT JOIN reservations r ON pr.reservation_id = r.reservation_id
    LEFT JOIN facilities f ON r.facility_id = f.facility_id
    $where
    ORDER BY pr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

function statusBadge($s) {
    $colors = ['paid'=>'badge-green','pending'=>'badge-yellow','refunded'=>'badge-blue'];
    return '<span class="status-badge '.($colors[$s]??'badge-gray').'">'.ucfirst($s).'</span>';
}
function methodIcon($m) {
    if ($m === 'gcash') return '<i class="fa-solid fa-mobile-screen-button" style="color:var(--blue);"></i> GCash';
    if ($m === 'cash')  return '<i class="fa-solid fa-money-bill-wave" style="color:var(--green);"></i> Cash';
    return '—';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
  <div>
    <h1>Payment Records</h1>
    <p>Track all guest payment transactions</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-credit-card"></i></div>
</div>

<!-- Summary cards -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
    <div class="stat-content">
      <h3 style="font-size:1.3rem;">₱<?= number_format($summary['total_collected'] ?? 0, 2) ?></h3>
      <p>Total Collected</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-content">
      <h3 style="font-size:1.3rem;">₱<?= number_format($summary['total_pending'] ?? 0, 2) ?></h3>
      <p>Pending Amount</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-content">
      <h3><?= $summary['paid_count'] ?></h3>
      <p>Paid Transactions</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-content">
      <h3><?= $summary['pending_count'] ?></h3>
      <p>Pending</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-rotate-left"></i></div>
    <div class="stat-content">
      <h3><?= $summary['refunded_count'] ?></h3>
      <p>Refunded</p>
    </div>
  </div>
</div>

<!-- Filter tabs -->
<div class="filter-tabs">
  <?php
  $filterDefs = [
    'all'      => ['label'=>'All',      'icon'=>'fa-solid fa-list'],
    'pending'  => ['label'=>'Pending',  'icon'=>'fa-solid fa-clock'],
    'paid'     => ['label'=>'Paid',     'icon'=>'fa-solid fa-circle-check'],
    'refunded' => ['label'=>'Refunded', 'icon'=>'fa-solid fa-rotate-left'],
  ];
  foreach ($filterDefs as $key => $def):
    $isActive = $filterStatus === $key;
    $cnt      = $countMap[$key] ?? 0;
  ?>
    <a href="?status=<?= $key ?>" class="filter-tab <?= $isActive ? 'active' : '' ?>">
      <i class="<?= $def['icon'] ?>"></i>
      <?= $def['label'] ?>
      <span class="filter-tab-count"><?= $cnt ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="section-header" style="margin-top:20px;">
  <div>
    <h2><?= $filterStatus === 'all' ? 'All Payments' : ucfirst($filterStatus).' Payments' ?></h2>
    <p><?= count($payments) ?> record<?= count($payments) != 1 ? 's' : '' ?> found</p>
  </div>
</div>

<div class="table-card">
  <?php if (empty($payments)): ?>
    <div class="empty-state">
      <i class="fa-solid fa-receipt"></i>
      <h3>No payment records</h3>
      <p>No <?= $filterStatus !== 'all' ? $filterStatus.' ' : '' ?>payments found.</p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Guest</th>
          <th>Facility</th>
          <th>Reservation</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Payment Date</th>
          <th>Status</th>
          <th>Recorded</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $pay): ?>
          <tr>
            <td><small style="color:var(--gray-400);">#<?= $pay['payment_id'] ?></small></td>
            <td>
              <div class="guest-info">
                <strong><?= e($pay['guest_name'] ?? '—') ?></strong>
                <small><?= e($pay['email'] ?? '') ?></small>
              </div>
            </td>
            <td><?= e($pay['facility_name'] ?? '—') ?></td>
            <td>
              <?php if ($pay['reservation_id']): ?>
                <small style="color:var(--gray-500);">Res #<?= $pay['reservation_id'] ?></small>
              <?php else: ?>
                <small style="color:var(--gray-300);">—</small>
              <?php endif; ?>
            </td>
            <td><strong>₱<?= number_format($pay['total_amount'], 2) ?></strong></td>
            <td><?= methodIcon($pay['payment_method']) ?></td>
            <td>
              <?php if ($pay['payment_date']): ?>
                <div class="date-info">
                  <strong><?= date('M d, Y', strtotime($pay['payment_date'])) ?></strong>
                  <?php if ($pay['payment_time']): ?>
                    <small><?= date('h:i A', strtotime($pay['payment_time'])) ?></small>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <span style="color:var(--gray-300);">—</span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($pay['status']) ?></td>
            <td><small style="color:var(--gray-400);"><?= date('M d, Y', strtotime($pay['created_at'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>