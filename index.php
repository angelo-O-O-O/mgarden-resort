<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Fetch rooms
$rooms = $db->query("SELECT r.*, rc.name as category_name 
    FROM rooms r 
    JOIN room_categories rc ON r.category_id = rc.id 
    WHERE r.is_available = 1 
    ORDER BY r.price_per_night ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch services (first 6)
$services = $db->query("SELECT * FROM services WHERE is_active = 1 LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Fetch packages
$packages = $db->query("SELECT * FROM packages WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

$catIcons = ['activity'=>'🏄','food_beverage'=>'🍽️','spa'=>'💆','transport'=>'🚐','event'=>'🎉','other'=>'✨'];
$pkgIcons = ['Romantic'=>'💑','Family'=>'👨‍👩‍👧‍👦','Beach'=>'🧘','Retreat'=>'🌿'];
?>

<!-- ── HERO ── -->
<section class="hero">
  <img src="https://images.unsplash.com/photo-1506197603052-3cc9c3a201bd?w=1600&q=80" alt="MGarden Beach Resort" class="hero-bg-img"/>
  <div class="hero-bg"></div>
  <div class="container">
    <div class="hero-content">
      <div class="hero-badge">📍 Calatagan, Batangas, Philippines</div>
      <h1>Where Nature<br><span>Meets Luxury</span></h1>
      <p>Escape to MGarden Beach Resort — a tropical paradise where lush gardens, crystal waters, and world-class hospitality await you.</p>
      <div class="hero-btns">
        <a href="#rooms" class="btn btn-primary" style="font-size:1rem;padding:13px 28px;">Explore & Book ↓</a>
        <a href="<?= SITE_URL ?>/pages/about.php" class="btn btn-ghost" style="font-size:1rem;padding:13px 28px;">Learn More</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><p>500+</p><p>Happy Guests</p></div>
        <div class="hero-stat"><p>15+</p><p>Room Types</p></div>
        <div class="hero-stat"><p>4.9 ★</p><p>Rating</p></div>
      </div>
    </div>
  </div>
  <div class="scroll-indicator"><div class="scroll-dot"></div></div>
</section>

<!-- ── FEATURES ── -->
<section class="section-light" style="padding:0;">
  <div class="container">
    <div class="features-grid">
      <div class="feature-item"><div class="feature-icon">🌿</div><p class="feature-title">Lush Garden Grounds</p><p class="feature-desc">Surrounded by tropical flora and tranquil landscapes.</p></div>
      <div class="feature-item"><div class="feature-icon">🏖️</div><p class="feature-title">Private Beach Access</p><p class="feature-desc">Exclusive beach access for resort guests only.</p></div>
      <div class="feature-item"><div class="feature-icon">🍳</div><p class="feature-title">Farm-to-Table Dining</p><p class="feature-desc">Fresh, locally-sourced ingredients every meal.</p></div>
      <div class="feature-item"><div class="feature-icon">🛡️</div><p class="feature-title">24/7 Security</p><p class="feature-desc">Round-the-clock security for your peace of mind.</p></div>
    </div>
  </div>
</section>

<!-- ── ROOMS ── -->
<section class="section" id="rooms">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title">Our Accommodations</h2>
      <p class="section-sub">From cozy garden rooms to exclusive beachfront villas</p>
    </div>
    <div class="grid-3">
      <?php foreach ($rooms as $room):
        $amenities = json_decode($room['amenities'] ?? '[]', true) ?: [];
        $placeholder = "https://placehold.co/600x400/d1fae5/065f46?text=" . urlencode($room['name']) . "&font=quicksand";
      ?>
      <div class="card room-card">
        <div class="room-img-wrap">
          <img src="<?= $placeholder ?>" alt="<?= e($room['name']) ?>"/>
          <div class="room-category-badge"><?= e($room['category_name']) ?></div>
        </div>
        <div class="room-body">
          <div class="flex justify-between items-center" style="margin-bottom:6px;">
            <h3 class="room-title"><?= e($room['name']) ?></h3>
            <div class="room-rating">⭐ 4.8</div>
          </div>
          <p class="room-desc"><?= e($room['description']) ?></p>
          <p class="room-capacity">👤 Up to <?= $room['capacity'] ?> guests</p>
          <div class="room-tags">
            <?php foreach (array_slice($amenities, 0, 3) as $a): ?>
              <span class="tag"><?= e($a) ?></span>
            <?php endforeach; ?>
            <?php if (count($amenities) > 3): ?>
              <span class="tag">+<?= count($amenities) - 3 ?> more</span>
            <?php endif; ?>
          </div>
          <div class="room-footer">
            <div><span class="room-price"><?= peso($room['price_per_night']) ?><span> / night</span></span></div>
            <a href="<?= SITE_URL ?>/pages/room-detail.php?id=<?= $room['id'] ?>" class="btn btn-primary btn-sm">Book Now</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── PACKAGES ── -->
<?php if (!empty($packages)): ?>
<section class="section section-dark">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title" style="color:#fff;">Special Packages</h2>
      <p class="section-sub" style="color:rgba(255,255,255,0.6);">Curated experiences for every type of traveler</p>
    </div>
    <div class="grid-3">
      <?php foreach ($packages as $pkg):
        $inclusions = json_decode($pkg['inclusions'] ?? '[]', true) ?: [];
        $icon = '🌿';
        foreach ($pkgIcons as $k => $v) { if (stripos($pkg['name'], $k) !== false) { $icon = $v; break; } }
      ?>
      <div class="package-card">
        <div class="package-icon"><?= $icon ?></div>
        <h3 class="package-name"><?= e($pkg['name']) ?></h3>
        <p class="package-desc"><?= e($pkg['description']) ?></p>
        <ul class="package-includes">
          <?php foreach (array_slice($inclusions, 0, 4) as $inc): ?>
            <li><?= e($inc) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="package-footer">
          <div class="package-price"><?= peso($pkg['price']) ?><span> / package</span></div>
          <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-yellow btn-sm">Book Package</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── SERVICES ── -->
<section class="section" id="services">
  <div class="container">
    <div class="text-center mb-8">
      <h2 class="section-title">Activities & Services</h2>
      <p class="section-sub">Enhance your stay with our curated experiences</p>
    </div>
    <div class="grid-3">
      <?php foreach ($services as $svc): ?>
      <div class="card service-card" style="padding:22px;">
        <div class="service-icon-wrap"><?= $catIcons[$svc['category']] ?? '✨' ?></div>
        <h3 class="service-name"><?= e($svc['name']) ?></h3>
        <p class="service-desc"><?= e($svc['description']) ?></p>
        <span class="service-cat"><?= e(str_replace('_',' ',$svc['category'])) ?></span>
        <div class="service-footer">
          <div>
            <span class="service-price"><?= peso($svc['price']) ?></span>
            <span class="service-unit"> <?= e($svc['unit']) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
  <div class="container">
    <h2>Ready for Your Paradise Escape?</h2>
    <p>Book your stay today and receive a complimentary welcome drink and early check-in (subject to availability).</p>
    <a href="<?= SITE_URL ?>/pages/register.php" class="btn btn-white" style="font-size:1.05rem;padding:14px 36px;">Reserve Now</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
