<?php
$page_title = 'Transaction Logs';
require_once __DIR__ . '/../includes/header.php';

$f_status = $_GET['status'] ?? '';
$f_method = $_GET['method'] ?? '';
$f_from   = $_GET['from']   ?? '';
$f_to     = $_GET['to']     ?? '';
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 15;
$offset   = ($page - 1) * $limit;

$conds = []; $params = []; $types = '';
if ($f_status) { $conds[] = 'p.status = ?';               $params[] = $f_status; $types .= 's'; }
if ($f_method) { $conds[] = 'p.payment_method = ?';       $params[] = $f_method; $types .= 's'; }
if ($f_from)   { $conds[] = 'DATE(p.created_at) >= ?';    $params[] = $f_from;   $types .= 's'; }
if ($f_to)     { $conds[] = 'DATE(p.created_at) <= ?';    $params[] = $f_to;     $types .= 's'; }
if ($search)   {
    $like = '%' . $search . '%';
    $conds[] = "(g.guest_name LIKE ? OR g.email LIKE ?)";
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

// Summary
$ss = $conn->prepare("SELECT COALESCE(SUM(p.total_amount),0) AS total, COUNT(*) AS cnt
    FROM payment_records p JOIN reservations r ON r.reservation_id=p.reservation_id JOIN guests g ON g.guest_id=r.guest_id $where");
if ($params) $ss->bind_param($types, ...$params);
$ss->execute();
$summary = $ss->get_result()->fetch_assoc();
$ss->close();

// Count
$cs = $conn->prepare("SELECT COUNT(*) FROM payment_records p JOIN reservations r ON r.reservation_id=p.reservation_id JOIN guests g ON g.guest_id=r.guest_id $where");
if ($params) $cs->bind_param($types, ...$params);
$cs->execute();
$total_rows  = $cs->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$cs->close();

// Data
$ds = $conn->prepare(
    "SELECT p.payment_id, p.reservation_id, p.total_amount, p.payment_method, p.status, p.created_at,
            g.guest_name, g.email,
            r.checkin_date, r.checkout_date, r.status AS res_status
     FROM payment_records p
     JOIN reservations r ON r.reservation_id=p.reservation_id
     JOIN guests g ON g.guest_id=r.guest_id
     $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
);
$bindValues = [...$params, $limit, $offset];
$ds->bind_param($types . 'ii', ...$bindValues);
$ds->execute();
$logs = $ds->get_result();
$ds->close();

function qstr(array $extra = []): string {
    $base = array_filter(['status'=>$_GET['status']??'','method'=>$_GET['method']??'',
        'from'=>$_GET['from']??'','to'=>$_GET['to']??'','search'=>$_GET['search']??'']);
    return http_build_query(array_merge($base, $extra));
}
?>

<!-- Summary -->
<div class="stats-grid mini">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-info">
            <span class="stat-label">Total (filtered)</span>
            <span class="stat-value">₱<?= number_format($summary['total'], 2) ?></span>
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <span class="stat-label">Transactions</span>
            <span class="stat-value"><?= number_format($summary['cnt']) ?></span>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <form method="GET" class="filter-form">
        <div class="input-icon">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Guest name or email…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['pending','paid','refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= $f_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="method">
            <option value="">All Methods</option>
            <?php foreach (['cash','gcash'] as $m): ?>
            <option value="<?= $m ?>" <?= $f_method === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>" title="From">
        <input type="date" name="to"   value="<?= htmlspecialchars($f_to) ?>"   title="To">
        <button type="submit" class="btn-primary">Filter</button>
        <a href="transaction_logs.php" class="btn-outline">Reset</a>
    </form>
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pay #</th><th>Res #</th><th>Guest</th><th>Email</th>
                    <th>Amount</th><th>Method</th><th>Pay Status</th><th>Res Status</th>
                    <th>Check-in</th><th>Check-out</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs->num_rows > 0):
                while ($row = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['payment_id'] ?></td>
                    <td><?= $row['reservation_id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['guest_name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                    <td><?= ucfirst($row['payment_method']) ?></td>
                    <td><span class="badge badge-<?= $row['status'] === 'paid' ? 'green' : ($row['status'] === 'pending' ? 'yellow' : 'red') ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td><span class="badge badge-<?= $row['res_status'] ?>"><?= ucfirst($row['res_status']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($row['checkin_date'])) ?></td>
                    <td><?= date('M j, Y', strtotime($row['checkout_date'])) ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="11" class="empty-state">No transactions match your filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?<?= qstr(['page' => $i]) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>