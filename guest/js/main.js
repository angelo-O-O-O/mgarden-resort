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
      // Highlight direct page links when there are no sections or the page is a dedicated page
      if (window.location.pathname.endsWith('/schedules.php')) {
        if (schedulesLink) schedulesLink.classList.add('active');
      } else if (homeLink) {
        homeLink.classList.add('active');
      }
    }
  }

  window.addEventListener('scroll', setActiveLink, { passive: true });
  setActiveLink();
});