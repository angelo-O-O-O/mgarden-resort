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

// Recent reviews for homepage showcase
$_rr = $db->query("
    SELECT rv.rating, rv.review_text, rv.created_at, g.guest_name, f.facility_name
    FROM reviews rv
    JOIN guests g  ON rv.guest_id    = g.guest_id
    JOIN facilities f ON rv.facility_id = f.facility_id
    WHERE rv.status = 'approved'
    ORDER BY rv.created_at DESC LIMIT 6
");
$recentReviews = $_rr ? $_rr->fetch_all(MYSQLI_ASSOC) : [];
$_rs = $db->query("SELECT COUNT(*) AS total, COALESCE(AVG(rating),0) AS avg_rating FROM reviews WHERE status='approved'");
$_rsRow      = $_rs ? $_rs->fetch_assoc() : ['total' => 0, 'avg_rating' => 0];
$liveAvgRating   = round((float)$_rsRow['avg_rating'], 1);
$liveTotalReviews = (int)$_rsRow['total'];

function catIcon($category) {
    $map = [
        'pool'=>'fa-solid fa-person-swimming',
        'beach'=>'fa-solid fa-umbrella-beach',
        'accommodation'=>'fa-solid fa-bed',
        'dining'=>'fa-solid fa-utensils',
        'spa'=>'fa-solid fa-spa',
        'sports'=>'fa-solid fa-person-running',
        'event'=>'fa-solid fa-calendar-days',
        'activity'=>'fa-solid fa-bullseye',
        'resort'=>'fa-solid fa-hotel'
    ];
    $cat = strtolower(trim($category ?? ''));
    foreach ($map as $key => $icon) {
        if (str_contains($cat, $key)) {
            return "<i class=\"{$icon}\"></i>";
        }
    }
    return '<i class="fa-solid fa-star"></i>';
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
      <div class="hero-badge"><i class="fas fa-map-marker-alt"></i> <?= e($address) ?></div>
      <h1><?= e($tagline) ?><br><span>in Batangas</span></h1>
      <p><?= e($description) ?></p>
      <div class="hero-btns">
        <a href="#facilities" class="btn btn-primary" style="font-size:1rem;padding:13px 28px;">Explore Facilities ↓</a>
        <a href="#about"      class="btn btn-ghost"   style="font-size:1rem;padding:13px 28px;">About Us</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><p><?= count($facilities) ?>+</p><p>Facilities</p></div>
        <div class="hero-stat"><p><?= $liveAvgRating > 0 ? number_format($liveAvgRating, 1) : '4.9' ?> <i class="fa-solid fa-star" style="color: var(--yellow);"></i></p><p>Rating</p></div>
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
      <div class="feature-item reveal stagger-1"><div class="feature-icon"><i class="fa-solid fa-leaf"></i></div><p class="feature-title">Lush Garden Grounds</p><p class="feature-desc">Surrounded by tropical flora and tranquil landscapes.</p></div>
      <div class="feature-item reveal stagger-2"><div class="feature-icon"><i class="fa-solid fa-umbrella-beach"></i></div><p class="feature-title">Beach Access</p><p class="feature-desc">Enjoy the resort's beautiful beach and crystal waters.</p></div>
      <div class="feature-item reveal stagger-3"><div class="feature-icon"><i class="fa-solid fa-utensils"></i></div><p class="feature-title">Resort Dining</p><p class="feature-desc">Fresh, locally-sourced ingredients every meal.</p></div>
      <div class="feature-item reveal stagger-4"><div class="feature-icon"><i class="fa-solid fa-shield-alt"></i></div><p class="feature-title">24/7 Security</p><p class="feature-desc">Round-the-clock security for your peace of mind.</p></div>
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
        <div class="empty-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
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
              <span style="font-size:0.8rem;color:var(--gray-400);"><i class="fa-solid fa-users"></i> Up to <?= (int)$f['max_capacity'] ?></span>
            <?php endif; ?>
          </div>
          <p class="room-desc"><?= e($f['description']) ?></p>
          <?php if (!$f['daytime_price'] && !$f['overnight_price']): ?>
          <div class="room-tags"><span class="tag">Contact for pricing</span></div>
          <?php endif; ?>
          <div class="room-footer">
            <div>
              <?php if ($f['daytime_price'] || $f['overnight_price']): ?>
                <span class="room-price"><span style="font-size:0.8rem;font-weight:500;color:var(--gray-400);">Starts at</span> <?= peso($f['daytime_price'] ?: $f['overnight_price']) ?></span>
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
        $grouped = [];
        foreach ($fData['rates'] as $rate) {
            $grouped[$rate['rate_type']][] = $rate;
        }
        $lowestPrice = min(array_column($fData['rates'], 'base_price'));
      ?>
      <div class="pricing-pro-card">

        <!-- Header: category badge, name, starting price -->
        <div class="pricing-pro-header">
          <?php if ($fData['category']): ?>
            <div style="margin-bottom:12px;">
              <span class="pricing-cat-badge"><?= e(ucfirst($fData['category'])) ?></span>
            </div>
          <?php endif; ?>
          <h3 class="pricing-pro-name"><?= e($fData['name']) ?></h3>
          <div style="margin-top:14px;">
            <p style="font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.38);margin-bottom:4px;">Starting from</p>
            <p style="font-size:2rem;font-weight:800;color:var(--yellow);line-height:1;letter-spacing:-0.01em;"><?= peso($lowestPrice) ?></p>
          </div>
        </div>

        <!-- Rate breakdown -->
        <div class="pricing-pro-body">
          <?php foreach ($grouped as $rateType => $rates): ?>
          <div>
            <div class="pricing-rate-type-label">
              <?= $rateType === 'daytime' ? '<i class="fa-solid fa-sun"></i> Daytime' : '<i class="fa-solid fa-moon"></i> Overnight' ?>
            </div>
            <?php foreach ($rates as $rate): ?>
            <div class="pricing-rate-row">
              <span class="pricing-guest-label">
                <?= $rate['guest_type'] === 'general' ? 'All guests' : e(ucfirst($rate['guest_type'])) ?>
              </span>
              <div class="pricing-price-col">
                <?php if ($rate['exceed_rate']): ?>
                  <span class="pricing-exceed-note">+<?= peso($rate['exceed_rate']) ?> / excess fee</span>
                <?php else: ?>
                  <span style="font-size:0.78rem;color:rgba(255,255,255,0.25);">—</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- CTA -->
        <div class="pricing-pro-footer">
          <a href="#facilities" class="btn btn-yellow btn-sm btn-full">
            <i class="fa-solid fa-calendar-check"></i> Book This Facility
          </a>
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
          <i class="fa-solid fa-leaf"></i> <?= e($resortName) ?>
        </div>
        <h3 style="font-size:1.7rem;font-weight:700;color:var(--green-dark);line-height:1.3;margin-bottom:16px;">
          <?= e($tagline) ?>
        </h3>
        <p style="color:var(--gray-500);font-size:0.96rem;line-height:1.8;margin-bottom:24px;">
          <?= e($description) ?>
        </p>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div class="contact-card">
            <div class="contact-icon"><i class="fa-solid fa-map-marker-alt"></i></div>
            <div><p class="contact-label">Location</p><p class="contact-val"><?= e($address) ?></p></div>
          </div>
          <div class="contact-card">
            <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
            <div><p class="contact-label">Phone</p><p class="contact-val">+63 912 345 6789</p></div>
          </div>
          <div class="contact-card">
            <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
            <div><p class="contact-label">Email</p><p class="contact-val">hello@mgardenresort.com</p></div>
          </div>
          <div class="contact-card">
            <div class="contact-icon"><i class="fa-solid fa-clock"></i></div>
            <div>
              <p class="contact-label">Resort Hours</p>
              <p class="contact-val">Check-in: <strong>2:00 PM</strong> &nbsp;|&nbsp; Check-out: <strong>12:00 PM</strong><br>Front Desk: <strong>24/7</strong></p>
            </div>
          </div>
        </div>
        <div style="margin-top:28px;display:flex;gap:10px;flex-wrap:wrap;">
          <a href="mailto:hello@mgardenresort.com" class="btn btn-primary"><i class="fa-solid fa-envelope"></i> Email Us</a>
          <a href="https://maps.google.com/?q=<?= urlencode($address) ?>" target="_blank" rel="noopener" class="btn btn-outline"><i class="fa-solid fa-map-marked-alt"></i> Get Directions</a>
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
            <p style="font-size:1.8rem;font-weight:700;color:var(--green);"><?= $liveAvgRating > 0 ? number_format($liveAvgRating, 1) : '4.9' ?><i class="fa-solid fa-star" style="color: var(--yellow); margin-left: 4px;"></i></p>
            <p style="font-size:0.78rem;color:var(--gray-400);font-weight:600;">Rating</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- REVIEWS -->
<?php if (!empty($recentReviews)): ?>
<section class="section section-light" id="reviews">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title">What Our Guests Say</h2>
      <p class="section-sub">
        <?php if ($liveAvgRating > 0): ?>
          <span style="color:var(--yellow);font-size:1.1rem;"><?= str_repeat('★', (int)round($liveAvgRating)) ?></span>
          <strong style="color:var(--green-dark);"> <?= number_format($liveAvgRating, 1) ?></strong>
          <span style="color:var(--gray-400);"> · <?= $liveTotalReviews ?> review<?= $liveTotalReviews !== 1 ? 's' : '' ?></span>
        <?php else: ?>
          Trusted by our guests
        <?php endif; ?>
      </p>
    </div>

    <div class="grid-3" style="gap:18px;">
      <?php foreach ($recentReviews as $rv): ?>
      <div class="card" style="padding:22px;border:2px solid var(--green-100);">
        <!-- Reviewer -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
          <div style="width:40px;height:40px;background:var(--green-100);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);font-size:1rem;flex-shrink:0;">
            <?= strtoupper(substr($rv['guest_name'] ?? 'G', 0, 1)) ?>
          </div>
          <div>
            <?php
              $parts = explode(' ', trim($rv['guest_name'] ?? 'Guest'));
              $displayName = count($parts) > 1
                ? $parts[0] . ' ' . strtoupper(substr(end($parts), 0, 1)) . '.'
                : $parts[0];
            ?>
            <p style="font-weight:700;font-size:0.88rem;color:var(--gray-800);"><?= e($displayName) ?></p>
            <p style="font-size:0.7rem;color:var(--gray-400);"><?= date('M Y', strtotime($rv['created_at'])) ?></p>
          </div>
        </div>
        <!-- Stars -->
        <div style="color:var(--yellow);font-size:1.05rem;letter-spacing:2px;margin-bottom:10px;">
          <?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?>
        </div>
        <!-- Text -->
        <?php if (!empty($rv['review_text'])): ?>
          <p style="color:var(--gray-600);font-size:0.87rem;line-height:1.65;margin-bottom:10px;">
            "<?= e(mb_strimwidth($rv['review_text'], 0, 140, '…')) ?>"
          </p>
        <?php endif; ?>
        <!-- Facility tag -->
        <p style="font-size:0.74rem;color:var(--green);font-weight:600;">
          <i class="fa-solid fa-umbrella-beach"></i> <?= e($rv['facility_name']) ?>
        </p>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center" style="margin-top:32px;">
      <a href="<?= SITE_URL ?>/guest/pages/reviews.php" class="btn btn-outline">
        <i class="fa-solid fa-star"></i> See All Reviews
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section class="cta-section">
  <div class="container">
    <h2>Ready for Your Paradise Escape?</h2>
    <p>Sign up and start booking your stay at <?= e($resortName) ?> — your tropical getaway awaits.</p>
    <a href="#facilities" class="btn btn-white" style="font-size:1.05rem;padding:14px 36px;">Reserve Now</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>