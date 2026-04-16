<?php
$pageTitle = 'Login';
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) redirect(SITE_URL . '/guest/index.php');

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
        $stmt = $db->prepare("SELECT guest_id, guest_name, email, password FROM guests WHERE email = ? AND login_type = 'local' LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $guest = $stmt->get_result()->fetch_assoc();

        if ($guest && password_verify($password, $guest['password'])) {
            $_SESSION['guest_id']   = $guest['guest_id'];
            $_SESSION['guest_name'] = $guest['guest_name'];
            $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/guest/index.php';
            unset($_SESSION['redirect_after_login']);
            setFlash('success', 'Welcome back, ' . explode(' ', trim($guest['guest_name']))[0] . '!');
            redirect($redirect);
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo">
        <img src="<?= SITE_URL ?>/images/mgardenlogo.jpg" alt="MGarden"
             style="width:100%;height:100%;object-fit:contain;border-radius:12px;"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block';"/>
        <span style="display:none;font-size:1.5rem;font-weight:700;color:#fff;">M</span>
      </div>
      <h1 class="auth-title">Welcome Back</h1>
      <p class="auth-sub">Sign in to your MGarden account</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="flash flash-error" style="margin-bottom:20px;border-radius:var(--radius);">
        <?= e($errors[0]) ?>
      </div>
    <?php endif; ?>

    <div class="auth-form-card">
      <form method="POST" action="" id="loginForm" novalidate>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-icon-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="you@example.com" value="<?= e($email) ?>"/>
          </div>
          <p class="form-error" id="emailError"></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Your password" style="padding-right:44px;"/>
            <button type="button" class="pw-toggle" id="pwToggle" onclick="togglePw('password','pwToggle')" aria-label="Show password">
              <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <p class="form-error" id="passwordError"></p>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
          Login →
        </button>

      </form>

      <div class="auth-divider"><span>or</span></div>

      <button type="button" class="btn-google" title="Coming soon" onclick="return false;">
        <svg width="18" height="18" viewBox="0 0 48 48">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          <path fill="none" d="M0 0h48v48H0z"/>
        </svg>
        Continue with Google
      </button>
    </div>

    <p class="auth-footer">
      Don't have an account? <a href="<?= SITE_URL ?>/guest/pages/signup.php">Sign Up</a>
    </p>
  </div>
</div>

<script>
function togglePw(inputId, btnId) {
  const input   = document.getElementById(inputId);
  const open    = document.getElementById('eyeOpen');
  const closed  = document.getElementById('eyeClosed');
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  open.style.display   = isHidden ? 'none'  : '';
  closed.style.display = isHidden ? ''      : 'none';
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  let valid = true;
  const email = document.getElementById('email');
  const emailErr = document.getElementById('emailError');
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!email.value.trim()) {
    emailErr.textContent = 'Email is required.';
    email.classList.add('input-error'); valid = false;
  } else if (!emailRegex.test(email.value.trim())) {
    emailErr.textContent = 'Please enter a valid email address.';
    email.classList.add('input-error'); valid = false;
  } else { emailErr.textContent = ''; email.classList.remove('input-error'); }

  const pw = document.getElementById('password');
  const pwErr = document.getElementById('passwordError');
  if (!pw.value.trim()) {
    pwErr.textContent = 'Password is required.';
    pw.classList.add('input-error'); valid = false;
  } else { pwErr.textContent = ''; pw.classList.remove('input-error'); }

  if (!valid) e.preventDefault();
});

['email','password'].forEach(function(id) {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', function() {
    el.classList.remove('input-error');
    const err = document.getElementById(id + 'Error');
    if (err) err.textContent = '';
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>