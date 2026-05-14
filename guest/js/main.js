// MGarden Beach Resort — Main JS

// ── NAVBAR TOGGLE (mobile) ──
function toggleNav() {
  const nav = document.getElementById('navLinks');
  if (nav) nav.classList.toggle('open');
}

// ── USER DROPDOWN ──
function toggleDropdown() {
  const menu = document.getElementById('dropdownMenu');
  if (menu) menu.classList.toggle('open');
}

document.addEventListener('click', function (e) {
  const dropdown = document.querySelector('.user-dropdown');
  const menu     = document.getElementById('dropdownMenu');
  if (dropdown && menu && !dropdown.contains(e.target)) {
    menu.classList.remove('open');
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

// ══════════════════════════════════════════════════════════
// MODAL SYSTEM
// ══════════════════════════════════════════════════════════

function openModal(id) {
  var overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.classList.add('active');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  var overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.classList.remove('active');
  if (!document.querySelector('.modal-overlay.active')) {
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
  }
}

function switchModal(fromId, toId) {
  closeModal(fromId);
  setTimeout(function () { openModal(toId); }, 120);
}

// Password toggle for modal inputs
function modalTogglePw(inputId, openSpanId, closedSpanId) {
  var input  = document.getElementById(inputId);
  var open   = document.getElementById(openSpanId);
  var closed = document.getElementById(closedSpanId);
  if (!input) return;
  var isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  if (open)   open.style.display   = isHidden ? 'none' : '';
  if (closed) closed.style.display = isHidden ? ''     : 'none';
}

// ── SUCCESS DIALOGUE ──
var _successTimer = null;

function showSuccessDialogue(title, msg, autoReload) {
  var titleEl = document.getElementById('successTitle');
  var msgEl   = document.getElementById('successMsg');
  var barFill = document.getElementById('successBarFill');
  if (titleEl) titleEl.textContent = title;
  if (msgEl)   msgEl.textContent   = msg;
  openModal('successModal');

  // Animated shrink bar that indicates auto-close timing
  var delay = 1000;
  if (barFill) {
    barFill.style.transition = 'none';
    barFill.style.transform  = 'scaleX(1)';
    // force reflow
    barFill.offsetWidth; // eslint-disable-line no-unused-expressions
    barFill.style.transition = 'transform ' + (delay / 1000) + 's linear';
    barFill.style.transform  = 'scaleX(0)';
  }

  clearTimeout(_successTimer);
  _successTimer = setTimeout(function () {
    closeModal('successModal');
    window.location.reload();
  }, delay);
}

document.addEventListener('DOMContentLoaded', function () {

  // ── SIGN-OUT CONFIRMATION ──
  var signoutLink = document.querySelector('.dropdown-item.text-red[href*="logout"]');
  if (signoutLink) {
    signoutLink.addEventListener('click', function (e) {
      e.preventDefault();
      // close dropdown first
      var menu = document.getElementById('dropdownMenu');
      if (menu) menu.classList.remove('open');
      openModal('signoutModal');
    });
  }

  // ── MODAL TRIGGER BUTTONS ──
  document.querySelectorAll('[data-modal-trigger]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      openModal(btn.getAttribute('data-modal-trigger') + 'Modal');
    });
  });

  // ── CLOSE ON OVERLAY CLICK ──
  document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay && overlay.id !== 'successModal') {
        closeModal(overlay.id);
      }
    });
  });

  // ── CLOSE ON ESC ──
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.active').forEach(function (m) {
        if (m.id !== 'successModal') closeModal(m.id);
      });
    }
  });

  // ── LOGIN MODAL FORM ──
  var loginForm = document.getElementById('loginModalForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var emailEl  = document.getElementById('mLoginEmail');
      var pwEl     = document.getElementById('mLoginPw');
      var emailErr = document.getElementById('mLoginEmailErr');
      var pwErr    = document.getElementById('mLoginPwErr');
      var errorBox = document.getElementById('loginModalError');
      var emailRe  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      var valid    = true;

      if (!emailEl.value.trim()) {
        emailErr.textContent = 'Email is required.';
        emailEl.classList.add('input-error'); valid = false;
      } else if (!emailRe.test(emailEl.value.trim())) {
        emailErr.textContent = 'Please enter a valid email.';
        emailEl.classList.add('input-error'); valid = false;
      } else { emailErr.textContent = ''; emailEl.classList.remove('input-error'); }

      if (!pwEl.value.trim()) {
        pwErr.textContent = 'Password is required.';
        pwEl.classList.add('input-error'); valid = false;
      } else { pwErr.textContent = ''; pwEl.classList.remove('input-error'); }

      if (!valid) return;

      var submitBtn = document.getElementById('loginModalSubmit');
      submitBtn.disabled     = true;
      submitBtn.textContent  = 'Signing in…';
      errorBox.style.display = 'none';

      fetch(loginForm.getAttribute('action'), {
        method: 'POST',
        body: new FormData(loginForm),
        credentials: 'same-origin'
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          closeModal('loginModal');
          showSuccessDialogue(
            'Welcome back, ' + data.name + '!',
            'You are now signed in to your MGarden account.'
          );
        } else {
          errorBox.textContent   = data.error || 'Login failed. Please try again.';
          errorBox.style.display = 'flex';
          submitBtn.disabled     = false;
          submitBtn.textContent  = 'Login →';
        }
      })
      .catch(function () {
        errorBox.textContent   = 'Network error. Please try again.';
        errorBox.style.display = 'flex';
        submitBtn.disabled     = false;
        submitBtn.textContent  = 'Login →';
      });
    });

    // Clear inline errors on input
    ['mLoginEmail', 'mLoginPw'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', function () {
        el.classList.remove('input-error');
        var err = document.getElementById(id === 'mLoginEmail' ? 'mLoginEmailErr' : 'mLoginPwErr');
        if (err) err.textContent = '';
      });
    });
  }

  // ── SIGNUP MODAL FORM ──
  var signupForm = document.getElementById('signupModalForm');
  if (signupForm) {
    signupForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var nameEl    = document.getElementById('mSignName');
      var emailEl   = document.getElementById('mSignEmail');
      var contactEl = document.getElementById('mSignContact');
      var pwEl      = document.getElementById('mSignPw');
      var confEl    = document.getElementById('mSignConfirm');
      var errorBox  = document.getElementById('signupModalError');
      var emailRe   = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      var valid     = true;

      function setErr(el, errId, msg) {
        var errEl = document.getElementById(errId);
        if (msg) { if (errEl) errEl.textContent = msg; el.classList.add('input-error'); valid = false; }
        else     { if (errEl) errEl.textContent = '';  el.classList.remove('input-error'); }
      }

      if (!nameEl.value.trim())        setErr(nameEl,    'mSignNameErr',    'Full name is required.');
      else                              setErr(nameEl,    'mSignNameErr',    '');

      if (!emailEl.value.trim())        setErr(emailEl,   'mSignEmailErr',   'Email is required.');
      else if (!emailRe.test(emailEl.value.trim())) setErr(emailEl, 'mSignEmailErr', 'Please enter a valid email.');
      else                              setErr(emailEl,   'mSignEmailErr',   '');

      if (contactEl.value.trim() && !/^[0-9]{11}$/.test(contactEl.value.trim()))
        setErr(contactEl, 'mSignContactErr', 'Contact number must be exactly 11 digits.');
      else setErr(contactEl, 'mSignContactErr', '');

      if (!pwEl.value)                  setErr(pwEl,  'mSignPwErr',      'Password is required.');
      else if (pwEl.value.length < 8)   setErr(pwEl,  'mSignPwErr',      'Password must be at least 8 characters.');
      else                              setErr(pwEl,  'mSignPwErr',      '');

      if (!confEl.value)                setErr(confEl,'mSignConfirmErr', 'Please confirm your password.');
      else if (confEl.value !== pwEl.value) setErr(confEl,'mSignConfirmErr', 'Passwords do not match.');
      else                              setErr(confEl,'mSignConfirmErr', '');

      if (!valid) return;

      var submitBtn = document.getElementById('signupModalSubmit');
      submitBtn.disabled     = true;
      submitBtn.textContent  = 'Creating account…';
      errorBox.style.display = 'none';

      fetch(signupForm.getAttribute('action'), {
        method: 'POST',
        body: new FormData(signupForm),
        credentials: 'same-origin'
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          closeModal('signupModal');
          showSuccessDialogue(
            'Welcome to MGarden, ' + data.name + '!',
            'Your account has been created. You are now signed in.',
            true
          );
        } else {
          errorBox.textContent   = data.error || 'Signup failed. Please try again.';
          errorBox.style.display = 'flex';
          submitBtn.disabled     = false;
          submitBtn.textContent  = 'Create Account →';
        }
      })
      .catch(function () {
        errorBox.textContent   = 'Network error. Please try again.';
        errorBox.style.display = 'flex';
        submitBtn.disabled     = false;
        submitBtn.textContent  = 'Create Account →';
      });
    });

    // Live password-match feedback
    ['mSignPw', 'mSignConfirm'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', function () {
        el.classList.remove('input-error');
        var pw   = document.getElementById('mSignPw').value;
        var conf = document.getElementById('mSignConfirm').value;
        var ce   = document.getElementById('mSignConfirmErr');
        if (conf && pw !== conf) {
          ce.textContent = 'Passwords do not match.';
          document.getElementById('mSignConfirm').classList.add('input-error');
        } else {
          if (ce) ce.textContent = '';
          document.getElementById('mSignConfirm').classList.remove('input-error');
        }
      });
    });

    // Digits-only for contact
    var contactEl = document.getElementById('mSignContact');
    if (contactEl) {
      contactEl.addEventListener('keypress', function (e) {
        if (!/[0-9]/.test(e.key)) e.preventDefault();
      });
    }
  }

});

