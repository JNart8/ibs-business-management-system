-- ============================================================
-- MIGRATION: Account-balance visibility setting
-- ============================================================
-- Wherever an account is picked — the POS, purchases, distributor
-- sales, expenses, customer and supplier deposits — each cash /
-- mobile money / bank account is listed with its current balance.
-- Some clients don't want cashiers and other front-line staff
-- seeing what the business holds, so this adds a toggle. When off,
-- only users with financial_accounts.access (who can open the
-- Financial Accounts page and see the balances there anyway) get
-- them; everyone else never receives them at all (not merely hidden).
-- Defaults to 1 (today's behavior) so this migration changes
-- nothing for any existing install until someone opts out.
-- ============================================================

ALTER TABLE `settings`
    ADD COLUMN `show_account_balances_to_all` TINYINT(1) NOT NULL DEFAULT 1 AFTER `pos_default_walkin`;

SELECT 'Migration complete: show_account_balances_to_all setting ready (defaults to on).' AS status;
