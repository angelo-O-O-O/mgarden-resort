<?php
$pageTitle = 'Schedules';
require_once __DIR__ . '/../includes/config.php';

$db       = getDB();
$guest_id = isLoggedIn() ? (int)$_SESSION['guest_id'] : 0;

// ── Fetch all facilities for filter ──
$facilities = $db->query("SELECT facility_id, facility_name, category FROM facilities ORDER BY facility_name ASC")->fetch_all(MYSQLI_ASSOC);

// ── Fetch reservations (approved + pending) ──
// "All schedules" = all guests' reservations (approved only shown to others, all shown to owner)
$allReservations = $db->query("
    SELECT r.reservation_id, r.guest_id, r.facility_id, r.checkin_date, r.checkout_date,
           r.rate_type, r.status, r.num_guests,
           f.facility_name, f.category
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    WHERE r.status IN ('pending','approved')
    ORDER BY r.checkin_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Build JS-ready data
$jsReservations = [];
foreach ($allReservations as $res) {
    $jsReservations[] = [
        'id'           => (int)$res['reservation_id'],
        'guest_id'     => (int)$res['guest_id'],
        'facility_id'  => (int)$res['facility_id'],
        'facility'     => $res['facility_name'],
        'category'     => $res['category'] ?? '',
        'checkin'      => $res['checkin_date'], 
        'checkout'     => $res['checkout_date'],
        'rate_type'    => $res['rate_type'],
        'status'       => $res['status'],
        'num_guests'   => (int)$res['num_guests'],
        'is_mine'      => ((int)$res['guest_id'] === $guest_id),
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Page Layout ── */
.sched-wrap {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
  padding: 32px 0 60px;
}
@media (max-width: 860px) {
  .sched-wrap { grid-template-columns: 1fr; }
}

/* ── Sidebar ── */
.sched-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.sidebar-card {
  background: #fff;
  border: 2px solid var(--green-100);
  border-radius: var(--radius);
  overflow: hidden;
}
.sidebar-card-head {
  background: var(--green-50);
  padding: 12px 16px;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--green-dark);
  border-bottom: 1px solid var(--green-100);
}

/* View toggle */
.view-toggle {
  display: flex;
  border-bottom: 2px solid var(--green-100);
}
.view-tab {
  flex: 1;
  padding: 10px 0;
  text-align: center;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--gray-400);
  cursor: pointer;
  border: none;
  background: none;
  transition: var(--transition);
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
}
.view-tab.active {
  color: var(--green-dark);
  border-bottom-color: var(--green);
  background: var(--green-50);
}
.view-tab:hover:not(.active) { background: var(--gray-50); color: var(--gray-600); }

/* Facility filter */
.facility-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: var(--transition);
  font-size: 0.83rem;
  font-weight: 600;
  color: var(--gray-600);
  user-select: none;
}
.facility-pill:hover { background: var(--green-50); color: var(--green-dark); }
.facility-pill.active { background: var(--green-50); color: var(--green-dark); }
.facility-pill input[type=checkbox] { display: none; }
.fac-check {
  width: 16px; height: 16px;
  border: 2px solid var(--gray-300);
  border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: var(--transition);
  background: #fff;
}
.facility-pill.active .fac-check {
  background: var(--green);
  border-color: var(--green);
}

