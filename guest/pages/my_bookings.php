<?php
$pageTitle = 'My Bookings';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db       = getDB();
$guest_id = (int)$_SESSION['guest_id'];

$reservations = $db->query("
    SELECT r.*, f.facility_name, f.category, f.photo,
           p.payment_method, p.status AS payment_status
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    LEFT JOIN payment_records p ON p.reservation_id = r.reservation_id
    WHERE r.guest_id = $guest_id
    ORDER BY r.reserved_at DESC, r.reservation_id DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch existing reviews keyed by reservation_id (graceful if table missing)
$reviewsByRes = [];
$_rvq = $db->query("SELECT reservation_id, review_id, rating FROM reviews WHERE guest_id = $guest_id AND status != 'rejected'");
if ($_rvq) {
    foreach ($_rvq->fetch_all(MYSQLI_ASSOC) as $_r) {
        $reviewsByRes[$_r['reservation_id']] = $_r;
    }
}

function catIcon($cat) {
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
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $k => $v) {
        if (str_contains($c, $k)) {
            return "<i class=\"{$v}\"></i>";
        }
    }
    return '<i class="fa-solid fa-star"></i>';
}

function statusBadge($status) {
    $map = [
        'pending'   => ['bg'=>'#fef9c3','color'=>'#854d0e','icon'=>'fa-solid fa-clock','label'=>'Pending'],
        'approved'  => ['bg'=>'#dcfce7','color'=>'#166534','icon'=>'fa-solid fa-check-circle','label'=>'Approved'],
        'cancelled' => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'fa-solid fa-times-circle','label'=>'Cancelled'],
    ];
    $s = $map[$status] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280','icon'=>'fa-solid fa-question-circle','label'=>ucfirst($status)];
    return "<span style=\"display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:9999px;font-size:0.75rem;font-weight:700;background:{$s['bg']};color:{$s['color']};\"><i class=\"{$s['icon']}\"></i> {$s['label']}</span>";
}

