-- ============================================================
-- MIGRATION: branch-level vs. company-wide users
-- ============================================================
-- Adds users.branch_scope: 'assigned' (default) means this user only
-- sees/manages data for branches they're actually assigned to
-- (user_branches); 'all' means they see everything regardless of
-- assignment — a "company-wide" user. This is independent of role:
-- an Admin can be scoped to one branch ("branch admin", manages their
-- location fully but can't see others), while a non-admin could in
-- principle be made company-wide too, though in practice this mainly
-- distinguishes branch-level admins from company-wide ones.
--
-- Backward compatible: every existing admin is set to 'all' (matches
-- their current unrestricted behavior — nobody's visibility shrinks
-- because this migration ran); staff/cashier default to 'assigned',
-- which has zero practical effect until a 2nd branch exists.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `branch_scope` ENUM('assigned', 'all') NOT NULL DEFAULT 'assigned' AFTER `branch_id`;

UPDATE `users` SET `branch_scope` = 'all' WHERE `role` = 'admin';

SELECT 'Migration complete: branch_scope ready (existing admins set to company-wide).' AS status;
