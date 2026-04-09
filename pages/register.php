<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) redirect(SITE_URL . '/index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db    = getDB();
    $name  = trim($_POST['name']     ?? '');
    $email = trim($_POST['email']    ?? '');
    $phone = trim($_POST['phone']    ?? '');
    $pass  = $_POST['password']      ?? '';
    $conf  = $_POST['password_confirmation'] ?? '';

    if (!$name)  $errors[] = 'Full name is required.';
    if (!$email) $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $conf)   $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check unique email
        $stmt = $db->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'This email is already registered. Please sign in.';
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($pass, PASSWORD_BCRYPT);
        $stmt   = $db->prepare("INSERT INTO users (name, email, phone, password, role, created_at, updated_at) VALUES (?,?,?,?,'guest',NOW(),NOW())");
        $stmt->bind_param('ssss', $name, $email, $phone, $hashed);
        $stmt->execute();
        $uid = $db->insert_id;

        $_SESSION['user_id']   = $uid;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = 'guest';
        setFlash('success', 'Welcome to MGarden, ' . explode(' ', $name)[0] . '! 🌿');
        redirect(SITE_URL . '/index.php');
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:480px;">
    <div class="auth-header">
      <div class="auth-logo">M</div>
      <h1 class="auth-title">Create an account</h1>
      <p class="auth-sub">Join MGarden and start booking your paradise stay</p>
    </div>

    <div class="card" style="padding:32px;">
      <?php foreach ($errors as $err): ?>
        <div class="flash flash-error" style="margin-bottom:12px;border-radius:var(--radius-sm);"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="input-icon-wrap">
            <span class="input-icon">👤</span>
            <input type="text" name="name" class="form-control" placeholder="Juan dela Cruz" value="<?= e($_POST['name'] ?? '') ?>" required/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="input-icon-wrap">
            <span class="input-icon">✉</span>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">📞</span>
            <input type="tel" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX" value="<?= e($_POST['phone'] ?? '') ?>"/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;padding:13px;font-size:1rem;">Create Account 🌿</button>
      </form>

      <p class="auth-footer">Already have an account? <a href="<?= SITE_URL ?>/pages/login.php">Sign In</a></p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
