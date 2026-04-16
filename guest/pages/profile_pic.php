<?php
require_once __DIR__ . '/../includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit; }

$db   = getDB();
$stmt = $db->prepare("SELECT profile_pic FROM guests WHERE guest_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row  = $stmt->get_result()->fetch_assoc();

if (!$row || empty($row['profile_pic'])) {
    http_response_code(404); exit;
}

$data     = $row['profile_pic'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($data);
if (!$mimeType || !str_starts_with($mimeType, 'image/')) {
    $mimeType = 'image/jpeg';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($data));
header('Cache-Control: public, max-age=3600');
echo $data;
exit;