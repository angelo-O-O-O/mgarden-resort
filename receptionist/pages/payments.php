<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── HANDLE APPROVE PAYMENT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    header('Content-Type: application/json');
    $id = (int)($_POST['payment_id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid payment ID']); exit; }

    $stmt = $db->prepare("
        UPDATE payment_records
        SET status = 'paid', payment_date = CURDATE(), payment_time = CURTIME()
        WHERE payment_id = ? AND status = 'pending'
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success'=>true,'message'=>'Payment approved']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Payment not found or already processed']);
    }
    exit;
}

// ── STATUS FILTER ──
$allowedStatuses = ['all','pending','paid','refunded'];
$filterStatus    = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $allowedStatuses)) $filterStatus = 'all';
$where = $filterStatus !== 'all' ? "WHERE pr.status = '$filterStatus'" : '';

// ── COUNTS ──
$counts = $db->query("SELECT status, COUNT(*) AS cnt FROM payment_records GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$countMap = ['all'=>0];
foreach ($counts as $row) { $countMap[$row['status']] = (int)$row['cnt']; $countMap['all'] += (int)$row['cnt']; }

// ── SUMMARY ──
$summary = $db->query("
    SELECT
        SUM(CASE WHEN status='paid'     THEN total_amount ELSE 0 END) AS total_collected,
        SUM(CASE WHEN status='pending'  THEN total_amount ELSE 0 END) AS total_pending,
        COUNT(CASE WHEN status='paid'     THEN 1 END) AS paid_count,
        COUNT(CASE WHEN status='pending'  THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status='refunded' THEN 1 END) AS refunded_count
    FROM payment_records
")->fetch_assoc();

// ── PAYMENT RECORDS ──
$payments = $db->query("
    SELECT pr.payment_id, pr.total_amount, pr.status, pr.payment_method,
           pr.payment_date, pr.payment_time, pr.created_at, pr.reservation_id,
           g.guest_name, g.email,
           f.facility_name
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
    return '<span style="color:var(--gray-300);">—</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
  <div>
    <h1>Payment Records</h1>
    <p>Track and confirm all guest payment transactions</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-credit-card"></i></div>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));">
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
    <div class="stat-content"><h3 style="font-size:1.25rem;">₱<?= number_format($summary['total_collected']??0,2) ?></h3><p>Collected</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-content"><h3 style="font-size:1.25rem;">₱<?= number_format($summary['total_pending']??0,2) ?></h3><p>Pending Amount</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-content"><h3><?= $summary['paid_count'] ?></h3><p>Paid</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-content"><h3><?= $summary['pending_count'] ?></h3><p>Pending</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-rotate-left"></i></div>
    <div class="stat-content"><h3><?= $summary['refunded_count'] ?></h3><p>Refunded</p></div>
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
    $cnt = $countMap[$key] ?? 0;
  ?>
    <a href="?status=<?= $key ?>" class="filter-tab <?= $filterStatus===$key?'active':'' ?>">
      <i class="<?= $def['icon'] ?>"></i> <?= $def['label'] ?>
      <span class="filter-tab-count"><?= $cnt ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="section-header" style="margin-top:20px;">
  <div>
    <h2><?= $filterStatus==='all'?'All Payments':ucfirst($filterStatus).' Payments' ?></h2>
    <p><?= count($payments) ?> record<?= count($payments)!=1?'s':'' ?> found</p>
  </div>
</div>

<div class="table-card">
  <?php if (empty($payments)): ?>
    <div class="empty-state">
      <i class="fa-solid fa-receipt"></i>
      <h3>No payment records</h3>
      <p>No <?= $filterStatus!=='all'?$filterStatus.' ':'' ?>payments found.</p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th><th>Guest</th><th>Facility</th><th>Reservation</th>
          <th>Amount</th><th>Method</th><th>Payment Date</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $pay): ?>
          <tr id="payrow-<?= $pay['payment_id'] ?>">
            <td><small style="color:var(--gray-400);">#<?= $pay['payment_id'] ?></small></td>
            <td>
              <div class="guest-info">
                <strong><?= e($pay['guest_name']??'—') ?></strong>
                <small><?= e($pay['email']??'') ?></small>
              </div>
            </td>
            <td><?= e($pay['facility_name']??'—') ?></td>
            <td>
              <?php if ($pay['reservation_id']): ?>
                <small style="color:var(--gray-500);">Res #<?= $pay['reservation_id'] ?></small>
              <?php else: ?>
                <small style="color:var(--gray-300);">—</small>
              <?php endif; ?>
            </td>
            <td><strong>₱<?= number_format($pay['total_amount'],2) ?></strong></td>
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
            <td id="paystatus-<?= $pay['payment_id'] ?>"><?= statusBadge($pay['status']) ?></td>
            <td>
              <?php if ($pay['status'] === 'pending'): ?>
                <button
                  class="btn btn-sm btn-primary"
                  onclick="approvePayment(<?= $pay['payment_id'] ?>, '<?= e($pay['guest_name']??'this guest') ?>', '<?= number_format($pay['total_amount'],2) ?>')"
                  id="paybtn-<?= $pay['payment_id'] ?>"
                >
                  <i class="fa-solid fa-circle-check"></i> Approve
                </button>
              <?php else: ?>
                <span style="color:var(--gray-300);font-size:0.82rem;">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Approve confirm modal -->
<div class="modal-backdrop" id="approveBackdrop" onclick="closeApproveModal()"></div>
<div class="modal" id="approveModal">
  <div class="modal-header">
    <div>
      <h3 class="modal-title">Confirm Payment</h3>
      <p class="modal-subtitle">Mark this payment as paid?</p>
    </div>
    <button class="modal-close" onclick="closeApproveModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <div class="approve-confirm-box">
      <div class="approve-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="approve-info">
        <p id="approveGuestName" style="font-weight:700;font-size:1rem;color:var(--gray-800);margin-bottom:4px;"></p>
        <p id="approveAmount" style="font-size:1.3rem;font-weight:700;color:var(--green);"></p>
        <p style="font-size:0.82rem;color:var(--gray-500);margin-top:6px;">
          Payment date and time will be recorded as right now.
        </p>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
      <button class="btn btn-outline" onclick="closeApproveModal()">Cancel</button>
      <button class="btn btn-primary" id="confirmApproveBtn" onclick="submitApprove()">
        <i class="fa-solid fa-circle-check"></i> Confirm Payment
      </button>
    </div>
  </div>
</div>

<script>
let pendingApproveId = null;

function approvePayment(paymentId, guestName, amount) {
  pendingApproveId = paymentId;
  document.getElementById('approveGuestName').textContent = guestName;
  document.getElementById('approveAmount').textContent    = '₱' + amount;
  document.getElementById('approveBackdrop').classList.add('show');
  document.getElementById('approveModal').classList.add('show');
}

function closeApproveModal() {
  document.getElementById('approveBackdrop').classList.remove('show');
  document.getElementById('approveModal').classList.remove('show');
  pendingApproveId = null;
}

function submitApprove() {
  if (!pendingApproveId) return;
  const btn = document.getElementById('confirmApproveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';

  const fd = new FormData();
  fd.append('action', 'approve');
  fd.append('payment_id', pendingApproveId);

  fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeApproveModal();
      if (data.success) {
        // Update row in-place without full reload
        const statusCell = document.getElementById('paystatus-' + pendingApproveId);
        const actionCell = document.getElementById('paybtn-' + pendingApproveId);
        if (statusCell) statusCell.innerHTML = '<span class="status-badge badge-green">Paid</span>';
        if (actionCell) actionCell.outerHTML = '<span style="color:var(--gray-300);font-size:0.82rem;">—</span>';
        showToast('Payment confirmed successfully.', 'success');
      } else {
        showToast(data.message || 'Failed to approve payment.', 'error');
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Confirm Payment';
    })
    .catch(() => {
      closeApproveModal();
      showToast('Network error. Please try again.', 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Confirm Payment';
    });
}

function showToast(msg, type) {
  const old = document.getElementById('payToast');
  if (old) old.remove();
  const t = document.createElement('div');
  t.id = 'payToast';
  t.className = 'flash flash-' + (type === 'success' ? 'success' : 'error');
  t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
  t.innerHTML = `<i class="fa-solid ${type==='success'?'fa-circle-check':'fa-circle-exclamation'}"></i> ${msg}
    <button onclick="this.parentElement.remove()" class="flash-close"><i class="fa-solid fa-times"></i></button>`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.4s'; setTimeout(()=>t.remove(),400); }, 4000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>