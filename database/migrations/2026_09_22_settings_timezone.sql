-- ============================================================
-- Configurable timezone.
--
-- Both the app's PHP timezone (app/config/app.php) and the MySQL
-- session timezone (app/config/database.php) were hardcoded to
-- Africa/Accra / +02:00 — fine for the original Ghana deployment, but
-- silently wrong for any client in a different timezone (e.g. a
-- Malawi deployment: Africa/Blantyre is also +02:00 today, so the
-- fixed MySQL offset happened to still match, but Accra observes no
-- DST swings either way it could still drift, and PHP's date()-based
-- timestamps everywhere else were computed in Ghana's actual zone).
--
-- Default is 'Africa/Accra' — matches the previous hardcoded value,
-- so an existing install's timestamps don't shift on migration.
-- Change it per install via Settings → General.
-- ============================================================

ALTER TABLE `settings`
ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT 'Africa/Accra' AFTER `currency`;

SELECT 'Migration complete: settings.timezone added (default Africa/Accra — change it in Settings for this install).' AS status;
