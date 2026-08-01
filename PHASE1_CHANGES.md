# Phase 1: Plan Tiers + Branch Scaffolding

## What changed

| File | Change |
|---|---|
| `database/migrations/2026_08_01_phase1_plans_and_branches.sql` | **New.** Adds `settings.plan`, a `branches` table (seeded with one "Main Branch"), and `branch_id` columns (defaulted to that branch) on `sales`, `purchases`, `stock_movements`, `expenses`. |
| `app/config/plans.php` | **New.** Single source of truth for what Core/Growth/Enterprise include. |
| `app/helpers/functions.php` | **Added** `planDefinitions()`, `currentPlan()`, `currentPlanDefinition()`, `planAllows()`, `withinUserLimit()`, `planUpgradeNotice()`. |
| `public/index.php` | **Added** a plan-gating block, right after the existing role-restriction block, that 403s any route in `$planFeatureMap` the current plan doesn't include. |
| `app/views/layout/header.php` | **Updated** desktop + mobile nav to hide menu items the current plan doesn't include (Distributor, Financial Accounts, Expenses, Suspense, advanced Reports). |
| `app/controllers/UserController.php` | **Added** a user-limit check in `storeUser()` — blocks creating a new user once the plan's `max_users` is hit. |
| `app/views/settings/index.php` | **Added** a read-only "Current plan" panel. |

## How to apply to a client database

1. **Back up first** — export the client's DB via phpMyAdmin (30 seconds, cheap insurance).
2. Run `database/migrations/2026_08_01_phase1_plans_and_branches.sql` against that client's database.
3. If the client isn't on Core (the default), set their plan explicitly:
   ```sql
   UPDATE settings SET plan = 'growth' WHERE id = 1;   -- or 'enterprise'
   ```
4. Deploy the updated codebase (upload over FTP/File Manager as usual — this migration doesn't require any new PHP extensions or server config).

## Changing a client's plan later

No redeploy needed — just run the `UPDATE settings SET plan = ...` query above against that
client's database. `currentPlan()` is cached per-request only (not per-session), so the change
takes effect on the very next page load.

## What this does *not* do yet (by design)

- **No self-service upgrade UI.** Plan changes are vendor-controlled via direct DB update, per
  the earlier decision. If you want clients to request an upgrade themselves later, that's a
  small addition (an "Upgrade" button that emails you), not a redesign.
- **No multi-branch UI.** The `branches` table and `branch_id` columns exist so that when
  multi-branch is eventually built (Phase 3), no historical sales/purchase/stock data needs to
  migrate — it already belongs to "Main Branch". But there's no branch picker, no per-branch
  stock, and no transfers yet. `products.current_stock` is still a single global number.
- **No granular permissions.** Phase 2 (a `permissions` / `role_permissions` table + an
  Enterprise role editor) is a separate, additive piece of work that doesn't touch anything
  built here — happy to start on it whenever you're ready.

## Testing checklist before rolling out to a real client
- [ ] Run the migration against a **copy** of a client DB first, not production directly.
- [ ] Log in as each role (admin/staff/cashier) on a `core`-plan install and confirm Distributor,
      Financial Accounts, Expenses, Suspense, and advanced Reports links are gone from the nav,
      and hitting those URLs directly returns the "Upgrade Required" page instead of a 500 error.
- [ ] Flip `settings.plan` to `growth`, reload (no logout needed), confirm those links reappear.
- [ ] Try creating a 4th user on a `core`-plan install (limit is 3) and confirm it's blocked with
      a clear message instead of silently succeeding.
