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

        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>