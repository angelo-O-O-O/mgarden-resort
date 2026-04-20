<?php
$page_title = 'My Profile';
require_once __DIR__ . '/../includes/header.php';

$admin_id = $_SESSION['admin_id'];
$errors = []; $success = '';

// Fetch current data
$stmt = $conn->prepare("SELECT admin_id, admin_name, username, address, contact_num, created_at FROM admin WHERE admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) { session_destroy(); header('Location: ' . ADMIN_URL . '/pages/login.php'); exit; }

$active_form = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';
    $active_form = $form;

    // ── Edit profile info ─────────────────────────────────────
    if ($form === 'info') {
        $admin_name  = trim($_POST['admin_name']  ?? '');
        $username    = trim($_POST['username']    ?? '');
        $address     = trim($_POST['address']     ?? '');
        $contact_num = trim($_POST['contact_num'] ?? '');

        if (!$admin_name || !$username) {
            $errors[] = 'Full name and username are required.';
        } else {
            $chk = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? AND admin_id != ?");
            $chk->bind_param('si', $username, $admin_id); $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) {
                $errors[] = 'That username is already taken.';
            } else {
                $upd = $conn->prepare("UPDATE admin SET admin_name=?, username=?, address=?, contact_num=? WHERE admin_id=?");
                $upd->bind_param('ssssi', $admin_name, $username, $address, $contact_num, $admin_id);
                if ($upd->execute()) {
                    $_SESSION['admin_name'] = $admin_name;
                    $success = 'Profile updated successfully.';
                    $admin = array_merge($admin, compact('admin_name','username','address','contact_num'));
                } else { $errors[] = $conn->error; }
                $upd->close();
            }
            $chk->close();
        }
    }

    // ── Change password ───────────────────────────────────────
    if ($form === 'password') {
        $current  = trim($_POST['current_password'] ?? '');
        $new_pass = trim($_POST['new_password']     ?? '');
        $confirm  = trim($_POST['confirm_password'] ?? '');

        if (!$current || !$new_pass || !$confirm) {
            $errors[] = 'All password fields are required.';
        } elseif ($new_pass !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } elseif (strlen($new_pass) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } else {
            $chk = $conn->prepare("SELECT password FROM admin WHERE admin_id = ?");
            $chk->bind_param('i', $admin_id); $chk->execute();
            $hashed = $chk->get_result()->fetch_row()[0]; $chk->close();
            if (!password_verify($current, $hashed)) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $new_hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $upd = $conn->prepare("UPDATE admin SET password=? WHERE admin_id=?");
                $upd->bind_param('si', $new_hashed, $admin_id);
                if ($upd->execute()) $success = 'Password changed successfully.'; else $errors[] = $conn->error;
                $upd->close();
            }
        }
    }
}
?>

<div class="profile-layout">

    <!-- Avatar card -->
    <div class="card profile-avatar-card">
        <div class="avatar-circle"><i class="fas fa-user-shield"></i></div>
        <h2><?= htmlspecialchars($admin['admin_name']) ?></h2>
        <span class="badge badge-green">Administrator</span>
        <p class="text-muted" style="margin-top:.5rem; font-size:.8rem;">
            Member since <?= date('F Y', strtotime($admin['created_at'])) ?>
        </p>
    </div>

    <div class="profile-forms">

        <?php if ($errors): ?>
            <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Edit Info -->
        <div class="card">
            <div class="card-head"><h3 class="section-title"><i class="fas fa-pen-to-square"></i> Edit Profile</h3></div>
            <form method="POST" style="margin-top:.5rem;">
                <input type="hidden" name="form" value="info">
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="admin_name" value="<?= htmlspecialchars($admin['admin_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required autocomplete="off">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_num" value="<?= htmlspecialchars($admin['contact_num'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($admin['address'] ?? '') ?>">
                    </div>
                </div>
                <div style="text-align:right;">
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-head"><h3 class="section-title"><i class="fas fa-lock"></i> Change Password</h3></div>
            <form method="POST" style="margin-top:.5rem;">
                <input type="hidden" name="form" value="password">
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>New Password * <small>(min. 8)</small></label>
                        <input type="password" name="new_password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required autocomplete="new-password">
                    </div>
                </div>
                <div style="text-align:right;">
                    <button type="submit" class="btn-primary">Change Password</button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>