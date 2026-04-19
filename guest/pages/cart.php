<?php
$pageTitle = 'My Cart';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db       = getDB();
$guest_id = (int)$_SESSION['guest_id'];

// ── Handle Remove ──
if (isset($_GET['delete'])) {
    $cid  = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM carts WHERE cart_id = ? AND guest_id = ?");
    $stmt->bind_param('ii', $cid, $guest_id);
    $stmt->execute();
    setFlash('success', 'Item removed from cart.');
    redirect(SITE_URL . '/guest/pages/cart.php');
}

// ── Handle Clear All ──
if (isset($_GET['clear'])) {
    $db->query("DELETE FROM cart_addons WHERE cart_id IN (SELECT cart_id FROM carts WHERE guest_id = $guest_id)");
    $db->query("DELETE FROM carts WHERE guest_id = $guest_id");
    setFlash('success', 'Cart cleared.');
    redirect(SITE_URL . '/guest/pages/cart.php');
}

// ── Handle Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $cid          = (int)$_POST['cart_id'];
    $checkin_date = trim($_POST['checkin_date'] ?? '');
    $ci_h         = (int)($_POST['ci_hour']   ?? 14);
    $ci_m         = (int)($_POST['ci_minute'] ?? 0);
    $ci_ampm      = $_POST['ci_ampm'] ?? 'PM';
    $co_h         = (int)($_POST['co_hour']   ?? 12);
    $co_m         = (int)($_POST['co_minute'] ?? 0);
    $co_ampm      = $_POST['co_ampm'] ?? 'PM';
    $kids_count   = max(0, (int)($_POST['kids_count']   ?? 0));
    $adults_count = max(0, (int)($_POST['adults_count'] ?? 0));
    $addon_ids    = $_POST['addon_ids']  ?? [];
    $addon_qtys   = $_POST['addon_qtys'] ?? [];

    $ci_h24 = $ci_ampm === 'PM' ? ($ci_h < 12 ? $ci_h + 12 : $ci_h) : ($ci_h === 12 ? 0 : $ci_h);
    $co_h24 = $co_ampm === 'PM' ? ($co_h < 12 ? $co_h + 12 : $co_h) : ($co_h === 12 ? 0 : $co_h);

    $checkin_time_full  = sprintf('%02d:%02d:00', $ci_h24, $ci_m);
    $checkout_time_full = sprintf('%02d:%02d:00', $co_h24, $co_m);
    $ciMin = $ci_h24 * 60 + $ci_m;
    $coMin = $co_h24 * 60 + $co_m;

    if ($coMin > $ciMin && $coMin <= 18 * 60) {
        $rate_type     = 'daytime';
        $checkout_date = $checkin_date;
    } else {
        $rate_type     = 'overnight';
        $checkout_date = date('Y-m-d', strtotime($checkin_date . ' +1 day'));
    }

    // Get facility info
    $fRow = $db->query("
        SELECT f.facility_id, f.max_capacity
        FROM facilities f
        JOIN carts c ON f.facility_id = c.facility_id
        WHERE c.cart_id = $cid
    ")->fetch_assoc();
    $fid    = (int)($fRow['facility_id'] ?? 0);
    $maxCap = (int)($fRow['max_capacity'] ?? 0);

    $pRows = $db->query("SELECT * FROM pricing WHERE facility_id = $fid")->fetch_all(MYSQLI_ASSOC);
    $pMap  = [];
    foreach ($pRows as $p) {
        $pMap[$p['rate_type']][$p['guest_type']] = [
            'base_price'  => (float)$p['base_price'],
            'exceed_rate' => (float)$p['exceed_rate'],
        ];
    }
    $rateData  = $pMap[$rate_type] ?? [];
    $firstRate = reset($rateData);
    $basePrice = $firstRate ? $firstRate['base_price'] : 0;
    $exceedFee = 0;
    $total     = $kids_count + $adults_count;
    if ($maxCap > 0 && $total > $maxCap) {
        $excess    = $total - $maxCap;
        $excRate   = $rateData['adults']['exceed_rate'] ?? ($rateData['general']['exceed_rate'] ?? 0);
        $exceedFee = $excess * $excRate;
    }
    $subtotal = $basePrice + $exceedFee;

    // ── Server-side addon availability check (exclude own reservation if linked) ──
    $updateErrors = [];
    if (!empty($addon_ids)) {
        $ciDT = $checkin_date  . ' ' . $checkin_time_full;
        $coDT = $checkout_date . ' ' . $checkout_time_full;

        // Find reservation_id linked to this cart item if any
        $ownResRow   = $db->query("SELECT reservation_id FROM reservations WHERE guest_id = $guest_id ORDER BY reservation_id DESC LIMIT 1")->fetch_assoc();
        $excludeClause = ''; // carts are not yet confirmed so nothing to exclude

        foreach ($addon_ids as $raw_aid) {
            $aid = (int)$raw_aid;
            $qty = max(1, (int)($addon_qtys[$aid] ?? 1));

            $aRow = $db->query("SELECT addon_name, limit_per_reservation FROM addons WHERE addon_id = $aid")->fetch_assoc();
            if (!$aRow || !(int)$aRow['limit_per_reservation']) continue;

            $limit = (int)$aRow['limit_per_reservation'];
            $bRes  = $db->query("
                SELECT COALESCE(SUM(ra.quantity), 0) AS booked
                FROM reservation_addons ra
                JOIN reservations r ON ra.reservation_id = r.reservation_id
                WHERE ra.addon_id = $aid
                  AND r.status IN ('pending','approved')
                  AND CONCAT(r.checkin_date,  ' ', COALESCE(r.checkin_time,  '00:00:00')) < '$coDT'
                  AND CONCAT(r.checkout_date, ' ', COALESCE(r.checkout_time, '23:59:59')) > '$ciDT'
            ")->fetch_assoc();

            $booked    = (int)($bRes['booked'] ?? 0);
            $remaining = $limit - $booked;

            if ($qty > $remaining) {
                $name          = e($aRow['addon_name']);
                $updateErrors[] = "Only $remaining slot(s) of \"$name\" available. Reduced your selection.";
                $addon_qtys[$aid] = max(0, $remaining);
                if ($remaining <= 0) {
                    $addon_ids = array_filter($addon_ids, fn($x) => (int)$x !== $aid);
                }
            }
        }
    }

    $stmt = $db->prepare("
        UPDATE carts
        SET checkin_date=?, checkout_date=?, checkin_time=?, checkout_time=?,
            kids_count=?, adults_count=?, rate_type=?, subtotal=?, exceed_fee=?
        WHERE cart_id=? AND guest_id=?
    ");
    $stmt->bind_param(
        'ssssiisddii',
        $checkin_date, $checkout_date, $checkin_time_full, $checkout_time_full,
        $kids_count, $adults_count, $rate_type, $subtotal, $exceedFee,
        $cid, $guest_id
    );

    if ($stmt->execute()) {
        $db->query("DELETE FROM cart_addons WHERE cart_id = $cid");
        foreach ($addon_ids as $raw_aid) {
            $aid = (int)$raw_aid;
            $qty = max(1, (int)($addon_qtys[$aid] ?? 1));
            if ($qty <= 0) continue;
            $aStmt = $db->prepare("SELECT addon_price FROM addons WHERE addon_id = ?");
            $aStmt->bind_param('i', $aid);
            $aStmt->execute();
            $aRow = $aStmt->get_result()->fetch_assoc();
            if ($aRow) {
                $asub   = $aRow['addon_price'] * $qty;
                $caStmt = $db->prepare("INSERT INTO cart_addons (cart_id, addon_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
                $caStmt->bind_param('iiid', $cid, $aid, $qty, $asub);
                $caStmt->execute();
            }
        }
        $msg = !empty($updateErrors) ? 'Cart updated. Note: ' . implode(' ', $updateErrors) : 'Cart item updated!';
        setFlash(!empty($updateErrors) ? 'error' : 'success', $msg);
    } else {
        setFlash('error', 'Could not update item.');
    }
    redirect(SITE_URL . '/guest/pages/cart.php');
}

// ── Fetch all addons ──
$allAddons = $db->query("SELECT * FROM addons ORDER BY addon_name ASC")->fetch_all(MYSQLI_ASSOC);

// ── Fetch Cart Items ──
$cartItems = $db->query("
    SELECT c.*, f.facility_name, f.category, f.photo, f.max_capacity, f.facility_id AS fac_id
    FROM carts c
    JOIN facilities f ON c.facility_id = f.facility_id
    WHERE c.guest_id = $guest_id
    ORDER BY c.added_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pricingByFacility = [];
foreach ($cartItems as &$item) {
    $cid = (int)$item['cart_id'];
    $fid = (int)$item['fac_id'];
    $item['addons']      = $db->query("
        SELECT ca.quantity, ca.subtotal, ca.addon_id, a.addon_name, a.addon_price
        FROM cart_addons ca
        JOIN addons a ON ca.addon_id = a.addon_id
        WHERE ca.cart_id = $cid
    ")->fetch_all(MYSQLI_ASSOC);
    $item['addon_total'] = array_sum(array_column($item['addons'], 'subtotal'));
    $item['grand_total'] = (float)$item['subtotal'] + $item['addon_total'];

    if (!isset($pricingByFacility[$fid])) {
        $pRows = $db->query("SELECT * FROM pricing WHERE facility_id = $fid")->fetch_all(MYSQLI_ASSOC);
        $pMap  = [];
        foreach ($pRows as $p) {
            $pMap[$p['rate_type']][$p['guest_type']] = [
                'base_price'  => (float)$p['base_price'],
                'exceed_rate' => (float)$p['exceed_rate'],
            ];
        }
        $pricingByFacility[$fid] = $pMap;
    }
    $item['pricingMap'] = $pricingByFacility[$fid];
}
unset($item);

$cartTotal = array_sum(array_column($cartItems, 'grand_total'));

function catIcon($cat) {
    $map = ['pool'=>'fa-solid fa-person-swimming','beach'=>'fa-solid fa-umbrella-beach',
            'room'=>'fa-solid fa-bed','family room'=>'fa-solid fa-people-roof',
            'cottage'=>'fa-solid fa-house-chimney','accommodation'=>'fa-solid fa-bed',
            'dining'=>'fa-solid fa-utensils','spa'=>'fa-solid fa-spa',
            'sports'=>'fa-solid fa-person-running','event'=>'fa-solid fa-calendar-days',
            'activity'=>'fa-solid fa-bullseye','resort'=>'fa-solid fa-hotel'];
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $k => $v) if (str_contains($c, $k)) return "<i class=\"$v\"></i>";
    return '<i class="fa-solid fa-star"></i>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Time Picker */
.time-picker-wrap{display:flex;align-items:center;border:2px solid var(--gray-200);border-radius:var(--radius);overflow:hidden;background:#fff;transition:var(--transition);}
.time-picker-wrap:focus-within{border-color:var(--green);}
.tp-col{display:flex;flex-direction:column;align-items:center;border-right:1px solid var(--gray-200);}
.tp-col:last-child{border-right:none;}
.tp-btn{width:100%;background:var(--green-50);border:none;color:var(--green-dark);font-size:0.85rem;font-weight:700;cursor:pointer;padding:4px 12px;line-height:1;transition:var(--transition);}
.tp-btn:hover{background:var(--green-100);}
.tp-val{padding:5px 12px;font-size:0.95rem;font-weight:700;color:var(--gray-800);min-width:44px;text-align:center;border-top:1px solid var(--gray-200);border-bottom:1px solid var(--gray-200);background:#fff;user-select:none;}
.tp-ampm-btn{padding:7px 11px;font-size:0.82rem;font-weight:700;background:none;border:none;cursor:pointer;color:var(--gray-400);transition:var(--transition);display:block;width:100%;text-align:center;}
.tp-ampm-btn.active{color:var(--green-dark);background:var(--green-50);}
input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0;}
input[type=number]{-moz-appearance:textfield;}

/* Edit Panel */
.cart-edit-panel{padding:24px;background:#fafffe;border-top:3px solid var(--green);}
.edit-sec{font-size:0.74rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:8px;margin-top:16px;}
.edit-sec:first-of-type{margin-top:0;}

/* Guest Stepper */
.guest-stepper{display:flex;align-items:center;border:2px solid var(--gray-200);border-radius:var(--radius);overflow:hidden;}
.guest-stepper button{background:var(--green-50);border:none;color:var(--green-dark);font-size:1.1rem;font-weight:700;cursor:pointer;padding:6px 14px;transition:var(--transition);}
.guest-stepper button:hover{background:var(--green-100);}
.guest-stepper input{border:none;border-left:1px solid var(--gray-200);border-right:1px solid var(--gray-200);text-align:center;font-weight:700;font-size:0.95rem;width:48px;padding:6px 0;outline:none;background:#fff;}

/* Addon cards in edit mode */
.ea-label{display:block;border:2px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;transition:var(--transition);overflow:hidden;}
.ea-label:hover:not(.ea-unavailable){border-color:var(--green-100);}
.ea-label.active{border-color:var(--green);background:var(--green-50);}
.ea-label.ea-unavailable{opacity:0.5;cursor:not-allowed;background:var(--gray-100);}
.ea-label.ea-unavailable *{pointer-events:none;}
.ea-qty-row{display:flex;align-items:center;gap:10px;padding:7px 12px;border-top:1px solid var(--green-100);background:#f0fdf4;}
.ea-stepper{display:flex;align-items:center;border:1px solid var(--green-200);border-radius:6px;overflow:hidden;background:#fff;}
.ea-stepper button{background:var(--green-50);border:none;color:var(--green-dark);font-weight:700;cursor:pointer;padding:2px 9px;font-size:1rem;}
.ea-stepper button:hover{background:var(--green-100);}
.ea-stepper input{border:none;border-left:1px solid var(--green-200);border-right:1px solid var(--green-200);text-align:center;font-weight:700;width:34px;padding:2px 0;font-size:0.82rem;outline:none;}

/* Availability badges in edit mode */
.ea-avail-badge{font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:var(--radius-full);white-space:nowrap;}
.ea-avail-ok  {background:var(--green-light);color:var(--green-dark);}
.ea-avail-low {background:var(--yellow-light);color:var(--yellow-dark);}
.ea-avail-none{background:var(--red-light);color:#991b1b;}
.ea-avail-unlim{background:var(--gray-100);color:var(--gray-500);}

/* Price preview */
.edit-price-preview{background:var(--green-50);border-radius:var(--radius);padding:14px 16px;margin-top:16px;}
</style>

<div class="container page-wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:32px;">
    <div>
      <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-bottom:4px;">My Cart</h1>
      <p style="color:var(--gray-400);"><?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?> in your cart</p>
    </div>
    <?php if (!empty($cartItems)): ?>
      <a href="?clear=1" onclick="return confirm('Clear all cart items?')" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red);background:#fff;">🗑 Clear All</a>
    <?php endif; ?>
  </div>

  <?php if (empty($cartItems)): ?>
    <div class="empty-state">
      <div class="empty-icon">🛒</div>
      <p class="empty-title">Your cart is empty</p>
      <p class="empty-desc">Browse our facilities and add a booking to get started.</p>
      <a href="<?= SITE_URL ?>/guest/index.php#facilities" class="btn btn-primary">Explore Facilities</a>
    </div>
  <?php else: ?>
  <div class="two-col">

    <!-- Cart Items -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($cartItems as $item):
        $cid    = $item['cart_id'];
        $fid    = $item['fac_id'];
        $pMap   = $item['pricingMap'];
        $maxCap = (int)$item['max_capacity'];
        $imgSrc = !empty($item['photo'])
            ? SITE_URL . '/guest/pages/facility_photo.php?id=' . $fid
            : 'https://placehold.co/300x200/d1fae5/065f46?text=' . urlencode($item['facility_name']) . '&font=quicksand';
        $currentAddonIds  = array_column($item['addons'], 'addon_id');
        $currentAddonQtys = array_column($item['addons'], 'quantity', 'addon_id');
      ?>
      <div class="card" style="padding:0;overflow:hidden;">

        <!-- VIEW MODE -->
        <div id="view_<?= $cid ?>">
          <div style="display:flex;flex-wrap:wrap;">
            <div style="width:180px;flex-shrink:0;overflow:hidden;min-height:160px;">
              <img src="<?= $imgSrc ?>" alt="<?= e($item['facility_name']) ?>"
                   style="width:100%;height:100%;object-fit:cover;"
                   onerror="this.src='https://placehold.co/300x200/d1fae5/065f46?text=<?= urlencode($item['facility_name']) ?>&font=quicksand'"/>
            </div>
            <div style="flex:1;padding:18px;min-width:0;">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                <div>
                  <h3 style="font-weight:700;font-size:1rem;color:var(--gray-800);margin-bottom:2px;"><?= e($item['facility_name']) ?></h3>
                  <?php if ($item['category']): ?>
                    <span style="font-size:0.72rem;color:var(--gray-400);"><?= catIcon($item['category']) ?> <?= e(ucfirst($item['category'])) ?></span>
                  <?php endif; ?>
                </div>
                <span style="padding:4px 12px;border-radius:var(--radius-full);font-size:0.75rem;font-weight:700;white-space:nowrap;background:<?= $item['rate_type']==='daytime'?'#fef9c3':'#dbeafe' ?>;color:<?= $item['rate_type']==='daytime'?'#854d0e':'#1e40af' ?>;">
                  <?= $item['rate_type']==='daytime'?'☀️ Daytime':'🌙 Overnight' ?>
                </span>
              </div>
              <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                <div style="background:var(--green-50);border-radius:var(--radius-sm);padding:8px 12px;font-size:0.82rem;">
                  <p style="color:var(--gray-400);font-size:0.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Check-in</p>
                  <p style="font-weight:700;color:var(--gray-800);"><?= date('M d, Y', strtotime($item['checkin_date'])) ?></p>
                  <p style="color:var(--green-dark);font-size:0.78rem;"><?= date('g:i A', strtotime($item['checkin_time'])) ?></p>
                </div>
                <div style="display:flex;align-items:center;color:var(--gray-300);">→</div>
                <div style="background:var(--green-50);border-radius:var(--radius-sm);padding:8px 12px;font-size:0.82rem;">
                  <p style="color:var(--gray-400);font-size:0.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Check-out</p>
                  <p style="font-weight:700;color:var(--gray-800);"><?= date('M d, Y', strtotime($item['checkout_date'])) ?></p>
                  <p style="color:var(--green-dark);font-size:0.78rem;"><?= date('g:i A', strtotime($item['checkout_time'])) ?></p>
                </div>
              </div>
              <p style="font-size:0.82rem;color:var(--gray-500);margin-bottom:8px;">
                👤 <?= (int)$item['adults_count'] ?> Adult<?= $item['adults_count']!=1?'s':'' ?>
                <?php if ($item['kids_count']>0): ?>&nbsp;·&nbsp; 🧒 <?= (int)$item['kids_count'] ?> Kid<?= $item['kids_count']!=1?'s':'' ?><?php endif; ?>
              </p>
              <?php if (!empty($item['addons'])): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                  <?php foreach ($item['addons'] as $addon): ?>
                    <span class="tag" style="font-size:0.72rem;">✨ <?= e($addon['addon_name']) ?> ×<?= (int)$addon['quantity'] ?> (<?= peso($addon['subtotal']) ?>)</span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--green-100);flex-wrap:wrap;gap:10px;">
                <div>
                  <span style="font-size:1.2rem;font-weight:700;color:var(--green-dark);"><?= peso($item['grand_total']) ?></span>
                  <span style="font-size:0.76rem;color:var(--gray-400);"> total</span>
                  <?php if ((float)$item['exceed_fee']>0): ?>
                    <p style="font-size:0.76rem;color:var(--red);margin-top:2px;">incl. <?= peso($item['exceed_fee']) ?> excess fee</p>
                  <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;">
                  <button onclick="toggleEdit(<?= $cid ?>)" class="btn btn-outline btn-sm">✏ Edit</button>
                  <a href="?delete=<?= $cid ?>" onclick="return confirm('Remove this item?')" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red);background:#fff;">🗑 Remove</a>
                </div>
              </div>
            </div>
          </div>
        </div><!-- /view -->

        <!-- EDIT MODE -->
        <div id="edit_<?= $cid ?>" style="display:none;">
          <form method="POST" id="eForm_<?= $cid ?>">
            <input type="hidden" name="update_cart" value="1"/>
            <input type="hidden" name="cart_id"     value="<?= $cid ?>"/>

            <div class="cart-edit-panel">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <h3 style="font-weight:700;color:var(--green-dark);font-size:1rem;">✏ Edit — <?= e($item['facility_name']) ?></h3>
                <button type="button" onclick="toggleEdit(<?= $cid ?>)" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:1.2rem;">✕</button>
              </div>

              <!-- Date -->
              <p class="edit-sec">📅 Check-in Date</p>
              <input type="date" name="checkin_date" id="eDate_<?= $cid ?>" class="form-control"
                     value="<?= e($item['checkin_date']) ?>" min="<?= date('Y-m-d') ?>"
                     onchange="eChange(<?= $cid ?>)"/>

              <!-- Times -->
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
                <div>
                  <p class="edit-sec" style="margin-top:0;">⏰ Check-in <small style="font-weight:400;color:var(--gray-400);">(min 2PM)</small></p>
                  <input type="hidden" name="ci_hour"   id="eci_h_<?= $cid ?>"  value="<?= date('g',strtotime($item['checkin_time'])) ?>"/>
                  <input type="hidden" name="ci_minute" id="eci_m_<?= $cid ?>"  value="<?= (int)date('i',strtotime($item['checkin_time'])) ?>"/>
                  <input type="hidden" name="ci_ampm"   id="eci_ap_<?= $cid ?>" value="<?= date('A',strtotime($item['checkin_time'])) ?>"/>
                  <div class="time-picker-wrap">
                    <div class="tp-col">
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'ci','h',1)">▲</button>
                      <div class="tp-val" id="eciH_<?= $cid ?>"><?= date('g',strtotime($item['checkin_time'])) ?></div>
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'ci','h',-1)">▼</button>
                    </div>
                    <div style="display:flex;align-items:center;padding:0 3px;font-weight:700;color:var(--gray-400);">:</div>
                    <div class="tp-col">
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'ci','m',1)">▲</button>
                      <div class="tp-val" id="eciM_<?= $cid ?>"><?= date('i',strtotime($item['checkin_time'])) ?></div>
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'ci','m',-1)">▼</button>
                    </div>
                    <div class="tp-col" style="border-left:1px solid var(--gray-200);">
                      <button type="button" class="tp-ampm-btn <?= date('A',strtotime($item['checkin_time']))==='AM'?'active':'' ?>" id="eciAM_<?= $cid ?>" onclick="eAmPm(<?= $cid ?>,'ci','AM')">AM</button>
                      <button type="button" class="tp-ampm-btn <?= date('A',strtotime($item['checkin_time']))==='PM'?'active':'' ?>" id="eciPM_<?= $cid ?>" onclick="eAmPm(<?= $cid ?>,'ci','PM')">PM</button>
                    </div>
                  </div>
                  <p id="eciErr_<?= $cid ?>" style="font-size:0.74rem;color:var(--red);margin-top:4px;"></p>
                </div>
                <div>
                  <p class="edit-sec" style="margin-top:0;">⏰ Check-out</p>
                  <input type="hidden" name="co_hour"   id="eco_h_<?= $cid ?>"  value="<?= date('g',strtotime($item['checkout_time'])) ?>"/>
                  <input type="hidden" name="co_minute" id="eco_m_<?= $cid ?>"  value="<?= (int)date('i',strtotime($item['checkout_time'])) ?>"/>
                  <input type="hidden" name="co_ampm"   id="eco_ap_<?= $cid ?>" value="<?= date('A',strtotime($item['checkout_time'])) ?>"/>
                  <div class="time-picker-wrap">
                    <div class="tp-col">
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'co','h',1)">▲</button>
                      <div class="tp-val" id="ecoH_<?= $cid ?>"><?= date('g',strtotime($item['checkout_time'])) ?></div>
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'co','h',-1)">▼</button>
                    </div>
                    <div style="display:flex;align-items:center;padding:0 3px;font-weight:700;color:var(--gray-400);">:</div>
                    <div class="tp-col">
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'co','m',1)">▲</button>
                      <div class="tp-val" id="ecoM_<?= $cid ?>"><?= date('i',strtotime($item['checkout_time'])) ?></div>
                      <button type="button" class="tp-btn" onclick="eStep(<?= $cid ?>,'co','m',-1)">▼</button>
                    </div>
                    <div class="tp-col" style="border-left:1px solid var(--gray-200);">
                      <button type="button" class="tp-ampm-btn <?= date('A',strtotime($item['checkout_time']))==='AM'?'active':'' ?>" id="ecoAM_<?= $cid ?>" onclick="eAmPm(<?= $cid ?>,'co','AM')">AM</button>
                      <button type="button" class="tp-ampm-btn <?= date('A',strtotime($item['checkout_time']))==='PM'?'active':'' ?>" id="ecoPM_<?= $cid ?>" onclick="eAmPm(<?= $cid ?>,'co','PM')">PM</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Auto checkout date + rate badge -->
              <div style="display:flex;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap;">
                <div style="flex:1;min-width:160px;">
                  <p style="font-size:0.74rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">Check-out Date (auto)</p>
                  <input type="text" id="eCoDate_<?= $cid ?>" class="form-control" readonly
                         style="background:var(--green-50);cursor:not-allowed;color:var(--gray-600);font-size:0.85rem;padding:8px 12px;"/>
                </div>
                <div>
                  <p style="font-size:0.74rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">Rate Type</p>
                  <span id="eRateBadge_<?= $cid ?>" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:var(--radius-full);font-size:0.82rem;font-weight:700;background:#dbeafe;color:#1e40af;">🌙 Overnight</span>
                </div>
              </div>

              <!-- Guests -->
              <p class="edit-sec">👤 Guests</p>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                  <p style="font-size:0.78rem;font-weight:600;color:var(--gray-500);margin-bottom:6px;">Adults</p>
                  <div class="guest-stepper">
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eAdults_<?= $cid ?>',-1)">−</button>
                    <input type="number" id="eAdults_<?= $cid ?>" name="adults_count" value="<?= (int)$item['adults_count'] ?>" min="0" onchange="eRecalc(<?= $cid ?>)"/>
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eAdults_<?= $cid ?>',1)">+</button>
                  </div>
                </div>
                <div>
                  <p style="font-size:0.78rem;font-weight:600;color:var(--gray-500);margin-bottom:6px;">Kids</p>
                  <div class="guest-stepper">
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eKids_<?= $cid ?>',-1)">−</button>
                    <input type="number" id="eKids_<?= $cid ?>" name="kids_count" value="<?= (int)$item['kids_count'] ?>" min="0" onchange="eRecalc(<?= $cid ?>)"/>
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eKids_<?= $cid ?>',1)">+</button>
                  </div>
                </div>
              </div>
              <?php if ($maxCap>0): ?>
                <p style="font-size:0.74rem;color:var(--gray-400);margin-top:6px;">Max capacity: <strong><?= $maxCap ?></strong>. Excess guests incur additional fees.</p>
              <?php endif; ?>

              <!-- Add-ons -->
              <?php if (!empty($allAddons)): ?>
              <p class="edit-sec">✨ Add-on Services
                <span id="eAvailStatus_<?= $cid ?>" style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--gray-400);margin-left:6px;font-size:0.72rem;"></span>
              </p>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <?php foreach ($allAddons as $addon):
                  $aid     = $addon['addon_id'];
                  $checked = in_array($aid, $currentAddonIds);
                  $qty     = $currentAddonQtys[$aid] ?? 1;
                  $price   = (float)$addon['addon_price'];
                  $limit   = (int)($addon['limit_per_reservation'] ?? 0);
                ?>
                <label class="ea-label <?= $checked?'active':'' ?>" id="eACard_<?= $cid ?>_<?= $aid ?>">
                  <input type="checkbox" name="addon_ids[]" value="<?= $aid ?>" style="display:none;"
                         <?= $checked?'checked':'' ?>
                         onchange="eToggleAddon(<?= $cid ?>,<?= $aid ?>,<?= $price ?>)"/>
                  <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;">
                    <div style="width:34px;height:34px;background:var(--green-50);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">✨</div>
                    <div style="flex:1;min-width:0;">
                      <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:2px;">
                        <p style="font-weight:700;font-size:0.82rem;color:var(--gray-800);"><?= e($addon['addon_name']) ?></p>
                        <span class="ea-avail-badge ea-avail-unlim" id="eAvailBadge_<?= $cid ?>_<?= $aid ?>">
                          <?= $limit ? "limit: $limit" : 'unlimited' ?>
                        </span>
                      </div>
                      <?php if (!empty($addon['addon_description'])): ?>
                        <p style="font-size:0.72rem;color:var(--gray-400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($addon['addon_description']) ?></p>
                      <?php endif; ?>
                      <p style="font-size:0.78rem;font-weight:700;color:var(--green-dark);"><?= peso($price) ?></p>
                    </div>
                    <div id="eACheck_<?= $cid ?>_<?= $aid ?>" style="width:20px;height:20px;border-radius:50%;border:2px solid <?= $checked?'var(--green)':'var(--gray-200)' ?>;background:<?= $checked?'var(--green)':'transparent' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <?php if ($checked): ?><span style="color:#fff;font-size:0.65rem;">✓</span><?php endif; ?>
                    </div>
                  </div>
                  <div id="eAQty_<?= $cid ?>_<?= $aid ?>" class="ea-qty-row" style="<?= $checked?'':'display:none;' ?>">
                    <span style="font-size:0.74rem;color:var(--gray-600);font-weight:600;">Qty:</span>
                    <div class="ea-stepper">
                      <button type="button" onclick="eAddonStep(<?= $cid ?>,<?= $aid ?>,<?= $price ?>,-1)">−</button>
                      <input type="number" name="addon_qtys[<?= $aid ?>]"
                             id="eAQtyI_<?= $cid ?>_<?= $aid ?>"
                             value="<?= $qty ?>" min="1" max="<?= $limit ?: 9999 ?>"
                             data-limit="<?= $limit ?>"
                             onchange="eAddonSub(<?= $cid ?>,<?= $aid ?>,<?= $price ?>)"/>
                      <button type="button" onclick="eAddonStep(<?= $cid ?>,<?= $aid ?>,<?= $price ?>,1)">+</button>
                    </div>
                    <span id="eASub_<?= $cid ?>_<?= $aid ?>" data-unit="<?= $price ?>" style="font-size:0.78rem;font-weight:700;color:var(--green-dark);"><?= peso($price*$qty) ?></span>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Price preview -->
              <div class="edit-price-preview">
                <p style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:8px;">Price Preview</p>
                <div style="display:flex;justify-content:space-between;font-size:0.84rem;margin-bottom:4px;">
                  <span style="color:var(--gray-600);">Base rate</span>
                  <span id="eBBase_<?= $cid ?>" style="font-weight:700;color:var(--gray-800);">₱0</span>
                </div>
                <div id="eBExcRow_<?= $cid ?>" style="display:none;justify-content:space-between;font-size:0.84rem;margin-bottom:4px;">
                  <span style="color:var(--gray-600);">Excess fee</span>
                  <span id="eBExc_<?= $cid ?>" style="font-weight:700;color:var(--red);">₱0</span>
                </div>
                <div id="eBAddRow_<?= $cid ?>" style="display:none;justify-content:space-between;font-size:0.84rem;margin-bottom:4px;">
                  <span style="color:var(--gray-600);">Add-ons</span>
                  <span id="eBAdd_<?= $cid ?>" style="font-weight:700;color:var(--gray-800);">₱0</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid var(--green-100);">
                  <span style="font-weight:700;color:var(--green-dark);">Estimated Total</span>
                  <span id="eBTotal_<?= $cid ?>" style="font-weight:700;font-size:1.05rem;color:var(--green-dark);">₱0</span>
                </div>
              </div>

              <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                <button type="button" onclick="toggleEdit(<?= $cid ?>)" class="btn btn-sm" style="border:2px solid var(--gray-200);color:var(--gray-600);">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">✔ Save Changes</button>
              </div>
            </div>
          </form>
        </div><!-- /edit -->

      </div>
      <?php endforeach; ?>
    </div>

    <!-- Order Summary -->
    <div>
      <div class="summary-card" style="position:sticky;top:80px;">
        <h2 class="summary-title">Order Summary</h2>
        <?php foreach ($cartItems as $item): ?>
        <div class="summary-row">
          <span style="max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.84rem;"><?= e($item['facility_name']) ?></span>
          <span style="font-weight:600;"><?= peso($item['grand_total']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="summary-row summary-total" style="margin-top:8px;">
          <span>Total</span>
          <span><?= peso($cartTotal) ?></span>
        </div>
        <p style="font-size:0.78rem;color:var(--gray-400);margin-top:4px;">Payment collected upon check-in</p>
        <a href="<?= SITE_URL ?>/guest/pages/payment_form.php"
           class="btn btn-primary btn-full"
           style="font-size:1rem;padding:13px;margin-top:18px;justify-content:center;text-decoration:none;display:flex;">
          ✔ Confirm Booking
        </a>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
          <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> No upfront payment needed</div>
          <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> Secure reservation</div>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<script>
const AVAIL_URL = '<?= SITE_URL ?>/guest/pages/addon_availability.php';

// Per-cart state
const eTP = {};
<?php foreach ($cartItems as $item):
  $cid  = $item['cart_id'];
  $ciH  = (int)date('g', strtotime($item['checkin_time']));
  $ciM  = (int)date('i', strtotime($item['checkin_time']));
  $ciAp = date('A', strtotime($item['checkin_time']));
  $coH  = (int)date('g', strtotime($item['checkout_time']));
  $coM  = (int)date('i', strtotime($item['checkout_time']));
  $coAp = date('A', strtotime($item['checkout_time']));
?>
eTP[<?= $cid ?>] = {
  ci:  { hour:<?= $ciH ?>, minute:<?= $ciM ?>, ampm:'<?= $ciAp ?>' },
  co:  { hour:<?= $coH ?>, minute:<?= $coM ?>, ampm:'<?= $coAp ?>' },
  pm:  <?= json_encode($item['pricingMap']) ?>,
  max: <?= (int)$item['max_capacity'] ?>,
  availTimer: null,
};
<?php endforeach; ?>

function ePad(n) { return String(n).padStart(2,'0'); }

function eToMin(cid, p) {
  const s = eTP[cid][p];
  const h24 = s.ampm==='PM' ? (s.hour===12?12:s.hour+12) : (s.hour===12?0:s.hour);
  return h24*60+s.minute;
}

function eTo24str(cid, p) {
  const s = eTP[cid][p];
  const h24 = s.ampm==='PM' ? (s.hour===12?12:s.hour+12) : (s.hour===12?0:s.hour);
  return ePad(h24)+':'+ePad(s.minute)+':00';
}

function eRender(cid, p) {
  const s = eTP[cid][p], pre = p==='ci'?'eci':'eco';
  document.getElementById(pre+'H_'+cid).textContent  = ePad(s.hour);
  document.getElementById(pre+'M_'+cid).textContent  = ePad(s.minute);
  document.getElementById(pre+'_h_'+cid).value       = s.hour;
  document.getElementById(pre+'_m_'+cid).value       = s.minute;
  document.getElementById(pre+'_ap_'+cid).value      = s.ampm;
  document.getElementById(pre+'AM_'+cid).classList.toggle('active', s.ampm==='AM');
  document.getElementById(pre+'PM_'+cid).classList.toggle('active', s.ampm==='PM');
  eChange(cid);
}

function eStep(cid, p, part, d) {
  const s = eTP[cid][p];
  if (part==='h') { s.hour+=d; if(s.hour>12)s.hour=1; if(s.hour<1)s.hour=12; }
  else { s.minute+=d*5; if(s.minute>=60)s.minute=0; if(s.minute<0)s.minute=55; }
  eRender(cid, p);
}

function eAmPm(cid, p, val) { eTP[cid][p].ampm=val; eRender(cid,p); }

function eRateType(cid) {
  const ci=eToMin(cid,'ci'), co=eToMin(cid,'co');
  return (co>ci && co<=18*60) ? 'daytime' : 'overnight';
}

function eFmtDate(s) {
  return new Date(s+'T12:00:00').toLocaleDateString('en-PH',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
}

function eAddDays(dateStr, n) {
  const d = new Date(dateStr+'T12:00:00'); d.setDate(d.getDate()+n);
  return d.toISOString().slice(0,10);
}

function eChange(cid) {
  const ci    = document.getElementById('eDate_'+cid)?.value||'';
  const rt    = eRateType(cid);
  const badge = document.getElementById('eRateBadge_'+cid);
  const coDateEl = document.getElementById('eCoDate_'+cid);
  const ciErr    = document.getElementById('eciErr_'+cid);
  const ciMin    = eToMin(cid,'ci');

  ciErr.textContent = ciMin<14*60 ? 'Check-in must be 2:00 PM or later.' : '';

  const coDate = rt==='daytime' ? ci : (ci ? eAddDays(ci,1) : '');
  coDateEl.value = !ci ? 'Select check-in date first' : (coDate ? eFmtDate(coDate) : '');

  badge.innerHTML        = rt==='daytime' ? '☀️ Daytime' : '🌙 Overnight';
  badge.style.background = rt==='daytime' ? '#fef9c3' : '#dbeafe';
  badge.style.color      = rt==='daytime' ? '#854d0e' : '#1e40af';

  eRecalc(cid);
  eScheduleAvail(cid);
}

// ── Availability for edit mode ──
function eScheduleAvail(cid) {
  clearTimeout(eTP[cid].availTimer);
  eTP[cid].availTimer = setTimeout(() => eFetchAvail(cid), 600);
}

function eFetchAvail(cid) {
  const ci = document.getElementById('eDate_'+cid)?.value;
  if (!ci) return;

  const rt     = eRateType(cid);
  const coDate = rt==='daytime' ? ci : eAddDays(ci,1);
  const ciTime = eTo24str(cid,'ci');
  const coTime = eTo24str(cid,'co');

  const statusEl = document.getElementById('eAvailStatus_'+cid);
  if (statusEl) statusEl.textContent = '(checking…)';

  // Mark all badges as loading
  document.querySelectorAll(`[id^="eAvailBadge_${cid}_"]`).forEach(b => {
    b.className = 'ea-avail-badge ea-avail-unlim'; b.textContent = '…';
  });

  const fd = new FormData();
  fd.append('checkin_date',  ci);
  fd.append('checkout_date', coDate);
  fd.append('checkin_time',  ciTime);
  fd.append('checkout_time', coTime);
  // No exclude_reservation_id since this is a cart item, not yet a reservation

  fetch(AVAIL_URL, { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => eApplyAvail(cid, data))
    .catch(() => {
      if (statusEl) statusEl.textContent = '';
    });
}

function eApplyAvail(cid, data) {
  const statusEl = document.getElementById('eAvailStatus_'+cid);
  if (statusEl) statusEl.textContent = '(updated)';

  for (const [aidStr, remaining] of Object.entries(data)) {
    const aid   = parseInt(aidStr);
    const card  = document.getElementById('eACard_'+cid+'_'+aid);
    const badge = document.getElementById('eAvailBadge_'+cid+'_'+aid);
    const cb    = card?.querySelector('input[type="checkbox"]');
    const qtyIn = document.getElementById('eAQtyI_'+cid+'_'+aid);
    if (!card || !badge) continue;

    if (remaining === null) {
      badge.className = 'ea-avail-badge ea-avail-unlim'; badge.textContent = 'unlimited';
      card.classList.remove('ea-unavailable');
      if (cb) cb.disabled = false;
    } else if (remaining === 0) {
      badge.className = 'ea-avail-badge ea-avail-none'; badge.textContent = 'fully booked';
      card.classList.add('ea-unavailable'); card.classList.remove('active');
      if (cb) { cb.checked = false; cb.disabled = true; }
      document.getElementById('eAQty_'+cid+'_'+aid).style.display = 'none';
      document.getElementById('eACheck_'+cid+'_'+aid).style.background = '';
      document.getElementById('eACheck_'+cid+'_'+aid).style.borderColor = 'var(--gray-200)';
      document.getElementById('eACheck_'+cid+'_'+aid).innerHTML = '';
    } else {
      const cls = remaining <= 3 ? 'ea-avail-low' : 'ea-avail-ok';
      badge.className = 'ea-avail-badge '+cls; badge.textContent = remaining+' left';
      card.classList.remove('ea-unavailable');
      if (cb) cb.disabled = false;
      if (qtyIn) {
        qtyIn.max = remaining;
        if (parseInt(qtyIn.value) > remaining) {
          qtyIn.value = remaining;
          eAddonSub(cid, aid, parseFloat(document.getElementById('eASub_'+cid+'_'+aid)?.dataset.unit||0));
        }
      }
    }
  }
  eRecalc(cid);
}

function eGuest(cid, id, d) {
  const el = document.getElementById(id);
  el.value = Math.max(0, parseInt(el.value||0)+d);
  eRecalc(cid);
}

function eRecalc(cid) {
  const rt     = eRateType(cid);
  const rd     = eTP[cid].pm[rt]||{};
  const fk     = Object.keys(rd)[0];
  const base   = fk ? rd[fk].base_price : 0;
  const adults = parseInt(document.getElementById('eAdults_'+cid)?.value||0);
  const kids   = parseInt(document.getElementById('eKids_'+cid)?.value||0);
  const total  = adults+kids;
  const maxC   = eTP[cid].max;
  let exc = 0;
  if (maxC>0 && total>maxC) {
    const er = (rd['adults']?.exceed_rate) ?? (rd['general']?.exceed_rate) ?? 0;
    exc = (total-maxC)*er;
  }
  let addons = 0;
  document.querySelectorAll(`#eForm_${cid} input[name="addon_ids[]"]:checked`).forEach(cb => {
    const aid = cb.value;
    const qi  = document.getElementById('eAQtyI_'+cid+'_'+aid);
    const si  = document.getElementById('eASub_'+cid+'_'+aid);
    if (qi && si) addons += parseFloat(si.dataset.unit||0)*parseInt(qi.value||1);
  });
  const grand = base+exc+addons;
  document.getElementById('eBBase_'+cid).textContent = '₱'+base.toLocaleString();
  const er2 = document.getElementById('eBExcRow_'+cid);
  er2.style.display = exc>0?'flex':'none';
  if (exc>0) document.getElementById('eBExc_'+cid).textContent = '+₱'+exc.toLocaleString();
  const ar = document.getElementById('eBAddRow_'+cid);
  ar.style.display = addons>0?'flex':'none';
  if (addons>0) document.getElementById('eBAdd_'+cid).textContent = '₱'+addons.toLocaleString();
  document.getElementById('eBTotal_'+cid).textContent = '₱'+grand.toLocaleString();
}

function eToggleAddon(cid, aid, price) {
  const cb   = document.querySelector(`#eForm_${cid} input[name="addon_ids[]"][value="${aid}"]`);
  const card = document.getElementById('eACard_'+cid+'_'+aid);
  const qty  = document.getElementById('eAQty_'+cid+'_'+aid);
  const chk  = document.getElementById('eACheck_'+cid+'_'+aid);
  if (cb.checked) {
    card.classList.add('active'); qty.style.display='flex';
    chk.style.background='var(--green)'; chk.style.borderColor='var(--green)';
    chk.innerHTML='<span style="color:#fff;font-size:0.65rem;">✓</span>';
  } else {
    card.classList.remove('active'); qty.style.display='none';
    chk.style.background=''; chk.style.borderColor='var(--gray-200)'; chk.innerHTML='';
  }
  eRecalc(cid);
}

function eAddonStep(cid, aid, price, d) {
  const i   = document.getElementById('eAQtyI_'+cid+'_'+aid);
  const max = parseInt(i.max)||9999;
  i.value   = Math.min(max, Math.max(1, parseInt(i.value||1)+d));
  eAddonSub(cid, aid, price);
}

function eAddonSub(cid, aid, price) {
  const i = document.getElementById('eAQtyI_'+cid+'_'+aid);
  const s = document.getElementById('eASub_'+cid+'_'+aid);
  if (s) s.textContent = '₱'+(price*parseInt(i.value||1)).toLocaleString();
  eRecalc(cid);
}

function toggleEdit(cid) {
  const v    = document.getElementById('view_'+cid);
  const e    = document.getElementById('edit_'+cid);
  const open = e.style.display !== 'none';
  v.style.display = open ? ''     : 'none';
  e.style.display = open ? 'none' : 'block';
  if (!open) {
    // Opening edit panel — trigger availability check for current dates
    eChange(cid);
    eRecalc(cid);
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>