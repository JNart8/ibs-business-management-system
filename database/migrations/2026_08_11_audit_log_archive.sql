-- ============================================================
-- MIGRATION: audit log archive table
-- ============================================================
-- Same structure as audit_log. Entries older than 1 year get moved
-- here (not deleted) by cron/archive_audit_log.php, keeping the live
-- table fast for day-to-day filtering while preserving full history
-- for compliance/lookback. See cron/archive_audit_log.php and
-- ARCHITECTURE.md for how this gets scheduled.
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_log_archive` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NULL,
    `action` VARCHAR(60) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `branch_id` INT UNSIGNED NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL,
    `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_archive_user` (`user_id`),
    INDEX `idx_audit_archive_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_archive_branch` (`branch_id`),
    INDEX `idx_audit_archive_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration complete: audit_log_archive ready.' AS status;
