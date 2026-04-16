<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/config.php';

$db = getDB();

// Resort Info
$infoRows = $db->query("SELECT info_key, key_value FROM resort_info")->fetch_all(MYSQLI_ASSOC);
$info = [];
foreach ($infoRows as $row) { $info[$row['info_key']] = $row['key_value']; }
$resortName  = $info['resort_name']  ?? SITE_NAME;
$tagline     = $info['tagline']      ?? 'Where Nature Meets Luxury';
$description = $info['description']  ?? 'Your perfect tropical escape in the heart of Batangas.';
$address     = $info['address']      ?? 'Calatagan, Batangas, Philippines';

// Facilities with pricing
$facilities = $db->query("
    SELECT f.facility_id, f.facility_name, f.description, f.max_capacity, f.category, f.photo,
        MIN(CASE WHEN p.rate_type = 'daytime'   THEN p.base_price END) AS daytime_price,
        MIN(CASE WHEN p.rate_type = 'overnight' THEN p.base_price END) AS overnight_price
    FROM facilities f
    LEFT JOIN pricing p ON f.facility_id = p.facility_id
    WHERE f.availability = 'available'
    GROUP BY f.facility_id
    ORDER BY f.facility_id ASC
")->fetch_all(MYSQLI_ASSOC);

// Pricing grouped by facility
$pricingRows = $db->query("
    SELECT p.*, f.facility_name, f.category
    FROM pricing p
    JOIN facilities f ON p.facility_id = f.facility_id
    WHERE f.availability = 'available'
    ORDER BY f.facility_id ASC, p.rate_type ASC, p.guest_type ASC
")->fetch_all(MYSQLI_ASSOC);

$pricingByFacility = [];
foreach ($pricingRows as $row) {
    $fid = $row['facility_id'];
    if (!isset($pricingByFacility[$fid])) {
        $pricingByFacility[$fid] = ['name' => $row['facility_name'], 'category' => $row['category'], 'rates' => []];
    }
    $pricingByFacility[$fid]['rates'][] = $row;
}

function catIcon($category) {
    $map = ['pool'=>'🏊','beach'=>'🏖️','accommodation'=>'🛏️','dining'=>'🍽️','spa'=>'💆','sports'=>'🏄','event'=>'🎉','activity'=>'🎯','resort'=>'🏨'];
    $cat = strtolower(trim($category ?? ''));
    foreach ($map as $key => $icon) { if (str_contains($cat, $key)) return $icon; }
    return '✨';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <img src="<?= SITE_URL ?>/images/cover_photo.jpg" alt="<?= e($resortName) ?>" class="hero-bg-img"
       onerror="this.src='https://images.unsplash.com/photo-1501854140801-50d01698950b?w=1600&q=80'"/>
  <div class="hero-bg"></div>
  <div class="container">
    <div class="hero-content">
      <div class="hero-badge">📍 <?= e($address) ?></div>
      <h1><?= e($tagline) ?><br><span>in Batangas</span></h1>
      <p><?= e($description) ?></p>
      <div class="hero-btns">
        <a href="#facilities" class="btn btn-primary" style="font-size:1rem;padding:13px 28px;">Explore Facilities ↓</a>
        <a href="#about"      class="btn btn-ghost"   style="font-size:1rem;padding:13px 28px;">About Us</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><p><?= count($facilities) ?>+</p><p>Facilities</p></div>
        <div class="hero-stat"><p>4.9 ★</p><p>Rating</p></div>
        <div class="hero-stat"><p>500+</p><p>Happy Guests</p></div>
      </div>
    </div>
  </div>
  <div class="scroll-indicator"><div class="scroll-dot"></div></div>
</section>

<!-- FEATURES STRIP -->
<section class="section-light" style="padding:0;">
  <div class="container">
    <div class="features-grid">
      <div class="feature-item"><div class="feature-icon">🌿</div><p class="feature-title">Lush Garden Grounds</p><p class="feature-desc">Surrounded by tropical flora and tranquil landscapes.</p></div>
      <div class="feature-item"><div class="feature-icon">🏖️</div><p class="feature-title">Beach Access</p><p class="feature-desc">Enjoy the resort's beautiful beach and crystal waters.</p></div>
      <div class="feature-item"><div class="feature-icon">🍳</div><p class="feature-title">Resort Dining</p><p class="feature-desc">Fresh, locally-sourced ingredients every meal.</p></div>
      <div class="feature-item"><div class="feature-icon">🛡️</div><p class="feature-title">24/7 Security</p><p class="feature-desc">Round-the-clock security for your peace of mind.</p></div>
    </div>
  </div>
</section>

<!-- FACILITIES -->
<section class="section" id="facilities">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title">Our Facilities</h2>
      <p class="section-sub">Everything you need for the perfect getaway</p>
    </div>
    <?php if (empty($facilities)): ?>
      <div class="empty-state">
        <div class="empty-icon">🏖️</div>
        <p class="empty-title">Facilities coming soon</p>
        <p class="empty-desc">We are preparing amazing experiences for you. Check back soon!</p>
      </div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach ($facilities as $f):
        $icon    = catIcon($f['category']);
        $hasPhoto = !empty($f['photo']);
        $imgSrc  = $hasPhoto
            ? SITE_URL . '/guest/pages/facility_photo.php?id=' . (int)$f['facility_id']
            : 'https://placehold.co/600x400/d1fae5/065f46?text=' . urlencode($f['facility_name']) . '&font=quicksand';
        $fallback = 'https://placehold.co/600x400/d1fae5/065f46?text=' . urlencode($f['facility_name']) . '&font=quicksand';
      ?>
      <div class="card room-card">
        <div class="room-img-wrap">
          <img src="<?= $imgSrc ?>" alt="<?= e($f['facility_name']) ?>" onerror="this.src='<?= $fallback ?>'"/>
          <?php if ($f['category']): ?>
            <div class="room-category-badge"><?= $icon ?> <?= e(ucfirst($f['category'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="room-body">
          <div class="flex justify-between items-center" style="margin-bottom:6px;">
            <h3 class="room-title"><?= e($f['facility_name']) ?></h3>
            <?php if ($f['max_capacity']): ?>
              <span style="font-size:0.8rem;color:var(--gray-400);">👤 Up to <?= (int)$f['max_capacity'] ?></span>
            <?php endif; ?>
          </div>
          <p class="room-desc"><?= e($f['description']) ?></p>
          <div class="room-tags">
            <?php if ($f['daytime_price']): ?>
              <span class="tag">☀️ Day: <?= peso($f['daytime_price']) ?></span>
            <?php endif; ?>
            <?php if ($f['overnight_price']): ?>
              <span class="tag">🌙 Night: <?= peso($f['overnight_price']) ?></span>
            <?php endif; ?>
            <?php if (!$f['daytime_price'] && !$f['overnight_price']): ?>
              <span class="tag">Contact for pricing</span>
            <?php endif; ?>
          </div>
          <div class="room-footer">
            <div>
              <?php if ($f['daytime_price']): ?>
                <span class="room-price"><?= peso($f['daytime_price']) ?><span> / daytime</span></span>
              <?php elseif ($f['overnight_price']): ?>
                <span class="room-price"><?= peso($f['overnight_price']) ?><span> / overnight</span></span>
              <?php else: ?>
                <span class="room-price" style="font-size:1rem;color:var(--gray-400);">See pricing below</span>
              <?php endif; ?>
            </div>
            <a href="<?= SITE_URL ?>/guest/pages/book_info.php?id=<?= (int)$f['facility_id'] ?>" class="btn btn-primary btn-sm">Book Now</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- PRICING -->
<?php if (!empty($pricingByFacility)): ?>
<section class="section section-dark" id="pricing">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title" style="color:#fff;">Rates &amp; Pricing</h2>
      <p class="section-sub" style="color:rgba(255,255,255,0.6);">Transparent pricing for every guest</p>
    </div>
    <div class="grid-3">
      <?php foreach ($pricingByFacility as $fid => $fData):
        // Group rates by rate_type
        $grouped   = [];
        foreach ($fData['rates'] as $rate) {
            $grouped[$rate['rate_type']][] = $rate;
        }
        // Check if all rates share the same base price
        $allPrices = array_unique(array_column($fData['rates'], 'base_price'));
        $sharedBase = count($allPrices) === 1 ? $allPrices[0] : null;
      ?>
      <div class="package-card" style="display:flex;flex-direction:column;">
        <div class="package-icon"><?= catIcon($fData['category']) ?></div>
        <h3 class="package-name"><?= e($fData['name']) ?></h3>
        <?php if ($fData['category']): ?>
          <p style="color:var(--yellow);font-size:0.73rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:16px;">
            <?= e(ucfirst($fData['category'])) ?>
          </p>
        <?php endif; ?>

        <?php if ($sharedBase !== null): ?>
          <!-- Single base price shown once prominently -->
          <div style="background:rgba(255,255,255,0.08);border-radius:10px;padding:14px 16px;margin-bottom:18px;text-align:center;">
            <p style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px;">
              Base Rate (per booking)
            </p>
            <p style="color:var(--yellow);font-size:1.9rem;font-weight:700;line-height:1;">
              <?= peso($sharedBase) ?>
            </p>
          </div>

          <!-- Breakdown: rate_type → guest_type → exceed rate only -->
          <div style="flex:1;">
            <?php foreach ($grouped as $rateType => $rates): ?>
              <p style="font-size:0.72rem;font-weight:700;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.07em;margin:12px 0 6px;">
                <?= $rateType === 'daytime' ? '☀️ Daytime' : '🌙 Overnight' ?>
              </p>
              <?php foreach ($rates as $rate): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid rgba(255,255,255,0.06);">
                  <span style="font-size:0.86rem;color:rgba(255,255,255,0.85);font-weight:600;">
                    <?= $rate['guest_type'] === 'general' ? 'All guests' : e(ucfirst($rate['guest_type'])) ?>
                  </span>
                  <?php if ($rate['exceed_rate']): ?>
                    <span style="font-size:0.75rem;background:rgba(234,179,8,0.15);color:var(--yellow);padding:3px 9px;border-radius:var(--radius-full);font-weight:700;white-space:nowrap;">
                      +<?= peso($rate['exceed_rate']) ?>/excess
                    </span>
                  <?php else: ?>
                    <span style="font-size:0.78rem;color:rgba(255,255,255,0.3);">—</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>

        <?php else: ?>
          <!-- Different base prices per rate — show full breakdown -->
          <div style="flex:1;">
            <?php foreach ($grouped as $rateType => $rates): ?>
              <p style="font-size:0.72rem;font-weight:700;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.07em;margin:12px 0 6px;">
                <?= $rateType === 'daytime' ? '☀️ Daytime' : '🌙 Overnight' ?>
              </p>
              <?php foreach ($rates as $rate): ?>
                <div style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.06);">
                  <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.86rem;color:rgba(255,255,255,0.85);font-weight:600;">
                      <?= $rate['guest_type'] === 'general' ? 'All guests' : e(ucfirst($rate['guest_type'])) ?>
                    </span>
                    <span style="color:var(--yellow);font-weight:700;font-size:0.95rem;"><?= peso($rate['base_price']) ?></span>
                  </div>
                  <?php if ($rate['exceed_rate']): ?>
                    <span style="font-size:0.74rem;color:rgba(255,255,255,0.4);">+<?= peso($rate['exceed_rate']) ?>/excess guest</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div style="margin-top:20px;">
          <span class="btn btn-yellow btn-sm btn-full nav-disabled" title="Booking coming soon">Book This Facility</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ABOUT US -->
<section class="section section-light" id="about">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title">About Us</h2>
      <p class="section-sub">Get to know <?= e($resortName) ?></p>
    </div>

    <div class="grid-2" style="gap:48px;align-items:center;">

      <!-- Text & contact info -->
      <div>
        <div style="display:inline-flex;align-items:center;gap:8px;background:var(--green-100);color:var(--green-dark);font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 16px;border-radius:var(--radius-full);margin-bottom:20px;">
          🌿 <?= e($resortName) ?>
        </div>
        <h3 style="font-size:1.7rem;font-weight:700;color:var(--green-dark);line-height:1.3;margin-bottom:16px;">
          <?= e($tagline) ?>
        </h3>
        <p style="color:var(--gray-500);font-size:0.96rem;line-height:1.8;margin-bottom:24px;">
          <?= e($description) ?>
        </p>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div class="contact-card">
            <div class="contact-icon">📍</div>
            <div><p class="contact-label">Location</p><p class="contact-val"><?= e($address) ?></p></div>
          </div>
          <div class="contact-card">
            <div class="contact-icon">📞</div>
            <div><p class="contact-label">Phone</p><p class="contact-val">+63 912 345 6789</p></div>
          </div>
          <div class="contact-card">
            <div class="contact-icon">✉️</div>
            <div><p class="contact-label">Email</p><p class="contact-val">hello@mgardenresort.com</p></div>
          </div>
          <div class="contact-card">
            <div class="contact-icon">⏰</div>
            <div>
              <p class="contact-label">Resort Hours</p>
              <p class="contact-val">Check-in: <strong>2:00 PM</strong> &nbsp;|&nbsp; Check-out: <strong>12:00 PM</strong><br>Front Desk: <strong>24/7</strong></p>
            </div>
          </div>
        </div>
        <div style="margin-top:28px;display:flex;gap:10px;flex-wrap:wrap;">
          <a href="mailto:hello@mgardenresort.com" class="btn btn-primary">✉️ Email Us</a>
          <a href="https://maps.google.com/?q=<?= urlencode($address) ?>" target="_blank" rel="noopener" class="btn btn-outline">🗺️ Get Directions</a>
        </div>
      </div>

      <!-- Photo + stats -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <div style="border-radius:var(--radius-lg);overflow:hidden;height:240px;box-shadow:var(--shadow-hover);">
          <img
            src="<?= SITE_URL ?>/images/cover_photo.jpg"
            alt="<?= e($resortName) ?>"
            style="width:100%;height:100%;object-fit:cover;"
            onerror="this.src='https://images.unsplash.com/photo-1501854140801-50d01698950b?w=800&q=80'"
          />
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="card" style="padding:20px;text-align:center;">
            <p style="font-size:1.8rem;font-weight:700;color:var(--green);"><?= count($facilities) ?>+</p>
            <p style="font-size:0.78rem;color:var(--gray-400);font-weight:600;">Facilities</p>
          </div>
          <div class="card" style="padding:20px;text-align:center;">
            <p style="font-size:1.8rem;font-weight:700;color:var(--green);">500+</p>
            <p style="font-size:0.78rem;color:var(--gray-400);font-weight:600;">Happy Guests</p>
          </div>
          <div class="card" style="padding:20px;text-align:center;">
            <p style="font-size:1.8rem;font-weight:700;color:var(--green);">4.9★</p>
            <p style="font-size:0.78rem;color:var(--gray-400);font-weight:600;">Rating</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="container">
    <h2>Ready for Your Paradise Escape?</h2>
    <p>Sign up and start booking your stay at <?= e($resortName) ?> — your tropical getaway awaits.</p>
    <span class="btn btn-white nav-disabled" style="font-size:1.05rem;padding:14px 36px;cursor:not-allowed;opacity:0.75;" title="Coming soon">Reserve Now</span>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>