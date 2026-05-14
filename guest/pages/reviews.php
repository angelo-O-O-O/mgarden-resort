<?php
$pageTitle = 'Guest Reviews';
require_once __DIR__ . '/../includes/config.php';

$db = getDB();

$filter_facility = (int)($_GET['facility'] ?? 0);

$facilities = $db->query("
    SELECT facility_id, facility_name FROM facilities
    WHERE availability = 'available' ORDER BY facility_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Reviews
if ($filter_facility > 0) {
    $stmt = $db->prepare("
        SELECT rv.review_id, rv.rating, rv.review_text, rv.created_at,
               g.guest_name, f.facility_id, f.facility_name
        FROM reviews rv
        JOIN guests g  ON rv.guest_id    = g.guest_id
        JOIN facilities f ON rv.facility_id = f.facility_id
        WHERE rv.status = 'approved' AND rv.facility_id = ?
        ORDER BY rv.created_at DESC
    ");
    $stmt->bind_param('i', $filter_facility);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $r = $db->query("
        SELECT rv.review_id, rv.rating, rv.review_text, rv.created_at,
               g.guest_name, f.facility_id, f.facility_name
        FROM reviews rv
        JOIN guests g  ON rv.guest_id    = g.guest_id
        JOIN facilities f ON rv.facility_id = f.facility_id
        WHERE rv.status = 'approved'
        ORDER BY rv.created_at DESC
    ");
    $reviews = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// Overall stats
$statsRow    = $db->query("SELECT COUNT(*) AS total, COALESCE(AVG(rating),0) AS avg_rating FROM reviews WHERE status='approved'")->fetch_assoc();
$avgRating   = round((float)($statsRow['avg_rating'] ?? 0), 1);
$totalReviews = (int)($statsRow['total'] ?? 0);

// Star distribution
$dist = [];
for ($i = 5; $i >= 1; $i--) {
    $row = $db->query("SELECT COUNT(*) AS cnt FROM reviews WHERE status='approved' AND rating=$i")->fetch_assoc();
    $dist[$i] = (int)($row['cnt'] ?? 0);
}

function guestDisplayName($name) {
    $parts = explode(' ', trim($name ?? 'Guest'));
    if (count($parts) === 1) return $parts[0];
    return $parts[0] . ' ' . strtoupper(substr(end($parts), 0, 1)) . '.';
}

function renderStars($rating, $size = '1rem') {
    $out = '<span style="color:var(--yellow);font-size:' . $size . ';letter-spacing:2px;">';
    for ($i = 1; $i <= 5; $i++) $out .= $i <= $rating ? '★' : '☆';
    return $out . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.review-card { border:2px solid var(--green-100); border-radius:var(--radius); background:#fff; padding:22px; transition:var(--transition); }
.review-card:hover { border-color:var(--green); box-shadow:var(--shadow); }
.reviewer-avatar { width:42px; height:42px; background:var(--green-100); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--green-dark); font-size:1.05rem; flex-shrink:0; }
.review-stats-wrap { display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; margin-bottom:40px; }
.review-stats-card { padding:28px 24px; flex-shrink:0; min-width:210px; }
.review-filter-col { flex:1; min-width:220px; }
@media (max-width: 600px) {
  .review-stats-card { width:100%; min-width:unset; }
}
</style>

<div class="container page-wrap">

  <!-- Header -->
  <div style="margin-bottom:32px;">
    <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-bottom:4px;"><i class="fa-solid fa-star" style="color:var(--yellow);"></i> Guest Reviews</h1>
    <p style="color:var(--gray-400);">Real experiences from our guests</p>
  </div>

  <!-- Stats + Filter -->
  <div class="review-stats-wrap">

    <!-- Rating Summary Card -->
    <div class="card review-stats-card">
      <p style="font-size:3.8rem;font-weight:700;color:var(--green-dark);line-height:1;text-align:center;">
        <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?>
      </p>
      <div style="text-align:center;margin:8px 0;"><?= renderStars((int)round($avgRating), '1.3rem') ?></div>
      <p style="text-align:center;color:var(--gray-400);font-size:0.82rem;margin-bottom:20px;">
        <?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?>
      </p>
      <!-- Distribution bars -->
      <div style="display:flex;flex-direction:column;gap:6px;">
        <?php for ($i = 5; $i >= 1; $i--):
          $pct = $totalReviews > 0 ? round(($dist[$i] / $totalReviews) * 100) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:6px;font-size:0.74rem;">
          <span style="color:var(--gray-500);width:8px;text-align:right;"><?= $i ?></span>
          <span style="color:var(--yellow);font-size:0.8rem;">★</span>
          <div style="flex:1;background:var(--gray-100);border-radius:var(--radius-full);height:6px;overflow:hidden;">
            <div style="width:<?= $pct ?>%;background:var(--yellow);height:100%;border-radius:var(--radius-full);transition:width 0.4s;"></div>
          </div>
          <span style="color:var(--gray-400);min-width:18px;text-align:right;"><?= $dist[$i] ?></span>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Filter + context -->
    <div class="review-filter-col">
      <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
        <div class="form-group" style="margin:0;flex:1;min-width:180px;">
          <label class="form-label">Filter by Facility</label>
          <select name="facility" class="form-control" onchange="this.form.submit()">
            <option value="0">All Facilities</option>
            <?php foreach ($facilities as $f): ?>
              <option value="<?= (int)$f['facility_id'] ?>"
                <?= $filter_facility === (int)$f['facility_id'] ? 'selected' : '' ?>>
                <?= e($f['facility_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($filter_facility > 0): ?>
          <a href="<?= SITE_URL ?>/guest/pages/reviews.php" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
      </form>

      <?php if ($filter_facility > 0):
        $facName = '';
        foreach ($facilities as $f) { if ((int)$f['facility_id'] === $filter_facility) { $facName = $f['facility_name']; break; } }
      ?>
        <p style="color:var(--gray-400);font-size:0.84rem;">
          Showing <strong><?= count($reviews) ?></strong> review<?= count($reviews) !== 1 ? 's' : '' ?> for
          <strong style="color:var(--green-dark);"><?= e($facName) ?></strong>
        </p>
      <?php else: ?>
        <p style="color:var(--gray-400);font-size:0.84rem;">Showing all <?= count($reviews) ?> review<?= count($reviews) !== 1 ? 's' : '' ?></p>
      <?php endif; ?>

      <?php if (isLoggedIn()): ?>
        <p style="margin-top:10px;font-size:0.82rem;color:var(--gray-400);">
          <i class="fa-solid fa-info-circle" style="color:var(--green);"></i>
          You can leave a review from <a href="<?= SITE_URL ?>/guest/pages/my_bookings.php" style="color:var(--green);font-weight:700;">My Bookings</a> after your stay.
        </p>
      <?php else: ?>
        <p style="margin-top:10px;font-size:0.82rem;color:var(--gray-400);">
          <a href="<?= SITE_URL ?>/guest/pages/login.php" style="color:var(--green);font-weight:700;">Sign in</a> to leave a review after your stay.
        </p>
      <?php endif; ?>
    </div>

  </div>

  <!-- Reviews Grid -->
  <?php if (empty($reviews)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa-solid fa-star"></i></div>
      <p class="empty-title">No reviews yet</p>
      <p class="empty-desc">Be the first to share your experience after your stay!</p>
    </div>
  <?php else: ?>
    <div class="grid-3" style="gap:18px;">
      <?php foreach ($reviews as $rv): ?>
      <div class="review-card">
        <!-- Reviewer info -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
          <div class="reviewer-avatar"><?= strtoupper(substr($rv['guest_name'] ?? 'G', 0, 1)) ?></div>
          <div>
            <p style="font-weight:700;font-size:0.9rem;color:var(--gray-800);"><?= e(guestDisplayName($rv['guest_name'])) ?></p>
            <p style="font-size:0.72rem;color:var(--gray-400);"><?= date('M d, Y', strtotime($rv['created_at'])) ?></p>
          </div>
        </div>
        <!-- Stars -->
        <div style="margin-bottom:10px;"><?= renderStars((int)$rv['rating'], '1.15rem') ?></div>
        <!-- Review text -->
        <?php if (!empty($rv['review_text'])): ?>
          <p style="color:var(--gray-600);font-size:0.88rem;line-height:1.65;margin-bottom:12px;">
            "<?= e($rv['review_text']) ?>"
          </p>
        <?php endif; ?>
        <!-- Facility tag -->
        <div style="padding-top:10px;border-top:1px solid var(--green-100);">
          <a href="<?= SITE_URL ?>/guest/pages/book_info.php?id=<?= (int)$rv['facility_id'] ?>"
             style="font-size:0.74rem;color:var(--green);font-weight:600;display:inline-flex;align-items:center;gap:4px;">
            <i class="fa-solid fa-umbrella-beach"></i> <?= e($rv['facility_name']) ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