// ── ACTIVE NAV LINK ──
document.addEventListener('DOMContentLoaded', function () {

  // Close mobile nav on link click
  document.querySelectorAll('.nav-link').forEach(function (link) {
    link.addEventListener('click', function () {
      const nav = document.getElementById('navLinks');
      if (nav) nav.classList.remove('open');
    });
  });

  const sections = document.querySelectorAll('section[id]');
  const links    = document.querySelectorAll('.nav-link');
  const homeLink = document.getElementById('navHome');
  const schedulesLink = document.getElementById('navSchedules');

  function setActiveLink() {
    let current = '';
    sections.forEach(function (sec) {
      const top = sec.getBoundingClientRect().top;
      if (top <= 100) current = sec.getAttribute('id');
    });

    // Clear all
    links.forEach(function (l) { l.classList.remove('active'); });

    if (current) {
      // Highlight matching anchor link
      links.forEach(function (link) {
        const href = link.getAttribute('href') || '';
        if (href.endsWith('#' + current)) {
          link.classList.add('active');
        }
      });
    } else {
      // Match current path against any nav link's PHP filename
      const path = window.location.pathname;
      let matched = null;
      links.forEach(function (link) {
        const href = link.getAttribute('href') || '';
        const phpFile = href.replace(/#.*$/, '').split('/').pop();
        if (phpFile && phpFile !== 'index.php' && path.endsWith('/' + phpFile)) {
          matched = link;
        }
      });
      if (matched) {
        matched.classList.add('active');
      } else if (homeLink) {
        homeLink.classList.add('active');
      }
    }
  }

  window.addEventListener('scroll', setActiveLink, { passive: true });
  setActiveLink();

  // ── SCROLL REVEAL ──
  (function () {
    var revealAll = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    if (!revealAll.length) return;

    // Auto-stagger direct children inside grid containers
    document.querySelectorAll('.grid-3, .grid-2, .features-grid').forEach(function (grid) {
      var children = Array.from(grid.querySelectorAll(':scope > .reveal, :scope > .reveal-left, :scope > .reveal-right'));
      children.forEach(function (child, i) {
        if (!child.className.match(/stagger-\d/)) {
          child.style.transitionDelay = (i * 0.1) + 's';
        }
      });
    });

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    revealAll.forEach(function (el) { observer.observe(el); });
  })();
});