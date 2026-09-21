-- ============================================================
-- MIGRATION: audit trail
-- ============================================================
-- A generic audit_log table plus one permission to view it.
-- Scope deliberately kept to money/access-changing actions, not
-- routine data entry — logging everything makes the log useless for
-- finding anything; logging too little defeats the purpose. See
-- ARCHITECTURE.md §6i/§6n for what's actually logged and why.
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NULL COMMENT 'Snapshot at time of action, so entries stay readable if the user is later deleted',
    `action` VARCHAR(60) NOT NULL COMMENT 'e.g. sale.void, user.update, role.permissions_changed',
    `entity_type` VARCHAR(50) NULL COMMENT 'e.g. sale, user, role, account, branch, settings',
    `entity_id` INT UNSIGNED NULL,
    `branch_id` INT UNSIGNED NULL COMMENT 'NULL for system-wide actions (e.g. role/permission changes) not tied to one branch',
    `details` TEXT NULL COMMENT 'JSON-encoded context — what changed, old/new values where relevant',
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_branch` (`branch_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`key`, `label`, `category`) VALUES
    ('audit.view', 'View Audit Log', 'Administration')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_key`) VALUES
    (1, 'audit.view');

SELECT 'Migration complete: audit trail ready.' AS status;
