<?php
/**
 * facility_availability.php — AJAX endpoint
 *
 * Checks whether a specific facility is available for a proposed booking window.
 *
 * POST params:
 *   facility_id    int
 *   checkin_date   Y-m-d
 *   checkout_date  Y-m-d
 *   checkin_time   H:i:s
 *   checkout_time  H:i:s
 *   num_guests     int  (needed for pool capacity check)
 *
 * Response JSON:
 * {
 *   available:       bool,
 *   category:        string,
 *   conflict_type:   "booked"|"capacity"|null,
 *   booked_dates:    ["Y-m-d", ...],   // dates blocked in this month (for date picker)
 *   remaining_guests: int|null,        // pool only: how many guest slots left
 *   message:         string
 * }
 */
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$db = getDB();

$facility_id  = (int)($_POST['facility_id']  ?? 0);
$checkin_date = trim($_POST['checkin_date']  ?? '');
$checkout_date= trim($_POST['checkout_date'] ?? '');
$checkin_time = trim($_POST['checkin_time']  ?? '00:00:00');
$checkout_time= trim($_POST['checkout_time'] ?? '23:59:59');
$num_guests   = max(1, (int)($_POST['num_guests'] ?? 1));
// For date picker: fetch all blocked dates in a month range
$fetch_month  = trim($_POST['fetch_month']   ?? ''); // Y-m format, optional

if (!$facility_id) {
    echo json_encode(['available' => false, 'message' => 'Invalid facility.']);
    exit;
}

// Fetch facility info
$fRow = $db->query("
    SELECT facility_id, facility_name, category, max_capacity
    FROM facilities WHERE facility_id = $facility_id
")->fetch_assoc();

if (!$fRow) {
    echo json_encode(['available' => false, 'message' => 'Facility not found.']);
    exit;
}

$category   = strtolower(trim($fRow['category'] ?? ''));
$maxCap     = (int)($fRow['max_capacity'] ?? 0);
$isPool     = str_contains($category, 'pool');
$isCottage  = str_contains($category, 'cottage');
$isRoom     = str_contains($category, 'room'); // covers both "room" and "family room"

// ── Build overlap condition ──
// Overlap: existing.checkin_datetime < proposed.checkout_datetime
//      AND existing.checkout_datetime > proposed.checkin_datetime
$ciDT = $db->real_escape_string($checkin_date  . ' ' . $checkin_time);
$coDT = $db->real_escape_string($checkout_date . ' ' . $checkout_time);

$overlapWhere = "
    facility_id = $facility_id
    AND status IN ('pending','approved')
    AND CONCAT(checkin_date,  ' ', COALESCE(checkin_time,  '00:00:00')) < '$coDT'
    AND CONCAT(checkout_date, ' ', COALESCE(checkout_time, '23:59:59')) > '$ciDT'
";

$available        = true;
$conflictType     = null;
$remainingGuests  = null;
$message          = '';

if ($checkin_date && $checkout_date) {
    if ($isPool && $maxCap > 0) {
        // Pool: check total guests in overlapping reservations vs max_capacity
        $res = $db->query("
            SELECT COALESCE(SUM(num_guests), 0) AS booked_guests
            FROM reservations
            WHERE $overlapWhere
        ")->fetch_assoc();
        $bookedGuests    = (int)($res['booked_guests'] ?? 0);
        $remainingGuests = max(0, $maxCap - $bookedGuests);

        if ($bookedGuests + $num_guests > $maxCap) {
            $available    = false;
            $conflictType = 'capacity';
            $message      = "This pool can accommodate {$maxCap} guests total. "
                          . "{$bookedGuests} guest(s) are already booked for this window, "
                          . "leaving only {$remainingGuests} slot(s). You requested {$num_guests}.";
        } else {
            $message = $remainingGuests . ' guest slot(s) remaining for this time window.';
        }
    } else {
        // Room, Cottage, Family Room (and any other): one booking per window
        $res = $db->query("
            SELECT COUNT(*) AS cnt FROM reservations WHERE $overlapWhere
        ")->fetch_assoc();
        $count = (int)($res['cnt'] ?? 0);
        if ($count > 0) {
            $available    = false;
            $conflictType = 'booked';
            $message      = 'This ' . ($fRow['category'] ?? 'facility') . ' is already reserved for your selected date and time.';
        } else {
            $message = 'Available for your selected time.';
        }
    }
}

// ── Fetch blocked date ranges for a calendar month (for date picker highlighting) ──
$blockedDates = [];
if ($fetch_month) {
    // fetch_month = "Y-m", e.g. "2025-05"
    $monthStart = $fetch_month . '-01';
    $monthEnd   = date('Y-m-t', strtotime($monthStart));

    if ($isPool && $maxCap > 0) {
        // For pool: a date is "blocked" if booked_guests >= max_capacity for that day
        $poolRows = $db->query("
            SELECT checkin_date, checkout_date, num_guests
            FROM reservations
            WHERE facility_id = $facility_id
              AND status IN ('pending','approved')
              AND checkin_date  <= '$monthEnd'
              AND checkout_date >= '$monthStart'
        ")->fetch_all(MYSQLI_ASSOC);

        // Build guest count per day
        $dayGuests = [];
        foreach ($poolRows as $r) {
            $start = new DateTime($r['checkin_date']);
            $end   = new DateTime($r['checkout_date']);
            $cur   = clone $start;
            while ($cur <= $end) {
                $key = $cur->format('Y-m-d');
                $dayGuests[$key] = ($dayGuests[$key] ?? 0) + (int)$r['num_guests'];
                $cur->modify('+1 day');
            }
        }
        foreach ($dayGuests as $date => $guests) {
            if ($guests >= $maxCap) $blockedDates[] = $date;
        }
    } else {
        // Room/Cottage: any reservation in this month blocks those dates
        $rows = $db->query("
            SELECT checkin_date, checkout_date
            FROM reservations
            WHERE facility_id = $facility_id
              AND status IN ('pending','approved')
              AND checkin_date  <= '$monthEnd'
              AND checkout_date >= '$monthStart'
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as $r) {
            $start = new DateTime($r['checkin_date']);
            $end   = new DateTime($r['checkout_date']);
            $cur   = clone $start;
            while ($cur <= $end) {
                $key = $cur->format('Y-m-d');
                if (!in_array($key, $blockedDates)) $blockedDates[] = $key;
                $cur->modify('+1 day');
            }
        }
    }
}

echo json_encode([
    'available'        => $available,
    'category'         => $fRow['category'],
    'conflict_type'    => $conflictType,
    'booked_dates'     => $blockedDates,
    'remaining_guests' => $remainingGuests,
    'message'          => $message,
]);