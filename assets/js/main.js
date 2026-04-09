// MGarden Beach Resort — Main JS

// ── NAVBAR TOGGLE ──
function toggleNav() {
  const nav = document.getElementById('navLinks');
  nav.classList.toggle('open');
}

function toggleDropdown() {
  const menu = document.getElementById('dropdownMenu');
  menu.classList.toggle('open');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  const dropdown = document.querySelector('.user-dropdown');
  const menu = document.getElementById('dropdownMenu');
  if (dropdown && menu && !dropdown.contains(e.target)) {
    menu.classList.remove('open');
  }
});

// ── CALENDAR ──
let calState = {
  year: new Date().getFullYear(),
  month: new Date().getMonth(),
  bookedDates: [],
  blockedDates: [],
};

function renderCalendar() {
  const grid = document.getElementById('calGrid');
  const titleEl = document.getElementById('calTitle');
  if (!grid) return;

  const months = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
  titleEl.textContent = months[calState.month] + ' ' + calState.year;

  const firstDay = new Date(calState.year, calState.month, 1).getDay();
  const daysInMonth = new Date(calState.year, calState.month + 1, 0).getDate();
  const today = new Date(); today.setHours(0,0,0,0);

  let html = '';
  ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
    html += `<div class="cal-day-header">${d}</div>`;
  });

  for (let i = 0; i < firstDay; i++) html += '<div class="cal-day empty"></div>';

  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(calState.year, calState.month, d);
    const dateStr = date.toISOString().slice(0,10);
    const isToday   = date.getTime() === today.getTime();
    const isPast    = date < today;
    const isBooked  = calState.bookedDates.includes(dateStr);
    const isBlocked = calState.blockedDates.includes(dateStr);

    let cls = 'cal-day';
    let title = '';
    if (isPast)       { cls += ' past'; }
    else if (isBlocked) { cls += ' blocked'; title = 'Blocked'; }
    else if (isBooked)  { cls += ' booked';  title = 'Booked'; }
    else                { cls += ' available'; title = 'Available'; }
    if (isToday) cls += ' today';

    html += `<div class="${cls}" title="${title}">${d}</div>`;
  }

  grid.innerHTML = html;
}

function calPrev() {
  calState.month--;
  if (calState.month < 0) { calState.month = 11; calState.year--; }
  renderCalendar();
}
function calNext() {
  calState.month++;
  if (calState.month > 11) { calState.month = 0; calState.year++; }
  renderCalendar();
}

function loadRoomCalendar(roomId) {
  // Highlight selected room button
  document.querySelectorAll('.room-sel-btn').forEach(b => b.classList.remove('active'));
  const activeBtn = document.querySelector(`[data-room="${roomId}"]`);
  if (activeBtn) activeBtn.classList.add('active');

  // Fetch booked & blocked dates via AJAX
  fetch(`${siteUrl}/pages/ajax_dates.php?room_id=${roomId}`)
    .then(r => r.json())
    .then(data => {
      calState.bookedDates  = data.booked  || [];
      calState.blockedDates = data.blocked || [];
      renderCalendar();
    })
    .catch(() => renderCalendar());
}

// ── DATE CALCULATION (booking form) ──
function calcNights() {
  const ci = document.getElementById('check_in');
  const co = document.getElementById('check_out');
  const priceEl  = document.getElementById('priceDisplay');
  const nightsEl = document.getElementById('nightsDisplay');
  const totalEl  = document.getElementById('totalDisplay');
  const breakEl  = document.getElementById('priceBreakdown');

  if (!ci || !co || !priceEl) return;

  const inDate  = new Date(ci.value);
  const outDate = new Date(co.value);
  if (!ci.value || !co.value || outDate <= inDate) {
    if (breakEl) breakEl.style.display = 'none';
    return;
  }

  const nights = Math.round((outDate - inDate) / 86400000);
  const pricePerNight = parseFloat(priceEl.dataset.price || 0);
  const total = nights * pricePerNight;

  if (nightsEl) nightsEl.textContent = nights + ' night' + (nights > 1 ? 's' : '');
  if (totalEl)  totalEl.textContent  = '₱' + total.toLocaleString();
  if (breakEl)  breakEl.style.display = 'block';

  // Set min check-out
  co.min = ci.value;
}

// ── FLASH AUTO-DISMISS ──
setTimeout(() => {
  const flash = document.getElementById('flashMsg');
  if (flash) flash.style.opacity = '0', setTimeout(() => flash.remove(), 400);
}, 4000);

// ── CONFIRM DIALOG ──
function confirmAction(message, formId) {
  if (confirm(message)) {
    document.getElementById(formId).submit();
  }
}

// ── CART ITEM EDIT TOGGLE ──
function toggleEdit(id) {
  const view = document.getElementById('view_' + id);
  const edit = document.getElementById('edit_' + id);
  if (view) view.style.display = view.style.display === 'none' ? 'block' : 'none';
  if (edit) edit.style.display = edit.style.display === 'none' ? 'block' : 'none';
}

// Init calendar on page load
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('calGrid')) {
    renderCalendar();
  }
  // Bind check-in/out change
  const ci = document.getElementById('check_in');
  const co = document.getElementById('check_out');
  if (ci) ci.addEventListener('change', calcNights);
  if (co) co.addEventListener('change', calcNights);

  // Set today as min date for check-in
  if (ci) {
    const today = new Date().toISOString().slice(0,10);
    ci.min = today;
  }
});
