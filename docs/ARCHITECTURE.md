# IBS Sales App — Multi-Plan / Multi-Branch Architecture

**Status:** Phase 1 shipped. Phase 1b (schema scaffolding) shipped. Phase 2 & 3 not started.
**Last updated:** 2026-08-02

---

## 1. Goal

Support three product tiers — **Core, Growth, Enterprise** — from a **single codebase**,
without maintaining three forked projects, deployed on **cPanel shared hosting**.

## 2. Deployment model (decided)

- One cPanel account, one **addon domain/subdomain + one database per client**.
- Each client gets a full copy of the same codebase (uploaded via FTP/File Manager —
  current host has no SSH access). No shared filesystem/symlink tricks between clients for now.
- The "one codebase" goal is satisfied by having a single repo/zip that gets deployed
  multiple times, not by clients sharing a filesystem or database.
- Plan tier lives in each client's own database (`settings.plan`), not in code — the
  same codebase behaves differently per client based on that one value.
- Revisit shared-core-via-symlinks if/when the host changes to one with SSH access.

## 3. Who controls plan changes

Vendor-controlled only, via direct database update (phpMyAdmin), e.g.:
```sql
UPDATE settings SET plan = 'growth' WHERE id = 1;
```
No self-service upgrade UI for now — not worth the added attack surface until there's
real client demand for it. `currentPlan()` is cached per-request only (not session), so
a plan change takes effect on the client's very next page load, no redeploy or logout needed.

## 4. Final feature matrix

| Feature | Core | Growth | Enterprise |
|---|---|---|---|
| Auth, RBAC (fixed roles), customer/supplier/product mgmt, sales, purchases, inventory, credit/deposits | ✅ | ✅ | ✅ |
| Basic reports: sales, low stock, top selling, stock valuation | ✅ | ✅ | ✅ |
| Advanced reports: profit margin, profit & loss, dead stock, receivables, payables | — | ✅ | ✅ |
| Bulk import/export | — | ✅ | ✅ |
| Suspense transactions | — | ✅ | ✅ |
| Financial accounts — view balances, view/edit account details, view transaction history, transfer funds | ✅ | ✅ | ✅ |
| Financial accounts — create new accounts | — | ✅ | ✅ |
| Expenses tracking | — | ✅ | ✅ |
| Distributor deliveries | — | — | ✅ |
| Multi-branch (stock/sales/purchases + transfers) | — | — | 🔜 Phase 3 |
| Granular per-feature permissions / custom roles | — | — | 🔜 Phase 2 |
| Max users | 3 | 8 | Unlimited |

**Deliberately excluded from the matrix:** "shared hosting/backups" and "priority support"
were in the original proposal but aren't code-gateable features — they're pricing/business
terms and belong on the pricing page only, not in `plans.php`.

## 5. Issues raised, and recommendations

### 5.1 "How do we keep a client's data safe when they upgrade Growth → Enterprise?"

**Concern:** upgrading a client shouldn't require migrating live sales/stock/financial history.

**Recommendation:** lay branch scaffolding down in Phase 1, *before* any client actually
needs multi-branch — a `branches` table seeded with one row ("Main Branch") on every
install, and `branch_id` columns (defaulted to that row) added to every table that will
eventually need to be branch-aware. Result: when a client upgrades and Phase 3 eventually
ships, there is no migration event — their entire history already belongs to "Main Branch."
Only new records reference new branches.

**Status:** done. See §6.

### 5.2 "Should products be segregated per branch?"

**Recommendation: No — shared catalog, per-branch stock.**
A product's identity (name, SKU, price, category) doesn't change by location; only the
*quantity on hand* does. Duplicating the catalog per branch guarantees drift (price
updates, new products, category changes all need repeating N times). Splitting stock into
a `branch_stock (product_id, branch_id, quantity, reorder_level)` table also makes
inter-branch stock transfers trivial — just moving a quantity between two rows for the
same product, not reconciling two different product records.

*Optional, future, not needed now:* a `branch_product_prices` override table if a client
ever needs different selling prices per location.

### 5.3 "Should customers be segregated per branch?"

**Recommendation: No — shared identity and balance, for risk-management reasons, not just convenience.**
`customers.credit_limit` / `current_balance` represent a credit relationship with the
*business*, not with one location. Segregating customers per branch would let a customer
rack up 2x their intended credit exposure by splitting purchases across branches, and
would hide a customer's existing balance from staff at a branch they haven't visited
before. Same logic applies to `suppliers`.

