<?php
require_once __DIR__ . '/../includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT photo FROM facilities WHERE facility_id = ? AND availability = 'available'");
$stmt->bind_param('i', $id);
$stmt->execute();
$row  = $stmt->get_result()->fetch_assoc();

if (!$row || empty($row['photo'])) {
    header("Location: https://placehold.co/600x400/d1fae5/065f46?text=No+Photo&font=quicksand");
    exit;
}

$photoData = $row['photo'];
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$mimeType  = $finfo->buffer($photoData);

if (!$mimeType || !str_starts_with($mimeType, 'image/')) {
    $mimeType = 'image/jpeg';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($photoData));
header('Cache-Control: public, max-age=3600');
echo $photoData;
exit;