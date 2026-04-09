<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();

$id   = (int)($_GET['id'] ?? 0);
$room = $db->query("SELECT r.*, rc.name as category_name FROM rooms r JOIN room_categories rc ON r.category_id = rc.id WHERE r.id = $id")->fetch_assoc();
if (!$room) { redirect(SITE_URL . '/index.php'); }

$amenities = json_decode($room['amenities'] ?? '[]', true) ?: [];
$services  = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// Handle add to cart
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $ci      = $_POST['check_in']  ?? '';
    $co      = $_POST['check_out'] ?? '';
    $guests  = (int)($_POST['num_guests'] ?? 1);
    $special = trim($_POST['special_requests'] ?? '');
    $svcIds  = $_POST['service_ids'] ?? [];

    if (!$ci)               $errors[] = 'Check-in date is required.';
    if (!$co)               $errors[] = 'Check-out date is required.';
    if ($ci && $co && $co <= $ci) $errors[] = 'Check-out must be after check-in.';
    if ($ci && $ci < date('Y-m-d')) $errors[] = 'Check-in cannot be in the past.';

    if (empty($errors)) {
        // Check availability
        $stmt = $db->prepare("SELECT id FROM bookings WHERE room_id=? AND status IN ('pending','confirmed','checked_in') AND check_in < ? AND check_out > ?");
        $stmt->bind_param('iss', $id, $co, $ci);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'This room is already booked for the selected dates. Please choose different dates.';
        }
    }

    if (empty($errors)) {
        $uid = $_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO carts (user_id, room_id, check_in, check_out, num_guests, special_requests, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())");
        $stmt->bind_param('iissis', $uid, $id, $ci, $co, $guests, $special);
        $stmt->execute();
        $cartId = $db->insert_id;

        foreach ($svcIds as $svcId) {
            $svcId = (int)$svcId;
            $db->query("INSERT INTO cart_services (cart_id, service_id, quantity, created_at, updated_at) VALUES ($cartId, $svcId, 1, NOW(), NOW())");
        }

        setFlash('success', '🛒 Room added to cart!');
        redirect(SITE_URL . '/pages/cart.php');
    }
}

$pageTitle = $room['name'];
require_once __DIR__ . '/../includes/header.php';
$placeholder = "https://placehold.co/1200x500/d1fae5/065f46?text=" . urlencode($room['name']) . "&font=quicksand";
$catIcons = ['activity'=>'🏄','food_beverage'=>'🍽️','spa'=>'💆','transport'=>'🚐','event'=>'🎉','other'=>'✨'];
?>
<script>const siteUrl = '<?= SITE_URL ?>';</script>

