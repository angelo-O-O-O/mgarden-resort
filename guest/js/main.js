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