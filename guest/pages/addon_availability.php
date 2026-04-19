<?php
/**
 * addon_availability.php — AJAX endpoint
 * Returns remaining available qty per addon for a given booking window.
 *
 * POST:
 *   checkin_date           Y-m-d
 *   checkout_date          Y-m-d
 *   checkin_time           H:i:s
 *   checkout_time          H:i:s
 *   exclude_reservation_id int (optional) — skip this reservation (edit mode)
 *
 * Response JSON: { "addon_id": remaining | null, ... }
 *   null  = no limit, always available
 *   0     = fully booked for this window
 *   N > 0 = N slots remaining
 */
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$db = getDB();

$checkin_date   = trim($_POST['checkin_date']  ?? '');
$checkout_date  = trim($_POST['checkout_date'] ?? '');
$checkin_time   = trim($_POST['checkin_time']  ?? '00:00:00');
$checkout_time  = trim($_POST['checkout_time'] ?? '23:59:59');
$exclude_res_id = (int)($_POST['exclude_reservation_id'] ?? 0);

if (!$checkin_date || !$checkout_date) {
    echo json_encode([]);
    exit;
}

// Full datetime strings for overlap comparison
$ciDT = $db->real_escape_string($checkin_date  . ' ' . $checkin_time);
$coDT = $db->real_escape_string($checkout_date . ' ' . $checkout_time);

// All addons
$allAddons = $db->query("SELECT addon_id, limit_per_reservation FROM addons")->fetch_all(MYSQLI_ASSOC);

// Already booked qty per addon across overlapping confirmed/pending reservations
// Overlap condition: existing.checkin < proposed.checkout AND existing.checkout > proposed.checkin
$excludeClause = $exclude_res_id ? "AND r.reservation_id != $exclude_res_id" : '';

$bookedRows = $db->query("
    SELECT ra.addon_id, COALESCE(SUM(ra.quantity), 0) AS booked_qty
    FROM reservation_addons ra
    JOIN reservations r ON ra.reservation_id = r.reservation_id
    WHERE r.status IN ('pending', 'approved')
      AND CONCAT(r.checkin_date,  ' ', COALESCE(r.checkin_time,  '00:00:00')) < '$coDT'
      AND CONCAT(r.checkout_date, ' ', COALESCE(r.checkout_time, '23:59:59')) > '$ciDT'
      $excludeClause
    GROUP BY ra.addon_id
")->fetch_all(MYSQLI_ASSOC);

$bookedMap = [];
foreach ($bookedRows as $row) {
    $bookedMap[(int)$row['addon_id']] = (int)$row['booked_qty'];
}

$result = [];
foreach ($allAddons as $addon) {
    $aid   = (int)$addon['addon_id'];
    $limit = $addon['limit_per_reservation'];

    if ($limit === null || (int)$limit === 0) {
        $result[$aid] = null; // no limit
    } else {
        $booked       = $bookedMap[$aid] ?? 0;
        $result[$aid] = max(0, (int)$limit - $booked);
    }
}

echo json_encode($result);