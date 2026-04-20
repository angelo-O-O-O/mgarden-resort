<?php
$page_title = 'Guests';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Build WHERE
$where = ''; $params = []; $types = '';
if ($search !== '') {
    $like = '%' . $search . '%';
    $where = "WHERE g.guest_name LIKE ? OR g.email LIKE ? OR g.contact_num LIKE ?";
    $params = [$like, $like, $like]; $types = 'sss';
}

// Count
$cs = $conn->prepare("SELECT COUNT(*) FROM guests g $where");
if ($params) $cs->bind_param($types, ...$params);
$cs->execute();
$total = $cs->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total / $limit));
$cs->close();

// Fetch
$sql  = "SELECT g.guest_id, g.guest_name, g.email, g.contact_num, g.address, g.created_at,
                COUNT(DISTINCT r.reservation_id) AS total_res
         FROM guests g LEFT JOIN reservations r ON r.guest_id = g.guest_id
         $where GROUP BY g.guest_id ORDER BY g.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($params) {
    $bindValues = [...$params, $limit, $offset];
    $stmt->bind_param($types . 'ii', ...$bindValues);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$guests = $stmt->get_result();
$stmt->close();
?>

<div class="toolbar">
    <form method="GET" class="search-form">
        <div class="input-icon">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search name, email, contact…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-primary">Search</button>
        <?php if ($search): ?><a href="guests.php" class="btn-outline">Clear</a><?php endif; ?>
    </form>
    <span class="record-count"><?= $total ?> guest<?= $total != 1 ? 's' : '' ?></span>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Address</th><th>Reservations</th><th>Joined</th><th></th></tr>
            </thead>
            <tbody>
            <?php if ($guests->num_rows > 0):
                while ($g = $guests->fetch_assoc()): ?>
                <tr>
                    <td><?= $g['guest_id'] ?></td>
                    <td><strong><?= htmlspecialchars($g['guest_name']) ?></strong></td>
                    <td><?= htmlspecialchars($g['email']) ?></td>
                    <td><?= htmlspecialchars($g['contact_num']) ?></td>
                    <td><?= htmlspecialchars($g['address']) ?></td>
                    <td><span class="badge badge-teal"><?= $g['total_res'] ?></span></td>
                    <td><?= date('M j, Y', strtotime($g['created_at'])) ?></td>
                    <td>
                        <button class="btn-icon btn-view" title="View" onclick="viewGuest(<?= $g['guest_id'] ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="empty-state">No guests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Guest Detail Modal -->
<div class="modal-overlay" id="guestModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle">Guest Details</h3>
            <button class="modal-close" onclick="closeModal('guestModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </div>
</div>

<script>
function viewGuest(id) {
    document.getElementById('guestModal').classList.add('open');
    document.getElementById('modalBody').innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    fetch('<?= ADMIN_URL ?>/pages/guest_detail.php?guest_id=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('modalTitle').textContent = d.name;
            document.getElementById('modalBody').innerHTML = d.html;
        })
        .catch(() => {
            document.getElementById('modalBody').innerHTML = '<p class="text-muted">Failed to load.</p>';
        });
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.getElementById('guestModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('guestModal');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
