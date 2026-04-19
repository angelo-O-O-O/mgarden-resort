<?php
$pageTitle = 'Book Facility';
require_once __DIR__ . '/../includes/config.php';

$db          = getDB();
$facility_id = (int)($_GET['id'] ?? 0);
if ($facility_id <= 0) redirect(SITE_URL . '/guest/index.php');

$stmt = $db->prepare("SELECT * FROM facilities WHERE facility_id = ? AND availability = 'available'");
$stmt->bind_param('i', $facility_id);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();
if (!$facility) redirect(SITE_URL . '/guest/index.php');

$pricingRows = $db->query("
    SELECT * FROM pricing WHERE facility_id = $facility_id
    ORDER BY rate_type ASC, guest_type ASC
")->fetch_all(MYSQLI_ASSOC);

$pricingMap = [];
foreach ($pricingRows as $p) {
    $pricingMap[$p['rate_type']][$p['guest_type']] = [
        'base_price'  => (float)$p['base_price'],
        'exceed_rate' => (float)$p['exceed_rate'],
    ];
}
$allPrices  = array_unique(array_column($pricingRows, 'base_price'));
$sharedBase = count($allPrices) === 1 ? (float)$allPrices[0] : null;

$addons = $db->query("SELECT * FROM addons ORDER BY addon_name ASC")->fetch_all(MYSQLI_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    requireLogin();

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
        $checkout_date = $checkin_date ? date('Y-m-d', strtotime($checkin_date . ' +1 day')) : '';
    }

    // Basic validation
    if (empty($checkin_date))              $errors[] = 'Check-in date is required.';
    elseif ($checkin_date < date('Y-m-d')) $errors[] = 'Check-in date cannot be in the past.';
    if ($ci_h24 < 14)                      $errors[] = 'Check-in time must be 2:00 PM or later.';
    if ($kids_count + $adults_count === 0) $errors[] = 'At least one guest is required.';

    // Server-side addon availability check
    if (empty($errors) && !empty($addon_ids)) {
        $ciDT = $checkin_date  . ' ' . $checkin_time_full;
        $coDT = $checkout_date . ' ' . $checkout_time_full;

        foreach ($addon_ids as $raw_aid) {
            $aid = (int)$raw_aid;
            $qty = max(1, (int)($addon_qtys[$aid] ?? 1));

            $aRow = $db->query("
                SELECT addon_name, limit_per_reservation FROM addons WHERE addon_id = $aid
            ")->fetch_assoc();
            if (!$aRow) continue;

            $limit = (int)($aRow['limit_per_reservation'] ?? 0);
            if ($limit === 0) continue; // no limit

            $bRes = $db->query("
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
                $name     = e($aRow['addon_name']);
                $errors[] = "Only $remaining slot(s) of \"$name\" available for your selected time. You requested $qty.";
            }
        }
    }

    if (empty($errors)) {
        $rateData  = $pricingMap[$rate_type] ?? [];
        $firstRate = reset($rateData);
        $basePrice = $firstRate ? $firstRate['base_price'] : 0;
        $exceedFee = 0;
        $maxCap    = (int)($facility['max_capacity'] ?? 0);
        $total     = $kids_count + $adults_count;
        if ($maxCap > 0 && $total > $maxCap) {
            $excess    = $total - $maxCap;
            $excRate   = $rateData['adults']['exceed_rate'] ?? ($rateData['general']['exceed_rate'] ?? 0);
            $exceedFee = $excess * $excRate;
        }
        $subtotal = $basePrice + $exceedFee;
        $guest_id = (int)$_SESSION['guest_id'];

        $stmt = $db->prepare("
            INSERT INTO carts
              (facility_id, guest_id, checkin_date, checkout_date,
               checkin_time, checkout_time, kids_count, adults_count,
               rate_type, subtotal, exceed_fee)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iissssiisdd',
            $facility_id, $guest_id, $checkin_date, $checkout_date,
            $checkin_time_full, $checkout_time_full,
            $kids_count, $adults_count, $rate_type, $subtotal, $exceedFee
        );

        if ($stmt->execute()) {
            $cart_id = $db->insert_id;
            foreach ($addon_ids as $raw_aid) {
                $aid = (int)$raw_aid;
                $qty = max(1, (int)($addon_qtys[$aid] ?? 1));
                $aStmt = $db->prepare("SELECT addon_price FROM addons WHERE addon_id = ?");
                $aStmt->bind_param('i', $aid);
                $aStmt->execute();
                $aRow = $aStmt->get_result()->fetch_assoc();
                if ($aRow) {
                    $asub   = $aRow['addon_price'] * $qty;
                    $caStmt = $db->prepare("
                        INSERT INTO cart_addons (cart_id, addon_id, quantity, subtotal)
                        VALUES (?, ?, ?, ?)
                    ");
                    $caStmt->bind_param('iiid', $cart_id, $aid, $qty, $asub);
                    $caStmt->execute();
                }
            }
            setFlash('success', '🛒 Added to cart successfully!');
            redirect(SITE_URL . '/guest/pages/cart.php');
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

function catIcon($cat) {
    $map = [
        'pool'=>'fa-solid fa-person-swimming','beach'=>'fa-solid fa-umbrella-beach',
        'room'=>'fa-solid fa-bed','family room'=>'fa-solid fa-people-roof',
        'cottage'=>'fa-solid fa-house-chimney','accommodation'=>'fa-solid fa-bed',
        'dining'=>'fa-solid fa-utensils','spa'=>'fa-solid fa-spa',
        'sports'=>'fa-solid fa-person-running','event'=>'fa-solid fa-calendar-days',
        'activity'=>'fa-solid fa-bullseye','resort'=>'fa-solid fa-hotel',
    ];
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $k => $v) if (str_contains($c, $k)) return "<i class=\"$v\"></i>";
    return '<i class="fa-solid fa-star"></i>';
}

$imgSrc = !empty($facility['photo'])
    ? SITE_URL . '/guest/pages/facility_photo.php?id=' . $facility_id
    : 'https://placehold.co/1200x500/d1fae5/065f46?text=' . urlencode($facility['facility_name']) . '&font=quicksand';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Time Picker ── */
.time-picker-wrap{display:flex;align-items:center;border:2px solid var(--gray-200);border-radius:var(--radius);overflow:hidden;background:#fff;transition:var(--transition);}
.time-picker-wrap:focus-within{border-color:var(--green);}
.tp-col{display:flex;flex-direction:column;align-items:center;border-right:1px solid var(--gray-200);}
.tp-col:last-child{border-right:none;}
.tp-btn{width:100%;background:var(--green-50);border:none;color:var(--green-dark);font-size:0.9rem;font-weight:700;cursor:pointer;padding:4px 14px;line-height:1;transition:var(--transition);}
.tp-btn:hover{background:var(--green-100);}
.tp-val{padding:6px 14px;font-size:1rem;font-weight:700;color:var(--gray-800);min-width:48px;text-align:center;border-top:1px solid var(--gray-200);border-bottom:1px solid var(--gray-200);background:#fff;user-select:none;}
.tp-ampm-btn{padding:10px 14px;font-size:0.9rem;font-weight:700;background:none;border:none;cursor:pointer;color:var(--gray-400);transition:var(--transition);}
.tp-ampm-btn.active{color:var(--green-dark);background:var(--green-50);}
input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0;}
input[type=number]{-moz-appearance:textfield;}

/* ── Addon availability states ── */
.addon-card-label{display:block;border:2px solid var(--gray-200);border-radius:var(--radius);overflow:hidden;cursor:pointer;transition:var(--transition);}
.addon-card-label:hover:not(.addon-unavailable){border-color:var(--green-100);background:var(--green-50);}
.addon-card-active{border-color:var(--green)!important;background:var(--green-50);}
.addon-unavailable{opacity:0.55;cursor:not-allowed;background:var(--gray-100)!important;border-color:var(--gray-200)!important;}
.addon-unavailable *{pointer-events:none;}
.avail-badge{display:inline-flex;align-items:center;gap:4px;font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:var(--radius-full);white-space:nowrap;}
.avail-ok   {background:var(--green-light);color:var(--green-dark);}
.avail-low  {background:var(--yellow-light);color:var(--yellow-dark);}
.avail-none {background:var(--red-light);color:#991b1b;}
.avail-unlim{background:var(--gray-100);color:var(--gray-500);}
.avail-wait {background:var(--gray-100);color:var(--gray-400);}
.addon-check-indicator{width:22px;height:22px;border:2px solid var(--gray-200);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition);}
.addon-card-active .addon-check-indicator{border-color:var(--green);background:var(--green);color:#fff;}
</style>

<div class="container" style="padding-top:40px;padding-bottom:60px;">

  <div class="room-detail-hero">
    <img src="<?= $imgSrc ?>" alt="<?= e($facility['facility_name']) ?>"
         onerror="this.src='https://placehold.co/1200x500/d1fae5/065f46?text=<?= urlencode($facility['facility_name']) ?>&font=quicksand'"/>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="flash flash-error" style="margin-bottom:24px;border-radius:var(--radius);">
    <div><?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?></div>
  </div>
  <?php endif; ?>

  <?php if (!isLoggedIn()): ?>
  <!-- Not logged in -->
  <div class="room-detail-grid">
    <div>
      <?php if ($facility['category']): ?><span class="tag"><?= catIcon($facility['category']) ?> <?= e(ucfirst($facility['category'])) ?></span><?php endif; ?>
      <h1 style="font-size:1.9rem;font-weight:700;color:var(--green-dark);margin:10px 0 6px;"><?= e($facility['facility_name']) ?></h1>
      <?php if ($facility['max_capacity']): ?><p style="color:var(--gray-400);font-size:0.9rem;margin-bottom:20px;">👤 Up to <?= (int)$facility['max_capacity'] ?> guests</p><?php endif; ?>
      <p style="color:var(--gray-500);line-height:1.7;"><?= e($facility['description']) ?></p>
    </div>
    <div>
      <div class="card booking-card" style="padding:24px;">
        <?php if ($sharedBase !== null): ?>
          <div style="margin-bottom:16px;"><span style="font-size:1.8rem;font-weight:700;color:var(--green-dark);"><?= peso($sharedBase) ?></span><span style="font-size:0.85rem;color:var(--gray-400);"> / booking</span></div>
        <?php endif; ?>
        <p style="color:var(--gray-500);margin-bottom:16px;font-size:0.9rem;">Please sign in to make a booking.</p>
        <a href="<?= SITE_URL ?>/guest/pages/login.php" class="btn btn-primary btn-full" style="justify-content:center;padding:13px;font-size:1rem;">Sign In to Book</a>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- Logged in: full booking form -->
  <form method="POST" id="bookingForm" novalidate>
    <input type="hidden" name="add_to_cart" value="1"/>
    <div class="room-detail-grid">

      <!-- ── LEFT ── -->
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
          <?php if ($facility['category']): ?><span class="tag"><?= catIcon($facility['category']) ?> <?= e(ucfirst($facility['category'])) ?></span><?php endif; ?>
        </div>
        <h1 style="font-size:1.9rem;font-weight:700;color:var(--green-dark);margin-bottom:6px;"><?= e($facility['facility_name']) ?></h1>
        <?php if ($facility['max_capacity']): ?><p style="color:var(--gray-400);font-size:0.9rem;margin-bottom:20px;">👤 Up to <?= (int)$facility['max_capacity'] ?> guests</p><?php endif; ?>

        <!-- Description -->
        <div style="margin-bottom:28px;">
          <h2 style="font-weight:700;font-size:1.1rem;margin-bottom:10px;color:var(--gray-800);">About this facility</h2>
          <p style="color:var(--gray-500);line-height:1.7;"><?= e($facility['description']) ?></p>
        </div>

        <!-- Rates -->
        <div style="margin-bottom:28px;">
          <h2 style="font-weight:700;font-size:1.1rem;margin-bottom:14px;color:var(--gray-800);">💰 Rates</h2>
          <?php $grouped = []; foreach ($pricingRows as $p) $grouped[$p['rate_type']][] = $p; ?>
          <?php if ($sharedBase !== null): ?>
            <div style="background:var(--green-50);border-radius:var(--radius);padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:0.84rem;color:var(--gray-500);font-weight:600;">Base Rate (per booking)</span>
              <span style="font-size:1.6rem;font-weight:700;color:var(--green-dark);"><?= peso($sharedBase) ?></span>
            </div>
          <?php endif; ?>
          <div class="amenity-grid">
            <?php foreach ($grouped as $rateType => $rates): foreach ($rates as $rate): ?>
              <div class="amenity-item" style="flex-direction:column;align-items:flex-start;gap:2px;">
                <span style="font-weight:700;font-size:0.84rem;"><?= $rateType==='daytime'?'☀️':'🌙' ?> <?= e(ucfirst($rateType)) ?><?php if ($rate['guest_type']!=='general'): ?> — <?= e(ucfirst($rate['guest_type'])) ?><?php endif; ?></span>
                <?php if ($sharedBase===null): ?><span style="color:var(--green-dark);font-weight:700;"><?= peso($rate['base_price']) ?></span><?php endif; ?>
                <?php if ($rate['exceed_rate']): ?><span style="font-size:0.74rem;color:var(--gray-400);">+<?= peso($rate['exceed_rate']) ?>/excess</span><?php endif; ?>
              </div>
            <?php endforeach; endforeach; ?>
          </div>
        </div>

        <!-- Add-ons -->
        <?php if (!empty($addons)): ?>
        <div style="margin-bottom:28px;">
          <h2 style="font-weight:700;font-size:1.1rem;margin-bottom:4px;color:var(--gray-800);">✨ Add-on Services</h2>
          <p id="addonHint" style="font-size:0.82rem;color:var(--gray-400);margin-bottom:12px;">
            Pick a date and time first to see real-time availability.
          </p>
          <div class="grid-2" style="gap:10px;">
            <?php foreach ($addons as $addon):
              $aid   = (int)$addon['addon_id'];
              $limit = (int)($addon['limit_per_reservation'] ?? 0);
            ?>
            <label class="addon-card-label" id="addonCard_<?= $aid ?>">
              <input type="checkbox"
                     name="addon_ids[]" value="<?= $aid ?>"
                     id="addonCb_<?= $aid ?>" style="display:none;"
                     onchange="toggleAddonQty(<?= $aid ?>, <?= (float)$addon['addon_price'] ?>)"/>
              <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;">
                <div style="width:40px;height:40px;background:var(--green-50);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">✨</div>
                <div style="flex:1;min-width:0;">
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:2px;">
                    <p style="font-weight:700;font-size:0.88rem;color:var(--gray-800);"><?= e($addon['addon_name']) ?></p>
                    <!-- Availability badge — updated by JS -->
                    <span class="avail-badge avail-wait" id="availBadge_<?= $aid ?>">
                      <?= $limit ? "limit: $limit" : 'unlimited' ?>
                    </span>
                  </div>
                  <?php if ($addon['addon_description']): ?>
                    <p style="font-size:0.76rem;color:var(--gray-400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($addon['addon_description']) ?></p>
                  <?php endif; ?>
                  <p style="font-size:0.82rem;font-weight:700;color:var(--green-dark);margin-top:2px;"><?= peso($addon['addon_price']) ?></p>
                </div>
                <div class="addon-check-indicator" id="addonCheckIcon_<?= $aid ?>">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
              </div>
              <!-- Qty row (shown when checked) -->
              <div class="addon-qty-wrap" id="addonQty_<?= $aid ?>" style="display:none;">
                <span style="font-size:0.78rem;color:var(--gray-500);font-weight:600;">Qty:</span>
                <div class="qty-stepper">
                  <button type="button" onclick="changeQty(<?= $aid ?>, -1, <?= (float)$addon['addon_price'] ?>)">−</button>
                  <input type="number"
                         name="addon_qtys[<?= $aid ?>]"
                         id="qtyInput_<?= $aid ?>"
                         value="1" min="1"
                         max="<?= $limit ?: 9999 ?>"
                         data-limit="<?= $limit ?>"
                         class="qty-input"
                         onchange="updateAddonSub(<?= $aid ?>, <?= (float)$addon['addon_price'] ?>)"/>
                  <button type="button" onclick="changeQty(<?= $aid ?>, 1, <?= (float)$addon['addon_price'] ?>)">+</button>
                </div>
                <span class="addon-sub" id="addonSub_<?= $aid ?>" data-unit="<?= (float)$addon['addon_price'] ?>"><?= peso($addon['addon_price']) ?></span>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div><!-- /left -->

      <!-- ── RIGHT: booking form ── -->
      <div>
        <div class="card booking-card" style="padding:24px;position:sticky;top:80px;">

          <?php if ($sharedBase !== null): ?>
            <div style="margin-bottom:20px;">
              <span style="font-size:1.8rem;font-weight:700;color:var(--green-dark);"><?= peso($sharedBase) ?></span>
              <span style="font-size:0.85rem;color:var(--gray-400);"> / booking</span>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label class="form-label">Check-in Date</label>
            <input type="date" id="checkin_date" name="checkin_date" class="form-control"
                   min="<?= date('Y-m-d') ?>" value="<?= e($_POST['checkin_date'] ?? '') ?>"
                   onchange="onAnyChange()"/>
            <p class="form-error" id="checkinError"></p>
          </div>

          <div class="form-group">
            <label class="form-label">Check-in Time <span style="color:var(--gray-400);font-size:0.75rem;font-weight:400;">(min. 2:00 PM)</span></label>
            <input type="hidden" name="ci_hour"   id="ci_hour"   value="2"/>
            <input type="hidden" name="ci_minute" id="ci_minute" value="0"/>
            <input type="hidden" name="ci_ampm"   id="ci_ampm"   value="PM"/>
            <div class="time-picker-wrap">
              <div class="tp-col">
                <button type="button" class="tp-btn" onclick="stepTime('ci','hour',1)">▲</button>
                <div class="tp-val" id="ciHourDisplay">02</div>
                <button type="button" class="tp-btn" onclick="stepTime('ci','hour',-1)">▼</button>
              </div>
              <div style="display:flex;align-items:center;padding:0 4px;font-weight:700;color:var(--gray-400);">:</div>
              <div class="tp-col">
                <button type="button" class="tp-btn" onclick="stepTime('ci','minute',1)">▲</button>
                <div class="tp-val" id="ciMinuteDisplay">00</div>
                <button type="button" class="tp-btn" onclick="stepTime('ci','minute',-1)">▼</button>
              </div>
              <div class="tp-col" style="border-left:1px solid var(--gray-200);">
                <button type="button" class="tp-ampm-btn" id="ciAM" onclick="setAmPm('ci','AM')">AM</button>
                <button type="button" class="tp-ampm-btn active" id="ciPM" onclick="setAmPm('ci','PM')">PM</button>
              </div>
            </div>
            <p class="form-error" id="ciTimeError"></p>
          </div>

          <div class="form-group">
            <label class="form-label">Check-out Time</label>
            <input type="hidden" name="co_hour"   id="co_hour"   value="12"/>
            <input type="hidden" name="co_minute" id="co_minute" value="0"/>
            <input type="hidden" name="co_ampm"   id="co_ampm"   value="PM"/>
            <div class="time-picker-wrap">
              <div class="tp-col">
                <button type="button" class="tp-btn" onclick="stepTime('co','hour',1)">▲</button>
                <div class="tp-val" id="coHourDisplay">12</div>
                <button type="button" class="tp-btn" onclick="stepTime('co','hour',-1)">▼</button>
              </div>
              <div style="display:flex;align-items:center;padding:0 4px;font-weight:700;color:var(--gray-400);">:</div>
              <div class="tp-col">
                <button type="button" class="tp-btn" onclick="stepTime('co','minute',1)">▲</button>
                <div class="tp-val" id="coMinuteDisplay">00</div>
                <button type="button" class="tp-btn" onclick="stepTime('co','minute',-1)">▼</button>
              </div>
              <div class="tp-col" style="border-left:1px solid var(--gray-200);">
                <button type="button" class="tp-ampm-btn" id="coAM" onclick="setAmPm('co','AM')">AM</button>
                <button type="button" class="tp-ampm-btn active" id="coPM" onclick="setAmPm('co','PM')">PM</button>
              </div>
            </div>
            <p style="font-size:0.78rem;color:var(--gray-400);margin-top:4px;">Same-day ≤ 6PM = ☀️ Daytime &nbsp;|&nbsp; Past 6PM or next-day AM = 🌙 Overnight</p>
          </div>

          <div class="form-group">
            <label class="form-label">Check-out Date</label>
            <input type="text" id="checkout_date_display" class="form-control" placeholder="Auto-computed" readonly
                   style="background:var(--green-50);cursor:not-allowed;color:var(--gray-600);"/>
          </div>

          <div class="form-group" id="rateTypeGroup" style="display:none;">
            <label class="form-label">Rate Type</label>
            <span id="rateTypeBadge" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:var(--radius-full);font-size:0.86rem;font-weight:700;"></span>
          </div>

          <div class="form-group">
            <label class="form-label">Guests</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <p style="font-size:0.78rem;font-weight:600;color:var(--gray-500);margin-bottom:6px;">Adults</p>
                <div class="qty-stepper-inline">
                  <button type="button" onclick="changeGuest('adults_count',-1)">−</button>
                  <input type="number" id="adults_count" name="adults_count" class="form-control"
                         value="<?= (int)($_POST['adults_count'] ?? 1) ?>" min="0"
                         style="text-align:center;" onchange="recalcPrice()"/>
                  <button type="button" onclick="changeGuest('adults_count',1)">+</button>
                </div>
              </div>
              <div>
                <p style="font-size:0.78rem;font-weight:600;color:var(--gray-500);margin-bottom:6px;">Kids</p>
                <div class="qty-stepper-inline">
                  <button type="button" onclick="changeGuest('kids_count',-1)">−</button>
                  <input type="number" id="kids_count" name="kids_count" class="form-control"
                         value="<?= (int)($_POST['kids_count'] ?? 0) ?>" min="0"
                         style="text-align:center;" onchange="recalcPrice()"/>
                  <button type="button" onclick="changeGuest('kids_count',1)">+</button>
                </div>
              </div>
            </div>
            <p class="form-error" id="guestError"></p>
            <?php if ($facility['max_capacity']): ?>
              <p style="font-size:0.78rem;color:var(--gray-400);margin-top:6px;">Max capacity: <strong><?= (int)$facility['max_capacity'] ?></strong>. Excess guests incur additional fees.</p>
            <?php endif; ?>
          </div>

          <!-- Price Breakdown -->
          <div id="priceBreakdown" style="display:none;background:var(--green-50);border-radius:var(--radius);padding:16px;margin-bottom:16px;">
            <p style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--gray-400);margin-bottom:10px;">Price Breakdown</p>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.86rem;">
              <span style="color:var(--gray-600);">Base rate</span>
              <span id="bdBase" style="font-weight:700;color:var(--gray-800);">₱0</span>
            </div>
            <div id="bdExceedRow" style="display:none;justify-content:space-between;margin-bottom:6px;font-size:0.86rem;">
              <span style="color:var(--gray-600);">Excess guest fee</span>
              <span id="bdExceed" style="font-weight:700;color:var(--red);">₱0</span>
            </div>
            <div id="bdAddonRow" style="display:none;justify-content:space-between;margin-bottom:6px;font-size:0.86rem;">
              <span style="color:var(--gray-600);">Add-ons</span>
              <span id="bdAddon" style="font-weight:700;color:var(--gray-800);">₱0</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid var(--green-100);">
              <span style="font-weight:700;color:var(--green-dark);">Estimated Total</span>
              <span id="bdTotal" style="font-weight:700;font-size:1.15rem;color:var(--green-dark);">₱0</span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="font-size:1rem;padding:13px;margin-bottom:10px;">🛒 Add to Cart</button>
          <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> No payment required at this stage</div>
            <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> Free cancellation before 48 hours</div>
            <div style="display:flex;gap:8px;font-size:0.8rem;color:var(--gray-400);"><span style="color:var(--green);">✔</span> Pay upon check-in</div>
          </div>
        </div>
      </div><!-- /right -->

    </div>
  </form>
  <?php endif; ?>
</div>

<script>
const pricingMap  = <?= json_encode($pricingMap) ?>;
const maxCapacity = <?= (int)($facility['max_capacity'] ?? 0) ?>;
const AVAIL_URL   = '<?= SITE_URL ?>/guest/pages/addon_availability.php';

// ── Time picker state ──
const tp = {
  ci: { hour: 2,  minute: 0, ampm: 'PM' },
  co: { hour: 12, minute: 0, ampm: 'PM' },
};

function pad(n) { return String(n).padStart(2, '0'); }

function renderPicker(prefix) {
  const s = tp[prefix];
  document.getElementById(prefix + 'HourDisplay').textContent   = pad(s.hour);
  document.getElementById(prefix + 'MinuteDisplay').textContent = pad(s.minute);
  document.getElementById(prefix + '_hour').value   = s.hour;
  document.getElementById(prefix + '_minute').value = s.minute;
  document.getElementById(prefix + '_ampm').value   = s.ampm;
  document.getElementById(prefix + 'AM').classList.toggle('active', s.ampm === 'AM');
  document.getElementById(prefix + 'PM').classList.toggle('active', s.ampm === 'PM');
  onAnyChange();
}

function stepTime(prefix, part, delta) {
  const s = tp[prefix];
  if (part === 'hour') {
    s.hour += delta;
    if (s.hour > 12) s.hour = 1;
    if (s.hour < 1)  s.hour = 12;
  } else {
    s.minute += delta * 5;
    if (s.minute >= 60) s.minute = 0;
    if (s.minute < 0)   s.minute = 55;
  }
  renderPicker(prefix);
}

function setAmPm(prefix, val) { tp[prefix].ampm = val; renderPicker(prefix); }

function pickerToMin(prefix) {
  const s = tp[prefix];
  const h24 = s.ampm === 'PM' ? (s.hour === 12 ? 12 : s.hour + 12) : (s.hour === 12 ? 0 : s.hour);
  return h24 * 60 + s.minute;
}

function pickerTo24str(prefix) {
  const s = tp[prefix];
  const h24 = s.ampm === 'PM' ? (s.hour === 12 ? 12 : s.hour + 12) : (s.hour === 12 ? 0 : s.hour);
  return pad(h24) + ':' + pad(s.minute) + ':00';
}

function getRateType() {
  const ci = pickerToMin('ci'), co = pickerToMin('co');
  return (co > ci && co <= 18 * 60) ? 'daytime' : 'overnight';
}

function addDays(dateStr, n) {
  const d = new Date(dateStr + 'T12:00:00');
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
}
function fmtDate(s) {
  return new Date(s + 'T12:00:00').toLocaleDateString('en-PH', { weekday:'short', year:'numeric', month:'short', day:'numeric' });
}

// ── Main change handler ──
function onAnyChange() {
  const ci      = document.getElementById('checkin_date').value;
  const ciMin   = pickerToMin('ci');
  const rt      = getRateType();
  const display = document.getElementById('checkout_date_display');
  const badge   = document.getElementById('rateTypeBadge');
  const group   = document.getElementById('rateTypeGroup');
  const ciErr   = document.getElementById('ciTimeError');

  ciErr.textContent = ciMin < 14 * 60 ? 'Check-in time must be 2:00 PM or later.' : '';

  const coDate = rt === 'daytime' ? ci : (ci ? addDays(ci, 1) : '');
  display.value = !ci ? 'Select check-in date first' : (coDate ? fmtDate(coDate) : '');

  group.style.display = 'block';
  if (rt === 'daytime') {
    badge.innerHTML = '☀️ Daytime'; badge.style.background = '#fef9c3'; badge.style.color = '#854d0e';
  } else {
    badge.innerHTML = '🌙 Overnight'; badge.style.background = '#dbeafe'; badge.style.color = '#1e40af';
  }

  recalcPrice();
  scheduleAvailCheck();
}

// ── Availability AJAX (debounced 600ms) ──
let availTimer = null;
function scheduleAvailCheck() {
  clearTimeout(availTimer);
  availTimer = setTimeout(fetchAvailability, 600);
}

function fetchAvailability() {
  const ci = document.getElementById('checkin_date').value;
  if (!ci) return;

  const rt      = getRateType();
  const coDate  = rt === 'daytime' ? ci : addDays(ci, 1);
  const ciTime  = pickerTo24str('ci');
  const coTime  = pickerTo24str('co');

  // Show loading badges
  document.querySelectorAll('.avail-badge').forEach(b => {
    b.className   = 'avail-badge avail-wait';
    b.textContent = 'checking…';
  });

  const fd = new FormData();
  fd.append('checkin_date',  ci);
  fd.append('checkout_date', coDate);
  fd.append('checkin_time',  ciTime);
  fd.append('checkout_time', coTime);

  fetch(AVAIL_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => applyAvailability(data))
    .catch(() => {
      document.querySelectorAll('.avail-badge').forEach(b => {
        b.className = 'avail-badge avail-wait'; b.textContent = 'unavailable';
      });
    });
}

function applyAvailability(data) {
  // data: { addon_id: remaining|null }
  document.getElementById('addonHint').textContent = 'Availability shown for your selected dates.';

  for (const [aidStr, remaining] of Object.entries(data)) {
    const aid   = parseInt(aidStr);
    const card  = document.getElementById('addonCard_' + aid);
    const badge = document.getElementById('availBadge_' + aid);
    const cb    = document.getElementById('addonCb_' + aid);
    const qtyIn = document.getElementById('qtyInput_' + aid);
    if (!card || !badge) continue;

    if (remaining === null) {
      // Unlimited
      badge.className   = 'avail-badge avail-unlim';
      badge.textContent = 'unlimited';
      card.classList.remove('addon-unavailable');
      if (cb) cb.disabled = false;
    } else if (remaining === 0) {
      // Fully booked — disable
      badge.className   = 'avail-badge avail-none';
      badge.textContent = 'fully booked';
      card.classList.add('addon-unavailable');
      card.classList.remove('addon-card-active');
      if (cb) { cb.checked = false; cb.disabled = true; }
      document.getElementById('addonQty_' + aid).style.display = 'none';
      document.getElementById('addonCheckIcon_' + aid).querySelector('svg').style.display = 'none';
    } else {
      // Some available
      const cls  = remaining <= 3 ? 'avail-low' : 'avail-ok';
      badge.className   = 'avail-badge ' + cls;
      badge.textContent = remaining + ' left';
      card.classList.remove('addon-unavailable');
      if (cb) { cb.disabled = false; }
      // Cap the qty stepper max to remaining
      if (qtyIn) {
        qtyIn.max = remaining;
        if (parseInt(qtyIn.value) > remaining) {
          qtyIn.value = remaining;
          updateAddonSub(aid, parseFloat(document.getElementById('addonSub_' + aid)?.dataset.unit || 0));
        }
      }
    }
  }
  recalcPrice();
}

// ── Guest steppers ──
function changeGuest(id, delta) {
  const el = document.getElementById(id);
  el.value = Math.max(0, parseInt(el.value || 0) + delta);
  recalcPrice();
}

// ── Price recalc ──
function recalcPrice() {
  const rt       = getRateType();
  const rateData = pricingMap[rt] || {};
  const firstKey = Object.keys(rateData)[0];
  const base     = firstKey ? rateData[firstKey].base_price : 0;
  if (!base) { document.getElementById('priceBreakdown').style.display = 'none'; return; }

  const adults = parseInt(document.getElementById('adults_count').value || 0);
  const kids   = parseInt(document.getElementById('kids_count').value   || 0);
  const total  = adults + kids;
  let exc = 0;
  if (maxCapacity > 0 && total > maxCapacity) {
    const er = rateData['adults']?.exceed_rate ?? rateData['general']?.exceed_rate ?? 0;
    exc = (total - maxCapacity) * er;
  }

  let addons = 0;
  document.querySelectorAll('input[name="addon_ids[]"]:checked').forEach(cb => {
    const aid  = cb.value;
    const qty  = parseInt(document.getElementById('qtyInput_' + aid)?.value || 1);
    const unit = parseFloat(document.getElementById('addonSub_'  + aid)?.dataset.unit || 0);
    addons += unit * qty;
  });

  const grand = base + exc + addons;
  document.getElementById('priceBreakdown').style.display = 'block';
  document.getElementById('bdBase').textContent  = '₱' + base.toLocaleString();
  document.getElementById('bdExceedRow').style.display = exc > 0 ? 'flex' : 'none';
  if (exc > 0) document.getElementById('bdExceed').textContent = '+₱' + exc.toLocaleString();
  document.getElementById('bdAddonRow').style.display = addons > 0 ? 'flex' : 'none';
  if (addons > 0) document.getElementById('bdAddon').textContent = '₱' + addons.toLocaleString();
  document.getElementById('bdTotal').textContent = '₱' + grand.toLocaleString();
}

// ── Addon toggle ──
function toggleAddonQty(aid, price) {
  const cb   = document.getElementById('addonCb_' + aid);
  const wrap = document.getElementById('addonQty_' + aid);
  const card = document.getElementById('addonCard_' + aid);
  const svg  = document.getElementById('addonCheckIcon_' + aid)?.querySelector('svg');
  if (cb.checked) {
    wrap.style.display = 'flex';
    card.classList.add('addon-card-active');
    if (svg) svg.style.display = '';
  } else {
    wrap.style.display = 'none';
    card.classList.remove('addon-card-active');
    if (svg) svg.style.display = 'none';
  }
  recalcPrice();
}

function changeQty(aid, delta, price) {
  const input = document.getElementById('qtyInput_' + aid);
  const max   = parseInt(input.max) || 9999;
  input.value = Math.min(max, Math.max(1, parseInt(input.value || 1) + delta));
  updateAddonSub(aid, price);
}

function updateAddonSub(aid, price) {
  const qty = parseInt(document.getElementById('qtyInput_' + aid).value || 1);
  const sub = document.getElementById('addonSub_' + aid);
  if (sub) sub.textContent = '₱' + (price * qty).toLocaleString();
  recalcPrice();
}

// ── Init ──
document.addEventListener('DOMContentLoaded', function () {
  renderPicker('ci');
  renderPicker('co');
  onAnyChange();

  document.getElementById('bookingForm')?.addEventListener('submit', function (e) {
    let valid = true;
    const ci     = document.getElementById('checkin_date').value;
    const ciMin  = pickerToMin('ci');
    const adults = parseInt(document.getElementById('adults_count').value || 0);
    const kids   = parseInt(document.getElementById('kids_count').value   || 0);

    document.getElementById('checkinError').textContent = '';
    document.getElementById('guestError').textContent   = '';

    if (!ci) {
      document.getElementById('checkinError').textContent = 'Check-in date is required.';
      document.getElementById('checkin_date').classList.add('input-error');
      valid = false;
    } else {
      document.getElementById('checkin_date').classList.remove('input-error');
    }
    if (ciMin < 14 * 60) {
      document.getElementById('ciTimeError').textContent = 'Check-in time must be 2:00 PM or later.';
      valid = false;
    }
    if (adults + kids === 0) {
      document.getElementById('guestError').textContent = 'At least one guest is required.';
      valid = false;
    }

    // Block submit if any checked addon is now unavailable
    let addonBlocked = false;
    document.querySelectorAll('input[name="addon_ids[]"]:checked').forEach(cb => {
      if (document.getElementById('addonCard_' + cb.value)?.classList.contains('addon-unavailable')) {
        addonBlocked = true;
      }
    });
    if (addonBlocked) {
      alert('One or more selected add-ons are no longer available for your chosen time. Please adjust your selection.');
      valid = false;
    }

    if (!valid) e.preventDefault();
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>