/* ── Calendar ── */
.cal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.cal-nav {
  background: var(--green-50);
  border: 2px solid var(--green-100);
  border-radius: 8px;
  padding: 6px 14px;
  font-weight: 700;
  color: var(--green-dark);
  cursor: pointer;
  font-size: 1rem;
  transition: var(--transition);
  line-height: 1;
}
.cal-nav:hover { background: var(--green-100); }
.cal-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--green-dark);
}
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.cal-day-label {
  text-align: center;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--gray-400);
  padding: 6px 0;
}
.cal-day {
  min-height: 80px;
  border-radius: 10px;
  border: 2px solid transparent;
  padding: 6px 7px;
  cursor: pointer;
  transition: all 0.15s ease;
  position: relative;
  background: #fff;
}
.cal-day:hover { border-color: var(--green-200); background: var(--green-50); }
.cal-day.today { border-color: var(--green); }
.cal-day.other-month { opacity: 0.35; pointer-events: none; }
.cal-day.selected { border-color: var(--green); background: var(--green-50); }
.cal-day.has-events { background: #f0fdf4; }
.cal-day-num {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--gray-700);
  margin-bottom: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
}
.cal-day.today .cal-day-num {
  background: var(--green-dark);
  color: #fff;
}
.cal-dot-wrap { display: flex; flex-wrap: wrap; gap: 2px; margin-top: 2px; }
.cal-dot {
  height: 6px;
  border-radius: 3px;
  flex: 1;
  min-width: 6px;
  max-width: 100%;
}
.dot-mine     { background: var(--green-dark); }
.dot-others   { background: #93c5fd; }
.dot-approved { background: #4ade80; }
.dot-pending  { background: #fbbf24; }

/* ── Detail Panel ── */
.detail-panel {
  background: #fff;
  border: 2px solid var(--green-100);
  border-radius: var(--radius);
  padding: 20px;
  margin-top: 16px;
  display: none;
}
.detail-panel.open { display: block; }
.detail-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--green-dark);
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.res-card {
  border: 2px solid var(--green-100);
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 8px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  transition: var(--transition);
}
.res-card:hover { border-color: var(--green-200); }
.res-card.mine { border-color: var(--green); background: var(--green-50); }
.res-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}

/* ── List view ── */
.list-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--green-100);
}
.list-item:last-child { border-bottom: none; }
.list-date-col {
  min-width: 90px;
  text-align: right;
}

/* Legend */
.legend-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  display: inline-block;
}

/* Status badge */
.status-pill {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 9999px;
  white-space: nowrap;
}
</style>

<div class="container">
  <div class="sched-wrap">

    <!-- ── SIDEBAR ── -->
    <div class="sched-sidebar">

      <!-- View Filter -->
      <div class="sidebar-card">
        <div class="view-toggle">
          <?php if ($guest_id): ?>
          <button class="view-tab active" id="tabAll"  onclick="setView('all')">All Schedules</button>
          <button class="view-tab"        id="tabMine" onclick="setView('mine')">My Schedules</button>
          <?php else: ?>
          <button class="view-tab active" style="flex:1;cursor:default;">All Schedules</button>
          <?php endif; ?>
        </div>
        <div style="padding:12px 14px;">
          <p style="font-size:0.76rem;color:var(--gray-400);">
            <?php if ($guest_id): ?>
              Toggle between viewing all resort reservations or only your own bookings.
            <?php else: ?>
              <a href="<?= SITE_URL ?>/guest/pages/login.php" style="color:var(--green-dark);font-weight:700;">Sign in</a> to see your own schedules.
            <?php endif; ?>
          </p>
        </div>
      </div>

      <!-- Facility Filter -->
      <div class="sidebar-card">
        <div class="sidebar-card-head"><i class="fa-solid fa-filter" style="margin-right:8px;"></i> Filter by Facility</div>
        <div style="padding:10px 10px;">
          <label class="facility-pill active" id="facAll" onclick="toggleFacility('all')">
            <div class="fac-check" id="fchkAll">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span>All Facilities</span>
          </label>
          <?php foreach ($facilities as $fac): ?>
          <label class="facility-pill" id="fac_<?= $fac['facility_id'] ?>" onclick="toggleFacility(<?= $fac['facility_id'] ?>)">
            <div class="fac-check" id="fchk_<?= $fac['facility_id'] ?>"></div>
            <span><?= e($fac['facility_name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Legend -->
      <div class="sidebar-card">
        <div class="sidebar-card-head"><i class="fa-solid fa-info-circle" style="margin-right:8px;"></i> Legend</div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:8px;">
          <?php if ($guest_id): ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--gray-600);">
            <span class="legend-dot" style="background:var(--green-dark);"></span> My Reservation
          </div>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--gray-600);">
            <span class="legend-dot" style="background:#93c5fd;"></span> Other Guests
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--gray-600);">
            <span class="legend-dot" style="background:#4ade80;"></span> Approved
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--gray-600);">
            <span class="legend-dot" style="background:#fbbf24;"></span> Pending
          </div>
        </div>
      </div>

      <!-- Summary counts -->
      <div class="sidebar-card">
        <div class="sidebar-card-head"><i class="fa-solid fa-chart-simple" style="margin-right:8px;"></i> Summary</div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:8px;">
          <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
            <span style="color:var(--gray-500);">Total visible</span>
            <span id="summTotal" style="font-weight:700;color:var(--gray-800);">0</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
            <span style="color:var(--gray-500);">Approved</span>
            <span id="summApproved" style="font-weight:700;color:#166534;">0</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
            <span style="color:var(--gray-500);">Pending</span>
            <span id="summPending" style="font-weight:700;color:#92400e;">0</span>
          </div>
          <?php if ($guest_id): ?>
          <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
            <span style="color:var(--gray-500);">Mine</span>
            <span id="summMine" style="font-weight:700;color:var(--green-dark);">0</span>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /.sched-sidebar -->

    <!-- ── MAIN CALENDAR AREA ── -->
    <div>
      <!-- Calendar header -->
      <div class="cal-header">
        <button class="cal-nav" onclick="changeMonth(-1)">◀</button>
        <div class="cal-title" id="calTitle"></div>
        <button class="cal-nav" onclick="changeMonth(1)">▶</button>
      </div>

      <!-- Day labels -->
      <div class="cal-grid" id="calDayLabels">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dl): ?>
        <div class="cal-day-label"><?= $dl ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Calendar grid -->
      <div class="cal-grid" id="calGrid" style="margin-top:4px;"></div>

      <!-- Day detail panel -->
      <div class="detail-panel" id="detailPanel">
        <div class="detail-title">
          <span id="detailDate"></span>
          <button onclick="closeDetail()" style="background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="detailList"></div>
      </div>

      <!-- Month list view -->
      <div id="listView" style="margin-top:16px;background:#fff;border:2px solid var(--green-100);border-radius:var(--radius);padding:20px;display:none;">
        <p style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:14px;">
          All reservations in <span id="listMonthLabel"></span>
        </p>
        <div id="listItems"></div>
        <p id="listEmpty" style="color:var(--gray-400);font-size:0.86rem;display:none;">No reservations found for this month with current filters.</p>
      </div>
    </div>

  </div>
