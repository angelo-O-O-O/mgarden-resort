<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) redirect(SITE_URL . '/index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Email and password are required.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $dest = $_SESSION['redirect_after_login'] ?? SITE_URL . '/index.php';
            unset($_SESSION['redirect_after_login']);
            setFlash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '! 🌿');
            redirect($dest);
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo">M</div>
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Sign in to manage your reservations</p>
    </div>

    <div class="card" style="padding:32px;">
      <?php foreach ($errors as $err): ?>
        <div class="flash flash-error" style="margin-bottom:18px;border-radius:var(--radius-sm);"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="input-icon-wrap">
            <span class="input-icon">✉</span>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;padding:13px;font-size:1rem;">Sign In</button>
      </form>

      <p class="auth-footer">Don't have an account? <a href="<?= SITE_URL ?>/pages/register.php">Sign Up</a></p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
