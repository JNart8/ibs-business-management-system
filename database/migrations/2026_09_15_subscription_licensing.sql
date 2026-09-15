-- ============================================================
-- MIGRATION: subscription lock + unlock codes
-- ============================================================
-- One row per install (like `settings`). `expires_at` drives
-- everything — see licenseState() in functions.php: the app shows
-- a renewal banner inside `warning_days` of expiry, and hard-locks
-- `grace_days` after expiry (app/config/licensing.php). No status
-- column on purpose — it's derived live from expires_at so there's
-- never a second source of truth to fall out of sync. Editing
-- expires_at directly (phpMyAdmin) is an intentional escape hatch,
-- same as flipping `settings.plan` already works today.
--
-- `install_id` is shown to the client on the /license page — they
-- quote it when requesting a renewal so the code you issue (see
-- tools/generate-license-code.php) is bound to this install only.
--
-- Grandfathers any install already in production: everyone gets a
-- 90-day starting window from the moment this migration runs, so
-- nothing gets locked out the instant this ships.
-- ============================================================

CREATE TABLE IF NOT EXISTS `license` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `install_id` CHAR(16) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `last_code_used` VARCHAR(255) NULL,
    `last_unlocked_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `license` (`install_id`, `expires_at`)
SELECT SUBSTRING(REPLACE(UUID(), '-', ''), 1, 16), DATE_ADD(NOW(), INTERVAL 90 DAY)
WHERE NOT EXISTS (SELECT 1 FROM `license`);

SELECT 'Subscription licensing migration complete: install grandfathered 90 days from today.' AS status;