</div>

<script>
const ALL_RES    = <?= json_encode($jsReservations) ?>;
const MY_GUEST   = <?= $guest_id ?>;
const MONTHS     = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAYS_SHORT = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

let currentYear  = new Date().getFullYear();
let currentMonth = new Date().getMonth(); // 0-indexed
let viewMode     = 'all';   // 'all' | 'mine'
let selFacIds    = new Set(['all']); // 'all' or specific IDs
let selectedDay  = null;

// ── Filtering ──
function getFiltered() {
  return ALL_RES.filter(r => {
    if (viewMode === 'mine' && !r.is_mine) return false;
    if (!selFacIds.has('all') && !selFacIds.has(r.facility_id)) return false;
    return true;
  });
}

// Check if a YYYY-MM-DD date falls in range [checkin, checkout)
function dateInRange(dateStr, checkin, checkout) {
  return dateStr >= checkin && dateStr <= checkout;
}

// Get all reservations active on a given day string YYYY-MM-DD
function resForDay(dayStr) {
  return getFiltered().filter(r => dateInRange(dayStr, r.checkin, r.checkout));
}

// ── Build calendar ──
function buildCalendar() {
  const title = document.getElementById('calTitle');
  title.textContent = MONTHS[currentMonth] + ' ' + currentYear;

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const daysInPrev  = new Date(currentYear, currentMonth, 0).getDate();
  const today       = new Date();
  const todayStr    = toDateStr(today);

  // Prev month padding
  for (let i = firstDay - 1; i >= 0; i--) {
    const d   = daysInPrev - i;
    const str = toDateStrParts(currentYear, currentMonth - 1, d);
    grid.appendChild(buildDayCell(d, str, true));
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const str  = toDateStrParts(currentYear, currentMonth, d);
    const cell = buildDayCell(d, str, false);
    if (str === todayStr) cell.classList.add('today');
    if (str === selectedDay) cell.classList.add('selected');
    grid.appendChild(cell);
  }

  // Next month padding to complete the grid (6 rows max)
  const total  = firstDay + daysInMonth;
  const remain = total % 7 === 0 ? 0 : 7 - (total % 7);
  for (let d = 1; d <= remain; d++) {
    const str = toDateStrParts(currentYear, currentMonth + 1, d);
    grid.appendChild(buildDayCell(d, str, true));
  }

  updateSummary();
  buildListView();
  if (selectedDay) renderDetail(selectedDay);
}

