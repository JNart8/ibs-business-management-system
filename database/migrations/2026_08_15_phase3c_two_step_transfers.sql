-- ============================================================
-- PHASE 3c FOLLOW-UP: TWO-STEP STOCK TRANSFERS
--
-- Transfers were instant (dispatch = receive, same request). This
-- adds the two-step workflow flagged as deferred at the time:
-- dispatch deducts the source branch immediately, but the
-- destination branch only gets credited once it confirms receipt
-- (which may be a different quantity than what was sent — loss/
-- damage in transit). A transfer still 'in_transit' (not yet
-- received) can also be cancelled, returning stock to the source —
-- this is the "undo a mistake" mechanism for the common case; a
-- post-receipt reversal is a separate, harder problem and is not
-- addressed here.
--
-- See ARCHITECTURE.md §6q/§6s for the full design rationale.
-- ============================================================

ALTER TABLE `stock_transfers`
ADD COLUMN `status` ENUM('in_transit', 'completed', 'cancelled') NOT NULL DEFAULT 'completed' AFTER `notes`,
ADD COLUMN `received_at` TIMESTAMP NULL AFTER `status`,
ADD COLUMN `received_by` INT UNSIGNED NULL AFTER `received_at`,
ADD CONSTRAINT `fk_transfers_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Existing transfers predate this workflow and already completed
-- instantly under the old model — the column default above already
-- backfills them to 'completed', so behavior for existing data is
-- unchanged.

ALTER TABLE `stock_transfer_items`
ADD COLUMN `quantity_received` DECIMAL(12,3) NULL AFTER `quantity`;

SELECT 'Phase 3c two-step transfers migration complete.' AS status;