function payBadge($method) {
    if (!$method) return '';
    $icons = ['cash'=>'<i class="fa-solid fa-money-bill-wave"></i> Pay at Resort','gcash'=>'<i class="fa-solid fa-mobile-alt"></i> GCash'];
    $label = $icons[$method] ?? ucfirst($method);
    return "<span style=\"font-size:0.76rem;color:var(--gray-400);\">$label</span>";
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.booking-card { border:2px solid var(--green-100); border-radius:var(--radius); overflow:hidden; background:#fff; margin-bottom:16px; transition:var(--transition); }
.booking-card:hover { border-color:var(--green-200); box-shadow:0 4px 18px rgba(0,0,0,0.06); }
.booking-card-img { width:160px; flex-shrink:0; overflow:hidden; }
.booking-card-img img { width:100%; height:100%; object-fit:cover; }
.booking-info-grid { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px; }
.binfo { background:var(--green-50); border-radius:var(--radius-sm); padding:8px 12px; min-width:120px; }
@media (max-width: 600px) {
  .booking-card-img { width:100%; height:180px; }
}
</style>

<div class="container page-wrap">

  <div style="margin-bottom:28px;">
    <h1 style="font-size:1.8rem;font-weight:700;color:var(--green-dark);margin-bottom:4px;">📋 My Bookings</h1>
    <p style="color:var(--gray-400);"><?= count($reservations) ?> reservation<?= count($reservations)!==1?'s':'' ?></p>
  </div>

  <?php if (empty($reservations)): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <p class="empty-title">No bookings yet</p>
      <p class="empty-desc">Browse our facilities and make your first reservation!</p>
      <a href="<?= SITE_URL ?>/guest/index.php#facilities" class="btn btn-primary">Explore Facilities</a>
    </div>
  <?php else: ?>
    <?php foreach ($reservations as $res):
      $imgSrc = !empty($res['photo'])
          ? SITE_URL . '/guest/pages/facility_photo.php?id=' . (int)$res['facility_id']
          : 'https://placehold.co/320x220/d1fae5/065f46?text=' . urlencode($res['facility_name']) . '&font=quicksand';
    ?>
    <div class="booking-card">
      <div style="display:flex;flex-wrap:wrap;">
        <div class="booking-card-img" style="min-height:150px;">
          <img src="<?= $imgSrc ?>" alt="<?= e($res['facility_name']) ?>"
               onerror="this.src='https://placehold.co/320x220/d1fae5/065f46?text=<?= urlencode($res['facility_name']) ?>&font=quicksand'"/>
        </div>
        <div style="flex:1;padding:18px;min-width:0;">

          <!-- Header row -->
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
            <div>
              <h3 style="font-weight:700;font-size:1rem;color:var(--gray-800);margin-bottom:3px;"><?= e($res['facility_name']) ?></h3>
              <?php if ($res['category']): ?>
                <span style="font-size:0.72rem;color:var(--gray-400);"><?= catIcon($res['category']) ?> <?= e(ucfirst($res['category'])) ?></span>
              <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <?= statusBadge($res['status']) ?>
              <span style="padding:4px 12px;border-radius:9999px;font-size:0.75rem;font-weight:700;
                background:<?= $res['rate_type']==='daytime'?'#fef9c3':'#dbeafe' ?>;
                color:<?= $res['rate_type']==='daytime'?'#854d0e':'#1e40af' ?>;">
                <?= $res['rate_type']==='daytime'?'<i class="fa-solid fa-sun"></i> Daytime':'<i class="fa-solid fa-moon"></i> Overnight' ?>
              </span>
            </div>
          </div>

          <!-- Dates -->
          <div class="booking-info-grid">
            <div class="binfo">
              <p style="color:var(--gray-400);font-size:0.68rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Check-in</p>
              <p style="font-weight:700;color:var(--gray-800);font-size:0.88rem;"><?= date('D, M d Y', strtotime($res['checkin_date'])) ?></p>
            </div>
            <div style="display:flex;align-items:center;color:var(--gray-300);">→</div>
            <div class="binfo">
              <p style="color:var(--gray-400);font-size:0.68rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Check-out</p>
              <p style="font-weight:700;color:var(--gray-800);font-size:0.88rem;"><?= date('D, M d Y', strtotime($res['checkout_date'])) ?></p>
            </div>
          </div>

          <!-- Guests + Payment + Amount -->
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding-top:10px;border-top:1px solid var(--green-100);margin-top:2px;">
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
              <span style="font-size:0.82rem;color:var(--gray-500);">👤 <?= (int)$res['num_guests'] ?> Guest<?= $res['num_guests']!=1?'s':'' ?></span>
              <?= payBadge($res['payment_method']) ?>
              <span style="font-size:0.74rem;color:var(--gray-400);">Reserved: <?= date('M d, Y', strtotime($res['reserved_at'])) ?></span>
            </div>
            <div style="text-align:right;">
              <p style="font-size:1.15rem;font-weight:700;color:var(--green-dark);"><?= peso($res['total_amount']) ?></p>
              <?php if ((float)$res['exceed_fee'] > 0): ?>
                <p style="font-size:0.74rem;color:var(--red);">incl. <?= peso($res['exceed_fee']) ?> excess fee</p>
              <?php endif; ?>
            </div>
          </div>

          <?php
            $isPastStay  = $res['status'] === 'approved' && $res['checkout_date'] < date('Y-m-d');
            $existReview = $reviewsByRes[$res['reservation_id']] ?? null;
          ?>
          <?php if ($isPastStay): ?>
          <div style="padding-top:10px;border-top:1px solid var(--green-100);margin-top:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <?php if ($existReview): ?>
              <div style="display:flex;align-items:center;gap:6px;">
                <span style="font-size:0.76rem;color:var(--gray-400);font-weight:600;">Your rating:</span>
                <span style="color:var(--yellow);font-size:1rem;letter-spacing:1px;">
                  <?= str_repeat('★', (int)$existReview['rating']) . str_repeat('☆', 5 - (int)$existReview['rating']) ?>
                </span>
                <span style="font-size:0.72rem;color:var(--green);font-weight:700;">✓ Reviewed</span>
              </div>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline" style="gap:5px;"
                onclick="openReviewModal(<?= (int)$res['reservation_id'] ?>, '<?= e(addslashes($res['facility_name'])) ?>')">
                <i class="fa-solid fa-star"></i> Leave a Review
              </button>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
ob_start();
?>
<!-- ── REVIEW MODAL ── -->
<style>
.review-modal-box { background:#fff; border-radius:var(--radius-lg); padding:28px 32px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.2); max-height:90vh; overflow-y:auto; }
.review-modal-btns { display:flex; gap:10px; }
@media (max-width: 480px) {
  .review-modal-box { padding:22px 18px; }
  .review-modal-btns { flex-direction:column; }
}
</style>
<div id="reviewModalBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1100;backdrop-filter:blur(2px);align-items:center;justify-content:center;padding:16px;box-sizing:border-box;">
  <div class="review-modal-box">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
      <h3 style="font-weight:700;font-size:1.15rem;color:var(--green-dark);">⭐ Rate Your Stay</h3>
      <button onclick="closeReviewModal()" style="background:none;border:none;font-size:1.4rem;color:var(--gray-400);line-height:1;cursor:pointer;">&times;</button>
    </div>
    <p id="reviewFacilityName" style="color:var(--gray-400);font-size:0.86rem;margin-bottom:20px;"></p>

    <form method="POST" action="<?= SITE_URL ?>/guest/pages/submit_review.php" id="reviewForm" onsubmit="return validateReview()">
      <input type="hidden" name="reservation_id" id="reviewResId"/>

      <div style="margin-bottom:20px;">
        <p style="font-size:0.78rem;font-weight:700;color:var(--gray-700);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">Your Rating</p>
        <div id="starPicker" style="display:flex;gap:6px;margin-bottom:6px;">
          <span class="review-star" data-value="1" style="font-size:2.4rem;color:var(--gray-200);cursor:pointer;line-height:1;transition:color 0.15s;">★</span>
          <span class="review-star" data-value="2" style="font-size:2.4rem;color:var(--gray-200);cursor:pointer;line-height:1;transition:color 0.15s;">★</span>
          <span class="review-star" data-value="3" style="font-size:2.4rem;color:var(--gray-200);cursor:pointer;line-height:1;transition:color 0.15s;">★</span>
          <span class="review-star" data-value="4" style="font-size:2.4rem;color:var(--gray-200);cursor:pointer;line-height:1;transition:color 0.15s;">★</span>
          <span class="review-star" data-value="5" style="font-size:2.4rem;color:var(--gray-200);cursor:pointer;line-height:1;transition:color 0.15s;">★</span>
        </div>
        <input type="hidden" name="rating" id="reviewRatingInput" value="0"/>
        <p id="ratingLabel" style="font-size:0.82rem;color:var(--green);font-weight:700;min-height:1.2em;"></p>
      </div>

      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Your Review <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
        <textarea name="review_text" class="form-control" rows="3" maxlength="600"
                  placeholder="Share details about your experience..." style="resize:vertical;"></textarea>
      </div>

      <div class="review-modal-btns">
        <button type="button" class="btn btn-outline btn-full" onclick="closeReviewModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-star"></i> Submit Review</button>
      </div>
    </form>
  </div>
</div>

<script>
const ratingLabels = ['', 'Terrible', 'Poor', 'Average', 'Good', 'Excellent'];
let currentStarRating = 0;

function openReviewModal(resId, facilityName) {
  document.getElementById('reviewResId').value = resId;
  document.getElementById('reviewFacilityName').textContent = facilityName;
  currentStarRating = 0;
  document.getElementById('reviewRatingInput').value = 0;
  updateStars(0);
  document.getElementById('reviewModalBackdrop').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
  document.getElementById('reviewModalBackdrop').style.display = 'none';
  document.body.style.overflow = '';
}

function updateStars(val) {
  document.querySelectorAll('.review-star').forEach((s, i) => {
    s.style.color = i < val ? 'var(--yellow)' : 'var(--gray-200)';
  });
  document.getElementById('ratingLabel').textContent = val ? ratingLabels[val] : '';
}

document.querySelectorAll('.review-star').forEach(star => {
  star.addEventListener('click', () => {
    currentStarRating = parseInt(star.dataset.value);
    document.getElementById('reviewRatingInput').value = currentStarRating;
    updateStars(currentStarRating);
  });
  star.addEventListener('mouseover', () => updateStars(parseInt(star.dataset.value)));
  star.addEventListener('mouseout',  () => updateStars(currentStarRating));
});

document.getElementById('reviewModalBackdrop').addEventListener('click', function(e) {
  if (e.target === this) closeReviewModal();
});

function validateReview() {
  if (parseInt(document.getElementById('reviewRatingInput').value) < 1) {
    alert('Please select a star rating before submitting.');
    return false;
  }
  return true;
}
</script>
<?php $pageModals = ob_get_clean(); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>