<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

// ── Stats ─────────────────────────────────────────────────────
$total_guests        = $conn->query("SELECT COUNT(*) FROM guests")->fetch_row()[0]               ?? 0;
$total_reservations  = $conn->query("SELECT COUNT(*) FROM reservations")->fetch_row()[0]         ?? 0;
$total_receptionists = $conn->query("SELECT COUNT(*) FROM receptionist")->fetch_row()[0]        ?? 0;
$total_earnings      = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM payment_records WHERE status IN ('paid')")->fetch_row()[0] ?? 0;
$earnings_month      = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM payment_records WHERE status IN ('paid') AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch_row()[0] ?? 0;
$today               = date('Y-m-d');
$checkins_today      = $conn->query("SELECT COUNT(*) FROM reservations WHERE DATE(checkin_date)='$today' AND status='approved'")->fetch_row()[0] ?? 0;

// Reservation by status
$res_status = [];
$rs = $conn->query("SELECT status, COUNT(*) AS cnt FROM reservations GROUP BY status");
while ($r = $rs->fetch_assoc()) $res_status[$r['status']] = (int)$r['cnt'];

// Monthly earnings (last 6 months)
$chart_labels = []; $chart_data = [];
$mq = $conn->query(
    "SELECT DATE_FORMAT(created_at,'%b %Y') AS lbl, DATE_FORMAT(created_at,'%Y-%m') AS k, COALESCE(SUM(total_amount),0) AS total
     FROM payment_records WHERE status IN ('paid') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY k, lbl ORDER BY k ASC"
);
while ($m = $mq->fetch_assoc()) { $chart_labels[] = $m['lbl']; $chart_data[] = (float)$m['total']; }

// Recent reservations
$recent = $conn->query(
    "SELECT r.reservation_id, g.guest_name,
            r.checkin_date, r.checkout_date, r.status, r.reserved_at
     FROM reservations r JOIN guests g ON g.guest_id = r.guest_id
     ORDER BY r.reserved_at DESC LIMIT 8"
);
?>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-info">
            <span class="stat-label">Total Earnings</span>
            <span class="stat-value">₱<?= number_format($total_earnings, 2) ?></span>
            <span class="stat-sub">₱<?= number_format($earnings_month, 2) ?> this month</span>
        </div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-info">
            <span class="stat-label">Reservations</span>
            <span class="stat-value"><?= $total_reservations ?></span>
            <span class="stat-sub"><?= $checkins_today ?> check-in<?= $checkins_today != 1 ? 's' : '' ?> today</span>
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <span class="stat-label">Registered Guests</span>
            <span class="stat-value"><?= $total_guests ?></span>
        </div>
    </div>
    <div class="stat-card slate">
        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info">
            <span class="stat-label">Receptionists</span>
            <span class="stat-value"><?= $total_receptionists ?></span>
        </div>
    </div>
</div>

<!-- Status + Chart -->
<div class="row-2col">
    <div class="card">
        <div class="card-head"><h2 class="section-title">Reservation Status</h2></div>
        <div class="status-row">
            <?php $statuses = [
                'pending'   => ['Pending',   'clock',          'yellow'],
                'approved'  => ['Approved',  'circle-check',   'green'],
                'cancelled' => ['Cancelled', 'circle-xmark',   'red'],
            ];
            foreach ($statuses as $k => [$lbl, $icon, $cls]): ?>
            <div class="status-badge <?= $cls ?>">
                <i class="fas fa-<?= $icon ?>"></i>
                <span class="status-num"><?= $res_status[$k] ?? 0 ?></span>
                <span class="status-lbl"><?= $lbl ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="section-title">Monthly Earnings</h2></div>
        <canvas id="earningsChart" height="150"></canvas>
    </div>
</div>

<!-- Recent Reservations -->
<div class="card">
    <div class="card-head">
        <h2 class="section-title">Recent Reservations</h2>
        <a href="<?= ADMIN_URL ?>/pages/transaction_logs.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Guest</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php if ($recent && $recent->num_rows > 0):
                while ($row = $recent->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['reservation_id'] ?></td>
                    <td><?= htmlspecialchars($row['guest_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($row['checkin_date'])) ?></td>
                    <td><?= date('M j, Y', strtotime($row['checkout_date'])) ?></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($row['reserved_at'])) ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="empty-state">No reservations yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('earningsChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{ label: 'Earnings (₱)', data: <?= json_encode($chart_data) ?>,
            backgroundColor: 'rgba(26,122,74,0.75)', borderRadius: 6 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