**What does vary per branch:** the *transactions* (a sale, a deposit, a purchase) — those
already carry `branch_id` as of Phase 1.

### 5.4 "Each branch should have its own accounts, especially cash"

**Recommendation: Yes — this is the one place segregation is correct, and it's a different
kind of entity from products/customers.** A cash account represents a physical drawer in a
physical location — it can't be shared the way a product catalog or a customer identity
can. Two branches can't share one cash balance any more than two physical tills can share
one drawer of banknotes.

Design: `accounts.branch_id`, **nullable**:
- Branch-scoped accounts (cash, mobile money at that till) get a `branch_id`.
- Company-wide accounts (a main bank account everything eventually settles into) get
  `branch_id = NULL` — genuinely shared, since a bank account isn't a physical object
  sitting in one store.
- Each new branch gets a default cash account auto-created (mirrors how `finance_accounts.sql`
  already seeds one default cash account today).
- The existing `account_transfers` table is the mechanism for "branch hands off
  end-of-day cash to head office" — no new table needed for that, just branch-aware
  `from_account`/`to_account`.

**Status:** schema scaffolding done (§6); branch-aware UI/reporting is Phase 3 work.

### 5.5 "Customers can deposit money and use it for a future purchase — what if that purchase happens at a different branch? We need to be able to transfer the money, and record any transfer charges (and decide whether the client or the business bears that cost)."

This is a real problem, and worth explaining *why* it's harder than a normal transfer:
customer credit is a **pooled liability**, not earmarked cash. When a customer deposits
GHS 500 at Branch A, that money is absorbed into Branch A's till — there's no way to trace
"this exact GHS 300 spent at Branch B six weeks later came from that specific deposit."
Real multi-location businesses don't try to trace individual notes for store credit/gift
cards either — they track a **net position between locations** and settle periodically.

**Recommendation: build a net-position report + manual settlement, not an automatic
per-transaction transfer.**

1. **Report, not a trigger:** "customer credit issued vs. redeemed, by branch, for a given
   period" — sum of deposits by the depositing branch, vs. sum of credit-funded sales by
   the redeeming branch. Tells whoever manages the business which branch is net owed cash.
2. **Settlement reuses the existing transfer mechanism:** the (soon to be branch-aware)
   `account_transfers` table already has everything needed — including a `charges` column
   that already exists in the schema today.
3. **Fee handling — one new column, no rebuild:**
   `account_transfers.charged_to ENUM('customer','business') DEFAULT 'business'`.
   - `business`: charges post as a `'charge'` entry in `account_transactions` — this ENUM
     value **already exists** in the current schema.
   - `customer`: recommend defaulting away from this for *internal branch-balancing*
     transfers specifically — the customer already fully paid at Branch A; the cost of
     you moving your own money between your own tills is an operating cost, not something
     to bill them for. Reserve `charged_to = customer` for the separate, unrelated case of
     a customer explicitly asking you to wire *their* money somewhere.
4. **Optional traceability:** nullable
   `account_transfers.settlement_type ENUM('routine','customer_credit_balancing') DEFAULT 'routine'`
   to distinguish these from ordinary treasury transfers in the ledger, without needing to
   link a transfer to one specific customer or deposit.

**What to explicitly avoid:** FIFO/lot-tracking of which specific deposit funds which
specific sale. It's significant engineering effort for a number nobody actually needs at
that granularity — the business only cares about the net branch position, not the
provenance of specific banknotes.

**Status:** design only — not yet built. Small, low-risk addition once `accounts.branch_id`
exists; fold into the Phase 3 build rather than treating it as a separate phase.

### 5.6 Granular permissions (Enterprise)

**Recommendation:** a `permissions` / `role_permissions` table, seeded so the existing
`admin`/`staff`/`cashier` roles behave *exactly* as they do today. All plans use the same
`can($permission)` check everywhere; Core/Growth simply never see the "manage roles"
screen (gated by `planAllows('advanced_permissions')`). Enterprise unlocks a UI to create
custom roles and toggle per-feature checkboxes.

**Status:** done. See §6 for what shipped, and §5.10 for a gap found while building it.

### 5.10 Gap found while building Phase 2: `/purchases` had no role restriction at all

While mapping every existing role check onto the new permission catalog, I found that
`/purchases` was dispatched to `PurchaseController` but was **never listed** in the old
`$roleRestrictions` array — meaning any logged-in user, including a cashier, could reach
it directly by URL. The dashboard only *hid* the "New Purchase" button for cashiers; it
never actually blocked the route. Flagging this immediately per your standing instruction,
rather than leaving it for later.

