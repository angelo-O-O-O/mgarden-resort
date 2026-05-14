<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/pages/my_bookings.php');
}

$db             = getDB();
$guest_id       = (int)$_SESSION['guest_id'];
$reservation_id = (int)($_POST['reservation_id'] ?? 0);
$rating         = (int)($_POST['rating'] ?? 0);
$review_text    = trim($_POST['review_text'] ?? '');

if ($rating < 1 || $rating > 5) {
    setFlash('error', 'Please select a rating between 1 and 5 stars.');
    redirect(SITE_URL . '/guest/pages/my_bookings.php');
}

// Validate: reservation belongs to guest, is approved, and checkout has passed
$stmt = $db->prepare("
    SELECT reservation_id, facility_id
    FROM reservations
    WHERE reservation_id = ? AND guest_id = ?
      AND status = 'approved'
      AND checkout_date < CURDATE()
");
$stmt->bind_param('ii', $reservation_id, $guest_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    setFlash('error', 'This reservation is not eligible for a review.');
    redirect(SITE_URL . '/guest/pages/my_bookings.php');
}

// Prevent duplicate reviews
$chk = $db->prepare("SELECT review_id FROM reviews WHERE guest_id = ? AND reservation_id = ?");
$chk->bind_param('ii', $guest_id, $reservation_id);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    setFlash('error', 'You have already reviewed this reservation.');
    redirect(SITE_URL . '/guest/pages/my_bookings.php');
}

$facility_id = (int)$res['facility_id'];
$ins = $db->prepare("
    INSERT INTO reviews (guest_id, facility_id, reservation_id, rating, review_text)
    VALUES (?, ?, ?, ?, ?)
");
$ins->bind_param('iiiis', $guest_id, $facility_id, $reservation_id, $rating, $review_text);

if ($ins->execute()) {
    setFlash('success', '⭐ Thank you for your review!');
} else {
    setFlash('error', 'Something went wrong. Please try again.');
}

redirect(SITE_URL . '/guest/pages/my_bookings.php');
