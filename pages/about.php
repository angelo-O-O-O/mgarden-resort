<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();

// Resort info
$infoRows = $db->query("SELECT `key`, `value` FROM resort_info")->fetch_all(MYSQLI_ASSOC);
$info = [];
foreach ($infoRows as $row) { $info[$row['key']] = $row['value']; }

// Approved reviews
$reviews = $db->query("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.is_approved=1 ORDER BY r.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$avgRating = count($reviews) > 0 ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;

$pageTitle = 'About Us';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- About Hero -->
<div class="about-hero">
  <img src="https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1400&q=80" alt="MGarden Resort" class="about-hero-bg"/>
  <div class="container about-hero-content">
    <h1 style="color:#fff;font-size:2.4rem;font-weight:700;">About Us</h1>
    <p style="color:rgba(255,255,255,0.75);margin-top:4px;"><?= e($info['tagline'] ?? 'Where Nature Meets Luxury') ?></p>
  </div>
</div>

<div class="container" style="padding-top:60px;padding-bottom:60px;">

  <!-- Story -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;margin-bottom:64px;" class="story-grid">
  <style>@media(max-width:768px){.story-grid{grid-template-columns:1fr!important;}}</style>
    <div>
      <h2 class="section-title">Our Story</h2>
      <p style="color:var(--gray-500);line-height:1.8;margin-bottom:14px;"><?= e($info['description'] ?? '') ?></p>
      <p style="color:var(--gray-400);font-size:0.88rem;">Established in <?= e($info['established_year'] ?? '2015') ?>, MGarden Beach Resort has been a sanctuary for travelers seeking an authentic tropical retreat without sacrificing the comforts of modern living.</p>
      <div style="display:flex;gap:12px;margin-top:24px;">
        <?php if (!empty($info['facebook'])): ?>
        <a href="<?= e($info['facebook']) ?>" target="_blank" class="btn btn-outline btn-sm">📘 Facebook</a>
        <?php endif; ?>
        <?php if (!empty($info['instagram'])): ?>
        <a href="<?= e($info['instagram']) ?>" target="_blank" class="btn btn-sm" style="border:2px solid #e1306c;color:#e1306c;">📸 Instagram</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-grid">
      <?php
      $established = (int)($info['established_year'] ?? 2015);
      $yearsOp = date('Y') - $established;
      $stats = [["$yearsOp+ Years", 'Operating'], ['10,000+', 'Happy Guests'], ['15+', 'Room Types'], [$avgRating > 0 ? "$avgRating ★" : '4.9 ★', 'Average Rating']];
      foreach ($stats as $s): ?>
      <div class="card stat-card" style="padding:24px;">
        <p class="stat-val"><?= e($s[0]) ?></p>
        <p class="stat-label"><?= e($s[1]) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <hr class="divider" style="margin:40px 0;"/>

  <!-- Contact -->
  <div style="margin-bottom:64px;">
    <h2 class="section-title" style="margin-bottom:24px;">Contact & Location</h2>
    <div class="contact-grid" style="margin-bottom:28px;">
      <?php foreach ([
        ['📍', 'Address',      $info['address']       ?? 'Calatagan, Batangas'],
        ['📞', 'Phone',        $info['phone']         ?? '+63 912 345 6789'],
        ['✉️', 'Email',        $info['email']         ?? 'hello@mgardenresort.com'],
        ['🕐', 'Check-in/out', ($info['check_in_time'] ?? '2:00 PM') . ' / ' . ($info['check_out_time'] ?? '12:00 PM')],
      ] as [$icon, $label, $val]): ?>
      <div class="card contact-card" style="padding:18px;">
        <div class="contact-icon"><?= $icon ?></div>
        <div>
          <p class="contact-label"><?= $label ?></p>
          <p class="contact-val"><?= e($val) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Map placeholder -->
    <div class="map-placeholder">
      <span style="font-size:2rem;">🗺️</span>
      <p style="font-weight:700;">Map Location</p>
      <p style="font-size:0.84rem;">Brgy. Mabuhay, Calatagan, Batangas</p>
      <a href="https://maps.google.com/?q=Calatagan,Batangas" target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;">Open in Google Maps →</a>
    </div>
  </div>

  <hr class="divider" style="margin:40px 0;"/>

  <!-- Reviews -->
  <div>
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;">
      <div>
        <h2 class="section-title" style="margin-bottom:6px;">Guest Reviews</h2>
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:1.3rem;color:#f59e0b;"><?= str_repeat('★', (int)round($avgRating)) ?><?= str_repeat('☆', 5 - (int)round($avgRating)) ?></span>
          <span style="font-size:1.6rem;font-weight:700;color:var(--green-dark);"><?= $avgRating ?: '—' ?></span>
          <span style="color:var(--gray-400);font-size:0.88rem;">(<?= count($reviews) ?> reviews)</span>
        </div>
      </div>
    </div>

    <?php if (empty($reviews)): ?>
    <div class="empty-state">
      <div class="empty-icon">⭐</div>
      <h2 class="empty-title">No reviews yet</h2>
      <p class="empty-desc">Be the first to leave a review after your stay!</p>
    </div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach ($reviews as $rev):
        $stars = (int)$rev['rating'];
        $initial = strtoupper(substr($rev['user_name'] ?? 'G', 0, 1));
      ?>
      <div class="card review-card">
        <div class="review-header">
          <div class="reviewer-avatar"><?= $initial ?></div>
          <div style="flex:1;">
            <p class="reviewer-name"><?= e($rev['user_name'] ?? 'Guest') ?></p>
            <p class="reviewer-date"><?= date('F Y', strtotime($rev['created_at'])) ?></p>
          </div>
          <div class="stars"><?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5-$stars) ?></div>
        </div>
        <?php if ($rev['title']): ?>
          <p class="review-title"><?= e($rev['title']) ?></p>
        <?php endif; ?>
        <p class="review-body"><?= e($rev['body']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
