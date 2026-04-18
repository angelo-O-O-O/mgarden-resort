// MGarden Beach Resort — Receptionist JS

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

// ── RESERVATION ACTIONS ──
function approveReservation(id) {
  if (confirm('Approve this reservation?')) updateReservationStatus(id, 'approved');
}
function cancelReservation(id) {
  if (confirm('Cancel this reservation? This cannot be undone.')) updateReservationStatus(id, 'cancelled');
}
function updateReservationStatus(reservationId, status) {
  const formData = new FormData();
  formData.append('reservation_id', reservationId);
  formData.append('status', status);
  const apiUrl = window.RECEPTIONIST_API?.updateReservationUrl || 'update_reservation.php';
  fetch(apiUrl, { method: 'POST', body: formData })
    .then(r => r.text())
    .then(text => {
      try { return JSON.parse(text); }
      catch (e) { throw new Error(text || 'Invalid JSON'); }
    })
    .then(data => {
      if (data.success) location.reload();
      else alert('Error: ' + (data.message || 'Failed to update'));
    })
    .catch(err => {
      console.error(err);
      alert('An error occurred. Check the console for details.');
    });
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