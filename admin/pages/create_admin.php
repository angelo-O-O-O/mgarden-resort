<?php
/**
 * create_admin.php — One-time admin account seeder.
 * Run this once to create your admin account, then DELETE this file.
 */
$page_title = 'Create Admin Account';
require_once __DIR__ . '/../includes/header_auth.php';

// Block if admin already exists
$check = $conn->query("SELECT COUNT(*) AS cnt FROM admin")->fetch_assoc();
if ((int)$check['cnt'] > 0): ?>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo" style="font-size:2.5rem; color:#dc2626;">⛔</div>
            <h1 class="auth-title" style="color:#dc2626;">Already Set Up</h1>
            <p class="auth-subtitle">An admin account already exists. <strong>Delete this file now.</strong></p>
            <a href="<?= ADMIN_URL ?>/pages/login.php" class="btn-primary btn-full" style="margin-top:1rem; text-align:center;">
                Go to Login
            </a>
        </div>
    </div>
<?php
    require_once __DIR__ . '/../includes/footer_auth.php';
    exit;
endif;

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_name  = trim($_POST['admin_name']  ?? '');
    $username    = trim($_POST['username']    ?? '');
    $password    = trim($_POST['password']    ?? '');
    $confirm     = trim($_POST['confirm']     ?? '');
    $address     = trim($_POST['address']     ?? '');
    $contact_num = trim($_POST['contact_num'] ?? '');

    if (!$admin_name || !$username || !$password || !$confirm) {
        $errors[] = 'All fields marked * are required.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare(
            "INSERT INTO admin (admin_name, username, password, address, contact_num) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('sssss', $admin_name, $username, $hashed, $address, $contact_num);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        }
        $stmt->close();
    }
}
?>

<div class="auth-container">
    <div class="auth-card" style="max-width:480px;">
        <div class="auth-logo">
            <img src="<?= BASE_URL ?>/images/logo.png" alt="MGarden Logo">
        </div>
        <h1 class="auth-title">Create Admin Account</h1>
        <span class="auth-badge" style="background:#fef9c3;color:#92400e;">ONE-TIME SETUP</span>
        <p class="auth-subtitle">Delete this file after creating the account.</p>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-top:1rem;">
                <i class="fas fa-circle-check"></i>
                Admin account created! <strong>Please delete <code>create_admin.php</code> now.</strong>
                <br><a href="<?= ADMIN_URL ?>/pages/login.php" style="font-weight:700;">Go to Login →</a>
            </div>
        <?php else: ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" style="margin-top:1rem;">
                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" style="text-align:left;">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="admin_name"
                       value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"
                       placeholder="e.g. Maria Santos" required>
            </div>
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="e.g. admin" required autocomplete="off">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Password * <small>(min. 8)</small></label>
                    <input type="password" name="password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm" required autocomplete="new-password">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address"
                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                       placeholder="e.g. Nasugbu, Batangas">
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_num"
                       value="<?= htmlspecialchars($_POST['contact_num'] ?? '') ?>"
                       placeholder="e.g. 09XX-XXX-XXXX">
            </div>
            <button type="submit" class="btn-primary btn-full">Create Admin Account</button>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_auth.php'; ?>