function buildDayCell(dayNum, dateStr, otherMonth) {
  const cell = document.createElement('div');
  cell.className = 'cal-day' + (otherMonth ? ' other-month' : '');
  cell.onclick   = () => selectDay(dateStr);

  const numEl = document.createElement('div');
  numEl.className   = 'cal-day-num';
  numEl.textContent = dayNum;
  cell.appendChild(numEl);

  const res = resForDay(dateStr);
  if (res.length > 0) {
    cell.classList.add('has-events');
    const dotWrap = document.createElement('div');
    dotWrap.className = 'cal-dot-wrap';
    // Show up to 3 dots then +N
    const show = res.slice(0, 3);
    show.forEach(r => {
      const dot = document.createElement('div');
      dot.className = 'cal-dot ' + dotClass(r);
      dot.title     = r.facility + ' — ' + r.status;
      dotWrap.appendChild(dot);
    });
    if (res.length > 3) {
      const more = document.createElement('div');
      more.style.cssText = 'font-size:0.6rem;font-weight:700;color:var(--gray-400);align-self:center;';
      more.textContent   = '+' + (res.length - 3);
      dotWrap.appendChild(more);
    }
    cell.appendChild(dotWrap);
  }

  return cell;
}

function dotClass(r) {
  if (r.is_mine) return 'cal-dot dot-mine';
  return r.status === 'approved' ? 'cal-dot dot-approved' : 'cal-dot dot-others';
}

// ── Day selection / detail ──
function selectDay(dateStr) {
  selectedDay = dateStr;
  buildCalendar();
  renderDetail(dateStr);
}

function renderDetail(dateStr) {
  const res   = resForDay(dateStr);
  const panel = document.getElementById('detailPanel');
  const dEl   = document.getElementById('detailDate');
  const list  = document.getElementById('detailList');

  const d = new Date(dateStr + 'T12:00:00');
  dEl.textContent = d.toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });

  if (res.length === 0) {
    list.innerHTML = '<p style="color:var(--gray-400);font-size:0.86rem;padding:8px 0;">No reservations on this date with current filters.</p>';
  } else {
    list.innerHTML = res.map(r => {
      const statusColor = r.status === 'approved'
        ? 'background:#dcfce7;color:#166534;'
        : 'background:#fef9c3;color:#854d0e;';
      const dotC = r.is_mine ? '#16a34a' : (r.status === 'approved' ? '#4ade80' : '#93c5fd');
      const nameLabel = r.is_mine
        ? '<span style="font-size:0.7rem;font-weight:700;color:var(--green-dark);background:var(--green-50);padding:1px 7px;border-radius:9999px;">Mine</span>'
        : '';
      return `
        <div class="res-card ${r.is_mine ? 'mine' : ''}">
          <div class="res-dot" style="background:${dotC};margin-top:5px;"></div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px;">
              <span style="font-weight:700;font-size:0.88rem;color:var(--gray-800);">${escHtml(r.facility)}</span>
              ${nameLabel}
              <span class="status-pill" style="${statusColor}">${r.status}</span>
            </div>
            <p style="font-size:0.78rem;color:var(--gray-500);margin-bottom:2px;">
              ${fmtDate(r.checkin)} → ${fmtDate(r.checkout)}
            </p>
          </div>
        </div>`;
    }).join('');
  }

  panel.classList.add('open');
}

function closeDetail() {
  selectedDay = null;
  document.getElementById('detailPanel').classList.remove('open');
  buildCalendar();
}

