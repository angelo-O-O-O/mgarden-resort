<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();

$rooms = $db->query("SELECT r.*, rc.name as category_name FROM rooms r JOIN room_categories rc ON r.category_id=rc.id WHERE r.is_available=1 ORDER BY r.price_per_night")->fetch_all(MYSQLI_ASSOC);
$selectedRoomId = (int)($_GET['room_id'] ?? ($rooms[0]['id'] ?? 0));
$selectedRoom   = null;
foreach ($rooms as $r) { if ($r['id'] === $selectedRoomId) { $selectedRoom = $r; break; } }

// Get booked date ranges for selected room
$bookedRanges = [];
if ($selectedRoom) {
    $res = $db->query("SELECT check_in, check_out FROM bookings WHERE room_id=$selectedRoomId AND status IN ('pending','confirmed','checked_in') AND check_out >= CURDATE()");
    while ($row = $res->fetch_assoc()) { $bookedRanges[] = $row; }
}

// Get blocked dates
$blockedDates = [];
if ($selectedRoom) {
    $res = $db->query("SELECT blocked_date FROM blocked_dates WHERE (room_id=$selectedRoomId OR room_id IS NULL) AND blocked_date >= CURDATE()");
    while ($row = $res->fetch_assoc()) { $blockedDates[] = $row['blocked_date']; }
}

// Build set of booked dates
$bookedSet = [];
foreach ($bookedRanges as $range) {
    $cur = strtotime($range['check_in']);
    $end = strtotime($range['check_out']);
    while ($cur < $end) { $bookedSet[date('Y-m-d', $cur)] = true; $cur = strtotime('+1 day', $cur); }
}

// Calendar month/year
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1) { $month = 12; $year--; } if ($month > 12) { $month = 1; $year++; }

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$firstDay    = (int)date('w', mktime(0,0,0,$month,1,$year));
$daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
$today       = date('Y-m-d');
$monthNames  = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

$pageTitle = 'Schedules';
require_once __DIR__ . '/../includes/header.php';
?>
<script>const siteUrl='<?= SITE_URL ?>';</script>

