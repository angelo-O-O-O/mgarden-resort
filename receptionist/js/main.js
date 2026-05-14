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

// ── CONFIRM MODAL (shared) ──
function showConfirmModal({ title, subtitle, icon, danger, confirmLabel, onConfirm }) {
  closeConfirmModal();

  const backdrop = document.createElement('div');
  backdrop.id = '__cfBackdrop';
  backdrop.className = 'modal-backdrop show';
  backdrop.onclick = closeConfirmModal;

  const iconBg    = danger ? 'var(--red-light)'   : 'var(--green-light)';
  const iconColor = danger ? 'var(--red)'          : 'var(--green)';
  const boxBg     = danger ? 'var(--red-light)'    : 'var(--green-50)';
  const boxBorder = danger ? '#fecaca'             : 'var(--green-100)';
  const btnClass  = danger ? 'btn-danger'          : 'btn-primary';

  const modal = document.createElement('div');
  modal.id = '__cfModal';
  modal.className = 'modal show';
  modal.innerHTML = `
    <div class="modal-header">
      <div>
        <h3 class="modal-title">${title}</h3>
        ${subtitle ? `<p class="modal-subtitle">${subtitle}</p>` : ''}
      </div>
      <button class="modal-close" onclick="closeConfirmModal()"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="approve-confirm-box" style="background:${boxBg};border-color:${boxBorder};">
        <div class="approve-icon" style="background:${iconBg};color:${iconColor};">
          <i class="fa-solid ${icon}"></i>
        </div>
        <div class="approve-info">
          <p style="font-size:0.92rem;color:var(--gray-700);line-height:1.5;">${subtitle || title}</p>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
        <button class="btn btn-outline" onclick="closeConfirmModal()">Cancel</button>
        <button class="btn ${btnClass}" id="__cfConfirmBtn">${confirmLabel}</button>
      </div>
    </div>`;

  document.body.appendChild(backdrop);
  document.body.appendChild(modal);
  document.getElementById('__cfConfirmBtn').onclick = function () {
    closeConfirmModal();
    onConfirm();
  };
}

function closeConfirmModal() {
  document.getElementById('__cfModal')?.remove();
  document.getElementById('__cfBackdrop')?.remove();
}

// ── RESERVATION ACTIONS ──
function approveReservation(id) {
  showConfirmModal({
    title: 'Approve Reservation',
    subtitle: 'Are you sure you want to approve this reservation?',
    icon: 'fa-circle-check',
    danger: false,
    confirmLabel: '<i class="fa-solid fa-circle-check"></i> Approve',
    onConfirm: () => updateReservationStatus(id, 'approved'),
  });
}
function cancelReservation(id) {
  showConfirmModal({
    title: 'Cancel Reservation',
    subtitle: 'Are you sure you want to cancel this reservation? This cannot be undone.',
    icon: 'fa-circle-xmark',
    danger: true,
    confirmLabel: '<i class="fa-solid fa-circle-xmark"></i> Cancel Reservation',
    onConfirm: () => updateReservationStatus(id, 'cancelled'),
  });
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

// ── SIGNOUT MODAL ──
function openSignoutModal() {
  document.getElementById('signoutBackdrop')?.classList.add('show');
  document.getElementById('signoutModal')?.classList.add('show');
}
function closeSignoutModal() {
  document.getElementById('signoutBackdrop')?.classList.remove('show');
  document.getElementById('signoutModal')?.classList.remove('show');
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