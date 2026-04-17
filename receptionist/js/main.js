// MGarden Beach Resort — Receptionist Main JS

// ── NAVBAR TOGGLE (mobile) ──
function toggleNav() {
  const nav = document.getElementById('navLinks');
  if (nav) nav.classList.toggle('open');
}

// ── USER DROPDOWN ──
function toggleDropdown() {
  const menu = document.getElementById('dropdownMenu');
  if (menu) menu.classList.toggle('show');
}

document.addEventListener('click', function (e) {
  const dropdown = document.querySelector('.user-dropdown');
  const menu     = document.getElementById('dropdownMenu');
  if (dropdown && menu && !dropdown.contains(e.target)) {
    menu.classList.remove('show');
  }
});

// ── FLASH AUTO-DISMISS ──
setTimeout(function () {
  const flash = document.getElementById('flashMsg');
  if (flash) {
    flash.style.transition = 'opacity 0.4s';
    flash.style.opacity    = '0';
    setTimeout(function () { flash.remove(); }, 400);
  }
}, 4000);

// ── PASSWORD TOGGLE ──
function togglePw(inputId, toggleId) {
  const input  = document.getElementById(inputId);
  const toggle = document.getElementById(toggleId);
  const eyeOpen  = toggle.querySelector('#eyeOpen');
  const eyeClosed = toggle.querySelector('#eyeClosed');

  if (input.type === 'password') {
    input.type = 'text';
    eyeOpen.style.display = 'none';
    eyeClosed.style.display = 'block';
  } else {
    input.type = 'password';
    eyeOpen.style.display = 'block';
    eyeClosed.style.display = 'none';
  }
}

// ── RESERVATION ACTIONS ──
function approveReservation(reservationId) {
  if (confirm('Are you sure you want to approve this reservation?')) {
    updateReservationStatus(reservationId, 'approved');
  }
}

function cancelReservation(reservationId) {
  if (confirm('Are you sure you want to cancel this reservation?')) {
    updateReservationStatus(reservationId, 'cancelled');
  }
}

function updateReservationStatus(reservationId, status) {
  // Create form data
  const formData = new FormData();
  formData.append('reservation_id', reservationId);
  formData.append('status', status);

  const apiUrl = window.RECEPTIONIST_API?.updateReservationUrl || 'update_reservation.php';

  // Send request
  fetch(apiUrl, {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(text => {
    try {
      return JSON.parse(text);
    } catch (error) {
      throw new Error(text || 'Invalid JSON response');
    }
  })
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to update reservation'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the reservation. See console for details.');
  });
}

// ── FORM VALIDATION ──
document.addEventListener('DOMContentLoaded', function () {
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      const email = document.getElementById('email');
      const password = document.getElementById('password');
      let isValid = true;

      // Reset errors
      document.getElementById('emailError').style.display = 'none';
      document.getElementById('passwordError').style.display = 'none';

      // Email validation
      if (!email.value.trim()) {
        document.getElementById('emailError').textContent = 'Email is required';
        document.getElementById('emailError').style.display = 'block';
        isValid = false;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        document.getElementById('emailError').textContent = 'Please enter a valid email';
        document.getElementById('emailError').style.display = 'block';
        isValid = false;
      }

      // Password validation
      if (!password.value.trim()) {
        document.getElementById('passwordError').textContent = 'Password is required';
        document.getElementById('passwordError').style.display = 'block';
        isValid = false;
      }

      if (!isValid) {
        e.preventDefault();
      }
    });
  }
});