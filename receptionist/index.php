<?php
require_once __DIR__ . '/includes/config.php';

if (isReceptionistLoggedIn()) redirect(SITE_URL . '/receptionist/pages/dashboard.php');

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email))
        $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (empty($password))
        $errors[] = 'Password is required.';

    if (empty($errors)) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT recpst_id, recpst_fname, recpst_lname, recpst_email, recpst_pass FROM receptionist WHERE recpst_email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $receptionist = $stmt->get_result()->fetch_assoc();

        if ($receptionist && password_verify($password, $receptionist['recpst_pass'])) {
            $_SESSION['recpst_id']   = $receptionist['recpst_id'];
            $_SESSION['recpst_name'] = $receptionist['recpst_fname'] . ' ' . $receptionist['recpst_lname'];
            $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/receptionist/pages/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            setFlash('success', 'Welcome back, ' . $receptionist['recpst_fname'] . '!');
            redirect($redirect);
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

require_once __DIR__ . '/includes/header_auth.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo">
        <img
          src="<?= SITE_URL ?>/images/mgardenlogo.jpg"
          alt="MGarden"
          onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
        />
        <span style="display:none;font-size:1.5rem;font-weight:700;color:#fff;">M</span>
      </div>
      <h1 class="auth-title">Receptionist Login</h1>
      <p class="auth-sub">Sign in to your receptionist account</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="margin: 0 32px 4px;">
        <div class="flash flash-error" style="position:static;max-width:100%;margin-bottom:0;">
          <i class="fa-solid fa-circle-exclamation"></i>
          <?= e($errors[0]) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="auth-form-card">
      <form method="POST" action="" id="loginForm" novalidate>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-icon-wrap">
            <span class="input-icon"><i class="fa-solid fa-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="you@example.com" value="<?= e($email) ?>"/>
          </div>
          <p class="form-error" id="emailError"></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-icon-wrap">
            <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Your password" style="padding-right:44px;"/>
            <button type="button" class="pw-toggle" id="pwToggle"
                    onclick="togglePw('password','pwToggle')" aria-label="Show password">
              <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <p class="form-error" id="passwordError"></p>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
          <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
        </button>

      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer_auth.php'; ?>