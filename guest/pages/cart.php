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

    // Get facility info for this cart item
    $fRow = $db->query("
        SELECT f.facility_id, f.max_capacity, f.category
        FROM facilities f JOIN carts c ON f.facility_id = c.facility_id
        WHERE c.cart_id = $cid
    ")->fetch_assoc();
    $fid      = (int)($fRow['facility_id'] ?? 0);
    $maxCap   = (int)($fRow['max_capacity'] ?? 0);
    $category = strtolower(trim($fRow['category'] ?? ''));
    $isPool   = str_contains($category, 'pool');

    $ciDT      = $checkin_date  . ' ' . $checkin_time_full;
    $coDT      = $checkout_date . ' ' . $checkout_time_full;
    $numGuests = $kids_count + $adults_count;

    // Facility conflict check
    $updateMsg = '';
    if ($isPool && $maxCap > 0) {
        $bRes = $db->query("
            SELECT COALESCE(SUM(num_guests),0) AS booked
            FROM reservations
            WHERE facility_id = $fid AND status IN ('pending','approved')
              AND CONCAT(checkin_date,' ',COALESCE(checkin_time,'00:00:00'))  < '$coDT'
              AND CONCAT(checkout_date,' ',COALESCE(checkout_time,'23:59:59')) > '$ciDT'
        ")->fetch_assoc();
        $booked    = (int)($bRes['booked'] ?? 0);
        $remaining = $maxCap - $booked;
        if ($booked + $numGuests > $maxCap) {
            $updateMsg = "⚠️ Pool capacity warning: only $remaining guest slot(s) available for this window.";
        }
    } else {
        $bRes = $db->query("
            SELECT COUNT(*) AS cnt FROM reservations
            WHERE facility_id = $fid AND status IN ('pending','approved')
              AND CONCAT(checkin_date,' ',COALESCE(checkin_time,'00:00:00'))  < '$coDT'
              AND CONCAT(checkout_date,' ',COALESCE(checkout_time,'23:59:59')) > '$ciDT'
        ")->fetch_assoc();
        if ((int)($bRes['cnt'] ?? 0) > 0) {
            $updateMsg = '⚠️ This facility is already reserved for the new dates. Your cart was updated but you will not be able to confirm this booking.';
        }
    }

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
    if ($maxCap > 0 && $numGuests > $maxCap) {
        $excess    = $numGuests - $maxCap;
        $excRate   = $rateData['adults']['exceed_rate'] ?? ($rateData['general']['exceed_rate'] ?? 0);
        $exceedFee = $excess * $excRate;
    }
    $subtotal = $basePrice + $exceedFee;

    // Addon availability check (cap qty, don't hard-block)
    foreach ($addon_ids as $raw_aid) {
        $aid = (int)$raw_aid;
        $qty = max(1, (int)($addon_qtys[$aid] ?? 1));
        $aRow = $db->query("SELECT addon_name, limit_per_reservation FROM addons WHERE addon_id = $aid")->fetch_assoc();
        if (!$aRow || !(int)$aRow['limit_per_reservation']) continue;
        $limit = (int)$aRow['limit_per_reservation'];
        $bRes  = $db->query("
            SELECT COALESCE(SUM(ra.quantity),0) AS booked
            FROM reservation_addons ra JOIN reservations r ON ra.reservation_id=r.reservation_id
            WHERE ra.addon_id=$aid AND r.status IN ('pending','approved')
              AND CONCAT(r.checkin_date,' ',COALESCE(r.checkin_time,'00:00:00'))  < '$coDT'
              AND CONCAT(r.checkout_date,' ',COALESCE(r.checkout_time,'23:59:59')) > '$ciDT'
        ")->fetch_assoc();
        $booked    = (int)($bRes['booked'] ?? 0);
        $remaining = $limit - $booked;
        if ($qty > $remaining) {
            $addon_qtys[$aid] = max(0, $remaining);
            if ($remaining <= 0) $addon_ids = array_filter($addon_ids, fn($x) => (int)$x !== $aid);
            $updateMsg .= " Add-on \"{$aRow['addon_name']}\" capped to $remaining.";
        }
    }

    $stmt = $db->prepare("
        UPDATE carts SET checkin_date=?,checkout_date=?,checkin_time=?,checkout_time=?,
        kids_count=?,adults_count=?,rate_type=?,subtotal=?,exceed_fee=?
        WHERE cart_id=? AND guest_id=?
    ");
    $stmt->bind_param('ssssiisddii',
        $checkin_date, $checkout_date, $checkin_time_full, $checkout_time_full,
        $kids_count, $adults_count, $rate_type, $subtotal, $exceedFee, $cid, $guest_id
    );

    if ($stmt->execute()) {
        $db->query("DELETE FROM cart_addons WHERE cart_id = $cid");
        foreach ($addon_ids as $raw_aid) {
            $aid = (int)$raw_aid;
            $qty = max(1, (int)($addon_qtys[$aid] ?? 1));
            if ($qty <= 0) continue;
            $aStmt = $db->prepare("SELECT addon_price FROM addons WHERE addon_id=?");
            $aStmt->bind_param('i', $aid); $aStmt->execute();
            $aRow = $aStmt->get_result()->fetch_assoc();
            if ($aRow) {
                $asub = $aRow['addon_price'] * $qty;
                $caStmt = $db->prepare("INSERT INTO cart_addons (cart_id,addon_id,quantity,subtotal) VALUES(?,?,?,?)");
                $caStmt->bind_param('iiid', $cid, $aid, $qty, $asub); $caStmt->execute();
            }
        }
        setFlash($updateMsg ? 'error' : 'success', $updateMsg ?: 'Cart item updated!');
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
    FROM carts c JOIN facilities f ON c.facility_id = f.facility_id
    WHERE c.guest_id = $guest_id ORDER BY c.added_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pricingByFacility = [];
foreach ($cartItems as &$item) {
    $cid = (int)$item['cart_id'];
    $fid = (int)$item['fac_id'];
    $item['addons']      = $db->query("
        SELECT ca.quantity, ca.subtotal, ca.addon_id, a.addon_name, a.addon_price
        FROM cart_addons ca JOIN addons a ON ca.addon_id=a.addon_id WHERE ca.cart_id=$cid
    ")->fetch_all(MYSQLI_ASSOC);
    $item['addon_total'] = array_sum(array_column($item['addons'], 'subtotal'));
    $item['grand_total'] = (float)$item['subtotal'] + $item['addon_total'];

    if (!isset($pricingByFacility[$fid])) {
        $pRows = $db->query("SELECT * FROM pricing WHERE facility_id=$fid")->fetch_all(MYSQLI_ASSOC);
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

    // Check current facility conflict status for view mode warning
    $cat    = strtolower(trim($item['category'] ?? ''));
    $ciDT   = $item['checkin_date']  . ' ' . ($item['checkin_time']  ?? '00:00:00');
    $coDT   = $item['checkout_date'] . ' ' . ($item['checkout_time'] ?? '23:59:59');
    $maxC   = (int)$item['max_capacity'];
    $isP    = str_contains($cat, 'pool');

    if ($isP && $maxC > 0) {
        $bRes = $db->query("
            SELECT COALESCE(SUM(num_guests),0) AS booked FROM reservations
            WHERE facility_id={$fid} AND status IN('pending','approved')
              AND CONCAT(checkin_date,' ',COALESCE(checkin_time,'00:00:00'))  < '$coDT'
              AND CONCAT(checkout_date,' ',COALESCE(checkout_time,'23:59:59')) > '$ciDT'
        ")->fetch_assoc();
        $b = (int)($bRes['booked'] ?? 0);
        $item['facility_conflict'] = ($b + (int)$item['adults_count'] + (int)$item['kids_count']) > $maxC;
        $item['conflict_msg']      = $item['facility_conflict'] ? "Pool over capacity: $b guest(s) already booked." : '';
    } else {
        $bRes = $db->query("
            SELECT COUNT(*) AS cnt FROM reservations
            WHERE facility_id={$fid} AND status IN('pending','approved')
              AND CONCAT(checkin_date,' ',COALESCE(checkin_time,'00:00:00'))  < '$coDT'
              AND CONCAT(checkout_date,' ',COALESCE(checkout_time,'23:59:59')) > '$ciDT'
        ")->fetch_assoc();
        $item['facility_conflict'] = (int)($bRes['cnt'] ?? 0) > 0;
        $item['conflict_msg']      = $item['facility_conflict'] ? 'This facility is already reserved for your selected dates.' : '';
    }
}
unset($item);

$cartTotal = array_sum(array_column($cartItems, 'grand_total'));
$hasConflict = !empty(array_filter($cartItems, fn($i) => $i['facility_conflict']));

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
/* Cart layout */
.cart-layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}
@media(max-width:900px){.cart-layout{grid-template-columns:1fr;}}

/* Select All bar */
.select-all-bar{display:flex;align-items:center;justify-content:space-between;background:var(--green-50);border:1.5px solid var(--green-100);border-radius:var(--radius);padding:10px 18px;margin-bottom:16px;flex-wrap:wrap;gap:10px;}
.select-all-label{display:flex;align-items:center;gap:8px;font-size:0.88rem;font-weight:600;color:var(--gray-700);cursor:pointer;user-select:none;}
.select-all-label input[type=checkbox]{width:17px;height:17px;accent-color:var(--green);cursor:pointer;flex-shrink:0;}

/* Cart card */
.cart-card{background:#fff;border-radius:var(--radius);border:2px solid var(--green-100);overflow:hidden;transition:var(--transition);}
.cart-card.conflict-border{border-color:#fca5a5;}
.cart-card-view{cursor:pointer;transition:background 0.15s;}
.cart-card-view:hover{background:var(--green-50);}
.cart-item-img{width:170px;flex-shrink:0;overflow:hidden;min-height:150px;position:relative;}

/* Checkbox overlay on image */
.cart-chk-overlay{position:absolute;top:10px;left:10px;width:28px;height:28px;background:rgba(255,255,255,0.93);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.18);z-index:2;transition:var(--transition);}
.cart-chk-overlay:hover{background:#fff;transform:scale(1.08);}
.cart-chk-overlay input[type=checkbox]{width:16px;height:16px;accent-color:var(--green);cursor:pointer;}

/* Edit panel */
.cart-edit-panel{padding:22px;background:#fafffe;border-top:3px solid var(--green);}
.edit-sec{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:6px;margin-top:14px;display:flex;align-items:center;gap:6px;}
.edit-sec:first-of-type{margin-top:0;}
.edit-sec i{color:var(--green);}
input[type=time].edit-time-input{cursor:pointer;letter-spacing:0.03em;width:100%;}
.guest-stepper{display:flex;align-items:center;border:2px solid var(--gray-200);border-radius:var(--radius);overflow:hidden;}
.guest-stepper button{background:var(--green-50);border:none;color:var(--green-dark);font-size:1.1rem;font-weight:700;cursor:pointer;padding:6px 14px;transition:var(--transition);}
.guest-stepper button:hover{background:var(--green-100);}
.guest-stepper input{border:none;border-left:1px solid var(--gray-200);border-right:1px solid var(--gray-200);text-align:center;font-weight:700;font-size:0.95rem;width:48px;padding:6px 0;outline:none;background:#fff;}

/* Addon */
.ea-label{display:block;border:2px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;transition:var(--transition);overflow:hidden;}
.ea-label:hover:not(.ea-unavailable){border-color:var(--green-100);}
.ea-label.active{border-color:var(--green);background:var(--green-50);}
.ea-label.ea-unavailable{opacity:0.5;cursor:not-allowed;background:var(--gray-100);}
.ea-label.ea-unavailable *{pointer-events:none;}
.ea-qty-row{display:flex;align-items:center;gap:10px;padding:7px 12px;border-top:1px solid var(--green-100);background:#f0fdf4;}
.ea-stepper{display:flex;align-items:center;border:1px solid var(--green-200);border-radius:6px;overflow:hidden;background:#fff;}
.ea-stepper button{background:var(--green-50);border:none;color:var(--green-dark);font-weight:700;cursor:pointer;padding:2px 9px;font-size:1rem;}
.ea-stepper input{border:none;border-left:1px solid var(--green-200);border-right:1px solid var(--green-200);text-align:center;font-weight:700;width:34px;padding:2px 0;font-size:0.82rem;outline:none;}
.ea-avail-badge{font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:var(--radius-full);white-space:nowrap;}
.ea-avail-ok{background:#dcfce7;color:var(--green-dark);}
.ea-avail-low{background:var(--yellow-light);color:var(--yellow-dark);}
.ea-avail-none{background:var(--red-light);color:#991b1b;}
.ea-avail-unlim{background:var(--gray-100);color:var(--gray-500);}
.edit-price-preview{background:var(--green-50);border-radius:var(--radius);padding:14px 16px;margin-top:16px;}

/* Banners */
.cart-conflict-banner{display:flex;align-items:flex-start;gap:10px;background:var(--red-light);border:1.5px solid #fca5a5;border-radius:var(--radius-sm);padding:10px 14px;font-size:0.82rem;color:#991b1b;margin-bottom:10px;}
.edit-conflict-banner{background:var(--yellow-light);border:1.5px solid #fde68a;border-radius:var(--radius-sm);padding:10px 14px;font-size:0.82rem;color:var(--yellow-dark);margin-bottom:12px;display:none;}

/* Summary sidebar */
.sum-card{background:#fff;border-radius:var(--radius);border:2px solid var(--green-100);padding:22px;position:sticky;top:80px;}
.sum-card-title{font-size:1rem;font-weight:700;color:var(--green-dark);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.sum-row{display:flex;justify-content:space-between;align-items:center;font-size:0.84rem;padding:5px 0;border-bottom:1px solid var(--green-50);gap:8px;}
.sum-row span:first-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%;}
.sum-total-row{display:flex;justify-content:space-between;padding-top:10px;margin-top:6px;border-top:2px solid var(--green-100);}

/* Grids */
.e-time-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;}
.e-guest-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.e-addon-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
@media(max-width:600px){
  .cart-item-img{width:100%;min-height:170px;}
  .e-time-grid,.e-guest-grid,.e-addon-grid{grid-template-columns:1fr;}
}
input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0;}
input[type=number]{-moz-appearance:textfield;}
</style>

<div class="container page-wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
    <div>
      <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-bottom:4px;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-cart-shopping"></i> My Cart
      </h1>
      <p style="color:var(--gray-400);"><?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?> in your cart</p>
    </div>
    <?php if (!empty($cartItems)): ?>
      <button onclick="openModal('clearCartModal')" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red);background:#fff;">
        <i class="fa-solid fa-trash"></i> Clear All
      </button>
    <?php endif; ?>
  </div>

  <?php if (empty($cartItems)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa-solid fa-cart-shopping" style="font-size:2.5rem;color:var(--green-200);"></i></div>
      <p class="empty-title">Your cart is empty</p>
      <p class="empty-desc">Browse our facilities and add a booking to get started.</p>
      <a href="<?= SITE_URL ?>/guest/index.php#facilities" class="btn btn-primary">Explore Facilities</a>
    </div>
  <?php else: ?>

  <!-- Select All bar -->
  <div class="select-all-bar">
    <label class="select-all-label">
      <input type="checkbox" id="selectAllChk" onchange="selectAll(this)" checked>
      <span>Select All</span>
    </label>
    <span id="selCountLabel" style="font-size:0.82rem;color:var(--gray-500);font-weight:600;"></span>
  </div>

  <div class="cart-layout">

    <!-- Cart Items -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($cartItems as $item):
        $cid    = $item['cart_id'];
        $fid    = $item['fac_id'];
        $pMap   = $item['pricingMap'];
        $maxCap = (int)$item['max_capacity'];
        $cat    = strtolower(trim($item['category'] ?? ''));
        $isPool = str_contains($cat, 'pool');
        $imgSrc = !empty($item['photo'])
            ? SITE_URL . '/guest/pages/facility_photo.php?id=' . $fid
            : 'https://placehold.co/300x200/d1fae5/065f46?text=' . urlencode($item['facility_name']) . '&font=quicksand';
        $currentAddonIds  = array_column($item['addons'], 'addon_id');
        $currentAddonQtys = array_column($item['addons'], 'quantity', 'addon_id');
        // Compute 24h time values for native time inputs
        $ciH   = (int)date('g', strtotime($item['checkin_time']));
        $ciM   = (int)date('i', strtotime($item['checkin_time']));
        $ciAp  = date('A', strtotime($item['checkin_time']));
        $ci24h = $ciAp === 'PM' ? ($ciH < 12 ? $ciH + 12 : $ciH) : ($ciH === 12 ? 0 : $ciH);
        $ciTV  = sprintf('%02d:%02d', $ci24h, $ciM);
        $coH   = (int)date('g', strtotime($item['checkout_time']));
        $coM   = (int)date('i', strtotime($item['checkout_time']));
        $coAp  = date('A', strtotime($item['checkout_time']));
        $co24h = $coAp === 'PM' ? ($coH < 12 ? $coH + 12 : $coH) : ($coH === 12 ? 0 : $coH);
        $coTV  = sprintf('%02d:%02d', $co24h, $coM);
      ?>

      <div class="cart-card <?= $item['facility_conflict'] ? 'conflict-border' : '' ?>" id="card_<?= $cid ?>">

        <!-- VIEW MODE -->
        <div id="view_<?= $cid ?>" class="cart-card-view" onclick="toggleCardSelect(event,<?= $cid ?>)">
          <div style="display:flex;flex-wrap:wrap;">
            <div class="cart-item-img">
              <img src="<?= $imgSrc ?>" alt="<?= e($item['facility_name']) ?>"
                   style="width:100%;height:100%;object-fit:cover;display:block;"
                   onerror="this.src='https://placehold.co/300x200/d1fae5/065f46?text=<?= urlencode($item['facility_name']) ?>&font=quicksand'"/>
              <label class="cart-chk-overlay" title="Include in checkout">
                <input type="checkbox" class="cart-item-chk"
                       value="<?= $cid ?>"
                       data-total="<?= $item['grand_total'] ?>"
                       data-name="<?= e($item['facility_name']) ?>"
                       data-conflict="<?= $item['facility_conflict'] ? '1' : '0' ?>"
                       onchange="updateSummary()"
                       checked>
              </label>
            </div>
            <div style="flex:1;padding:16px;min-width:0;">

              <?php if ($item['facility_conflict']): ?>
              <div class="cart-conflict-banner">
                <i class="fa-solid fa-triangle-exclamation" style="margin-top:2px;flex-shrink:0;"></i>
                <span><?= e($item['conflict_msg']) ?> Uncheck this item or edit dates to proceed.</span>
              </div>
              <?php endif; ?>

              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                <div>
                  <h3 style="font-weight:700;font-size:1rem;color:var(--gray-800);margin-bottom:2px;"><?= e($item['facility_name']) ?></h3>
                  <?php if ($item['category']): ?><span style="font-size:0.72rem;color:var(--gray-400);"><?= catIcon($item['category']) ?> <?= e(ucfirst($item['category'])) ?></span><?php endif; ?>
                </div>
                <span style="padding:4px 12px;border-radius:var(--radius-full);font-size:0.75rem;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;gap:5px;background:<?= $item['rate_type']==='daytime'?'#fef9c3':'#dbeafe' ?>;color:<?= $item['rate_type']==='daytime'?'#854d0e':'#1e40af' ?>;">
                  <?= $item['rate_type']==='daytime'?'<i class="fa-solid fa-sun"></i> Daytime':'<i class="fa-solid fa-moon"></i> Overnight' ?>
                </span>
              </div>

              <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                <div style="background:var(--green-50);border-radius:var(--radius-sm);padding:8px 12px;font-size:0.82rem;">
                  <p style="color:var(--gray-400);font-size:0.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Check-in</p>
                  <p style="font-weight:700;color:var(--gray-800);"><?= date('M d, Y', strtotime($item['checkin_date'])) ?></p>
                  <p style="color:var(--green-dark);font-size:0.78rem;"><?= date('g:i A', strtotime($item['checkin_time'])) ?></p>
                </div>
                <div style="display:flex;align-items:center;color:var(--gray-300);"><i class="fa-solid fa-arrow-right"></i></div>
                <div style="background:var(--green-50);border-radius:var(--radius-sm);padding:8px 12px;font-size:0.82rem;">
                  <p style="color:var(--gray-400);font-size:0.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Check-out</p>
                  <p style="font-weight:700;color:var(--gray-800);"><?= date('M d, Y', strtotime($item['checkout_date'])) ?></p>
                  <p style="color:var(--green-dark);font-size:0.78rem;"><?= date('g:i A', strtotime($item['checkout_time'])) ?></p>
                </div>
              </div>

              <p style="font-size:0.82rem;color:var(--gray-500);margin-bottom:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span><i class="fa-solid fa-user" style="color:var(--green);"></i> <?= (int)$item['adults_count'] ?> Adult<?= $item['adults_count']!=1?'s':'' ?></span>
                <?php if ($item['kids_count']>0): ?><span style="color:var(--gray-300);">·</span><span><i class="fa-solid fa-child" style="color:var(--green);"></i> <?= (int)$item['kids_count'] ?> Kid<?= $item['kids_count']!=1?'s':'' ?></span><?php endif; ?>
              </p>

              <?php if (!empty($item['addons'])): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                  <?php foreach ($item['addons'] as $addon): ?>
                    <span class="tag" style="font-size:0.72rem;"><i class="fa-solid fa-star" style="color:var(--green);"></i> <?= e($addon['addon_name']) ?> ×<?= (int)$addon['quantity'] ?> (<?= peso($addon['subtotal']) ?>)</span>
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
                  <button onclick="toggleEdit(<?= $cid ?>)" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-pencil"></i> Edit
                  </button>
                  <button onclick="openDeleteModal(<?= $cid ?>)" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red);background:#fff;">
                    <i class="fa-solid fa-trash"></i> Remove
                  </button>
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

              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h3 style="font-weight:700;color:var(--green-dark);font-size:1rem;display:flex;align-items:center;gap:8px;">
                  <i class="fa-solid fa-pencil"></i> Edit — <?= e($item['facility_name']) ?>
                </h3>
                <button type="button" onclick="toggleEdit(<?= $cid ?>)" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:1.2rem;line-height:1;">
                  <i class="fa-solid fa-times"></i>
                </button>
              </div>

              <div id="eConflictBanner_<?= $cid ?>" class="edit-conflict-banner">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span id="eConflictMsg_<?= $cid ?>"></span>
              </div>

              <p class="edit-sec"><i class="fa-solid fa-calendar-days"></i> Check-in Date</p>
              <input type="date" name="checkin_date" id="eDate_<?= $cid ?>" class="form-control"
                     value="<?= e($item['checkin_date']) ?>" min="<?= date('Y-m-d') ?>"
                     onchange="eChange(<?= $cid ?>)"/>

              <div class="e-time-grid">
                <div>
                  <p class="edit-sec" style="margin-top:0;"><i class="fa-solid fa-clock"></i> Check-in Time</p>
                  <input type="hidden" name="ci_hour"   id="eci_h_<?= $cid ?>"  value="<?= $ciH ?>"/>
                  <input type="hidden" name="ci_minute" id="eci_m_<?= $cid ?>"  value="<?= $ciM ?>"/>
                  <input type="hidden" name="ci_ampm"   id="eci_ap_<?= $cid ?>" value="<?= $ciAp ?>"/>
                  <input type="time" id="eCiTime_<?= $cid ?>" class="form-control edit-time-input"
                         value="<?= $ciTV ?>" onchange="eSyncTime(<?= $cid ?>,'ci')"/>
                  <p id="eciErr_<?= $cid ?>" style="font-size:0.74rem;color:var(--red);margin-top:4px;"></p>
                </div>
                <div>
                  <p class="edit-sec" style="margin-top:0;"><i class="fa-solid fa-clock"></i> Check-out Time</p>
                  <input type="hidden" name="co_hour"   id="eco_h_<?= $cid ?>"  value="<?= $coH ?>"/>
                  <input type="hidden" name="co_minute" id="eco_m_<?= $cid ?>"  value="<?= $coM ?>"/>
                  <input type="hidden" name="co_ampm"   id="eco_ap_<?= $cid ?>" value="<?= $coAp ?>"/>
                  <input type="time" id="eCoTime_<?= $cid ?>" class="form-control edit-time-input"
                         value="<?= $coTV ?>" onchange="eSyncTime(<?= $cid ?>,'co')"/>
                </div>
              </div>

              <div style="display:flex;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap;">
                <div style="flex:1;min-width:160px;">
                  <p style="font-size:0.74rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">Check-out Date (auto)</p>
                  <input type="text" id="eCoDate_<?= $cid ?>" class="form-control" readonly
                         style="background:var(--green-50);cursor:not-allowed;color:var(--gray-600);font-size:0.85rem;padding:8px 12px;"/>
                </div>
                <div>
                  <p style="font-size:0.74rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">Rate Type</p>
                  <span id="eRateBadge_<?= $cid ?>" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:var(--radius-full);font-size:0.82rem;font-weight:700;background:#dbeafe;color:#1e40af;">
                    <i class="fa-solid fa-moon"></i> Overnight
                  </span>
                </div>
              </div>

              <p class="edit-sec"><i class="fa-solid fa-users"></i> Guests</p>
              <div class="e-guest-grid">
                <div>
                  <p style="font-size:0.78rem;font-weight:600;color:var(--gray-500);margin-bottom:6px;">Adults</p>
                  <div class="guest-stepper">
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eAdults_<?= $cid ?>',-1)">−</button>
                    <input type="number" id="eAdults_<?= $cid ?>" name="adults_count" value="<?= (int)$item['adults_count'] ?>" min="0" onchange="eChange(<?= $cid ?>)"/>
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eAdults_<?= $cid ?>',1)">+</button>
                  </div>
                </div>
                <div>
                  <p style="font-size:0.78rem;font-weight:600;color:var(--gray-500);margin-bottom:6px;">Kids</p>
                  <div class="guest-stepper">
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eKids_<?= $cid ?>',-1)">−</button>
                    <input type="number" id="eKids_<?= $cid ?>" name="kids_count" value="<?= (int)$item['kids_count'] ?>" min="0" onchange="eChange(<?= $cid ?>)"/>
                    <button type="button" onclick="eGuest(<?= $cid ?>,'eKids_<?= $cid ?>',1)">+</button>
                  </div>
                </div>
              </div>

              <?php if (!empty($allAddons)): ?>
              <p class="edit-sec">
                <i class="fa-solid fa-star"></i> Add-on Services
                <span id="eAvailStatus_<?= $cid ?>" style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--gray-400);font-size:0.72rem;margin-left:4px;"></span>
              </p>
              <div class="e-addon-grid">
                <?php foreach ($allAddons as $addon):
                  $aid     = $addon['addon_id'];
                  $checked = in_array($aid, $currentAddonIds);
                  $qty     = $currentAddonQtys[$aid] ?? 1;
                  $price   = (float)$addon['addon_price'];
                  $limit   = (int)($addon['limit_per_reservation'] ?? 0);
                ?>
                <label class="ea-label <?= $checked?'active':'' ?>" id="eACard_<?= $cid ?>_<?= $aid ?>">
                  <input type="checkbox" name="addon_ids[]" value="<?= $aid ?>" style="display:none;"
                         <?= $checked?'checked':'' ?> onchange="eToggleAddon(<?= $cid ?>,<?= $aid ?>,<?= $price ?>)"/>
                  <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;">
                    <div style="width:34px;height:34px;background:var(--green-50);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.95rem;flex-shrink:0;color:var(--green-dark);">
                      <i class="fa-solid fa-star"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:2px;">
                        <p style="font-weight:700;font-size:0.82rem;color:var(--gray-800);"><?= e($addon['addon_name']) ?></p>
                        <span class="ea-avail-badge ea-avail-unlim" id="eAvailBadge_<?= $cid ?>_<?= $aid ?>"><?= $limit?"limit:$limit":'unlimited' ?></span>
                      </div>
                      <?php if (!empty($addon['addon_description'])): ?><p style="font-size:0.72rem;color:var(--gray-400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($addon['addon_description']) ?></p><?php endif; ?>
                      <p style="font-size:0.78rem;font-weight:700;color:var(--green-dark);"><?= peso($price) ?></p>
                    </div>
                    <div id="eACheck_<?= $cid ?>_<?= $aid ?>" style="width:20px;height:20px;border-radius:50%;border:2px solid <?= $checked?'var(--green)':'var(--gray-200)' ?>;background:<?= $checked?'var(--green)':'transparent' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <?php if ($checked): ?><span style="color:#fff;font-size:0.65rem;"><i class="fa-solid fa-check"></i></span><?php endif; ?>
                    </div>
                  </div>
                  <div id="eAQty_<?= $cid ?>_<?= $aid ?>" class="ea-qty-row" style="<?= $checked?'':'display:none;' ?>">
                    <span style="font-size:0.74rem;color:var(--gray-600);font-weight:600;">Qty:</span>
                    <div class="ea-stepper">
                      <button type="button" onclick="eAddonStep(<?= $cid ?>,<?= $aid ?>,<?= $price ?>,-1)">−</button>
                      <input type="number" name="addon_qtys[<?= $aid ?>]" id="eAQtyI_<?= $cid ?>_<?= $aid ?>"
                             value="<?= $qty ?>" min="1" max="<?= $limit?:9999 ?>" data-limit="<?= $limit ?>"
                             onchange="eAddonSub(<?= $cid ?>,<?= $aid ?>,<?= $price ?>)"/>
                      <button type="button" onclick="eAddonStep(<?= $cid ?>,<?= $aid ?>,<?= $price ?>,1)">+</button>
                    </div>
                    <span id="eASub_<?= $cid ?>_<?= $aid ?>" data-unit="<?= $price ?>" style="font-size:0.78rem;font-weight:700;color:var(--green-dark);"><?= peso($price*$qty) ?></span>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

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
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Save Changes</button>
              </div>

            </div>
          </form>
        </div><!-- /edit -->

      </div>
      <?php endforeach; ?>
    </div><!-- /items -->

    <!-- Order Summary -->
    <div>
      <div class="sum-card">
        <p class="sum-card-title"><i class="fa-solid fa-receipt" style="color:var(--green);"></i> Order Summary</p>

        <div id="summaryItemList">
          <?php foreach ($cartItems as $item): ?>
          <div class="sum-row" id="sumRow_<?= $item['cart_id'] ?>">
            <span><?= $item['facility_conflict'] ? '<i class="fa-solid fa-triangle-exclamation" style="color:#991b1b;"></i> ' : '' ?><?= e($item['facility_name']) ?></span>
            <span style="font-weight:600;white-space:nowrap;"><?= peso($item['grand_total']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="sum-total-row">
          <span style="font-weight:700;color:var(--green-dark);">Total</span>
          <span id="summaryTotal" style="font-weight:700;font-size:1.1rem;color:var(--green-dark);"><?= peso($cartTotal) ?></span>
        </div>
        <p style="font-size:0.78rem;color:var(--gray-400);margin-top:4px;">Payment collected upon check-in</p>

        <div id="summaryConflictWarn" style="display:none;background:var(--red-light);border-radius:var(--radius-sm);padding:10px 14px;margin-top:12px;font-size:0.82rem;color:#991b1b;gap:8px;align-items:flex-start;">
          <i class="fa-solid fa-triangle-exclamation" style="flex-shrink:0;margin-top:1px;"></i>
          <span>Selected item(s) have booking conflicts. Uncheck them to proceed.</span>
        </div>

        <button id="confirmBtn" onclick="confirmSelected()" class="btn btn-primary btn-full"
                style="font-size:1rem;padding:13px;margin-top:18px;justify-content:center;display:flex;gap:8px;">
          <i class="fa-solid fa-circle-check"></i> Confirm Booking
        </button>

        <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
          <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><i class="fa-solid fa-check" style="color:var(--green);margin-top:1px;"></i> No upfront payment needed</div>
          <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><i class="fa-solid fa-check" style="color:var(--green);margin-top:1px;"></i> Secure reservation</div>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<script>
const AVAIL_URL     = '<?= SITE_URL ?>/guest/pages/addon_availability.php';
const FAC_AVAIL_URL = '<?= SITE_URL ?>/guest/pages/facility_availability.php';

const eTP = {};
<?php foreach ($cartItems as $item):
  $cid  = $item['cart_id'];
  $fid  = $item['fac_id'];
  $cat  = strtolower(trim($item['category'] ?? ''));
  $jsH  = (int)date('g', strtotime($item['checkin_time']));
  $jsM  = (int)date('i', strtotime($item['checkin_time']));
  $jsAp = date('A', strtotime($item['checkin_time']));
  $joH  = (int)date('g', strtotime($item['checkout_time']));
  $joM  = (int)date('i', strtotime($item['checkout_time']));
  $joAp = date('A', strtotime($item['checkout_time']));
?>
eTP[<?= $cid ?>] = {
  ci:         { hour:<?= $jsH ?>, minute:<?= $jsM ?>, ampm:'<?= $jsAp ?>' },
  co:         { hour:<?= $joH ?>, minute:<?= $joM ?>, ampm:'<?= $joAp ?>' },
  pm:         <?= json_encode($item['pricingMap']) ?>,
  max:        <?= (int)$item['max_capacity'] ?>,
  facilityId: <?= $fid ?>,
  isPool:     <?= str_contains($cat, 'pool') ? 'true' : 'false' ?>,
  availTimer: null,
};
<?php endforeach; ?>

function ePad(n) { return String(n).padStart(2,'0'); }

function eToMin(cid, p) {
  const s = eTP[cid][p];
  const h24 = s.ampm==='PM'?(s.hour===12?12:s.hour+12):(s.hour===12?0:s.hour);
  return h24*60+s.minute;
}
function eTo24str(cid, p) {
  const s = eTP[cid][p];
  const h24 = s.ampm==='PM'?(s.hour===12?12:s.hour+12):(s.hour===12?0:s.hour);
  return ePad(h24)+':'+ePad(s.minute)+':00';
}

function eSyncTime(cid, p) {
  const input = document.getElementById((p==='ci'?'eCiTime_':'eCoTime_')+cid);
  if (!input || !input.value) return;
  const [h24str, minStr] = input.value.split(':');
  const h24 = parseInt(h24str, 10), min = parseInt(minStr, 10);
  let h12 = h24 % 12; if (h12===0) h12=12;
  const ampm = h24>=12?'PM':'AM';
  eTP[cid][p] = { hour:h12, minute:min, ampm };
  const pre = p==='ci'?'eci':'eco';
  document.getElementById(pre+'_h_'+cid).value  = h12;
  document.getElementById(pre+'_m_'+cid).value  = min;
  document.getElementById(pre+'_ap_'+cid).value = ampm;
  eChange(cid);
}

function eRateType(cid) {
  const ci=eToMin(cid,'ci'), co=eToMin(cid,'co');
  return (co>ci&&co<=18*60)?'daytime':'overnight';
}
function eAddDays(dateStr,n) {
  const d=new Date(dateStr+'T12:00:00'); d.setDate(d.getDate()+n);
  return d.toISOString().slice(0,10);
}
function eFmtDate(s) {
  return new Date(s+'T12:00:00').toLocaleDateString('en-PH',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
}

function eChange(cid) {
  const ci       = document.getElementById('eDate_'+cid)?.value||'';
  const rt       = eRateType(cid);
  const badge    = document.getElementById('eRateBadge_'+cid);
  const coDateEl = document.getElementById('eCoDate_'+cid);
  const ciErr    = document.getElementById('eciErr_'+cid);
  const ciMin    = eToMin(cid,'ci');

  ciErr.textContent = ciMin<14*60?'Check-in must be 2:00 PM or later.':'';
  const coDate = rt==='daytime'?ci:(ci?eAddDays(ci,1):'');
  coDateEl.value = !ci?'Select date first':(coDate?eFmtDate(coDate):'');
  if (rt==='daytime') {
    badge.innerHTML='<i class="fa-solid fa-sun"></i> Daytime';
    badge.style.background='#fef9c3'; badge.style.color='#854d0e';
  } else {
    badge.innerHTML='<i class="fa-solid fa-moon"></i> Overnight';
    badge.style.background='#dbeafe'; badge.style.color='#1e40af';
  }

  eRecalc(cid);
  clearTimeout(eTP[cid].availTimer);
  eTP[cid].availTimer = setTimeout(() => {
    if (ci) { eFetchFacilityAvail(cid, ci, coDate); eFetchAddonAvail(cid, ci, coDate); }
  }, 600);
}

function eFetchFacilityAvail(cid, ciDate, coDate) {
  const adults = parseInt(document.getElementById('eAdults_'+cid)?.value||0);
  const kids   = parseInt(document.getElementById('eKids_'+cid)?.value||0);
  const fd = new FormData();
  fd.append('facility_id',   eTP[cid].facilityId);
  fd.append('checkin_date',  ciDate);
  fd.append('checkout_date', coDate||ciDate);
  fd.append('checkin_time',  eTo24str(cid,'ci'));
  fd.append('checkout_time', eTo24str(cid,'co'));
  fd.append('num_guests',    adults+kids);
  fetch(FAC_AVAIL_URL, {method:'POST',body:fd})
    .then(r=>r.json())
    .then(data => {
      const banner = document.getElementById('eConflictBanner_'+cid);
      const msgEl  = document.getElementById('eConflictMsg_'+cid);
      if (!data.available) {
        msgEl.textContent    = data.message;
        banner.style.display = 'flex';
      } else {
        banner.style.display = 'none';
      }
    }).catch(()=>{});
}

function eFetchAddonAvail(cid, ciDate, coDate) {
  const statusEl = document.getElementById('eAvailStatus_'+cid);
  if (statusEl) statusEl.textContent = '(checking…)';
  document.querySelectorAll(`[id^="eAvailBadge_${cid}_"]`).forEach(b => {
    b.className='ea-avail-badge ea-avail-unlim'; b.textContent='…';
  });
  const fd = new FormData();
  fd.append('checkin_date',  ciDate);
  fd.append('checkout_date', coDate||ciDate);
  fd.append('checkin_time',  eTo24str(cid,'ci'));
  fd.append('checkout_time', eTo24str(cid,'co'));
  fetch(AVAIL_URL, {method:'POST',body:fd})
    .then(r=>r.json())
    .then(data => {
      if (statusEl) statusEl.textContent='(updated)';
      for (const [aidStr, remaining] of Object.entries(data)) {
        const aid   = parseInt(aidStr);
        const card  = document.getElementById('eACard_'+cid+'_'+aid);
        const badge = document.getElementById('eAvailBadge_'+cid+'_'+aid);
        const cb    = card?.querySelector('input[type="checkbox"]');
        const qtyIn = document.getElementById('eAQtyI_'+cid+'_'+aid);
        if (!card||!badge) continue;
        if (remaining===null) {
          badge.className='ea-avail-badge ea-avail-unlim'; badge.textContent='unlimited';
          card.classList.remove('ea-unavailable'); if(cb) cb.disabled=false;
        } else if (remaining===0) {
          badge.className='ea-avail-badge ea-avail-none'; badge.textContent='fully booked';
          card.classList.add('ea-unavailable'); card.classList.remove('active');
          if(cb){cb.checked=false;cb.disabled=true;}
          document.getElementById('eAQty_'+cid+'_'+aid).style.display='none';
          const chk=document.getElementById('eACheck_'+cid+'_'+aid);
          chk.style.background='';chk.style.borderColor='var(--gray-200)';chk.innerHTML='';
        } else {
          const cls=remaining<=3?'ea-avail-low':'ea-avail-ok';
          badge.className='ea-avail-badge '+cls; badge.textContent=remaining+' left';
          card.classList.remove('ea-unavailable'); if(cb) cb.disabled=false;
          if(qtyIn){qtyIn.max=remaining;if(parseInt(qtyIn.value)>remaining){qtyIn.value=remaining;eAddonSub(cid,aid,parseFloat(document.getElementById('eASub_'+cid+'_'+aid)?.dataset.unit||0));}}
        }
      }
      eRecalc(cid);
    }).catch(()=>{if(statusEl)statusEl.textContent='';});
}

function eGuest(cid,id,d) {
  const el=document.getElementById(id);
  el.value=Math.max(0,parseInt(el.value||0)+d);
  eChange(cid);
}
function eRecalc(cid) {
  const rt=eRateType(cid), rd=eTP[cid].pm[rt]||{}, fk=Object.keys(rd)[0];
  const base=fk?rd[fk].base_price:0;
  const adults=parseInt(document.getElementById('eAdults_'+cid)?.value||0);
  const kids=parseInt(document.getElementById('eKids_'+cid)?.value||0);
  const total=adults+kids, maxC=eTP[cid].max;
  let exc=0;
  if(maxC>0&&total>maxC){const er=(rd['adults']?.exceed_rate)??(rd['general']?.exceed_rate)??0;exc=(total-maxC)*er;}
  let addons=0;
  document.querySelectorAll(`#eForm_${cid} input[name="addon_ids[]"]:checked`).forEach(cb=>{
    const aid=cb.value,qi=document.getElementById('eAQtyI_'+cid+'_'+aid),si=document.getElementById('eASub_'+cid+'_'+aid);
    if(qi&&si) addons+=parseFloat(si.dataset.unit||0)*parseInt(qi.value||1);
  });
  const grand=base+exc+addons;
  document.getElementById('eBBase_'+cid).textContent='₱'+base.toLocaleString();
  const er2=document.getElementById('eBExcRow_'+cid); er2.style.display=exc>0?'flex':'none';
  if(exc>0) document.getElementById('eBExc_'+cid).textContent='+₱'+exc.toLocaleString();
  const ar=document.getElementById('eBAddRow_'+cid); ar.style.display=addons>0?'flex':'none';
  if(addons>0) document.getElementById('eBAdd_'+cid).textContent='₱'+addons.toLocaleString();
  document.getElementById('eBTotal_'+cid).textContent='₱'+grand.toLocaleString();
}
function eToggleAddon(cid,aid,price) {
  const cb=document.querySelector(`#eForm_${cid} input[name="addon_ids[]"][value="${aid}"]`);
  const card=document.getElementById('eACard_'+cid+'_'+aid);
  const qty=document.getElementById('eAQty_'+cid+'_'+aid);
  const chk=document.getElementById('eACheck_'+cid+'_'+aid);
  if(cb.checked){
    card.classList.add('active');qty.style.display='flex';
    chk.style.background='var(--green)';chk.style.borderColor='var(--green)';
    chk.innerHTML='<span style="color:#fff;font-size:0.65rem;"><i class="fa-solid fa-check"></i></span>';
  } else {
    card.classList.remove('active');qty.style.display='none';
    chk.style.background='';chk.style.borderColor='var(--gray-200)';chk.innerHTML='';
  }
  eRecalc(cid);
}
function eAddonStep(cid,aid,price,d) {
  const i=document.getElementById('eAQtyI_'+cid+'_'+aid);
  const max=parseInt(i.max)||9999;
  i.value=Math.min(max,Math.max(1,parseInt(i.value||1)+d));
  eAddonSub(cid,aid,price);
}
function eAddonSub(cid,aid,price) {
  const i=document.getElementById('eAQtyI_'+cid+'_'+aid);
  const s=document.getElementById('eASub_'+cid+'_'+aid);
  if(s) s.textContent='₱'+(price*parseInt(i.value||1)).toLocaleString();
  eRecalc(cid);
}
function toggleEdit(cid) {
  const v=document.getElementById('view_'+cid);
  const e=document.getElementById('edit_'+cid);
  const open=e.style.display!=='none';
  v.style.display=open?'':'none';
  e.style.display=open?'none':'block';
  if (!open) {
    const s=eTP[cid];
    const ciH24=s.ci.ampm==='PM'?(s.ci.hour===12?12:s.ci.hour+12):(s.ci.hour===12?0:s.ci.hour);
    const coH24=s.co.ampm==='PM'?(s.co.hour===12?12:s.co.hour+12):(s.co.hour===12?0:s.co.hour);
    const ciIn=document.getElementById('eCiTime_'+cid), coIn=document.getElementById('eCoTime_'+cid);
    if(ciIn) ciIn.value=ePad(ciH24)+':'+ePad(s.ci.minute);
    if(coIn) coIn.value=ePad(coH24)+':'+ePad(s.co.minute);
    eChange(cid);
  }
}

function toggleCardSelect(event, cid) {
  // Skip if the click was on an interactive element (button, link, input, label)
  if (event.target.closest('button, a, input, label, select, textarea')) return;
  const chk = document.querySelector(`.cart-item-chk[value="${cid}"]`);
  if (chk) { chk.checked = !chk.checked; updateSummary(); }
}

function updateSummary() {
  const checkboxes=document.querySelectorAll('.cart-item-chk');
  let total=0, count=0, hasConflict=false;
  checkboxes.forEach(chk => {
    const row=document.getElementById('sumRow_'+chk.value);
    if(chk.checked){
      count++;
      total+=parseFloat(chk.dataset.total)||0;
      if(chk.dataset.conflict==='1') hasConflict=true;
      if(row) row.style.display='';
    } else {
      if(row) row.style.display='none';
    }
  });
  document.getElementById('summaryTotal').textContent=
    '₱'+total.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('selCountLabel').textContent=
    count+' item'+(count!==1?'s':'')+' selected';
  const warn=document.getElementById('summaryConflictWarn');
  const btn=document.getElementById('confirmBtn');
  warn.style.display=hasConflict?'flex':'none';
  btn.disabled=(count===0||hasConflict);
  btn.style.opacity=(count===0||hasConflict)?'0.5':'';
  btn.style.cursor=(count===0||hasConflict)?'not-allowed':'';
  const master=document.getElementById('selectAllChk');
  if(master){
    master.indeterminate=count>0&&count<checkboxes.length;
    master.checked=count===checkboxes.length;
  }
  // Persist unchecked IDs so page reloads keep the user's selection
  const unchecked=[...checkboxes].filter(c=>!c.checked).map(c=>c.value);
  try { localStorage.setItem('cartUnchecked', JSON.stringify(unchecked)); } catch(e){}
}

function selectAll(master) {
  document.querySelectorAll('.cart-item-chk').forEach(chk=>{ chk.checked=master.checked; });
  updateSummary();
}

function openDeleteModal(cid) {
  document.getElementById('deleteCartConfirmBtn').href = '?delete=' + cid;
  openModal('deleteCartModal');
}

function confirmSelected() {
  const checked=[...document.querySelectorAll('.cart-item-chk:checked')];
  if(!checked.length||checked.some(c=>c.dataset.conflict==='1')) return;
  window.location.href='<?= SITE_URL ?>/guest/pages/payment_form.php?ids='+checked.map(c=>c.value).join(',');
}

document.addEventListener('DOMContentLoaded', () => {
  // Restore unchecked state from before the last page reload
  try {
    const unchecked = JSON.parse(localStorage.getItem('cartUnchecked') || '[]');
    unchecked.forEach(id => {
      const chk = document.querySelector(`.cart-item-chk[value="${id}"]`);
      if (chk) chk.checked = false;
    });
  } catch(e) {}
  updateSummary();
});
</script>

<?php
$pageModals = '
<div class="modal-overlay" id="clearCartModal" role="dialog" aria-modal="true">
  <div class="confirm-dialogue">
    <div class="confirm-icon-wrap">
      <i class="fa-solid fa-trash"></i>
    </div>
    <p class="confirm-title">Clear Cart?</p>
    <p class="confirm-msg">This will remove all items from your cart. This action cannot be undone.</p>
    <div class="confirm-actions">
      <button class="btn btn-outline" onclick="closeModal(\'clearCartModal\')">Cancel</button>
      <a href="?clear=1" class="btn btn-red"><i class="fa-solid fa-trash"></i> Clear All</a>
    </div>
  </div>
</div>

<div class="modal-overlay" id="deleteCartModal" role="dialog" aria-modal="true">
  <div class="confirm-dialogue">
    <div class="confirm-icon-wrap">
      <i class="fa-solid fa-trash"></i>
    </div>
    <p class="confirm-title">Remove Item?</p>
    <p class="confirm-msg">Are you sure you want to remove this item from your cart?</p>
    <div class="confirm-actions">
      <button class="btn btn-outline" onclick="closeModal(\'deleteCartModal\')">Cancel</button>
      <a href="#" id="deleteCartConfirmBtn" class="btn btn-red"><i class="fa-solid fa-trash"></i> Remove</a>
    </div>
  </div>
</div>
';
require_once __DIR__ . '/../includes/footer.php'; ?>