<?php
$pageTitle = 'Guest Reviews';
require_once __DIR__ . '/../includes/config.php';

$db = getDB();

$filter_facility = (int)($_GET['facility'] ?? 0);
$filter_rating   = (int)($_GET['rating']   ?? 0);
if ($filter_rating < 1 || $filter_rating > 5) $filter_rating = 0;

$facilities = $db->query("
    SELECT facility_id, facility_name FROM facilities
    WHERE availability = 'available' ORDER BY facility_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Build filtered reviews query
$where  = ["rv.status = 'approved'"];
$params = [];
$types  = '';
if ($filter_facility > 0) { $where[] = 'rv.facility_id = ?'; $params[] = $filter_facility; $types .= 'i'; }
if ($filter_rating   > 0) { $where[] = 'rv.rating = ?';      $params[] = $filter_rating;   $types .= 'i'; }
$whereSQL = implode(' AND ', $where);

if ($params) {
    $stmt = $db->prepare("
        SELECT rv.review_id, rv.rating, rv.review_text, rv.created_at,
               g.guest_name, f.facility_id, f.facility_name
        FROM reviews rv
        JOIN guests g  ON rv.guest_id    = g.guest_id
        JOIN facilities f ON rv.facility_id = f.facility_id
        WHERE $whereSQL ORDER BY rv.created_at DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $r = $db->query("
        SELECT rv.review_id, rv.rating, rv.review_text, rv.created_at,
               g.guest_name, f.facility_id, f.facility_name
        FROM reviews rv
        JOIN guests g  ON rv.guest_id    = g.guest_id
        JOIN facilities f ON rv.facility_id = f.facility_id
        WHERE rv.status = 'approved' ORDER BY rv.created_at DESC
    ");
    $reviews = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// Overall stats (always unfiltered for the summary card)
$statsRow     = $db->query("SELECT COUNT(*) AS total, COALESCE(AVG(rating),0) AS avg_rating FROM reviews WHERE status='approved'")->fetch_assoc();
$avgRating    = round((float)($statsRow['avg_rating'] ?? 0), 1);
$totalReviews = (int)($statsRow['total'] ?? 0);

// Star distribution (unfiltered)
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

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'just now';
    if ($diff < 3600)     { $m  = floor($diff/60);       return $m  . ' min ago'; }
    if ($diff < 86400)    { $h  = floor($diff/3600);     return $h  . ' hr ago'; }
    if ($diff < 2592000)  { $d  = floor($diff/86400);    return $d  . ' day'   . ($d >1?'s':'') . ' ago'; }
    if ($diff < 31536000) { $mo = floor($diff/2592000);  return $mo . ' month' . ($mo>1?'s':'') . ' ago'; }
    $y = floor($diff/31536000); return $y . ' year' . ($y>1?'s':'') . ' ago';
}

// URL builder — keeps both filters in sync
function rvUrl($rating = 0, $facility = 0) {
    $p = [];
    if ($facility > 0) $p[] = 'facility=' . $facility;
    if ($rating   > 0) $p[] = 'rating='   . $rating;
    return SITE_URL . '/guest/pages/reviews.php' . ($p ? '?' . implode('&', $p) : '');
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Stats card wrapper ── */
.rv-stats-wrap { flex:1; min-width:280px; padding:22px 26px; }

/* ── Stats inner layout (big score + bars) ── */
.rv-stats-card { display:flex; gap:24px; align-items:center; }
.rv-big-score  { flex-shrink:0; }
.rv-big-num    { font-size:4rem; font-weight:800; color:var(--green-dark); line-height:1; }
.rv-dist       { flex:1; display:flex; flex-direction:column; gap:5px; }

/* Display-only bar rows */
.rv-dist-row   { display:flex; align-items:center; gap:10px; padding:3px 0; }
.rv-dist-track { flex:1; height:11px; background:var(--gray-100); border-radius:var(--radius-full); overflow:hidden; }
.rv-dist-fill  { height:100%; background:var(--yellow); border-radius:var(--radius-full); transition:width 0.4s; }
.rv-dist-label { flex-shrink:0; width:90px; display:flex; align-items:baseline; gap:4px; }
.rv-dist-label-star  { font-size:0.82rem; font-weight:700; color:var(--gray-700); }
.rv-dist-label-star .fa-star { font-size:0.7rem; color:var(--yellow); margin-left:1px; }
.rv-dist-label-count { font-size:0.72rem; color:var(--gray-400); white-space:nowrap; }

/* ── Star filter pills ── */
.rv-pills { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:24px; }
.rv-pill  {
  display:inline-flex; align-items:center; gap:6px;
  padding:8px 16px; border-radius:var(--radius-full);
  border:1.5px solid var(--green-100);
  background:#fff; text-decoration:none;
  transition:var(--transition); white-space:nowrap;
}
.rv-pill:hover  { border-color:var(--green); background:var(--green-50); }
.rv-pill.active { border-color:var(--green); background:var(--green-50); }
.rv-pill-num    { font-size:0.88rem; font-weight:700; color:var(--green-dark); }
.rv-pill.active .rv-pill-num { color:var(--green); }
.rv-pill-label  { font-size:0.82rem; color:var(--gray-500); }
.rv-pill.active .rv-pill-label { color:var(--gray-700); font-weight:600; }

/* ── Review cards ── */
.rv-card       { background:#fff; border:2px solid var(--green-100); border-radius:var(--radius); padding:22px 24px; transition:var(--transition); }
.rv-card:hover { border-color:var(--green-200); box-shadow:var(--shadow); }
.rv-avatar     { width:44px; height:44px; background:var(--green-100); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--green-dark); font-size:1.1rem; flex-shrink:0; }

/* ── Tablet / small desktop (≤768px) ── */
@media (max-width:768px) {
  .rv-dist-label-count { display:none; }
  .rv-dist-label       { width:60px; }
}

/* ── Mobile (≤640px) ── */
@media (max-width:640px) {
  .rv-outer-row    { flex-direction:column !important; }
  .rv-stats-wrap   { min-width:0; width:100%; padding:18px 20px; }
  .rv-filter-panel { width:100% !important; min-width:unset !important; }
  .rv-big-num      { font-size:2.8rem; }
  .rv-stats-card   { gap:18px; }
  .rv-dist-label   { width:54px; }
  .rv-pills        { gap:6px; }
  .rv-pill         { padding:7px 12px; font-size:0.82rem; }
  .rv-card         { padding:16px; }
}

/* ── Very small screens (≤400px) ── */
@media (max-width:400px) {
  .rv-stats-card  { flex-direction:column; align-items:stretch; gap:14px; }
  .rv-big-score   { display:flex; align-items:center; gap:18px; }
  .rv-big-num     { font-size:2.6rem; }
  .rv-dist-label  { width:54px; }
  .rv-pill-label  { display:none; }
  .rv-pill        { padding:7px 10px; }
}
</style>

<div class="container page-wrap">

  <div style="margin-bottom:24px;">
    <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-bottom:4px;">
      <i class="fa-solid fa-star" style="color:var(--yellow);"></i> Guest Reviews
    </h1>
    <p style="color:var(--gray-400);">Real experiences from our guests</p>
  </div>

  <!-- Stats + Filter row -->
  <div class="rv-outer-row" style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;margin-bottom:28px;">

    <!-- Score + clickable bars -->
    <div class="card rv-stats-card rv-stats-wrap">

      <div class="rv-big-score">
        <p class="rv-big-num"><?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></p>
        <div style="color:var(--yellow);font-size:1.1rem;letter-spacing:2px;margin:5px 0 3px;">
          <?php for ($i=1;$i<=5;$i++) echo $i<=(int)round($avgRating)?'★':'☆'; ?>
        </div>
        <p style="color:var(--gray-400);font-size:0.78rem;"><?= $totalReviews ?> rating<?= $totalReviews!==1?'s':'' ?></p>
      </div>

      <div class="rv-dist">
        <?php for ($i=5;$i>=1;$i--):
          $pct = $totalReviews > 0 ? round(($dist[$i]/$totalReviews)*100) : 0;
        ?>
        <div class="rv-dist-row">
          <div class="rv-dist-track"><div class="rv-dist-fill" style="width:<?= $pct ?>%;"></div></div>
          <div class="rv-dist-label">
            <span class="rv-dist-label-star"><?= $i ?> <i class="fa-solid fa-star"></i></span>
            <span class="rv-dist-label-count"><?= $dist[$i] ?></span>
          </div>
        </div>
        <?php endfor; ?>
      </div>

    </div>

    <!-- Filter panel -->
    <div class="rv-filter-panel" style="min-width:220px;flex-shrink:0;">

      <form method="GET" style="margin-bottom:12px;">
        <?php if ($filter_rating > 0): ?>
          <input type="hidden" name="rating" value="<?= $filter_rating ?>"/>
        <?php endif; ?>
        <label class="form-label">Filter by Facility</label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <select name="facility" class="form-control" style="flex:1;min-width:0;width:100%;" onchange="this.form.submit()">
            <option value="0">All Facilities</option>
            <?php foreach ($facilities as $f): ?>
              <option value="<?= (int)$f['facility_id'] ?>" <?= $filter_facility===(int)$f['facility_id']?'selected':'' ?>>
                <?= e($f['facility_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <!-- Active facility chip -->
      <?php if ($filter_facility > 0):
        $facName = '';
        foreach ($facilities as $f) { if ((int)$f['facility_id']===$filter_facility) { $facName=$f['facility_name']; break; } }
      ?>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
        <a href="<?= rvUrl($filter_rating, 0) ?>"
           style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:var(--green-100);border-radius:var(--radius-full);font-size:0.76rem;font-weight:700;color:var(--green-dark);text-decoration:none;">
          <?= e($facName) ?> <span style="font-weight:400;margin-left:2px;">×</span>
        </a>
        <?php if ($filter_rating > 0): ?>
        <a href="<?= rvUrl() ?>"
           style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:var(--gray-100);border-radius:var(--radius-full);font-size:0.76rem;color:var(--gray-500);text-decoration:none;">
          Clear all
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <p style="color:var(--gray-400);font-size:0.82rem;">
        Showing <strong style="color:var(--gray-700);"><?= count($reviews) ?></strong>
        review<?= count($reviews)!==1?'s':'' ?>
        <?php if ($filter_rating > 0): ?>
          with <strong style="color:var(--green-dark);"><?= $filter_rating ?>★</strong>
        <?php endif; ?>
        <?php if ($filter_facility > 0): ?>
          for <strong style="color:var(--green-dark);"><?= e($facName ?? '') ?></strong>
        <?php endif; ?>
      </p>

      <?php if (isLoggedIn()): ?>
        <p style="margin-top:10px;font-size:0.8rem;color:var(--gray-400);">
          <i class="fa-solid fa-info-circle" style="color:var(--green);"></i>
          Leave a review from <a href="<?= SITE_URL ?>/guest/pages/my_bookings.php" style="color:var(--green);font-weight:700;">My Bookings</a>.
        </p>
      <?php else: ?>
        <p style="margin-top:10px;font-size:0.8rem;color:var(--gray-400);">
          <a href="<?= SITE_URL ?>/guest/pages/login.php" style="color:var(--green);font-weight:700;">Sign in</a> to leave a review after your stay.
        </p>
      <?php endif; ?>
    </div>

  </div>

  <!-- Star filter pills -->
  <div class="rv-pills">
    <a href="<?= rvUrl(0, $filter_facility) ?>" class="rv-pill<?= $filter_rating===0?' active':'' ?>">
      <span class="rv-pill-num"><i class="fa-solid fa-star" style="font-size:0.72rem;color:<?= $filter_rating===0?'var(--green)':'var(--yellow)' ?>;"></i></span>
      <span class="rv-pill-label">All</span>
    </a>
    <?php
    $starLabels = [5=>'Excellent', 4=>'Good', 3=>'Average', 2=>'Poor', 1=>'Terrible'];
    for ($i=5;$i>=1;$i--):
      $isActive = $filter_rating === $i;
      $href     = $isActive ? rvUrl(0, $filter_facility) : rvUrl($i, $filter_facility);
    ?>
    <a href="<?= $href ?>" class="rv-pill<?= $isActive?' active':'' ?>">
      <span class="rv-pill-num"><?= $i ?> <i class="fa-solid fa-star" style="font-size:0.72rem;color:<?= $isActive?'var(--green)':'var(--yellow)' ?>;"></i></span>
      <span class="rv-pill-label"><?= $starLabels[$i] ?></span>
    </a>
    <?php endfor; ?>
  </div>

  <!-- Reviews list -->
  <?php if (empty($reviews)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa-solid fa-star"></i></div>
      <p class="empty-title">No reviews yet</p>
      <p class="empty-desc">Be the first to share your experience after your stay!</p>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($reviews as $rv): ?>
      <div class="rv-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
          <div style="display:flex;gap:10px;align-items:center;min-width:0;">
            <div class="rv-avatar"><?= strtoupper(substr($rv['guest_name']??'G',0,1)) ?></div>
            <div style="min-width:0;">
              <p style="font-weight:700;font-size:0.92rem;color:var(--gray-800);margin-bottom:2px;"><?= e(guestDisplayName($rv['guest_name'])) ?></p>
              <p style="font-size:0.74rem;color:var(--gray-400);"><?= timeAgo($rv['created_at']) ?></p>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="color:var(--yellow);font-size:1rem;letter-spacing:1px;line-height:1;">
              <?php for($i=1;$i<=5;$i++) echo $i<=(int)$rv['rating']?'★':'☆'; ?>
            </div>
            <p style="font-size:0.76rem;font-weight:700;color:var(--gray-500);margin-top:2px;"><?= number_format((float)$rv['rating'],1) ?></p>
          </div>
        </div>
        <?php if (!empty($rv['review_text'])): ?>
          <p style="color:var(--gray-600);font-size:0.88rem;line-height:1.65;margin-bottom:14px;">"<?= e($rv['review_text']) ?>"</p>
        <?php endif; ?>
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
