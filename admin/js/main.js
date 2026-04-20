// MGarden Beach Resort — Admin JS

// ── SIDEBAR ──
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  if (sb) sb.classList.remove('open');
  if (ov) ov.classList.remove('show');
}

// ── TOPBAR DATE ──
(function updateDate() {
  const el = document.getElementById('topbarDate');
  if (!el) return;
  el.textContent = new Date().toLocaleDateString('en-PH', {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
  });
})();

// ── FLASH AUTO-DISMISS ──
setTimeout(function () {
  const flash = document.getElementById('flashMsg');
  if (flash) {
    flash.style.transition = 'opacity 0.4s';
    flash.style.opacity    = '0';
    setTimeout(() => flash.remove(), 400);
  }
}, 4000);

// ── PASSWORD TOGGLE ──
function togglePw(inputId, toggleId) {
  const input     = document.getElementById(inputId);
  const toggle    = document.getElementById(toggleId);
  const eyeOpen   = toggle.querySelector('#eyeOpen');
  const eyeClosed = toggle.querySelector('#eyeClosed');
  if (input.type === 'password') {
    input.type = 'text';
    if (eyeOpen)   eyeOpen.style.display   = 'none';
    if (eyeClosed) eyeClosed.style.display = 'block';
  } else {
    input.type = 'password';
    if (eyeOpen)   eyeOpen.style.display   = 'block';
    if (eyeClosed) eyeClosed.style.display = 'none';
  }
}

// ── LOGIN FORM VALIDATION ──
document.addEventListener('DOMContentLoaded', function () {
  const loginForm = document.getElementById('loginForm');
  if (!loginForm) return;
  loginForm.addEventListener('submit', function (e) {
    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const emailErr = document.getElementById('emailError');
    const passErr  = document.getElementById('passwordError');
    let isValid    = true;

    emailErr.style.display = 'none';
    passErr.style.display  = 'none';

    if (!email.value.trim()) {
      emailErr.textContent = 'Email is required';
      emailErr.style.display = 'block'; isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      emailErr.textContent = 'Please enter a valid email';
      emailErr.style.display = 'block'; isValid = false;
    }
    if (!password.value.trim()) {
      passErr.textContent = 'Password is required';
      passErr.style.display = 'block'; isValid = false;
    }
    if (!isValid) e.preventDefault();
  });
});

// ── MODAL MANAGEMENT ──
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.add('open');
}
function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove('open');
}
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
  if (e.target.classList.contains('modal-close')) {
    e.target.closest('.modal-overlay').classList.remove('open');
  }
});

// ── COMMON UTILITIES ──
function deleteConfirm(message) {
  return confirm(message || 'Are you sure you want to delete this?');
}