<div class="container page-wrap">
  <div class="text-center page-header">
    <h1>Availability Schedules</h1>
    <p>Check which dates are available for your preferred room</p>
  </div>

  <!-- Room selector -->
  <div class="grid-3" style="margin-bottom:32px;gap:12px;">
    <?php foreach ($rooms as $r): ?>
    <a href="?room_id=<?= $r['id'] ?>&month=<?= $month ?>&year=<?= $year ?>"
       class="card" style="padding:16px;border:2px solid <?= $r['id']===$selectedRoomId ? 'var(--green)' : 'transparent' ?>;background:<?= $r['id']===$selectedRoomId ? 'var(--green-50)' : '#fff' ?>;text-decoration:none;">
      <p style="font-weight:700;color:<?= $r['id']===$selectedRoomId?'var(--green-dark)':'var(--gray-800)' ?>;font-size:0.95rem;"><?= e($r['name']) ?></p>
      <p style="font-size:0.78rem;color:var(--gray-400);margin-top:2px;"><?= e($r['category_name']) ?> · <?= $r['capacity'] ?> guests</p>
      <p style="font-weight:700;color:var(--green);margin-top:6px;font-size:0.9rem;"><?= peso($r['price_per_night']) ?>/night</p>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($selectedRoom): ?>
  <div style="display:grid;grid-template-columns:1fr 320px;gap:28px;" class="two-col-cal">
  <style>@media(max-width:900px){.two-col-cal{grid-template-columns:1fr!important;}}</style>

    <!-- Calendar -->
    <div class="cal-wrap">
      <div class="cal-nav">
        <button onclick="location.href='?room_id=<?= $selectedRoomId ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>'">&lt;</button>
        <span class="cal-title"><?= $monthNames[$month] ?> <?= $year ?></span>
        <button onclick="location.href='?room_id=<?= $selectedRoomId ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>'">&gt;</button>
      </div>
      <div class="cal-grid" style="padding:12px;">
        <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $dh): ?>
          <div class="cal-day-header"><?= $dh ?></div>
        <?php endforeach; ?>

        <?php for ($e = 0; $e < $firstDay; $e++): ?>
          <div class="cal-day empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
          $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $d);
          $isPast   = $dateStr < $today;
          $isToday  = $dateStr === $today;
          $isBlocked = in_array($dateStr, $blockedDates);
          $isBooked  = isset($bookedSet[$dateStr]);

          $cls = 'cal-day';
          $title = '';
          if ($isPast)        { $cls .= ' past'; $title = 'Past date'; }
          elseif ($isBlocked) { $cls .= ' blocked'; $title = 'Blocked / Unavailable'; }
          elseif ($isBooked)  { $cls .= ' booked';  $title = 'Already Booked'; }
          else                { $cls .= ' available'; $title = 'Available'; }
          if ($isToday) $cls .= ' today';
        ?>
          <div class="<?= $cls ?>" title="<?= $title ?>"><?= $d ?></div>
        <?php endfor; ?>
      </div>
      <div class="cal-legend">
        <div class="legend-item"><div class="legend-dot" style="background:var(--green-50);border:1px solid var(--green-100);"></div> Available</div>
        <div class="legend-item"><div class="legend-dot" style="background:#fed7aa;"></div> Booked</div>
        <div class="legend-item"><div class="legend-dot" style="background:#fecaca;"></div> Blocked</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--gray-100);border:2px solid var(--green);"></div> Today</div>
      </div>
    </div>

    <!-- Room details -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      <div class="card" style="padding:22px;">
        <h3 style="font-weight:700;color:var(--gray-800);margin-bottom:16px;">Room Details</h3>
        <table style="width:100%;font-size:0.88rem;border-collapse:collapse;">
          <tr><td style="color:var(--gray-500);padding:6px 0;">Type</td><td style="font-weight:600;text-align:right;"><?= e($selectedRoom['category_name']) ?></td></tr>
          <tr><td style="color:var(--gray-500);padding:6px 0;">Capacity</td><td style="font-weight:600;text-align:right;"><?= $selectedRoom['capacity'] ?> guests</td></tr>
          <tr><td style="color:var(--gray-500);padding:6px 0;">Rate/night</td><td style="font-weight:600;color:var(--green);text-align:right;"><?= peso($selectedRoom['price_per_night']) ?></td></tr>
          <?php if ($selectedRoom['weekend_price']): ?>
          <tr><td style="color:var(--gray-500);padding:6px 0;">Weekend rate</td><td style="font-weight:600;color:var(--green);text-align:right;"><?= peso($selectedRoom['weekend_price']) ?></td></tr>
          <?php endif; ?>
        </table>
        <a href="<?= SITE_URL ?>/pages/room-detail.php?id=<?= $selectedRoom['id'] ?>" class="btn btn-primary btn-full" style="margin-top:16px;">Book This Room</a>
      </div>

      <!-- Legend card -->
      <div class="card" style="padding:22px;">
        <h3 style="font-weight:700;color:var(--gray-800);margin-bottom:16px;">Legend</h3>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ([
            ['background:var(--green-100)', 'Available', 'You can book this date'],
            ['background:#fed7aa',          'Booked',    'Already reserved'],
            ['background:#fecaca',          'Blocked',   'Unavailable / maintenance'],
            ['background:var(--gray-100)',  'Past',      'Date has passed'],
          ] as [$style, $label, $desc]): ?>
          <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:8px;<?= $style ?>;flex-shrink:0;"></div>
            <div>
              <p style="font-weight:700;font-size:0.88rem;color:var(--gray-800);"><?= $label ?></p>
              <p style="font-size:0.78rem;color:var(--gray-400);"><?= $desc ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
