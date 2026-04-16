<?php
$pageTitle = 'Sign Up';
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) redirect(SITE_URL . '/guest/index.php');

$errors      = [];
$guest_name  = '';
$email       = '';
$contact_num = '';
$address_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name  = trim($_POST['guest_name']  ?? '');
    $email       = trim($_POST['email']       ?? '');
    $contact_num = trim($_POST['contact_num'] ?? '');
    $address_val = trim($_POST['address']     ?? '');
    $password    = trim($_POST['password']    ?? '');
    $confirm     = trim($_POST['confirm']     ?? '');

    if (empty($guest_name))   $errors[] = 'Full name is required.';
    if (empty($email))        $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!empty($contact_num) && !preg_match('/^[0-9]{11}$/', $contact_num)) $errors[] = 'Contact number must be exactly 11 digits.';
    if (empty($password))     $errors[] = 'Password is required.';
    elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT guest_id FROM guests WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt   = $db->prepare("INSERT INTO guests (guest_name, email, password, contact_num, address, login_type) VALUES (?, ?, ?, ?, ?, 'local')");
            $stmt->bind_param('sssss', $guest_name, $email, $hashed, $contact_num, $address_val);
            if ($stmt->execute()) {
                $_SESSION['guest_id']   = $db->insert_id;
                $_SESSION['guest_name'] = $guest_name;
                setFlash('success', 'Welcome to MGarden, ' . explode(' ', trim($guest_name))[0] . '!');
                redirect(SITE_URL . '/guest/index.php');
            } else {
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card" style="max-width:500px;">
    <div class="auth-header">
      <div class="auth-logo">
        <img src="<?= SITE_URL ?>/images/mgardenlogo.jpg" alt="MGarden"
             style="width:100%;height:100%;object-fit:contain;border-radius:12px;"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block';"/>
        <span style="display:none;font-size:1.5rem;font-weight:700;color:#fff;">M</span>
      </div>
      <h1 class="auth-title">Create Account</h1>
      <p class="auth-sub">Join MGarden Beach Resort today</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="flash flash-error" style="margin-bottom:20px;border-radius:var(--radius);">
        <?= e($errors[0]) ?>
      </div>
    <?php endif; ?>

    <div class="auth-form-card">
      <form method="POST" action="" id="signupForm" novalidate>

        <div class="form-group">
          <label class="form-label" for="guest_name">Full Name <span style="color:var(--red)">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">👤</span>
            <input type="text" id="guest_name" name="guest_name" class="form-control"
                   placeholder="Juan dela Cruz" value="<?= e($guest_name) ?>"/>
          </div>
          <p class="form-error" id="nameError"></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address <span style="color:var(--red)">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="you@example.com" value="<?= e($email) ?>"/>
          </div>
          <p class="form-error" id="emailError"></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="contact_num">Contact Number</label>
          <div class="input-icon-wrap">
            <span class="input-icon">📞</span>
            <input type="text" id="contact_num" name="contact_num" class="form-control"
                   placeholder="09XXXXXXXXX" maxlength="11" value="<?= e($contact_num) ?>"/>
          </div>
          <p class="form-error" id="contactError"></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="address">Address</label>
          <div class="input-icon-wrap">
            <span class="input-icon">📍</span>
            <input type="text" id="address" name="address" class="form-control"
                   placeholder="City, Province" value="<?= e($address_val) ?>"/>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password <span style="color:var(--red)">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="At least 8 characters" style="padding-right:44px;"/>
            <button type="button" class="pw-toggle" onclick="togglePw('password','eyeOpen1','eyeClosed1')" aria-label="Show password">
              <svg id="eyeOpen1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eyeClosed1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <p class="form-error" id="passwordError"></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm">Confirm Password <span style="color:var(--red)">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="confirm" name="confirm" class="form-control"
                   placeholder="Repeat your password" style="padding-right:44px;"/>
            <button type="button" class="pw-toggle" onclick="togglePw('confirm','eyeOpen2','eyeClosed2')" aria-label="Show password">
              <svg id="eyeOpen2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eyeClosed2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <p class="form-error" id="confirmError"></p>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
          Create Account →
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
      Already have an account? <a href="<?= SITE_URL ?>/guest/pages/login.php">Login</a>
    </p>
  </div>
</div>

<script>
function togglePw(inputId, openId, closedId) {
  const input  = document.getElementById(inputId);
  const open   = document.getElementById(openId);
  const closed = document.getElementById(closedId);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  open.style.display   = isHidden ? 'none' : '';
  closed.style.display = isHidden ? ''     : 'none';
}

document.getElementById('signupForm').addEventListener('submit', function(e) {
  let valid = true;

  const name = document.getElementById('guest_name');
  const nameErr = document.getElementById('nameError');
  if (!name.value.trim()) {
    nameErr.textContent = 'Full name is required.';
    name.classList.add('input-error'); valid = false;
  } else { nameErr.textContent = ''; name.classList.remove('input-error'); }

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

  const contact = document.getElementById('contact_num');
  const contactErr = document.getElementById('contactError');
  if (contact.value.trim() && !/^[0-9]{11}$/.test(contact.value.trim())) {
    contactErr.textContent = 'Contact number must be exactly 11 digits.';
    contact.classList.add('input-error'); valid = false;
  } else { contactErr.textContent = ''; contact.classList.remove('input-error'); }

  const pw = document.getElementById('password');
  const pwErr = document.getElementById('passwordError');
  if (!pw.value) {
    pwErr.textContent = 'Password is required.';
    pw.classList.add('input-error'); valid = false;
  } else if (pw.value.length < 8) {
    pwErr.textContent = 'Password must be at least 8 characters.';
    pw.classList.add('input-error'); valid = false;
  } else { pwErr.textContent = ''; pw.classList.remove('input-error'); }

  const confirm = document.getElementById('confirm');
  const confirmErr = document.getElementById('confirmError');
  if (!confirm.value) {
    confirmErr.textContent = 'Please confirm your password.';
    confirm.classList.add('input-error'); valid = false;
  } else if (confirm.value !== pw.value) {
    confirmErr.textContent = 'Passwords do not match.';
    confirm.classList.add('input-error'); valid = false;
  } else { confirmErr.textContent = ''; confirm.classList.remove('input-error'); }

  if (!valid) e.preventDefault();
});

['guest_name','email','contact_num','password','confirm'].forEach(function(id) {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', function() {
    el.classList.remove('input-error');
    const map = {guest_name:'nameError',email:'emailError',contact_num:'contactError',password:'passwordError',confirm:'confirmError'};
    const err = document.getElementById(map[id]);
    if (err) err.textContent = '';
    if (id === 'confirm' || id === 'password') {
      const pw  = document.getElementById('password').value;
      const con = document.getElementById('confirm').value;
      const ce  = document.getElementById('confirmError');
      if (con && pw !== con) { ce.textContent = 'Passwords do not match.'; document.getElementById('confirm').classList.add('input-error'); }
      else { ce.textContent = ''; document.getElementById('confirm').classList.remove('input-error'); }
    }
  });
});

document.getElementById('contact_num').addEventListener('keypress', function(e) {
  if (!/[0-9]/.test(e.key)) e.preventDefault();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>