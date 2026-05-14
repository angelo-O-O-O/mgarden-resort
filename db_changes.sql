-- ============================================================
-- MGarden Beach Resort — DB Changes
-- ============================================================

-- Ratings & Reviews table
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id`      int(11)     NOT NULL AUTO_INCREMENT,
  `guest_id`       int(11)     NOT NULL,
  `facility_id`    int(11)     NOT NULL,
  `reservation_id` int(11)     DEFAULT NULL,
  `rating`         tinyint(1)  NOT NULL DEFAULT 5,
  `review_text`    text        DEFAULT NULL,
  `created_at`     timestamp   NOT NULL DEFAULT current_timestamp(),
  `status`         enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `unique_reservation_review` (`guest_id`, `reservation_id`),
  KEY `idx_facility` (`facility_id`),
  KEY `idx_guest`    (`guest_id`),
  KEY `idx_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
