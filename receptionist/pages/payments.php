<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── HANDLE APPROVE ──
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

<style>
.pay-toolbar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin: 20px 0 14px;
}
.pay-search-wrap {
  position: relative; flex: 1; min-width: 200px; max-width: 320px;
}
.pay-search-wrap i {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  color: var(--gray-400); font-size: 0.82rem; pointer-events: none;
}
.pay-search-wrap input {
  width: 100%; padding: 8px 12px 8px 34px;
  border: 1.5px solid var(--gray-200); border-radius: var(--radius);
  font-size: 0.88rem; font-family: inherit; color: var(--gray-800);
  background: #fff; transition: var(--transition);
}
.pay-search-wrap input:focus {
  outline: none; border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(5,150,105,0.08);
}
.date-filter-wrap { position: relative; }
.date-filter-pop {
  display: none; position: absolute; top: calc(100% + 8px); right: 0;
  background: #fff; border: 1.5px solid var(--gray-200);
  border-radius: var(--radius); box-shadow: 0 8px 32px rgba(0,0,0,0.13);
  padding: 16px; z-index: 400; min-width: 230px;
}
.date-filter-pop.open { display: block; }
.mini-cal-nav {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px;
}
.mini-cal-nav span { font-size: 0.88rem; font-weight: 700; color: var(--gray-800); }
.mini-cal-nav button {
  background: none; border: none; cursor: pointer; padding: 4px 7px;
  border-radius: 5px; color: var(--gray-500); font-size: 0.8rem;
  transition: var(--transition);
}
.mini-cal-nav button:hover { background: var(--gray-100); color: var(--gray-800); }
.mini-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.mini-cal-dow {
  text-align: center; font-size: 0.68rem; font-weight: 700;
  color: var(--gray-400); padding: 3px 0; text-transform: uppercase;
}
.mini-cal-day {
  text-align: center; font-size: 0.8rem; padding: 5px 2px;
  border-radius: 5px; border: none; background: none;
  cursor: pointer; font-family: inherit; color: var(--gray-700);
  transition: var(--transition);
}
.mini-cal-day:hover  { background: var(--green-100); color: var(--green-dark); }
.mini-cal-day.today  { font-weight: 700; color: var(--green); }
.mini-cal-day.selected { background: var(--green); color: #fff; font-weight: 700; }
.date-clear-btn {
  display: block; width: 100%; margin-top: 10px;
  padding: 7px; border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-sm); background: none;
  font-family: inherit; font-size: 0.82rem; color: var(--gray-500);
  cursor: pointer; transition: var(--transition);
}
.date-clear-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }
.btn-date-active { background: var(--green) !important; color: #fff !important; border-color: var(--green) !important; }
</style>

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

<!-- Toolbar -->
<div class="pay-toolbar">
  <div class="pay-search-wrap">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" id="searchInput" placeholder="Search by guest name…" oninput="applyFilters()">
  </div>
  <div class="date-filter-wrap">
    <button class="btn btn-outline btn-sm" id="dateFilterBtn" onclick="toggleDatePop(event)">
      <i class="fa-solid fa-calendar-days"></i>
      <span id="dateFilterLabel">Filter by Date</span>
    </button>
    <div class="date-filter-pop" id="dateFilterPop">
      <div id="miniCalBody"></div>
    </div>
  </div>
</div>

<!-- Section header -->
<div class="section-header">
  <div>
    <h2><?= $filterStatus==='all'?'All Payments':ucfirst($filterStatus).' Payments' ?></h2>
    <p id="recordCount"><?= count($payments) ?> record<?= count($payments)!=1?'s':'' ?> found</p>
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
    <div class="empty-state" id="noResultsState" style="display:none;">
      <i class="fa-solid fa-magnifying-glass"></i>
      <h3>No results found</h3>
      <p>Try adjusting your search or filters.</p>
    </div>
    <div style="overflow-x:auto;">
      <table id="payTable">
        <thead>
          <tr>
            <th>#</th><th>Guest</th><th>Facility</th><th>Reservation</th>
            <th>Amount</th><th>Method</th><th>Payment Date</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody id="payTableBody">
          <?php foreach ($payments as $pay): ?>
            <tr
              id="payrow-<?= $pay['payment_id'] ?>"
              data-guest="<?= strtolower(e($pay['guest_name'] ?? '')) ?>"
              data-paydate="<?= $pay['payment_date'] ? e($pay['payment_date']) : '' ?>"
            >
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
                  <div class="action-buttons" id="payactions-<?= $pay['payment_id'] ?>">
                    <button
                      class="btn btn-sm btn-primary"
                      onclick="approvePayment(<?= $pay['payment_id'] ?>, '<?= e($pay['guest_name']??'this guest') ?>', '<?= number_format($pay['total_amount'],2) ?>')"
                    >
                      <i class="fa-solid fa-circle-check"></i> Approve
                    </button>
                  </div>
                <?php else: ?>
                  <span style="color:var(--gray-300);font-size:0.82rem;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
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
// ── Approve ──
let pendingApproveId = null;

function approvePayment(id, name, amount) {
  pendingApproveId = id;
  document.getElementById('approveGuestName').textContent = name;
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

  fetch('', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      closeApproveModal();
      if (data.success) {
        document.getElementById('paystatus-' + pendingApproveId).innerHTML = '<span class="status-badge badge-green">Paid</span>';
        const actions = document.getElementById('payactions-' + pendingApproveId);
        if (actions) actions.outerHTML = '<span style="color:var(--gray-300);font-size:0.82rem;">—</span>';
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


// ── Search + Date filter ──
let selectedDate = null;
let calYear, calMonth;
const CAL_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const CAL_DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa'];
const TODAY_STR  = new Date().toISOString().slice(0, 10);

function applyFilters() {
  const search = (document.getElementById('searchInput')?.value ?? '').toLowerCase().trim();
  const rows   = Array.from(document.querySelectorAll('#payTableBody tr'));

  const visible = rows.filter(row => {
    if (search && !row.dataset.guest.includes(search))          return false;
    if (selectedDate && row.dataset.paydate !== selectedDate)   return false;
    return true;
  });

  rows.forEach(r => r.style.display = 'none');

  const noResults = document.getElementById('noResultsState');
  const table     = document.getElementById('payTable');
  if (noResults && table) {
    noResults.style.display = visible.length === 0 ? '' : 'none';
    table.style.display     = visible.length === 0 ? 'none' : '';
  }

  visible.forEach(r => r.style.display = '');

  const countEl = document.getElementById('recordCount');
  if (countEl) countEl.textContent = visible.length + ' record' + (visible.length !== 1 ? 's' : '') + ' found';
}

function renderCal() {
  const now = new Date();
  if (calYear  === undefined) calYear  = now.getFullYear();
  if (calMonth === undefined) calMonth = now.getMonth();

  const firstDow    = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

  let html = `<div class="mini-cal-nav">
    <button onclick="calNav(-1)"><i class="fa-solid fa-chevron-left"></i></button>
    <span>${CAL_MONTHS[calMonth]} ${calYear}</span>
    <button onclick="calNav(1)"><i class="fa-solid fa-chevron-right"></i></button>
  </div><div class="mini-cal-grid">`;

  CAL_DAYS.forEach(d => { html += `<div class="mini-cal-dow">${d}</div>`; });
  for (let i = 0; i < firstDow; i++) html += '<div></div>';

  for (let d = 1; d <= daysInMonth; d++) {
    const ds  = `${calYear}-${String(calMonth + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const cls = ['mini-cal-day', ds === TODAY_STR ? 'today' : '', ds === selectedDate ? 'selected' : ''].join(' ');
    html += `<button class="${cls}" onclick="pickDate('${ds}')">${d}</button>`;
  }

  html += '</div>';
  if (selectedDate) {
    html += `<button class="date-clear-btn" onclick="clearDateFilter()"><i class="fa-solid fa-xmark"></i> Clear filter</button>`;
  }
  document.getElementById('miniCalBody').innerHTML = html;
}

function calNav(dir) {
  calMonth += dir;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  if (calMonth < 0)  { calMonth = 11; calYear--; }
  renderCal();
}

function pickDate(ds) {
  selectedDate = ds;
  const d = new Date(ds + 'T00:00:00');
  document.getElementById('dateFilterLabel').textContent =
    d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
  const btn = document.getElementById('dateFilterBtn');
  btn.classList.add('btn-date-active');
  btn.classList.remove('btn-outline');
  document.getElementById('dateFilterPop').classList.remove('open');
  applyFilters();
}

function clearDateFilter() {
  selectedDate = null;
  document.getElementById('dateFilterLabel').textContent = 'Filter by Date';
  const btn = document.getElementById('dateFilterBtn');
  btn.classList.remove('btn-date-active');
  btn.classList.add('btn-outline');
  document.getElementById('dateFilterPop').classList.remove('open');
  applyFilters();
}

function toggleDatePop(e) {
  e.stopPropagation();
  const pop = document.getElementById('dateFilterPop');
  const isOpen = pop.classList.contains('open');
  pop.classList.toggle('open');
  if (!isOpen) renderCal();
}

document.addEventListener('click', function(e) {
  const pop = document.getElementById('dateFilterPop');
  const btn = document.getElementById('dateFilterBtn');
  if (pop && !pop.contains(e.target) && !btn.contains(e.target)) {
    pop.classList.remove('open');
  }
});

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

document.addEventListener('DOMContentLoaded', applyFilters);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