**Fix:** added a `purchases.manage` permission (granted to Admin/Staff by default, matching
every other inventory-adjacent module) and included `/purchases` in the new
`$permissionRestrictions` map in `public/index.php`. This is a small, deliberate behavior
change for Core/Growth Cashier accounts: as of this migration, they can no longer reach
`/purchases` by direct URL (they never could through the UI anyway, so the practical
impact should be limited to closing a URL-guessing gap, not to anyone's normal workflow).

**Status:** done. See §6.

### 5.7 Multi-branch build scope

Confirmed as a **full build**: separate stock/sales/purchases per branch, plus transfers.
Flagged as its own project phase (not a quick flag-flip) because `products.current_stock`
is currently a single global integer, not a per-location table — every controller that
reads or writes stock needs to change, not just the schema. Recommendation stands: don't
bundle this into Phase 1/2, build it once there's a real Enterprise client to build against.

### 5.8 "Core can't see account balances at all — but sales already let you pick which account a sale posts to"

**Client's proposal:** Core should be able to *view* the Financial Accounts page (balances,
transaction history) and *move funds between accounts* (transfer), but not *create* new
accounts. Two default accounts (Cash, Mobile Money) get seeded at implementation time so
Core clients never need to create one.

**Recommendation: agreed, with one addition** — bundle per-account transaction history
into the same "base" access as balances. Viewing a balance without being able to see how
it was arrived at undermines trust in the number; both are read-only and equally low-risk
to expose.

**Design:** split the single `financial_accounts` feature into two keys:
- `financial_accounts` (view balances, view transaction history, transfer funds) → **all plans**.
- `manage_financial_accounts` (create new accounts) → **Growth+ only**.

The "+ Add Account" button/modal is gated in the view; the actual enforcement is at the
router level (`/financial-accounts/store` requires `manage_financial_accounts`), consistent
with how role restrictions are enforced elsewhere in this app — hiding the button is a UX
nicety, the backend check is what actually matters.

**Status:** done. See §6.

### 5.9 "Is an in-app edit screen for accounts (name/number) a must-have?"

**Yes — and this stopped being a "someday" gap the moment §5.8/§6 shipped a default
Mobile Money account with `provider = NULL` and `account_number = NULL`.** Without an edit
screen, the only way for a client to record their real MoMo number would be a manual DB
edit on your end, for every single client, immediately after onboarding. That's the kind
of gap worth flagging as soon as it's spotted rather than waiting to be asked, per your
standing instruction — so: flagging it now, and I've built it rather than leaving it open.

**What's editable:** `name`, `provider`, `account_number`, and an active/inactive toggle
(soft-retire an account without deleting it — deletion isn't offered anywhere in this app,
correctly, since `account_transactions`/`account_transfers` reference accounts by ID and a
hard delete would orphan financial history).

**What's deliberately *not* editable, and why:**
- **`type`** (cash/mobile_money/bank) — changing it after transactions exist would
  misclassify historical records; the color-coded UI and reporting logic assume it's stable.
- **`balance`** — must only ever move through a deposit/transfer/sale, never a direct edit,
  or the `account_transactions` ledger stops reconciling with the account's actual balance.
- **Default accounts (`is_default = 1`) can't be deactivated** — Core clients only have
  these two; removing the ability to post sales entirely would be a bigger problem than
  the typo it might be fixing.
- **The last active account overall can't be deactivated**, regardless of plan — sales and
  purchases need at least one account to post to.

**Access:** bundled into the same base tier as viewing balances/transfers (§5.8) — editing
your own account's contact details is basic upkeep, not "adding capacity," so it's
available on every plan, not just Growth+.

**Status:** done. See §6.

### 5.11 "The account ledger can grow enormously — add filtering and export"

**Concern:** an account's transaction history has no practical ceiling — it only grows,
never gets archived — so scrolling through it to find something becomes unworkable over
time, and there was no way to get the data out for offline analysis or a client's
bookkeeper.

**Built:** a filter bar on the ledger page (type: deposit/withdrawal/transfer in/transfer
out/charge; date range) and a CSV export button. The critical design decision: **the export
must never diverge from what's filtered on screen**, so both the ledger page
(`FinancialAccountsController::showTransactions()`) and the CSV export
(`ExportController::exportAccountLedger()`) call the exact same two shared helper functions
— `ledgerFilters()` and `buildLedgerWhere()` — added to the global `functions.php` rather
than duplicated per-controller (unlike most other exports in this app, which each rebuild
their own filter logic inline; this one case justified sharing code across controllers
specifically because "export matches the screen" was the explicit requirement, and any
future filter added to one place is now guaranteed to apply to the other automatically).

