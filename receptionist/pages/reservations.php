<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── STATUS FILTER ──
$allowedStatuses = ['all', 'pending', 'approved', 'cancelled'];
$filterStatus    = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $allowedStatuses)) $filterStatus = 'all';

$whereClause = $filterStatus !== 'all' ? "WHERE r.status = '$filterStatus'" : '';

// ── COUNTS PER STATUS ──
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
    ];
    return '<span class="status-badge ' . ($colors[$status] ?? 'badge-gray') . '">' . ucfirst($status) . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.res-toolbar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin: 20px 0 14px;
}
.res-search-wrap {
  position: relative; flex: 1; min-width: 200px; max-width: 320px;
}
.res-search-wrap i {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  color: var(--gray-400); font-size: 0.82rem; pointer-events: none;
}
.res-search-wrap input {
  width: 100%; padding: 8px 12px 8px 34px;
  border: 1.5px solid var(--gray-200); border-radius: var(--radius);
  font-size: 0.88rem; font-family: inherit; color: var(--gray-800);
  background: #fff; transition: var(--transition);
}
.res-search-wrap input:focus {
  outline: none; border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(5,150,105,0.08);
}

.date-filter-wrap { position: relative; }
.date-filter-pop {
  display: none; position: absolute; top: calc(100% + 8px); right: 0;
  background: #fff; border: 1.5px solid var(--gray-200);
  border-radius: var(--radius); box-shadow: 0 8px 32px rgba(0,0,0,0.13);
  padding: 16px; z-index: 400; min-width: 210px;
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
.mini-cal-grid {
  display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;
}
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
.mini-cal-day:hover { background: var(--green-100); color: var(--green-dark); }
.mini-cal-day.today { font-weight: 700; color: var(--green); }
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

.rate-th-inner { display: flex; align-items: center; gap: 5px; }
.rate-drop-wrap { position: relative; }
.rate-drop-btn {
  background: #fff; border: 1.5px solid var(--gray-300);
  border-radius: 5px; padding: 5px 12px; cursor: pointer;
  font-size: 1rem; color: var(--gray-600); line-height: 1;
  transition: var(--transition); font-family: inherit;
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 40px;
}
.rate-drop-btn:hover,
.rate-drop-btn.active { border-color: var(--green); color: var(--green); background: var(--green-50); }
.rate-drop-menu {
  display: none; position: fixed;
  background: #fff; border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-sm); box-shadow: 0 6px 20px rgba(0,0,0,0.13);
  z-index: 9999; min-width: 120px; overflow: hidden;
}
.rate-drop-menu.open { display: block; }
.rate-drop-item {
  display: block; width: 100%; padding: 8px 14px; text-align: left;
  background: none; border: none; font-family: inherit;
  font-size: 0.82rem; color: var(--gray-700); cursor: pointer;
  transition: var(--transition); text-transform: none; letter-spacing: 0;
}
.rate-drop-item:hover { background: var(--gray-50); color: var(--green); }
.rate-drop-item.selected { color: var(--green); font-weight: 700; background: var(--green-50); }

.pagination-bar {
  display: grid; grid-template-columns: 1fr auto 1fr;
  align-items: center;
  padding: 12px 16px; border-top: 1px solid var(--gray-100);
  gap: 10px;
}
.pagination-wrap {
  display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
}
.pag-btn {
  min-width: 34px; height: 34px; padding: 0 10px;
  border: 1.5px solid var(--gray-200); background: #fff;
  border-radius: var(--radius-sm); font-family: inherit;
  font-size: 0.84rem; font-weight: 600; color: var(--gray-600);
  cursor: pointer; transition: var(--transition);
  display: inline-flex; align-items: center; justify-content: center;
}
.pag-btn:hover:not(:disabled) { border-color: var(--green); color: var(--green); }
.pag-btn.active { background: var(--green); color: #fff; border-color: var(--green); }
.pag-btn:disabled { opacity: 0.38; cursor: default; }
.pag-ellipsis { color: var(--gray-400); padding: 0 3px; line-height: 34px; }
.per-page-wrap {
  display: flex; align-items: center; gap: 8px;
  font-size: 0.82rem; color: var(--gray-500);
}
.per-page-select {
  padding: 5px 10px; border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-sm); background: #fff;
  font-family: inherit; font-size: 0.82rem; color: var(--gray-700);
  cursor: pointer; transition: var(--transition);
}
.per-page-select:focus { outline: none; border-color: var(--green); }
</style>

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

<!-- Toolbar -->
<div class="res-toolbar">
  <div class="res-search-wrap">
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
    <h2><?= $filterStatus === 'all' ? 'All Reservations' : ucfirst($filterStatus) . ' Reservations' ?></h2>
    <p id="recordCount"><?= count($reservations) ?> record<?= count($reservations) != 1 ? 's' : '' ?> found</p>
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
    <div class="empty-state" id="noResultsState" style="display:none;">
      <i class="fa-solid fa-magnifying-glass"></i>
      <h3>No results found</h3>
      <p>Try adjusting your search or filters.</p>
    </div>
    <div style="overflow-x:auto;">
      <table id="resTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Guest</th>
            <th>Facility</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>
              <div class="rate-th-inner">
                Rate
                <div class="rate-drop-wrap">
                  <button class="rate-drop-btn" id="rateDropBtn" onclick="toggleRateDrop(event)"><i class="fa-solid fa-chevron-down"></i></button>
                  <div class="rate-drop-menu" id="rateDropMenu">
                    <button class="rate-drop-item selected" onclick="setRate('all', this)">All</button>
                    <button class="rate-drop-item" onclick="setRate('overnight', this)">Overnight</button>
                    <button class="rate-drop-item" onclick="setRate('daytime', this)">Daytime</button>
                  </div>
                </div>
              </div>
            </th>
            <th>Total Guests</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Reserved On</th>
          </tr>
        </thead>
        <tbody id="resTableBody">
          <?php foreach ($reservations as $res): ?>
            <tr
              data-guest="<?= strtolower(e($res['guest_name'])) ?>"
              data-reservedon="<?= date('Y-m-d', strtotime($res['reserved_at'])) ?>"
              data-rate="<?= e($res['rate_type']) ?>"
            >
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
              <td><?= date('M d, Y H:i', strtotime($res['reserved_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="pagination-bar" id="paginationBar" style="display:none;">
      <div class="per-page-wrap">
        Rows per page:
        <select class="per-page-select" id="perPageSelect" onchange="changePerPage(this.value)">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
      <div class="pagination-wrap" id="pagination"></div>
    </div>
  <?php endif; ?>
</div>

<script>
let rowsPerPage  = 10;
let currentPage  = 1;
let visibleRows  = [];
let currentRate  = 'all';

function getRows() {
  return Array.from(document.querySelectorAll('#resTableBody tr'));
}

function applyFilters() {
  const search = (document.getElementById('searchInput')?.value ?? '').toLowerCase().trim();
  const rate   = currentRate;

  visibleRows = getRows().filter(row => {
    if (search && !row.dataset.guest.includes(search))           return false;
    if (selectedDate && row.dataset.reservedon !== selectedDate) return false;
    if (rate !== 'all' && row.dataset.rate !== rate)             return false;
    return true;
  });

  getRows().forEach(r => r.style.display = 'none');

  const noResults = document.getElementById('noResultsState');
  const table     = document.getElementById('resTable');
  if (noResults && table) {
    noResults.style.display = visibleRows.length === 0 ? '' : 'none';
    table.style.display     = visibleRows.length === 0 ? 'none' : '';
  }

  const countEl = document.getElementById('recordCount');
  if (countEl) {
    countEl.textContent = visibleRows.length + ' record' + (visibleRows.length !== 1 ? 's' : '') + ' found';
  }

  currentPage = 1;
  renderPage();
}

function renderPage() {
  visibleRows.forEach(r => r.style.display = 'none');
  const start = (currentPage - 1) * rowsPerPage;
  visibleRows.slice(start, start + rowsPerPage).forEach(r => r.style.display = '');
  renderPagination();
}

function renderPagination() {
  const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
  const pag = document.getElementById('pagination');
  const bar = document.getElementById('paginationBar');
  if (!pag || !bar) return;

  bar.style.display = visibleRows.length > 0 ? '' : 'none';

  if (totalPages <= 1) { pag.innerHTML = ''; return; }

  let html = `<button class="pag-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i></button>`;

  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
      html += `<button class="pag-btn ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    } else if (i === currentPage - 3 || i === currentPage + 3) {
      html += `<span class="pag-ellipsis">…</span>`;
    }
  }

  html += `<button class="pag-btn" onclick="goPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}><i class="fa-solid fa-chevron-right"></i></button>`;
  pag.innerHTML = html;
}

function goPage(page) {
  const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
  if (page < 1 || page > totalPages) return;
  currentPage = page;
  renderPage();
}

function changePerPage(val) {
  rowsPerPage = parseInt(val, 10);
  currentPage = 1;
  renderPage();
}

// ── Mini calendar ──
let selectedDate = null;
let calYear, calMonth;

const CAL_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const CAL_DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa'];
const TODAY_STR  = new Date().toISOString().slice(0, 10);

function renderCal() {
  const now = new Date();
  if (calYear  === undefined) calYear  = now.getFullYear();
  if (calMonth === undefined) calMonth = now.getMonth();

  const firstDow = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

  let html = `<div class="mini-cal-nav">
    <button onclick="calNav(-1)"><i class="fa-solid fa-chevron-left"></i></button>
    <span>${CAL_MONTHS[calMonth]} ${calYear}</span>
    <button onclick="calNav(1)"><i class="fa-solid fa-chevron-right"></i></button>
  </div><div class="mini-cal-grid">`;

  CAL_DAYS.forEach(d => { html += `<div class="mini-cal-dow">${d}</div>`; });
  for (let i = 0; i < firstDow; i++) html += '<div></div>';

  for (let d = 1; d <= daysInMonth; d++) {
    const ds = `${calYear}-${String(calMonth + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const cls = [
      'mini-cal-day',
      ds === TODAY_STR    ? 'today'    : '',
      ds === selectedDate ? 'selected' : '',
    ].join(' ');
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
    d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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

// ── Rate dropdown ──
function toggleRateDrop(e) {
  e.stopPropagation();
  const menu = document.getElementById('rateDropMenu');
  const btn  = document.getElementById('rateDropBtn');
  if (menu.classList.contains('open')) {
    menu.classList.remove('open');
    return;
  }
  const rect = btn.getBoundingClientRect();
  menu.style.top  = (rect.bottom + 4) + 'px';
  menu.style.left = rect.left + 'px';
  menu.classList.add('open');
}

function setRate(val, el) {
  currentRate = val;
  document.querySelectorAll('.rate-drop-item').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  const btn = document.getElementById('rateDropBtn');
  btn.classList.toggle('active', val !== 'all');
  document.getElementById('rateDropMenu').classList.remove('open');
  applyFilters();
}

document.addEventListener('click', function (e) {
  const pop = document.getElementById('dateFilterPop');
  const btn = document.getElementById('dateFilterBtn');
  if (pop && !pop.contains(e.target) && !btn.contains(e.target)) {
    pop.classList.remove('open');
  }
  const rateMenu = document.getElementById('rateDropMenu');
  const rateBtn  = document.getElementById('rateDropBtn');
  if (rateMenu && !rateMenu.contains(e.target) && e.target !== rateBtn) {
    rateMenu.classList.remove('open');
  }
});

document.addEventListener('DOMContentLoaded', applyFilters);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
