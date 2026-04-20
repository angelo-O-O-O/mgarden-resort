<?php
require_once __DIR__ . '/../includes/config.php';
if (!isset($_SESSION['admin_id'])) { http_response_code(403); exit; }
header('Content-Type: application/json');

$guest_id = (int)($_GET['guest_id'] ?? 0);
if (!$guest_id) { echo json_encode(['error' => 'Invalid']); exit; }

$stmt = $conn->prepare("SELECT guest_id, guest_name, email, contact_num, address, created_at FROM guests WHERE guest_id = ?");
$stmt->bind_param('i', $guest_id); $stmt->execute();
$g = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$g) { echo json_encode(['error' => 'Not found']); exit; }

$rs = $conn->prepare("SELECT reservation_id, checkin_date, checkout_date, status, created_at FROM reservations WHERE guest_id = ? ORDER BY created_at DESC LIMIT 20");
$rs->bind_param('i', $guest_id); $rs->execute();
$reservations = $rs->get_result(); $rs->close();

$ps = $conn->prepare(
    "SELECT p.payment_id, p.total_amount, p.payment_method, p.status, p.created_at, r.reservation_id
     FROM payment_records p JOIN reservations r ON r.reservation_id=p.reservation_id
     WHERE r.guest_id = ? ORDER BY p.created_at DESC LIMIT 20"
);
$ps->bind_param('i', $guest_id); $ps->execute();
$payments = $ps->get_result(); $ps->close();

ob_start(); ?>
<div class="guest-detail-grid">
    <div class="detail-section">
        <h4><i class="fas fa-user"></i> Personal Info</h4>
        <table class="detail-table">
            <tr><td>Name</td><td><?= htmlspecialchars($g['guest_name']) ?></td></tr>
            <tr><td>Email</td><td><?= htmlspecialchars($g['email']) ?></td></tr>
            <tr><td>Contact</td><td><?= htmlspecialchars($g['contact_num']) ?></td></tr>
            <tr><td>Address</td><td><?= htmlspecialchars($g['address']) ?></td></tr>
            <tr><td>Joined</td><td><?= date('F j, Y', strtotime($g['created_at'])) ?></td></tr>
        </table>
    </div>
    <div class="detail-section">
        <h4><i class="fas fa-calendar-check"></i> Reservations</h4>
        <div class="mini-scroll">
        <table class="data-table">
            <thead><tr><th>#</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($reservations->num_rows > 0):
                while ($r = $reservations->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['reservation_id'] ?></td>
                    <td><?= date('M j, Y', strtotime($r['checkin_date'])) ?></td>
                    <td><?= date('M j, Y', strtotime($r['checkout_date'])) ?></td>
                    <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="4" class="empty-state">No reservations.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <div class="detail-section">
        <h4><i class="fas fa-receipt"></i> Payments</h4>
        <div class="mini-scroll">
        <table class="data-table">
            <thead><tr><th>Res #</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if ($payments->num_rows > 0):
                while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?= $p['reservation_id'] ?></td>
                    <td>₱<?= number_format($p['total_amount'], 2) ?></td>
                    <td><?= ucwords(str_replace('_',' ',$p['payment_method'])) ?></td>
                    <td><span class="badge badge-<?= $p['status'] === 'paid' ? 'green' : 'yellow' ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="empty-state">No payments.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();
echo json_encode(['name' => htmlspecialchars($g['guest_name']), 'html' => $html]);
?>