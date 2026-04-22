<?php
$pageTitle = 'Confirm Booking';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db       = getDB();
$guest_id = (int)$_SESSION['guest_id'];

// ── Fetch cart items ──
$cartItems = $db->query("
    SELECT c.*, f.facility_name, f.category, f.max_capacity, f.facility_id AS fac_id
    FROM carts c
    JOIN facilities f ON c.facility_id = f.facility_id
    WHERE c.guest_id = $guest_id
    ORDER BY c.added_at ASC
")->fetch_all(MYSQLI_ASSOC);

if (empty($cartItems)) {
    setFlash('error', 'Your cart is empty.');
    redirect(SITE_URL . '/guest/pages/cart.php');
}

foreach ($cartItems as &$item) {
    $cid = (int)$item['cart_id'];
    $item['addons']      = $db->query("
        SELECT ca.quantity, ca.subtotal, ca.addon_id, a.addon_name, a.addon_price
        FROM cart_addons ca JOIN addons a ON ca.addon_id = a.addon_id
        WHERE ca.cart_id = $cid
    ")->fetch_all(MYSQLI_ASSOC);
    $item['addon_total'] = array_sum(array_column($item['addons'], 'subtotal'));
    $item['grand_total'] = (float)$item['subtotal'] + $item['addon_total'];
}
unset($item);

$cartTotal = array_sum(array_column($cartItems, 'grand_total'));

// ── Handle Confirm ──
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_method = $_POST['payment_method'] ?? '';

    if (!in_array($payment_method, ['cash','gcash'])) {
        $errors[] = 'Please select a payment method.';
    }

    // ── Hard facility conflict check ──
    if (empty($errors)) {
        foreach ($cartItems as $item) {
            $fid      = (int)$item['fac_id'];
            $cat      = strtolower(trim($item['category'] ?? ''));
            $isPool   = str_contains($cat, 'pool');
            $maxCap   = (int)$item['max_capacity'];
            $ciDT     = $item['checkin_date']  . ' ' . ($item['checkin_time']  ?? '00:00:00');
            $coDT     = $item['checkout_date'] . ' ' . ($item['checkout_time'] ?? '23:59:59');
            $fname    = e($item['facility_name']);

            if ($isPool && $maxCap > 0) {
                $numGuests = (int)$item['adults_count'] + (int)$item['kids_count'];
                $bRes = $db->query("
                    SELECT COALESCE(SUM(num_guests),0) AS booked FROM reservations
                    WHERE facility_id=$fid AND status IN('pending','approved')
                      AND CONCAT(checkin_date,' ',COALESCE(checkin_time,'00:00:00'))  < '$coDT'
                      AND CONCAT(checkout_date,' ',COALESCE(checkout_time,'23:59:59')) > '$ciDT'
                ")->fetch_assoc();
                $booked    = (int)($bRes['booked'] ?? 0);
                $remaining = $maxCap - $booked;
                if ($booked + $numGuests > $maxCap) {
                    $errors[] = "\"$fname\": Pool capacity exceeded. Only $remaining guest slot(s) remaining. Please go back and adjust your booking.";
                }
            } else {
                $bRes = $db->query("
                    SELECT COUNT(*) AS cnt FROM reservations
                    WHERE facility_id=$fid AND status IN('pending','approved')
                      AND CONCAT(checkin_date,' ',COALESCE(checkin_time,'00:00:00'))  < '$coDT'
                      AND CONCAT(checkout_date,' ',COALESCE(checkout_time,'23:59:59')) > '$ciDT'
                ")->fetch_assoc();
                if ((int)($bRes['cnt'] ?? 0) > 0) {
                    $errors[] = "\"$fname\" is already reserved for your selected dates. Please go back and choose different dates.";
                }
            }
        }
    }

    // ── Hard addon availability check ──
    if (empty($errors)) {
        foreach ($cartItems as $item) {
            if (empty($item['addons'])) continue;
            $ciDT  = $item['checkin_date']  . ' ' . ($item['checkin_time']  ?? '00:00:00');
            $coDT  = $item['checkout_date'] . ' ' . ($item['checkout_time'] ?? '23:59:59');
            $fname = e($item['facility_name']);

            foreach ($item['addons'] as $addon) {
                $aid   = (int)$addon['addon_id'];
                $qty   = (int)$addon['quantity'];
                $aRow  = $db->query("SELECT addon_name, limit_per_reservation FROM addons WHERE addon_id=$aid")->fetch_assoc();
                if (!$aRow) continue;
                $limit = (int)($aRow['limit_per_reservation'] ?? 0);
                if ($limit === 0) continue;

                $bRes = $db->query("
                    SELECT COALESCE(SUM(ra.quantity),0) AS booked
                    FROM reservation_addons ra JOIN reservations r ON ra.reservation_id=r.reservation_id
                    WHERE ra.addon_id=$aid AND r.status IN('pending','approved')
                      AND CONCAT(r.checkin_date,' ',COALESCE(r.checkin_time,'00:00:00'))  < '$coDT'
                      AND CONCAT(r.checkout_date,' ',COALESCE(r.checkout_time,'23:59:59')) > '$ciDT'
                ")->fetch_assoc();
                $booked    = (int)($bRes['booked'] ?? 0);
                $remaining = $limit - $booked;
                if ($qty > $remaining) {
                    $aname    = e($aRow['addon_name']);
                    $errors[] = "\"$aname\" for $fname: only $remaining slot(s) available but you requested $qty. Please go back and adjust.";
                }
            }
        }
    }

    // ── All checks passed: insert reservations ──
    if (empty($errors)) {
        $today = date('Y-m-d');
        $now   = date('H:i:s');

        foreach ($cartItems as $item) {
            $num_guests    = (int)$item['adults_count'] + (int)$item['kids_count'];
            $total_amount  = (float)$item['grand_total'];
            $exceed_fee    = (float)$item['exceed_fee'];
            $facility_id   = (int)$item['fac_id'];
            $checkin_time  = $item['checkin_time']  ?? null;
            $checkout_time = $item['checkout_time'] ?? null;

            $rStmt = $db->prepare("
                INSERT INTO reservations
                  (guest_id, facility_id, num_guests, checkin_date, checkout_date,
                   checkin_time, checkout_time, rate_type, total_amount, exceed_fee,
                   reserved_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $rStmt->bind_param(
                'iiisssssdds',
                $guest_id, $facility_id, $num_guests,
                $item['checkin_date'], $item['checkout_date'],
                $checkin_time, $checkout_time,
                $item['rate_type'], $total_amount, $exceed_fee, $today
            );
            $rStmt->execute();
            $reservation_id = $db->insert_id;

            // Insert reservation_addons
            if (!empty($item['addons'])) {
                $raStmt = $db->prepare("
                    INSERT INTO reservation_addons (reservation_id, addon_id, quantity, subtotal)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($item['addons'] as $addon) {
                    $aid = (int)$addon['addon_id'];
                    $qty = (int)$addon['quantity'];
                    $sub = (float)$addon['subtotal'];
                    $raStmt->bind_param('iiid', $reservation_id, $aid, $qty, $sub);
                    $raStmt->execute();
                }
            }

            // Insert payment record
            $pStmt = $db->prepare("
                INSERT INTO payment_records
                  (guest_id, reservation_id, total_amount, status, payment_method, payment_date, payment_time)
                VALUES (?, ?, ?, 'pending', ?, ?, ?)
            ");
            $pStmt->bind_param('iidsss', $guest_id, $reservation_id, $total_amount, $payment_method, $today, $now);
            $pStmt->execute();
        }

        // Clear cart
        $db->query("DELETE FROM cart_addons WHERE cart_id IN (SELECT cart_id FROM carts WHERE guest_id = $guest_id)");
        $db->query("DELETE FROM carts WHERE guest_id = $guest_id");

        setFlash('success', '🎉 Booking confirmed! Your reservation is pending approval.');
        redirect(SITE_URL . '/guest/pages/my_bookings.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.payment-wrap{max-width:780px;margin:40px auto 60px;padding:0 16px;}
.payment-card{background:#fff;border-radius:var(--radius);border:2px solid var(--green-100);overflow:hidden;margin-bottom:16px;}
.payment-card-head{background:var(--green-50);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--green-100);}
.payment-method-option{border:2px solid var(--gray-200);border-radius:var(--radius);padding:18px 20px;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:14px;margin-bottom:12px;background:#fff;position:relative;}
.payment-method-option:hover{border-color:var(--green-100);}
.payment-method-option.selected{border-color:var(--green);background:var(--green-50);}
.payment-method-option.disabled{opacity:0.55;cursor:not-allowed;background:var(--gray-50);}
.pay-radio{width:20px;height:20px;border-radius:50%;border:2px solid var(--gray-300);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition);}
.pay-radio.checked{border-color:var(--green);background:var(--green);}
.pay-radio.checked::after{content:'';width:8px;height:8px;border-radius:50%;background:#fff;}
.pay-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.coming-soon-pill{position:absolute;right:18px;top:50%;transform:translateY(-50%);background:var(--gray-200);color:var(--gray-500);font-size:0.68rem;font-weight:700;padding:3px 10px;border-radius:var(--radius-full);text-transform:uppercase;letter-spacing:0.06em;}
.summary-line{display:flex;justify-content:space-between;font-size:0.86rem;margin-bottom:6px;}
.summary-divider{border:none;border-top:1px solid var(--green-100);margin:10px 0;}
</style>

<div class="payment-wrap">

  <div style="margin-bottom:28px;">
    <a href="<?= SITE_URL ?>/guest/pages/cart.php" style="font-size:0.84rem;color:var(--green-dark);text-decoration:none;">← Back to Cart</a>
    <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-top:8px;margin-bottom:4px;">Confirm Booking</h1>
    <p style="color:var(--gray-400);">Review your booking and select a payment method.</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="flash flash-error" style="margin-bottom:20px;border-radius:var(--radius);">
    <div>
      <p style="font-weight:700;margin-bottom:6px;">Unable to confirm booking:</p>
      <?php foreach ($errors as $err): ?><div style="margin-bottom:4px;">• <?= $err ?></div><?php endforeach; ?>
      <p style="margin-top:10px;font-size:0.84rem;">
        <a href="<?= SITE_URL ?>/guest/pages/cart.php" style="color:#991b1b;font-weight:700;text-decoration:underline;">← Go back to cart</a>
      </p>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" id="payForm">
    <input type="hidden" name="confirm_payment" value="1"/>

    <h2 style="font-size:1rem;font-weight:700;color:var(--gray-800);margin-bottom:12px;">📋 Booking Summary</h2>

    <?php foreach ($cartItems as $item): ?>
    <div class="payment-card">
      <div class="payment-card-head">
        <div>
          <p style="font-weight:700;font-size:0.95rem;color:var(--gray-800);"><?= e($item['facility_name']) ?></p>
          <span style="font-size:0.75rem;padding:3px 10px;border-radius:var(--radius-full);font-weight:700;background:<?= $item['rate_type']==='daytime'?'#fef9c3':'#dbeafe' ?>;color:<?= $item['rate_type']==='daytime'?'#854d0e':'#1e40af' ?>;">
            <?= $item['rate_type']==='daytime'?'☀️ Daytime':'🌙 Overnight' ?>
          </span>
        </div>
        <span style="font-size:1.1rem;font-weight:700;color:var(--green-dark);"><?= peso($item['grand_total']) ?></span>
      </div>
      <div style="padding:16px 20px;">
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
          <div style="background:var(--green-50);border-radius:var(--radius-sm);padding:8px 14px;min-width:130px;">
            <p style="font-size:0.68rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px;">Check-in</p>
            <p style="font-weight:700;color:var(--gray-800);font-size:0.88rem;"><?= date('D, M d Y', strtotime($item['checkin_date'])) ?></p>
            <?php if (!empty($item['checkin_time'])): ?><p style="font-size:0.78rem;color:var(--green-dark);"><?= date('g:i A', strtotime($item['checkin_time'])) ?></p><?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;color:var(--gray-300);">→</div>
          <div style="background:var(--green-50);border-radius:var(--radius-sm);padding:8px 14px;min-width:130px;">
            <p style="font-size:0.68rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px;">Check-out</p>
            <p style="font-weight:700;color:var(--gray-800);font-size:0.88rem;"><?= date('D, M d Y', strtotime($item['checkout_date'])) ?></p>
            <?php if (!empty($item['checkout_time'])): ?><p style="font-size:0.78rem;color:var(--green-dark);"><?= date('g:i A', strtotime($item['checkout_time'])) ?></p><?php endif; ?>
          </div>
        </div>
        <p style="font-size:0.84rem;color:var(--gray-500);margin-bottom:10px;">
          👤 <?= (int)$item['adults_count'] ?> Adult<?= $item['adults_count']!=1?'s':'' ?>
          <?php if ($item['kids_count']>0): ?>&nbsp;·&nbsp; 🧒 <?= (int)$item['kids_count'] ?> Kid<?= $item['kids_count']!=1?'s':'' ?><?php endif; ?>
        </p>
        <div class="summary-line">
          <span style="color:var(--gray-500);">Base rate</span>
          <span style="font-weight:600;"><?= peso((float)$item['subtotal'] - (float)$item['exceed_fee']) ?></span>
        </div>
        <?php if ((float)$item['exceed_fee'] > 0): ?>
        <div class="summary-line">
          <span style="color:var(--gray-500);">Excess guest fee</span>
          <span style="font-weight:600;color:var(--red);">+<?= peso($item['exceed_fee']) ?></span>
        </div>
        <?php endif; ?>
        <?php foreach ($item['addons'] as $addon): ?>
        <div class="summary-line">
          <span style="color:var(--gray-500);">✨ <?= e($addon['addon_name']) ?> ×<?= (int)$addon['quantity'] ?></span>
          <span style="font-weight:600;"><?= peso($addon['subtotal']) ?></span>
        </div>
        <?php endforeach; ?>
        <hr class="summary-divider"/>
        <div class="summary-line">
          <span style="font-weight:700;color:var(--gray-800);">Subtotal</span>
          <span style="font-weight:700;color:var(--green-dark);"><?= peso($item['grand_total']) ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Grand Total -->
    <div style="background:var(--green-dark);color:#fff;border-radius:var(--radius);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;">
      <span style="font-weight:700;font-size:1rem;">Grand Total</span>
      <span style="font-weight:700;font-size:1.4rem;"><?= peso($cartTotal) ?></span>
    </div>

    <h2 style="font-size:1rem;font-weight:700;color:var(--gray-800);margin-bottom:12px;">💳 Payment Method</h2>

    <div class="payment-method-option" id="optCash" onclick="selectPayment('cash')">
      <input type="radio" name="payment_method" value="cash" id="radioCash" style="display:none;"/>
      <div class="pay-radio" id="radioCashDot"></div>
      <div class="pay-icon" style="background:#dcfce7;">🏖️</div>
      <div style="flex:1;">
        <p style="font-weight:700;font-size:0.95rem;color:var(--gray-800);margin-bottom:2px;">Pay at the Resort</p>
        <p style="font-size:0.82rem;color:var(--gray-400);">Pay in cash upon arrival. No upfront payment needed.</p>
      </div>
    </div>

    <div class="payment-method-option disabled" title="GCash payment coming soon">
      <div class="pay-radio"></div>
      <div class="pay-icon" style="background:#ede9fe;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/GCash_logo.svg/320px-GCash_logo.svg.png"
             alt="GCash" style="width:28px;height:28px;object-fit:contain;"
             onerror="this.replaceWith(document.createTextNode('G'))"/>
      </div>
      <div style="flex:1;">
        <p style="font-weight:700;font-size:0.95rem;color:var(--gray-800);margin-bottom:2px;">Pay via GCash</p>
        <p style="font-size:0.82rem;color:var(--gray-400);">Send payment to our GCash number before check-in.</p>
      </div>
      <span class="coming-soon-pill">Coming Soon</span>
    </div>

    <p id="payMethodErr" style="color:var(--red);font-size:0.82rem;margin-top:-4px;margin-bottom:16px;display:none;">Please select a payment method.</p>

    <button type="submit" class="btn btn-primary btn-full"
            style="font-size:1.05rem;padding:15px;margin-top:8px;"
            onclick="return validatePay()">
      ✅ Confirm Booking — <?= peso($cartTotal) ?>
    </button>

    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
      <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> Reservation will be <strong>pending</strong> until approved by the resort.</div>
      <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> Free cancellation before 48 hours of check-in.</div>
    </div>
  </form>
</div>

<script>
let selectedMethod = null;
function selectPayment(method) {
  selectedMethod = method;
  document.getElementById('radioCash').checked = (method==='cash');
  document.getElementById('optCash').classList.toggle('selected', method==='cash');
  document.getElementById('radioCashDot').classList.toggle('checked', method==='cash');
  document.getElementById('payMethodErr').style.display = 'none';
}
function validatePay() {
  if (!selectedMethod) {
    document.getElementById('payMethodErr').style.display = 'block';
    document.getElementById('payMethodErr').scrollIntoView({behavior:'smooth',block:'center'});
    return false;
  }
  return true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>