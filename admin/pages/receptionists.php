<?php
$page_title = 'Receptionists';
require_once __DIR__ . '/../includes/header.php';

$errors = []; $success = '';
$action = $_POST['action'] ?? '';

// ── ADD ───────────────────────────────────────────────────────
if ($action === 'add') {
    $fn  = trim($_POST['first_name']  ?? '');
    $ln  = trim($_POST['last_name']   ?? '');
    $em  = trim($_POST['email']       ?? '');
    $pw  = trim($_POST['password']    ?? '');
    $cn  = trim($_POST['contact_num'] ?? '');
    $role = trim($_POST['role'] ?? 'receptionist');

    if (!$fn || !$ln || !$em || !$pw) {
        $errors[] = 'Required fields are missing.';
    } elseif (strlen($pw) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        $ins  = $conn->prepare("INSERT INTO receptionist (recpst_fname,recpst_lname,recpst_email,recpst_pass,recpst_cnum,role) VALUES (?,?,?,?,?,?)");
        $ins->bind_param('ssssss', $fn, $ln, $em, $hash, $cn, $role);
        if ($ins->execute()) $success = 'Receptionist added successfully.';
        else $errors[] = 'Database error: ' . $conn->error;
        $ins->close();
    }
}

// ── EDIT ──────────────────────────────────────────────────────
if ($action === 'edit') {
    $rid = (int)($_POST['recpst_id'] ?? 0);
    $fn  = trim($_POST['first_name']   ?? '');
    $ln  = trim($_POST['last_name']    ?? '');
    $em  = trim($_POST['email']        ?? '');
    $cn  = trim($_POST['contact_num']  ?? '');
    $role = trim($_POST['role'] ?? 'receptionist');
    $np  = trim($_POST['new_password'] ?? '');

    if (!$rid || !$fn || !$ln || !$em) {
        $errors[] = 'Required fields are missing.';
    } else {
        if ($np !== '') {
            if (strlen($np) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
            else {
                $hash = password_hash($np, PASSWORD_BCRYPT);
                $upd  = $conn->prepare("UPDATE receptionist SET recpst_fname=?,recpst_lname=?,recpst_email=?,recpst_pass=?,recpst_cnum=?,role=? WHERE recpst_id=?");
                $upd->bind_param('ssssssi', $fn, $ln, $em, $hash, $cn, $role, $rid);
                if ($upd->execute()) $success = 'Receptionist updated.'; else $errors[] = $conn->error;
                $upd->close();
            }
        } else {
            $upd = $conn->prepare("UPDATE receptionist SET recpst_fname=?,recpst_lname=?,recpst_email=?,recpst_cnum=?,role=? WHERE recpst_id=?");
            $upd->bind_param('sssssi', $fn, $ln, $em, $cn, $role, $rid);
            if ($upd->execute()) $success = 'Receptionist updated.'; else $errors[] = $conn->error;
            $upd->close();
        }
    }
}

// ── DELETE ────────────────────────────────────────────────────
if ($action === 'delete') {
    $rid = (int)($_POST['recpst_id'] ?? 0);
    if ($rid) {
        $del = $conn->prepare("DELETE FROM receptionist WHERE recpst_id = ?");
        $del->bind_param('i', $rid);
        if ($del->execute()) $success = 'Receptionist removed.'; else $errors[] = $conn->error;
        $del->close();
    }
}

// ── FETCH LIST ────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$where = ''; $params = []; $types = '';
if ($search !== '') {
    $like = '%' . $search . '%';
    $where = "WHERE CONCAT(recpst_fname,' ',recpst_lname) LIKE ? OR recpst_fname LIKE ? OR recpst_lname LIKE ? OR recpst_email LIKE ?";
    $params = [$like, $like, $like, $like]; $types = 'ssss';
}
$stmt = $conn->prepare("SELECT recpst_id,recpst_fname,recpst_lname,recpst_email,recpst_cnum,role,created_at FROM receptionist $where ORDER BY created_at DESC");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$recs = $stmt->get_result();
$stmt->close();
?>

<div class="toolbar">
    <form method="GET" class="search-form">
        <div class="input-icon">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search receptionists…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-primary">Search</button>
        <?php if ($search): ?><a href="receptionists.php" class="btn-outline">Clear</a><?php endif; ?>
    </form>
    <button class="btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Receptionist
    </button>
</div>

<?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Role</th><th>Added</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($recs->num_rows > 0):
                while ($r = $recs->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['recpst_id'] ?></td>
                    <td><strong><?= htmlspecialchars($r['recpst_fname'] . ' ' . $r['recpst_lname']) ?></strong></td>
                    <td><?= htmlspecialchars($r['recpst_email']) ?></td>
                    <td><?= htmlspecialchars($r['recpst_cnum']) ?></td>
                    <td><?= htmlspecialchars($r['role']) ?></td>
                    <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td class="action-btns">
                        <button class="btn-icon btn-edit" title="Edit"
                            onclick='openEdit(<?= json_encode($r) ?>)'>
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="btn-icon btn-delete" title="Delete"
                            onclick="openDelete(<?= $r['recpst_id'] ?>, '<?= htmlspecialchars(addslashes($r['recpst_fname'] . ' ' . $r['recpst_lname'])) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="empty-state">No receptionists found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Receptionist</h3>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-row-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
                </div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                <div class="form-row-2">
                    <div class="form-group"><label>Password * <small>(min. 8)</small></label><input type="password" name="password" required autocomplete="new-password"></div>
                    <div class="form-group"><label>Contact</label><input type="text" name="contact_num"></div>
                </div>
                <div class="form-group"><label>Role</label><input type="text" name="role" value="receptionist"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-outline" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Receptionist</h3>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="recpst_id" id="eId">
                <div class="form-row-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="eFn" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="eLn" required></div>
                </div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" id="eEm" required></div>
                <div class="form-row-2">
                    <div class="form-group"><label>New Password <small>(blank = keep)</small></label><input type="password" name="new_password" autocomplete="new-password"></div>
                    <div class="form-group"><label>Contact</label><input type="text" name="contact_num" id="eCn"></div>
                </div>
                <div class="form-group"><label>Role</label><input type="text" name="role" id="eRole"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-outline" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p>Remove <strong id="dName"></strong>? This cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="recpst_id" id="dId">
                <div class="modal-actions">
                    <button type="button" class="btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openEdit(r) {
    document.getElementById('eId').value = r.recpst_id;
    document.getElementById('eFn').value = r.recpst_fname;
    document.getElementById('eLn').value = r.recpst_lname;
    document.getElementById('eEm').value = r.recpst_email;
    document.getElementById('eCn').value = r.recpst_cnum || '';
    document.getElementById('eRole').value = r.role || 'receptionist';
    openModal('editModal');
}
function openDelete(id, name) {
    document.getElementById('dId').value          = id;
    document.getElementById('dName').textContent  = name;
    openModal('deleteModal');
}
['addModal','editModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) closeModal(id); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>