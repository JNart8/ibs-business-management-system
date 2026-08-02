-- ============================================================
-- MIGRATION: single-session enforcement
-- ============================================================
-- Adds a session_token column used to detect when a user logs in
-- from a second place — the older session is then signed out
-- automatically on its next request. See enforceSingleSession()
-- in app/helpers/functions.php for the full mechanism.
--
-- Backward compatible: every existing user gets NULL here, which
-- is treated as "no enforcement yet" for any of their already-active
-- sessions — nobody is forced to log out just because this migration
-- ran. Enforcement begins the next time each user logs in fresh.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `session_token` VARCHAR(64) NULL DEFAULT NULL AFTER `locked_until`;

SELECT 'Migration complete: single-session enforcement ready.' AS status;
