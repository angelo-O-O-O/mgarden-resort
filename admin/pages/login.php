<?php
$page_title = 'Admin Login';
require_once __DIR__ . '/../includes/header_auth.php';

// Already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . ADMIN_URL . '/pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT admin_id, admin_name, password FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id']   = $row['admin_id'];
                $_SESSION['admin_name'] = $row['admin_name'];
                header('Location: ' . ADMIN_URL . '/pages/dashboard.php');
                exit;
            }
        }
        $error = 'Invalid username or password.';
        $stmt->close();
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="<?= BASE_URL ?>/images/mgardenlogo.jpg" alt="MGarden Logo" onerror="this.style.display='none';this.parentElement.innerHTML='<span style=\'font-size:2rem;color:white\'>M</span>'">
        </div>
        <h1 class="auth-title">MGarden Beach Resort</h1>
        <span class="auth-badge">ADMIN PORTAL</span>
        <p class="auth-subtitle">Sign in to access the admin dashboard</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="admin" required autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>
            <button type="submit" class="btn-primary btn-full">Sign In</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_auth.php'; ?>