**Access:** the export button is gated behind the existing `imports_exports` plan feature
(Growth+) — the same gate every other CSV export in the app already uses. Filtering and
viewing the ledger itself stays available on every plan, matching §5.8. This means Core can
filter the on-screen ledger but not export it; flagging this explicitly in case Core should
be able to export its own ledger too — it wasn't asked for, so I kept the existing boundary
rather than assuming an upsell opportunity should be removed.

**Known systemic gap, not introduced here:** none of the CSV export types in this app
(products, customers, sales, transactions, and now the account ledger) are permission-gated
— only plan-gated. A cashier without `financial_accounts.access` couldn't discover an
account's ledger through the UI, but could still hit `/export/account-ledger?account_id=1`
directly if they guessed the URL, the same way they always could for `/export/customers`,
`/export/sales`, etc. This predates this change and applies across the board — worth a
dedicated pass if it matters for your threat model, but out of scope for this specific
request.

**Status:** done. See §6c.

## 6. What's actually been built (Phase 1 + 1b)

| File | Change |
|---|---|
| `database/migrations/2026_08_01_phase1_plans_and_branches.sql` | `settings.plan`; `branches` table seeded with "Main Branch"; `branch_id` (default 1) on `sales`, `purchases`, `stock_movements`, `expenses`. |
| `database/migrations/2026_08_02_phase1b_accounts_users_branch.sql` | `accounts.branch_id` (nullable, default 1 — set to `NULL` later to mark an account as shared/company-wide); `users.branch_id` (nullable, default `NULL` — unused until Phase 3 ships a branch-aware login/POS flow). |
| `database/migrations/2026_08_03_phase1c_seed_default_momo_account.sql` | Seeds a default Mobile Money account (idempotent) alongside the existing default Cash Account, so Core clients — who can't create accounts — have both out of the box. |
| `app/config/plans.php` (updated) | Split `financial_accounts` (view/transfer — now on all plans) from `manage_financial_accounts` (create accounts — Growth+ only). |
| `public/index.php` (updated) | Route gate narrowed from all of `/financial-accounts` to just `/financial-accounts/store`. |
| `app/views/layout/header.php` (updated) | Financial Accounts nav link now visible on all plans. |
| `app/views/financial_accounts/index.php` (updated) | "+ Add Account" button gated behind `manage_financial_accounts`; "Move Funds" visible to all; each account card now has an Edit link. |
| `app/controllers/FinancialAccountsController.php` (updated) | New `edit`/`update` actions — editable: name, provider, account number, active status. Locked: type, balance. Guards against deactivating a default or the last active account. |
| `app/views/financial_accounts/edit.php` (new) | The edit form itself. |
| `app/config/plans.php` | Single source of truth for Core/Growth/Enterprise feature lists and limits. |
| `app/helpers/functions.php` | `currentPlan()`, `currentPlanDefinition()`, `planAllows()`, `withinUserLimit()`, `planUpgradeNotice()`. |
| `public/index.php` | Plan-gating block (403s a route if the current plan doesn't include its feature), next to the existing role-restriction block. |
| `app/views/layout/header.php` | Desktop + mobile nav hide items the current plan doesn't include. |
| `app/controllers/UserController.php` | Blocks creating a new user once `max_users` is hit. |
| `app/views/settings/index.php` | Read-only "Current plan" panel. |


**Not yet built:** anything that *reads* `branch_id` to actually scope behavior — today
every table has the column, but every query still behaves as if there's one branch,
because there is only one branch per install right now. That's intentional — see §5.1.

## 6b. What's actually been built (Phase 2)

| File | Change |
|---|---|
| `database/migrations/2026_08_04_phase2_permissions.sql` | New `roles`, `permissions`, `role_permissions` tables; `users.role_id`. Seeded to reproduce today's access rules exactly, plus closes the `/purchases` gap (§5.10). All existing users backfilled to their matching system role. |
| `app/helpers/functions.php` (updated) | New `can()`, `currentUserPermissions()`, `roleSlug()`, `roleName()`, `assignableRoles()`, `canManageAnything()`. |
| `public/index.php` (updated) | `$roleRestrictions` replaced with a permission-based `$permissionRestrictions` map; `/roles` added to the dispatch table and gated by `roles.manage` + the `advanced_permissions` plan feature. |
| `app/controllers/UserController.php` (updated) | Admin-only gate now `can('users.manage')`; create/edit now read/write `role_id` (validated against `assignableRoles()`), keeping the legacy `role` enum in sync for the few remaining cosmetic reads. |
| `app/controllers/SettingsController.php` (updated) | Admin-only gate now `can('settings.manage')`. |
| `app/controllers/SaleController.php` (updated) | Back-date check now `can('sales.backdate')`; both edit/void checks now `can('sales.edit')` (previously referenced a `'manager'` role that was never actually assignable). |
| `app/views/users/create.php`, `edit.php` (updated) | Role dropdown now built from `assignableRoles()` — shows custom roles too when the plan allows it — and posts `role_id` instead of a hardcoded string. |
| `app/views/users/index.php`, `app/views/account/index.php` (updated) | Role badge now shows the real role name via `role_id` (so custom role names display correctly), not just the 3-value enum. |
| `app/views/dashboard/index.php`, `sales/index.php`, `sales/pos.php` (updated) | UI conditionals converted from hardcoded role checks to `can()`. |
| `app/views/layout/header.php` (updated) | Users/Settings nav links now permission-based; whole back-office nav section visibility now uses new `canManageAnything()` helper instead of an admin/staff string check; new Roles nav link (Enterprise + `roles.manage` only). |
| `app/controllers/RoleController.php` (new) | Full CRUD for roles: list, create custom role, edit any role's permission set (including system roles), delete (custom roles only, only if unassigned). Guards the built-in Admin role from ever losing `users.manage`/`roles.manage` via the UI. |
| `app/views/roles/index.php`, `create.php`, `edit.php` (new) | The role management screens themselves. |

**Not built (deliberately deferred):** permission granularity stops at the module level
(e.g. one `products.manage` toggle, not separate view/create/edit/delete toggles per
module) — this matches the granularity the app already enforced before Phase 2, and going
finer is a bigger UI/UX exercise better scoped if a real client asks for it.

## 6c. What's actually been built (account ledger filtering + export)

| File | Change |
|---|---|
| `app/helpers/functions.php` (updated) | New shared `ledgerFilters()` and `buildLedgerWhere()` — used by both the ledger screen and its export so they can never drift apart. |
| `app/controllers/FinancialAccountsController.php` (updated) | `showTransactions()` now applies type + date-range filters via the shared helpers. |
| `app/controllers/ExportController.php` (updated) | New `account-ledger` export type; `exportAccountLedger()` reuses the same shared helpers. |
| `app/views/financial_accounts/transactions.php` (updated) | Filter bar (type + date range), result count, "no matches" vs. "no transactions" empty states, and an Export CSV button gated behind `imports_exports` (Growth+). |

## 6d. What's actually been built (export permissions, decimal stock, single-session login)

### Export permission gap — closed
| File | Change |
|---|---|
| `app/controllers/ExportController.php` (updated) | New `$exportPermissionMap` checked via `can()` before any export runs: `categories`→`categories.manage`, `products`→`products.manage`, `suppliers`→`suppliers.manage`, `customers`→`customers.manage`, `transactions`→`transactions.manage`, `account-ledger`→`financial_accounts.access`, `stocks`→`stock.manage`. Report exports and `sales` intentionally left ungated, matching their source pages. |

Confirmed separately: Core already couldn't reach any `/export/*` route (including the ledger), since the whole prefix requires the Growth+ `imports_exports` plan feature — no change was needed there.

### Decimal stock quantities
Products sold by weight/volume (e.g. "0.5 kg") couldn't be recorded — every quantity field
only accepted whole numbers, both in the database (`INT` columns) and in the UI (`min="1"`,
`parseInt()`, integer-only stepper clamps). Products sold by count (pcs, box, dozen) are
unaffected by this change — decimals are now merely *allowed*, not required of anyone.

| File | Change |
|---|---|
| `database/migrations/2026_08_05_decimal_stock_quantities.sql` (new) | Converts `products.current_stock`/`reorder_level`, `stock_movements.quantity`/`previous_stock`/`new_stock`, `sale_items.quantity`, `purchase_items.quantity`, and `product_cost_history.quantity`/`stock_before`/`stock_after` from `INT` to `DECIMAL(12,3)`. Existing whole numbers convert losslessly. |
| `app/controllers/DistributorController.php`, `ImportController.php`, `PurchaseController.php`, `SaleController.php`, `StockController.php`, `ProductController.php` (updated) | Every `intval()` applied to a quantity or stock value changed to `floatval()` (roughly 20 call sites across these six files, including a third stock-adjustment action in `StockController` found while sweeping). One float-equality comparison (`$diff === 0` in the stock-adjustment save) changed to an epsilon check, since exact `===` comparison on floats is unreliable. |
| `app/views/sales/pos.php`, `purchases/create.php`, `purchases/edit.php`, `distributor/create.php`, `stock/stock-in.php`, `stock/stock-out.php`, `stock/adjust.php`, `products/create.php`, `products/edit.php` (updated) | Quantity inputs: `min`/`step` now allow decimals; JS quantity clamps lowered from `1` to `0.01`; `parseInt()` → `parseFloat()` throughout cart/preview calculations. |
| `app/helpers/functions.php` (updated) | New `formatQty()` — displays whole numbers plainly (`5`) but keeps up to 3 decimal places for fractional quantities (`0.5`), trimming trailing zeros. Used everywhere `number_format($quantity)` was silently rounding a real fractional quantity down to a whole number for display (receipts, purchase view, sales/profit-loss/top-selling reports, CSV exports). |

### Single-session login enforcement
**Chosen approach: automatic invalidation of the older session, not an interactive "someone
else is logged in — continue anyway?" prompt.** The two options cost the same at steady
state — either way, every authenticated request needs one cheap check to know whether this
session has been superseded — so the "least likely to affect speed" option is the one that
doesn't *also* add an extra round-trip at login time (a confirmation screen would). Automatic
invalidation wins on that basis without giving up anything on ongoing request speed.

**Mechanism:** a random token is generated on every successful login and stored in both the
`users` table and the session. Every authenticated request compares the two with a single
indexed primary-key lookup — the same cost class as `currentPlan()`/`currentUserPermissions()`,
which already run once per request. A mismatch means a newer login has happened elsewhere for
that account, so the older session is destroyed immediately with a clear message on next use.
Multiple browser tabs from the *same* login share one session/token and are unaffected — this
only catches a genuinely separate, newer login.

| File | Change |
|---|---|
| `database/migrations/2026_08_06_single_session_enforcement.sql` (new) | Adds `users.session_token` (nullable). Existing sessions are unaffected until their user's next fresh login — nobody is forced out purely by this migration running. |
| `app/controllers/AuthController.php` (updated) | `processLogin()` generates a fresh token, stores it in the DB and `$_SESSION`. `processLogout()` clears the DB token. |
| `app/helpers/functions.php` (updated) | New `enforceSingleSession()` — compares session vs. DB token; destroys the session and redirects to `/login` with an explanatory message on mismatch. |
| `public/index.php` (updated) | Calls `enforceSingleSession()` right after the existing login-required gate, before any permission/plan checks. |

## 7. Roadmap

| Phase | Scope | Risk / dependency |
|---|---|---|
| **1** ✅ | Plan tiers, feature gating, route/nav enforcement, user limits | Shipped |
| **1b** ✅ | `accounts.branch_id`, `users.branch_id` scaffolding | Shipped |
| **2** ✅ | `permissions` / `role_permissions` tables + Enterprise role editor | Shipped |
| **3** | Multi-branch build: `branch_stock` replacing `products.current_stock`, branch-aware POS/reporting, inter-branch transfers, customer-credit branch settlement (§5.5) | Needs a real Enterprise client to design against; biggest single phase |

## 7b. Phase 2 testing checklist before rolling out to a real client

- [ ] Run `2026_08_04_phase2_permissions.sql` against a **copy** of a client DB first.
- [ ] Log in as admin, staff, and cashier on a Core-plan install; confirm nothing changed
      except that cashier can no longer reach `/purchases` directly (the §5.10 fix).
- [ ] On an Enterprise-plan install, confirm the "Roles" nav link appears for an
      admin-level user and confirm `/roles` 403s on Core/Growth even for an admin.
- [ ] Create a custom role with only `products.manage` + `stock.manage`; assign it to a
      test user; confirm they can reach Products/Stock but nothing else, and that the
      main nav shows the Inventory dropdown but not People/Finance.
- [ ] Try to uncheck "Manage Users" and "Manage Roles & Permissions" on the built-in Admin
      role and save — confirm both stay checked regardless (the lockout guard).
- [ ] Try to delete a custom role that's still assigned to a user — confirm it's blocked
      with a clear message instead of silently orphaning that user's `role_id`.
- [ ] Confirm a user's role badge on `/users` and `/account` shows the actual custom role
      name, not a generic fallback.

## 7c. Testing checklist for this round (export permissions, decimal stock, single-session)

- [ ] Run both new migrations against a **copy** of a client DB first.
- [ ] As a cashier, confirm `/export/customers` (and the others in the permission map) now
      redirect with an error instead of downloading a CSV.
- [ ] Sell a kg-priced product with quantity `0.5` through POS; confirm it saves, deducts
      stock correctly, and displays as `0.5` (not `0` or `1`) on the receipt and in reports.
- [ ] Run a stock-in, stock-out, and stock adjustment with a decimal quantity; confirm the
      resulting `current_stock` is correct to 3 decimal places.
- [ ] Do a purchase with a decimal quantity, then edit that purchase — confirm the quantity
      isn't rounded when the edit form loads.
- [ ] Log in as the same user from two different browsers; confirm the first one gets
      signed out (with the explanatory message) on its very next click, not immediately —
      it only triggers on the next request, not via any live push.
- [ ] Confirm opening two tabs in the *same* browser after one login does **not** log
      either tab out — only a genuinely separate login should trigger this.

## 6e. What's actually been built (POS default-customer setting)

**Investigation first:** POS already defaulted to Walk-in Customer, both internally
(`showPOS()` loaded it and passed it to the page) and visibly (a blue "Walk-in Customer"
chip shown by default, next to a search box hinting "Walk-in (default)"). No bug — this was
already working as described.

**The actual question: should this be a setting, and is that advisable?** Yes, and cheaply
so. The reasoning: since walk-in customers are already blocked from credit and deposit
payments at the code level (pre-existing checks in `SaleController`), defaulting to walk-in
carries no data-integrity risk — the risky path (accidentally invoicing the wrong customer
on credit) is already guarded regardless of this setting. So the choice is purely a workflow
preference: a shop that's mostly walk-in retail wants the field pre-filled; a shop that's
mostly registered/credit customers (e.g. B2B or wholesale-leaning installs using this same
codebase) would rather the cashier be forced to actively choose every time, as a habit-forming
safeguard against forgetting. That's a real, if narrow, use case worth the one column and one
checkbox it costs to support — not something to build a bigger system around.

| File | Change |
|---|---|
| `database/migrations/2026_08_07_pos_default_customer_setting.sql` (new) | Adds `settings.pos_default_walkin` (default `1` — today's behavior, so this changes nothing until someone opts out). |
| `app/controllers/SaleController.php` (updated) | `showPOS()` only loads the walk-in customer as the initial selection when the setting is on; otherwise passes `null`. |
| `app/controllers/SettingsController.php`, `app/views/settings/index.php` (updated) | New toggle on the Settings page, alongside the existing discount-type setting. |
| `app/views/sales/pos.php` (updated) | Search box placeholder text adapts to whichever mode is active. No JS changes were needed — the badge display already hides correctly for a `null` selection, and a "please select a customer" guard already existed before `completeSale()` reads `selectedCustomer.id`, so turning the default off can't crash the sale flow; it can only require an explicit pick first. |

## 6f. Gap found and closed: the walk-in setting assumed a walk-in customer already exists

**What you noticed:** turning "Pre-select Walk-in Customer" on didn't actually make a
walk-in customer appear — because the setting only *reads* `customers.is_default = 1`, it
never *creates* that row. `schema.sql` seeds it (`WALK-IN-001` / "Walk-in Customer") for a
brand-new install, but any database that predates that seed line, or where the row was
deleted, would have the setting silently do nothing.

**Fix:** a new `ensureWalkInCustomerExists()` helper, called from two places so the fix
doesn't depend on remembering to re-save Settings:
- **`showPOS()`** — self-heals on the very next POS page load if the setting is on but no
  `is_default = 1` row exists.
- **Settings save handler** — also runs when you explicitly turn the setting on, so flipping
  it is immediately effective even before anyone opens POS.

Both call sites are idempotent (checks for an existing row first), and the auto-created row
uses `is_default = 1` — meaning every existing protection already keyed off that column
(can't delete it, can't take deposits on it, excluded from the customer list/reports)
applies automatically with no further changes needed.

Your plan to always seed "Walk-In-Customer" during implementation is still the right habit
going forward — this fix is a safety net for databases that predate that habit, not a
replacement for it.

| File | Change |
|---|---|
| `app/helpers/functions.php` (updated) | New `ensureWalkInCustomerExists()`. |
| `app/controllers/SaleController.php` (updated) | `showPOS()` calls it before loading the walk-in customer. |
| `app/controllers/SettingsController.php` (updated) | Calls it when `pos_default_walkin` is saved as on. |

## 6g. Header nav grouping: Users, Roles, Settings → "Administration"

**Ask:** the header was growing (Dashboard, POS, Inventory▾, People▾, Finance▾, Reports▾,
Users, Roles, Settings — nine slots when everything's unlocked), and Users/Roles/Settings
were three flat top-level items with no grouping.

**Recommendation: group all three, not just two.** Grouping only Users+Roles would leave
Settings stranded as a lone top-level item with no real space saved, and it's exactly as
admin-only as the other two — whoever can manage one of these three usually needs all of
them. Bundled into one "Administration" dropdown, matching the exact pattern already used
for Inventory/People/Finance/Reports (desktop), with a collapsible section on mobile
matching the existing Reports pattern.

| File | Change |
|---|---|
| `app/views/layout/header.php` (updated) | Users/Roles/Settings replaced with one "Administration" dropdown (desktop) / collapsible section (mobile, new `adminOpen` Alpine state). Visibility unchanged — each item still only shows if its own permission/plan check passes; the whole group hides if none do. |

## 6h. Phase 3a in progress: multi-branch foundation

**Decisions confirmed before building:**
- A user can be assigned to **multiple** branches (new `user_branches` pivot table), but POS
  shows **no picker** — each user has one "active" branch (`users.branch_id`) that's used
  automatically, changeable via a small switcher (header, desktop only for now) if they're
  assigned to more than one. Admins aren't restricted to their assigned branches for viewing,
  only for which branch their own POS sales post to.
- Multi-branch is **not** purely an Enterprise feature — every Enterprise client gets it
  automatically, but it can also be sold to a Growth client as a paid add-on
  (`settings.addon_multi_branch`). See `hasMultiBranch()`.

**Built so far:**

| File | Change |
|---|---|
| `database/migrations/2026_08_08_phase3a_multi_branch_foundation.sql` (new) | `settings.addon_multi_branch`; `branches.manage` permission (admin by default); `user_branches` pivot table, backfilled so every existing active user is assigned to (and has active-branch set to) Main Branch. |
| `app/helpers/functions.php` (updated) | `hasMultiBranch()`, `userBranches($userId)`, `activeBranchId()`, `activeBranchName()`. |
| `app/controllers/BranchController.php` (new) | Branch CRUD — create, edit (name/address/phone/active), no delete (matches accounts/roles — history would orphan). Guards against deactivating the last active branch. |
| `app/views/branches/index.php`, `create.php`, `edit.php` (new) | The branch management screens. |
| `public/index.php` (updated) | `/branches` added to the permission map and dispatch table; a dedicated `hasMultiBranch()` gate added alongside (not a plain plan-tier feature, so it doesn't fit the existing `$planFeatureMap` loop). |
| `app/views/layout/header.php` (updated) | "Branches" added to the Administration dropdown/section; a branch switcher (desktop) next to the account menu, shown only for a user assigned to more than one branch. |
| `app/controllers/UserController.php`, `app/views/users/create.php`, `edit.php` (updated) | Branch assignment checkboxes + "primary" radio, shown only when `hasMultiBranch()`. New `saveUserBranches()` keeps `user_branches` and the active `users.branch_id` in sync. |
| `app/controllers/AccountController.php` (updated) | New `switch-branch` action — validates the requested branch is one the user is actually assigned to before changing their active branch. |
| `app/controllers/SaleController.php`, `PurchaseController.php` (updated) | Sales and purchases now record `branch_id = activeBranchId()` instead of relying on the column's default of `1`. |

**Still to do for 3a** (not yet built):
- `stock_movements` and `expenses` inserts still rely on the column default rather than
  `activeBranchId()` — same pattern as sales/purchases above, just not wired yet.
- No report yet filters by branch (an admin viewing Reports today still sees everything
  combined across branches — correct for a single-branch install, but not yet
  branch-aware for a multi-branch one).
- Mobile branch switcher not built yet (desktop only for now).
- Haven't yet tested the full loop end-to-end (create a 2nd branch, assign a user to both,
  switch between them, confirm a sale lands on the right branch).

**Deferred to later sub-phases**, per the original phased plan:
- **3b** — replace `products.current_stock` with a `branch_stock` table (the schema-breaking
  part; every stock read/write needs to change, not just a column).
- **3c** — inter-branch stock transfers.
- **3d** — the customer-credit cross-branch settlement design from §5.5.

## 8. Open decisions for later (not blocking anything now)

- Self-service plan upgrade UI — revisit if clients start asking for it.
- `branch_product_prices` override table — only if a client needs per-branch pricing.
- Whether staff can belong to more than one branch (would need a pivot table instead of
  a single `users.branch_id`) — decide when designing the Phase 3 login/POS flow.
