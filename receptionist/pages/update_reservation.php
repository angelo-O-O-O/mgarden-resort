<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reservationId = (int) ($_POST['reservation_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if (!$reservationId || !in_array($status, ['approved', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$db = getDB();

// Update reservation status
$stmt = $db->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
$stmt->bind_param('si', $status, $reservationId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Reservation ' . $status . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update reservation']);
}
?>