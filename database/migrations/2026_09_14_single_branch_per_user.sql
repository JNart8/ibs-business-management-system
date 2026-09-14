-- ============================================================
-- MIGRATION: one branch per user
-- ============================================================
-- Decided: a non-company-wide user belongs to exactly one branch.
-- users/create.php and edit.php previously allowed checking multiple
-- branches per user via branch_ids[]. For any user currently assigned
-- to more than one, keep only their primary-marked branch, falling
-- back to users.branch_id, then the lowest branch_id, if none is
-- marked primary — generic, not a one-off fix for a specific user.
-- ============================================================

DELETE ub FROM user_branches ub
JOIN (
    SELECT t.user_id,
           COALESCE(
               (SELECT branch_id FROM user_branches WHERE user_id = t.user_id AND is_primary = 1 LIMIT 1),
               (SELECT branch_id FROM users WHERE id = t.user_id),
               (SELECT MIN(branch_id) FROM user_branches WHERE user_id = t.user_id)
           ) AS keep_branch_id
    FROM user_branches t
    GROUP BY t.user_id
) keep ON keep.user_id = ub.user_id
WHERE ub.branch_id != keep.keep_branch_id;

-- Every remaining user_branches row is now the sole one for its user —
-- make sure it's flagged primary and users.branch_id agrees with it.
UPDATE user_branches SET is_primary = 1;

UPDATE users u
JOIN user_branches ub ON ub.user_id = u.id
SET u.branch_id = ub.branch_id;

SELECT 'Single-branch-per-user migration complete.' AS status;