// ── List view ──
function buildListView() {
  const listView = document.getElementById('listView');
  const listItems = document.getElementById('listItems');
  const listEmpty = document.getElementById('listEmpty');
  const label = document.getElementById('listMonthLabel');

  label.textContent = MONTHS[currentMonth] + ' ' + currentYear;

  const monthStart = toDateStrParts(currentYear, currentMonth, 1);
  const monthEnd   = toDateStrParts(currentYear, currentMonth, new Date(currentYear, currentMonth + 1, 0).getDate());

  const res = getFiltered().filter(r =>
    r.checkin <= monthEnd && r.checkout >= monthStart
  );

  if (res.length === 0) {
    listItems.innerHTML = '';
    listEmpty.style.display = 'block';
  } else {
    listEmpty.style.display = 'none';
    listItems.innerHTML = res.map(r => {
      const dotC = r.is_mine ? '#16a34a' : (r.status === 'approved' ? '#4ade80' : '#93c5fd');
      const statusColor = r.status === 'approved'
        ? 'background:#dcfce7;color:#166534;'
        : 'background:#fef9c3;color:#854d0e;';
      return `
        <div class="list-item">
          <div style="width:10px;height:10px;border-radius:50%;background:${dotC};flex-shrink:0;"></div>
          <div style="flex:1;min-width:0;">
            <p style="font-weight:700;font-size:0.86rem;color:var(--gray-800);">${escHtml(r.facility)}</p>
            <p style="font-size:0.76rem;color:var(--gray-500);">
              ${fmtDate(r.checkin)} → ${fmtDate(r.checkout)}
            </p>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
            <span class="status-pill" style="${statusColor}">${r.status}</span>
            ${r.is_mine ? '<span style="font-size:0.68rem;background:var(--green-50);color:var(--green-dark);padding:1px 7px;border-radius:9999px;font-weight:700;">Mine</span>' : ''}
          </div>
        </div>`;
    }).join('');
  }
}

// ── Summary ──
function updateSummary() {
  const res = getFiltered();
  document.getElementById('summTotal').textContent    = res.length;
  document.getElementById('summApproved').textContent = res.filter(r=>r.status==='approved').length;
  document.getElementById('summPending').textContent  = res.filter(r=>r.status==='pending').length;
  const mineEl = document.getElementById('summMine');
  if (mineEl) mineEl.textContent = res.filter(r=>r.is_mine).length;
}

// ── View mode ──
function setView(mode) {
  viewMode = mode;
  document.getElementById('tabAll')?.classList.toggle('active',  mode === 'all');
  document.getElementById('tabMine')?.classList.toggle('active', mode === 'mine');
  buildCalendar();
}

// ── Facility filter ──
function toggleFacility(id) {
  if (id === 'all') {
    selFacIds = new Set(['all']);
  } else {
    selFacIds.delete('all');
    if (selFacIds.has(id)) {
      selFacIds.delete(id);
      if (selFacIds.size === 0) selFacIds = new Set(['all']);
    } else {
      selFacIds.add(id);
    }
  }
  updateFacilityUI();
  buildCalendar();
}

function updateFacilityUI() {
  const allActive = selFacIds.has('all');
  const allEl  = document.getElementById('facAll');
  const chkAll = document.getElementById('fchkAll');
  allEl.classList.toggle('active', allActive);
  chkAll.innerHTML = allActive
    ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
    : '';

  <?php foreach ($facilities as $fac): ?>
  (function() {
    const fid  = <?= $fac['facility_id'] ?>;
    const el   = document.getElementById('fac_' + fid);
    const chk  = document.getElementById('fchk_' + fid);
    if (!el || !chk) return;
    const on = !selFacIds.has('all') && selFacIds.has(fid);
    el.classList.toggle('active', on);
    chk.innerHTML = on
      ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
      : '';
  })();
  <?php endforeach; ?>
}

// ── Navigation ──
function changeMonth(delta) {
  currentMonth += delta;
  if (currentMonth > 11) { currentMonth = 0; currentYear++; }
  if (currentMonth < 0)  { currentMonth = 11; currentYear--; }
  closeDetail();
  buildCalendar();
}

// ── Helpers ──
function toDateStr(date) {
  return date.getFullYear() + '-' +
    String(date.getMonth() + 1).padStart(2,'0') + '-' +
    String(date.getDate()).padStart(2,'0');
}

function toDateStrParts(y, m, d) {
  const date = new Date(y, m, d);
  return date.getFullYear() + '-' +
    String(date.getMonth() + 1).padStart(2,'0') + '-' +
    String(date.getDate()).padStart(2,'0');
}

function fmtDate(s) {
  const d = new Date(s + 'T12:00:00');
  return d.toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──
document.addEventListener('DOMContentLoaded', function() {
  buildCalendar();
  document.getElementById('listView').style.display = 'block';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>