-- ============================================================
-- MIGRATION: security alert "seen" tracking
-- ============================================================
-- Supports the new in-app high-risk-change alert on the dashboard
-- (a role losing users.manage/roles.manage, a role deleted, a sale
-- voided/edited, a product's selling price dropping 30%+). Visiting
-- /audit marks everything seen by stamping this column — see
-- AuditController::listAuditLog() and DashboardController.php.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `last_security_alert_seen_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login`;

SELECT 'Security alert seen-tracking migration complete.' AS status;