<div class="container" style="padding-top:40px;padding-bottom:60px;">

  <!-- Hero image -->
  <div class="room-detail-hero">
    <img src="<?= $placeholder ?>" alt="<?= e($room['name']) ?>"/>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="flash flash-error" style="margin-bottom:20px;border-radius:var(--radius);">
    <div><?php foreach($errors as $e_) echo '• ' . e($e_) . '<br>'; ?></div>
  </div>
  <?php endif; ?>

  <div class="room-detail-grid">

    <!-- LEFT: Details -->
    <div>
      <div style="margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
          <span class="tag" style="font-size:0.78rem;"><?= e($room['category_name']) ?></span>
          <span style="margin-left:auto;font-size:0.84rem;color:var(--gray-400);">⭐ 4.8 &nbsp;(128 reviews)</span>
        </div>
        <h1 style="font-size:1.9rem;font-weight:700;color:var(--green-dark);margin-bottom:6px;"><?= e($room['name']) ?></h1>
        <p style="color:var(--gray-400);font-size:0.9rem;">👤 Up to <?= $room['capacity'] ?> guests</p>
      </div>

      <div style="margin-bottom:28px;">
        <h2 style="font-weight:700;font-size:1.1rem;margin-bottom:10px;color:var(--gray-800);">About this room</h2>
        <p style="color:var(--gray-500);line-height:1.7;"><?= e($room['description']) ?></p>
      </div>

      <div style="margin-bottom:28px;">
        <h2 style="font-weight:700;font-size:1.1rem;margin-bottom:12px;color:var(--gray-800);">Amenities</h2>
        <div class="amenity-grid">
          <?php foreach ($amenities as $a): ?>
            <div class="amenity-item">✔ <?= e($a) ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <h2 style="font-weight:700;font-size:1.1rem;margin-bottom:12px;color:var(--gray-800);">Add-on Services</h2>
        <div class="grid-2" style="gap:10px;">
          <?php foreach (array_slice($services, 0, 8) as $svc): ?>
          <label class="amenity-item" style="cursor:pointer;border:2px solid var(--gray-200);background:#fff;border-radius:var(--radius);padding:12px;gap:12px;transition:all 0.15s;" onmouseover="this.style.borderColor='var(--green)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--gray-200)'"
            onclick="this.style.borderColor=this.querySelector('input').checked?'var(--green)':'var(--gray-200)';this.style.background=this.querySelector('input').checked?'var(--green-50)':'#fff'">
            <input type="checkbox" form="bookingForm" name="service_ids[]" value="<?= $svc['id'] ?>" style="display:none;" onchange="this.closest('label').style.borderColor=this.checked?'var(--green)':'var(--gray-200)';this.closest('label').style.background=this.checked?'var(--green-50)':'#fff'"/>
            <div style="font-size:1.4rem;"><?= $catIcons[$svc['category']] ?? '✨' ?></div>
            <div style="flex:1;">
              <p style="font-weight:700;font-size:0.88rem;color:var(--gray-800);"><?= e($svc['name']) ?></p>
              <p style="font-size:0.76rem;color:var(--gray-400);"><?= peso($svc['price']) ?> <?= e($svc['unit']) ?></p>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Booking form -->
    <div>
      <div class="card booking-card" style="padding:24px;">
        <div class="price-display">
          <span class="price-main" id="priceDisplay" data-price="<?= $room['price_per_night'] ?>"><?= peso($room['price_per_night']) ?></span>
          <span style="font-size:0.85rem;color:var(--gray-400);"> / night</span>
        </div>

        <form method="POST" id="bookingForm">
          <div class="form-group">
            <label class="form-label">Check-in Date</label>
            <input type="date" name="check_in" id="check_in" class="form-control" required value="<?= e($_POST['check_in'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Check-out Date</label>
            <input type="date" name="check_out" id="check_out" class="form-control" required value="<?= e($_POST['check_out'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Number of Guests</label>
            <select name="num_guests" class="form-control">
              <?php for ($i = 1; $i <= $room['capacity']; $i++): ?>
                <option value="<?= $i ?>" <?= (($_POST['num_guests'] ?? 1) == $i) ? 'selected' : '' ?>><?= $i ?> Guest<?= $i > 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Special Requests (optional)</label>
            <textarea name="special_requests" class="form-control" rows="2" placeholder="Any special requests?"><?= e($_POST['special_requests'] ?? '') ?></textarea>
          </div>

          <!-- Price breakdown (shown after dates selected) -->
          <div class="price-breakdown" id="priceBreakdown" style="display:none;margin-bottom:16px;">
            <div class="price-row">
              <span><?= peso($room['price_per_night']) ?> × <span id="nightsDisplay">0</span></span>
              <span id="totalDisplay">₱0</span>
            </div>
            <div class="price-row total">
              <span>Estimated Total</span>
              <span id="totalDisplay2"></span>
            </div>
          </div>

          <?php if (isLoggedIn()): ?>
            <button type="submit" class="btn btn-primary btn-full" style="font-size:1rem;padding:13px;">🛒 Add to Cart</button>
          <?php else: ?>
            <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-primary btn-full" style="font-size:1rem;padding:13px;justify-content:center;">Sign In to Book</a>
          <?php endif; ?>
        </form>

        <div class="trust-items">
          <div class="trust-item">✔ <span>No payment required at this stage</span></div>
          <div class="trust-item">✔ <span>Free cancellation before 48 hours</span></div>
          <div class="trust-item">✔ <span>Pay upon check-in</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Sync both total display elements
document.addEventListener('DOMContentLoaded', function() {
  function calc() {
    const ci = document.getElementById('check_in').value;
    const co = document.getElementById('check_out').value;
    const bp = document.getElementById('priceBreakdown');
    if (!ci || !co) { bp.style.display='none'; return; }
    const d1 = new Date(ci), d2 = new Date(co);
    if (d2 <= d1) { bp.style.display='none'; return; }
    const nights = Math.round((d2-d1)/86400000);
    const price  = parseFloat(document.getElementById('priceDisplay').dataset.price);
    const total  = nights * price;
    document.getElementById('nightsDisplay').textContent = nights + ' night' + (nights>1?'s':'');
    document.getElementById('totalDisplay').textContent  = '₱' + total.toLocaleString();
    document.getElementById('totalDisplay2').textContent = '₱' + total.toLocaleString();
    bp.style.display = 'block';
  }
  document.getElementById('check_in').addEventListener('change', calc);
  document.getElementById('check_out').addEventListener('change', calc);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
