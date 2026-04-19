<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── MONTH/YEAR NAVIGATION ──
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

// ── ALL RESERVATIONS THIS MONTH (ALL statuses) ──
$reservations = $db->query("
    SELECT r.reservation_id, r.checkin_date, r.checkout_date,
           r.status, r.num_guests, r.total_amount, r.rate_type,
           g.guest_name, f.facility_name, f.category
    FROM reservations r
    JOIN guests g ON r.guest_id = g.guest_id
    JOIN facilities f ON r.facility_id = f.facility_id
    WHERE r.checkin_date <= '$monthEnd'
      AND r.checkout_date >= '$monthStart'
    ORDER BY r.checkin_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Build day → reservations map
$dayMap = [];
foreach ($reservations as $res) {
    $start = new DateTime($res['checkin_date']);
    $end   = new DateTime($res['checkout_date']);
    $cur   = clone $start;
    while ($cur < $end) {
        if ((int)$cur->format('n') === $month && (int)$cur->format('Y') === $year) {
            $dayMap[$cur->format('Y-m-d')][] = $res;
        }
        $cur->modify('+1 day');
    }
}

// ── MONTH STATS ──
$monthStats = $db->query("
    SELECT
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'pending'   THEN 1 END) AS pending,
        COUNT(CASE WHEN status = 'approved'  THEN 1 END) AS approved,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled
    FROM reservations
    WHERE checkin_date BETWEEN '$monthStart' AND '$monthEnd'
")->fetch_assoc();

// ── UPCOMING CHECK-INS (next 7 days, non-cancelled) ──
$upcoming = $db->query("
    SELECT r.reservation_id, r.checkin_date, r.checkout_date,
           r.num_guests, r.status, r.rate_type,
           g.guest_name, g.contact_num, g.email,
           f.facility_name, f.category
    FROM reservations r
    JOIN guests g ON r.guest_id = g.guest_id
    JOIN facilities f ON r.facility_id = f.facility_id
    WHERE r.checkin_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND r.status IN ('pending','approved')
    ORDER BY r.checkin_date ASC
")->fetch_all(MYSQLI_ASSOC);

function catIcon($cat) {
    $map = ['pool'=>'fa-solid fa-person-swimming','room'=>'fa-solid fa-bed',
            'family room'=>'fa-solid fa-people-roof','cottage'=>'fa-solid fa-house-chimney',
            'beach'=>'fa-solid fa-umbrella-beach','dining'=>'fa-solid fa-utensils',
            'spa'=>'fa-solid fa-spa','event'=>'fa-solid fa-calendar-days'];
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $k => $icon) if (str_contains($c, $k)) return "<i class=\"{$icon}\"></i>";
    return '<i class="fa-solid fa-star"></i>';
}
function statusBadge($s) {
    $colors = ['pending'=>'badge-yellow','approved'=>'badge-green',
               'cancelled'=>'badge-red','completed'=>'badge-blue'];
    return '<span class="status-badge '.($colors[$s]??'badge-gray').'">'.ucfirst($s).'</span>';
}

$firstDayOfMonth = (int)date('w', strtotime($monthStart));
$daysInMonth     = (int)date('t', strtotime($monthStart));
$today           = date('Y-m-d');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
  <div>
    <h1>Reservation Calendar</h1>
    <p>Viewing <?= $monthNames[$month] ?> <?= $year ?> — all statuses</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-calendar-days"></i></div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-calendar"></i></div>
    <div class="stat-content"><h3><?= $monthStats['total'] ?></h3><p>Total</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-content"><h3><?= $monthStats['pending'] ?></h3><p>Pending</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-content"><h3><?= $monthStats['approved'] ?></h3><p>Approved</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-flag-checkered"></i></div>
    <div class="stat-content"><h3><?= $monthStats['completed'] ?></h3><p>Completed</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa-solid fa-circle-xmark"></i></div>
    <div class="stat-content"><h3><?= $monthStats['cancelled'] ?></h3><p>Cancelled</p></div>
  </div>
</div>

<!-- Calendar -->
<div class="cal-card">
  <div class="cal-nav">
    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-sm btn-outline">
      <i class="fa-solid fa-chevron-left"></i> <?= $monthNames[$prevMonth] ?>
    </a>
    <h2 class="cal-nav-title"><?= $monthNames[$month] ?> <?= $year ?></h2>
    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-sm btn-outline">
      <?= $monthNames[$nextMonth] ?> <i class="fa-solid fa-chevron-right"></i>
    </a>
  </div>

  <div class="cal-header-row">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
      <div class="cal-col-head"><?= $d ?></div>
    <?php endforeach; ?>
  </div>

  <div class="cal-grid">
    <?php for ($i = 0; $i < $firstDayOfMonth; $i++): ?>
      <div class="cal-day cal-day--empty"></div>
    <?php endfor; ?>

    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
      $dayRes  = $dayMap[$dateStr] ?? [];
      $cls     = 'cal-day';
      if ($dateStr === $today) $cls .= ' cal-day--today';
      if (!empty($dayRes))     $cls .= ' cal-day--has-res';
    ?>
      <div class="<?= $cls ?>" onclick="openDayModal('<?= $dateStr ?>')">
        <span class="cal-day-num"><?= $d ?></span>
        <?php if (!empty($dayRes)): ?>
          <div class="cal-day-pills">
            <?php foreach (array_slice($dayRes, 0, 2) as $r): ?>
              <div class="cal-pill cal-pill--<?= $r['status'] ?>"><?= e($r['guest_name']) ?></div>
            <?php endforeach; ?>
            <?php if (count($dayRes) > 2): ?>
              <div class="cal-pill cal-pill--more">+<?= count($dayRes) - 2 ?> more</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>

    <?php
    $total = $firstDayOfMonth + $daysInMonth;
    $trail = $total % 7 === 0 ? 0 : 7 - ($total % 7);
    for ($i = 0; $i < $trail; $i++): ?>
      <div class="cal-day cal-day--empty"></div>
    <?php endfor; ?>
  </div>

  <div class="cal-legend">
    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--green);"></span> Approved</div>
    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--yellow);"></span> Pending</div>
    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--blue);"></span> Completed</div>
    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--red);"></span> Cancelled</div>
    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:rgba(5,150,105,0.15);border:2px solid var(--green);"></span> Today</div>
  </div>
