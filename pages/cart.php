<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

// Handle delete
if (isset($_GET['delete'])) {
    $cid = (int)$_GET['delete'];
    $db->query("DELETE FROM carts WHERE id=$cid AND user_id=$uid");
    setFlash('success', 'Item removed from cart.');
    redirect(SITE_URL . '/pages/cart.php');
}

// Handle clear all
if (isset($_GET['clear'])) {
    $db->query("DELETE FROM carts WHERE user_id=$uid");
    setFlash('success', 'Cart cleared.');
    redirect(SITE_URL . '/pages/cart.php');
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $cid     = (int)$_POST['cart_id'];
    $ci      = $_POST['check_in'];
    $co      = $_POST['check_out'];
    $guests  = (int)$_POST['num_guests'];
    $special = $db->real_escape_string($_POST['special_requests'] ?? '');
    if ($ci && $co && $co > $ci) {
        $db->query("UPDATE carts SET check_in='$ci', check_out='$co', num_guests=$guests, special_requests='$special', updated_at=NOW() WHERE id=$cid AND user_id=$uid");
        setFlash('success', 'Cart item updated!');
    } else {
        setFlash('error', 'Invalid dates. Check-out must be after check-in.');
    }
    redirect(SITE_URL . '/pages/cart.php');
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $items = $db->query("SELECT c.*, r.price_per_night, r.name as room_name FROM carts c JOIN rooms r ON c.room_id = r.id WHERE c.user_id=$uid")->fetch_all(MYSQLI_ASSOC);
    if (empty($items)) { setFlash('error', 'Your cart is empty.'); redirect(SITE_URL . '/pages/cart.php'); }

    foreach ($items as $item) {
        $nights = max(1, (int)((strtotime($item['check_out']) - strtotime($item['check_in'])) / 86400));
        $roomSub = $nights * $item['price_per_night'];

        // Get services for this cart item
        $svcRows = $db->query("SELECT cs.service_id, s.price FROM cart_services cs JOIN services s ON cs.service_id=s.id WHERE cs.cart_id={$item['id']}")->fetch_all(MYSQLI_ASSOC);
        $svcSub  = array_sum(array_column($svcRows, 'price'));
        $total   = $roomSub + $svcSub;
        $ref     = 'MGR-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $ci      = $item['check_in']; $co = $item['check_out'];
        $guests  = $item['num_guests']; $rid = $item['room_id'];
        $special = $db->real_escape_string($item['special_requests'] ?? '');

        $db->query("INSERT INTO bookings (booking_reference, user_id, room_id, check_in, check_out, num_guests, room_subtotal, services_subtotal, total_amount, special_requests, status, payment_status, created_at, updated_at)
            VALUES ('$ref', $uid, $rid, '$ci', '$co', $guests, $roomSub, $svcSub, $total, '$special', 'pending', 'unpaid', NOW(), NOW())");
        $bid = $db->insert_id;

        foreach ($svcRows as $svc) {
            $sid = $svc['service_id']; $up = $svc['price'];
            $db->query("INSERT INTO booking_services (booking_id, service_id, quantity, unit_price, subtotal, created_at, updated_at) VALUES ($bid, $sid, 1, $up, $up, NOW(), NOW())");
        }
    }
    $db->query("DELETE FROM carts WHERE user_id=$uid");
    setFlash('success', '🎉 Booking confirmed! Check "My Bookings" for details.');
    redirect(SITE_URL . '/pages/my-bookings.php');
}

// Fetch cart items
$cartItems = $db->query("SELECT c.*, r.name as room_name, r.price_per_night, r.capacity, rc.name as category_name
    FROM carts c 
    JOIN rooms r ON c.room_id = r.id 
    JOIN room_categories rc ON r.category_id = rc.id
    WHERE c.user_id=$uid ORDER BY c.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch services for each cart item
foreach ($cartItems as &$item) {
    $item['services'] = $db->query("SELECT s.name, s.price FROM cart_services cs JOIN services s ON cs.service_id=s.id WHERE cs.cart_id={$item['id']}")->fetch_all(MYSQLI_ASSOC);
    $nights = max(1, (int)((strtotime($item['check_out']) - strtotime($item['check_in'])) / 86400));
    $item['nights']     = $nights;
    $item['room_total'] = $nights * $item['price_per_night'];
    $item['svc_total']  = array_sum(array_column($item['services'], 'price'));
    $item['subtotal']   = $item['room_total'] + $item['svc_total'];
}
unset($item);

$grandTotal = array_sum(array_column($cartItems, 'subtotal'));

$pageTitle = 'My Cart';
require_once __DIR__ . '/../includes/header.php';
?>
<script>const siteUrl='<?= SITE_URL ?>';</script>

<div class="container page-wrap">
  <div class="page-header flex justify-between items-center" style="flex-wrap:wrap;gap:12px;">
    <div>
      <h1>My Booking Cart</h1>
      <p><?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?> in cart</p>
    </div>
    <?php if (!empty($cartItems)): ?>
    <a href="?clear=1" onclick="return confirm('Clear all cart items?')" class="btn btn-sm" style="color:var(--red);border-color:var(--red);background:#fff;">🗑 Clear All</a>
    <?php endif; ?>
  </div>

  <?php if (empty($cartItems)): ?>
  <div class="empty-state">
    <div class="empty-icon">🛒</div>
    <h2 class="empty-title">Your cart is empty</h2>
    <p class="empty-desc">Browse our rooms and add bookings to get started.</p>
    <a href="<?= SITE_URL ?>/index.php#rooms" class="btn btn-primary">Explore Rooms</a>
  </div>
  <?php else: ?>
  <div class="two-col">
    <!-- Cart items -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($cartItems as $item):
        $placeholder = "https://placehold.co/300x200/d1fae5/065f46?text=" . urlencode($item['room_name']) . "&font=quicksand";
      ?>
      <div class="card" style="padding:0;overflow:hidden;">
        <div class="cart-item" style="padding:16px;gap:16px;">
          <img src="<?= $placeholder ?>" alt="<?= e($item['room_name']) ?>" class="cart-item-img"/>
          <div class="cart-item-body">
            <h3 class="cart-item-title"><?= e($item['room_name']) ?></h3>
            <p style="font-size:0.76rem;color:var(--gray-400);margin-bottom:6px;"><?= e($item['category_name']) ?></p>

            <!-- View mode -->
            <div id="view_<?= $item['id'] ?>">
              <p class="cart-item-meta">
                📅 <?= date('M d', strtotime($item['check_in'])) ?> → <?= date('M d, Y', strtotime($item['check_out'])) ?>
                &nbsp;·&nbsp; 👤 <?= $item['num_guests'] ?> guest<?= $item['num_guests'] > 1 ? 's' : '' ?>
                &nbsp;·&nbsp; <?= $item['nights'] ?> night<?= $item['nights'] > 1 ? 's' : '' ?>
              </p>
              <?php if (!empty($item['services'])): ?>
              <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                <?php foreach ($item['services'] as $svc): ?>
                  <span class="tag">+ <?= e($svc['name']) ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="cart-actions">
                <button onclick="toggleEdit(<?= $item['id'] ?>)" class="btn btn-outline btn-sm">✏ Edit</button>
                <a href="?delete=<?= $item['id'] ?>" onclick="return confirm('Remove this item?')" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red);">🗑 Remove</a>
              </div>
            </div>

            <!-- Edit mode -->
            <div id="edit_<?= $item['id'] ?>" style="display:none;">
              <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;">
                <input type="hidden" name="update_cart" value="1"/>
                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>"/>
                <div>
                  <label class="form-label">Check-in</label>
                  <input type="date" name="check_in" class="form-control" value="<?= e($item['check_in']) ?>" min="<?= date('Y-m-d') ?>" style="font-size:0.82rem;padding:8px;"/>
                </div>
                <div>
                  <label class="form-label">Check-out</label>
                  <input type="date" name="check_out" class="form-control" value="<?= e($item['check_out']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" style="font-size:0.82rem;padding:8px;"/>
                </div>
                <div>
                  <label class="form-label">Guests</label>
                  <select name="num_guests" class="form-control" style="font-size:0.82rem;padding:8px;">
                    <?php for ($i=1;$i<=$item['capacity'];$i++): ?>
                      <option <?= $item['num_guests']==$i?'selected':'' ?>><?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div style="grid-column:1/-1;">
                  <label class="form-label">Special Requests</label>
                  <input type="text" name="special_requests" class="form-control" value="<?= e($item['special_requests'] ?? '') ?>" style="font-size:0.82rem;padding:8px;"/>
                </div>
                <div style="grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end;">
                  <button type="button" onclick="toggleEdit(<?= $item['id'] ?>)" class="btn btn-sm" style="border:2px solid var(--gray-200);color:var(--gray-600);">Cancel</button>
                  <button type="submit" class="btn btn-primary btn-sm">✔ Save</button>
                </div>
              </form>
            </div>
          </div>

          <div style="text-align:right;flex-shrink:0;">
            <p class="cart-item-price"><?= peso($item['subtotal']) ?></p>
            <p style="font-size:0.76rem;color:var(--gray-400);margin-top:2px;"><?= peso($item['price_per_night']) ?> × <?= $item['nights'] ?>n</p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Summary -->
    <div>
      <div class="summary-card">
        <h2 class="summary-title">Order Summary</h2>
        <?php foreach ($cartItems as $item): ?>
        <div class="summary-row">
          <span style="max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($item['room_name']) ?></span>
          <span style="font-weight:600;"><?= peso($item['subtotal']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="summary-row summary-total">
          <span>Total</span>
          <span><?= peso($grandTotal) ?></span>
        </div>
        <p style="font-size:0.78rem;color:var(--gray-400);margin-top:4px;">Payment collected upon check-in</p>

        <form method="POST" style="margin-top:18px;">
          <button type="submit" name="checkout" class="btn btn-primary btn-full" style="font-size:1rem;padding:13px;" onclick="return confirm('Confirm all bookings?')">
            ✔ Confirm Booking
          </button>
        </form>

        <div class="trust-items" style="margin-top:16px;">
          <div class="trust-item">✔ <span>Free cancellation before 48 hours</span></div>
          <div class="trust-item">✔ <span>No upfront payment needed</span></div>
          <div class="trust-item">✔ <span>Secure reservation</span></div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