</div>

<!-- Upcoming check-ins -->
<div class="section-header" style="margin-top:28px;">
  <div>
    <h2><i class="fa-solid fa-bell" style="color:var(--yellow);font-size:0.95rem;margin-right:6px;"></i>Upcoming Check-ins</h2>
    <p>Guests arriving within the next 7 days</p>
  </div>
  <?php if (!empty($upcoming)): ?>
    <span class="status-badge badge-yellow"><?= count($upcoming) ?> arriving</span>
  <?php endif; ?>
</div>

<div class="table-card">
  <?php if (empty($upcoming)): ?>
    <div class="empty-state">
      <i class="fa-solid fa-calendar-xmark"></i>
      <h3>No upcoming arrivals</h3>
      <p>No guests are checking in within the next 7 days.</p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr><th>Guest</th><th>Facility</th><th>Check-in</th><th>Check-out</th><th>Rate</th><th>Pax</th><th>Status</th><th>Contact</th></tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $res): ?>
          <tr>
            <td><div class="guest-info"><strong><?= e($res['guest_name']) ?></strong><small><?= e($res['email']) ?></small></div></td>
            <td><div class="facility-info"><?= catIcon($res['category']) ?><span><?= e($res['facility_name']) ?></span></div></td>
            <td><strong><?= date('M d, Y', strtotime($res['checkin_date'])) ?></strong></td>
            <td><?= date('M d, Y', strtotime($res['checkout_date'])) ?></td>
            <td><span class="status-badge badge-gray"><?= ucfirst($res['rate_type']) ?></span></td>
            <td><?= $res['num_guests'] ?> pax</td>
            <td><?= statusBadge($res['status']) ?></td>
            <td><small><?= e($res['contact_num']) ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Day modal -->
<div class="modal-backdrop" id="modalBackdrop" onclick="closeDayModal()"></div>
<div class="modal" id="dayModal">
  <div class="modal-header">
    <div>
      <h3 class="modal-title" id="modalTitle">Reservations</h3>
      <p class="modal-subtitle" id="modalSubtitle"></p>
    </div>
    <button class="modal-close" onclick="closeDayModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body" id="modalBody"></div>
</div>

<script>
const DAY_MAP = <?= json_encode($dayMap, JSON_HEX_TAG) ?>;

function openDayModal(dateStr) {
  const res = DAY_MAP[dateStr] || [];
  const d   = new Date(dateStr + 'T00:00:00');
  document.getElementById('modalTitle').textContent =
    d.toLocaleDateString('en-PH', {weekday:'long',month:'long',day:'numeric',year:'numeric'});
  document.getElementById('modalSubtitle').textContent =
    res.length ? res.length + ' reservation(s) on this day' : 'No reservations';

  const body  = document.getElementById('modalBody');
  const badge = {pending:'badge-yellow',approved:'badge-green',completed:'badge-blue',cancelled:'badge-red'};

  if (!res.length) {
    body.innerHTML = `<div class="empty-state" style="padding:28px 20px;">
      <i class="fa-solid fa-calendar"></i><h3>No reservations</h3><p>No bookings on this day.</p></div>`;
  } else {
    body.innerHTML = res.map(r => `
      <div class="modal-res-item">
        <div class="modal-res-top">
          <strong>${esc(r.guest_name)}</strong>
          <span class="status-badge ${badge[r.status]||'badge-gray'}">${r.status}</span>
        </div>
        <div class="modal-res-details">
          <span><i class="fa-solid fa-door-open"></i> ${esc(r.facility_name)}</span>
          <span><i class="fa-solid fa-calendar"></i> ${fmtDate(r.checkin_date)} → ${fmtDate(r.checkout_date)}</span>
          <span><i class="fa-solid fa-users"></i> ${r.num_guests} guest(s) &nbsp;|&nbsp; <i class="fa-solid fa-tag"></i> ${r.rate_type}</span>
          <span><i class="fa-solid fa-peso-sign"></i> ₱${parseFloat(r.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
        </div>
      </div>`).join('');
  }
  document.getElementById('modalBackdrop').classList.add('show');
  document.getElementById('dayModal').classList.add('show');
}
function closeDayModal() {
  document.getElementById('modalBackdrop').classList.remove('show');
  document.getElementById('dayModal').classList.remove('show');
}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML;}
function fmtDate(s){if(!s)return'—';return new Date(s+'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>