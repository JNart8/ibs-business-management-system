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
| **3a** ✅ | Branches, user-branch assignment, branch-level vs. company-wide scoping, branch-scoped Sales/Purchases/Expenses history and reports | Shipped |
| **3b** ✅ | `branch_stock` replacing `products.current_stock` as the source of truth for every stock read/write | Shipped |
| **3c** | Inter-branch stock transfers | Not started |
| **3d** | Customer-credit cross-branch settlement (§5.5) | Not started |

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

## 6h. Phase 3a: multi-branch foundation

**Decisions confirmed before building:**
- A user can be assigned to **multiple** branches (new `user_branches` pivot table), but POS
  shows **no picker** — each user has one "active" branch (`users.branch_id`) that's used
  automatically, changeable via a small switcher (header, desktop only for now) if they're
  assigned to more than one.
- Multi-branch is **not** purely an Enterprise feature — every Enterprise client gets it
  automatically, but it can also be sold to a Growth client as a paid add-on
  (`settings.addon_multi_branch`). See `hasMultiBranch()`.
- **Branch-level vs. company-wide admins**, added mid-build: visibility scope is independent
  of role. `users.branch_scope` (`'assigned'` or `'all'`) determines whether a user only
  sees/manages data for their assigned branch(es), or everything regardless of assignment.
  An Admin can be scoped to one branch ("branch admin" — full control of their location,
  can't see others); a company-wide user (typically the owner or a head-office admin) sees
  everything. Existing admins are migrated to `'all'` so nobody's visibility shrinks when
  this ships.
- **Sales history is branch-scoped end to end**, not just the list page — every single-sale
  lookup (view, edit, receipt, payment, void) is scoped too, so a branch-restricted user
  can't reach another branch's sale by guessing a URL. Fails closed: a user assigned to zero
  branches sees nothing, not everything.

**Built:**

| File | Change |
|---|---|
| `database/migrations/2026_08_08_phase3a_multi_branch_foundation.sql` (new) | `settings.addon_multi_branch`; `branches.manage` permission (admin by default); `user_branches` pivot table, backfilled so every existing active user is assigned to (and has active-branch set to) Main Branch. |
| `database/migrations/2026_08_09_phase3a_branch_scope.sql` (new) | `users.branch_scope`, backfilled to `'all'` for existing admins (no visibility regression) and `'assigned'` for everyone else (no practical effect until a 2nd branch exists). |
| `app/helpers/functions.php` (updated) | `hasMultiBranch()`, `userBranches()`, `activeBranchId()`, `activeBranchName()`, `branchName()`, `isCompanyWide()`, `visibleBranchIds()`, `branchScopeSql()`. |
| `app/controllers/BranchController.php` (new) | Branch CRUD — create, edit (name/address/phone/active), no delete (matches accounts/roles — history would orphan). Guards against deactivating the last active branch. |
| `app/views/branches/index.php`, `create.php`, `edit.php` (new) | The branch management screens. |
| `public/index.php` (updated) | `/branches` added to the permission map and dispatch table; a dedicated `hasMultiBranch()` gate added alongside (not a plain plan-tier feature, so it doesn't fit the existing `$planFeatureMap` loop). |
| `app/views/layout/header.php` (updated) | "Branches" added to the Administration dropdown/section; a branch switcher (desktop) next to the account menu, shown only for a user assigned to more than one branch. |
| `app/controllers/UserController.php`, `app/views/users/create.php`, `edit.php`, `index.php` (updated) | Branch assignment checkboxes + "primary" radio + branch-scope toggle (Branch-level / Company-wide), shown only when `hasMultiBranch()`. New `saveUserBranches()` keeps `user_branches` and the active `users.branch_id` in sync. Users list shows a Branch column. |
| `app/controllers/AccountController.php` (updated) | New `switch-branch` action — validates the requested branch is one the user is actually assigned to before changing their active branch. |
| `app/controllers/SaleController.php` (updated) | Every sale write records `branch_id = activeBranchId()`. Sales history (`listSales`, the daily summary card, and every single-sale lookup — view/edit/update/receipt/pay/void) now applies `branchScopeSql()`. Void's stock-return movement is attributed to the *sale's own* branch, not the voiding user's current active branch, since it's reversing history that happened at a specific location. |
| `app/controllers/PurchaseController.php`, `DistributorController.php`, `ImportController.php`, `ProductController.php`, `StockController.php` (updated) | Every `purchases`, `stock_movements`, and (via `ExpensesController.php`) `expenses` insert now records the actual `activeBranchId()` instead of relying on the column's default of `1`. |

**Still to do for 3a:**
- No report yet filters by branch (an admin viewing Reports today still sees everything
  combined across branches — correct for a single-branch install, but not yet
  branch-aware for a multi-branch one). Purchases history and Expenses history haven't had
  the same view/edit-level scoping sales history just got, either — same pattern, just not
  applied there yet.
- Mobile branch switcher not built yet (desktop only for now).
- Haven't yet tested the full loop end-to-end (create a 2nd branch, assign a user to both,
  switch between them, confirm a sale lands on the right branch, confirm a branch-scoped
  user can't see the other branch's history).

**Deferred to later sub-phases**, per the original phased plan:
- **3b** — replace `products.current_stock` with a `branch_stock` table (the schema-breaking
  part; every stock read/write needs to change, not just a column).
- **3c** — inter-branch stock transfers.
- **3d** — the customer-credit cross-branch settlement design from §5.5.

## 6j. Phase 3a continued: Purchases, Expenses, and mobile switcher

Extended the same branch-scoping pattern from §6h to the two other places it was missing:

| File | Change |
|---|---|
| `app/controllers/PurchaseController.php` (updated) | `listPurchases` (list, count, today's stats) and all 7 single-purchase lookups (view, edit, update, receipt, pay form, process payment, void) now apply `branchScopeSql()`, matching sales history exactly. |
| `app/controllers/ExpensesController.php` (updated) | `listExpenses` and `deleteExpense` now branch-scoped. Note: had to use the **aliased** form (`branchScopeSql('e')`) here specifically, because `expenses` and `accounts` **both** have a `branch_id` column and this query joins them — an unaliased `branch_id IN (...)` would have been ambiguous SQL. |
| `app/views/layout/header.php` (updated) | Mobile branch switcher added, matching the desktop one — a dropdown for 2+ assigned branches, a plain label for exactly 1, nothing shown otherwise. |

## 6k. Before starting Reports: a conceptual wrinkle worth flagging

Reports (`ReportsController.php`) is a large, separate body of work — 12 report functions,
~1,450 lines — so I paused before diving in rather than rushing it. One thing worth deciding
first: **receivables and payables reports don't branch-scope the same way sales/purchases
do.**

A customer's outstanding balance is a company-wide number by design (§5.3 — customers are
shared across branches on purpose, so a credit limit can't be gamed by splitting purchases
across locations). That means "receivables owed to us" isn't really a per-branch fact the
way "sales made at Branch 2 today" is — a customer could owe money from purchases at three
different branches, and the balance is one number, not three. Branch-scoping that report
by *which branch's sales contributed* is possible (sum only the sales rows visible to this
user), but it would show a different, smaller number than the customer's actual
`current_balance` — which could be confusing rather than helpful for a branch admin trying
to reconcile.

**My suggestion when we get to it:** sales-report-style numbers (sales, top-selling, stock
valuation, profit/loss, profit margin, dead stock, low stock) branch-scope cleanly the same
way sales/purchases just did. Receivables/payables should probably stay company-wide
regardless of viewer (or clearly labeled "company-wide" if shown to a branch-scoped user),
since showing a partial balance as if it were the whole picture could cause a real
bookkeeping mistake. Flagging now so this gets decided deliberately rather than by whichever
way I happen to implement it first.

**Decision made:** branch-scope with a partial-view label — see §6l for how this was built.

## 6l. Receivables & payables: scoped and shown as partial, per your decision

You chose to branch-scope these rather than leave them company-wide. Implementation:

- **Screen and export both recompute `amount_owed` from branch-scoped invoices** (`SUM` of
  unpaid `sales`/`purchases` rows visible to the viewer) instead of reading
  `customers.current_balance` / `suppliers.current_balance` directly — those columns stay
  company-wide by design (§5.3) and were never touched. This is the same principle as the
  account-ledger export from earlier: the export recomputes the same way the screen does, so
  the two can never show different numbers for the same view.
- **A clear "partial view" banner/note** appears whenever the viewer is branch-scoped (not
  company-wide) — on screen (`receivables.php`, `payables.php`) and in the CSV footer
  (`exportReceivables`, `exportPayables`), explaining that a customer's/supplier's true
  balance can include activity from other branches.
- **`payablesReport()`/`exportPayables()` needed a real restructure**, not just a WHERE
  clause addition — the original query read `ABS(suppliers.current_balance)` directly, which
  has no branch dimension to filter by at all. Rewritten to sum `purchases.amount_due`
  per-supplier instead (mirroring how `receivablesReport()` already worked), with the
  `HAVING amount_owed > 0.01` filter replacing the old `WHERE current_balance < 0` — because
  a supplier could have zero owed at *this* branch even with a nonzero company-wide balance
  from purchases elsewhere, or vice versa.

## 6m. Other reports: scoped where it's meaningful, left alone where it isn't

Extended branch-scoping to every other **transaction-based** report (both the screen and its
matching CSV export): `salesReport`/`exportSalesReport`, `topSellingReport`/`exportTopSelling`,
`profitLossReport`/`exportProfitLoss`. These all derive their numbers from `sales`/`purchases`/
`expenses` rows, which already carry `branch_id`, so scoping them is a direct application of
the same `branchScopeSql()` pattern — no special "partial" caveat needed, since (unlike
receivables/payables) there's no company-wide balance field being partially represented; a
branch admin's sales report is simply, accurately, their branch's sales.

**Deliberately left unscoped:** `stockValuationReport`, `lowStockReport`, `deadStockReport`,
`profitMarginReport`. All four are fundamentally **product/stock-centric** — built from
`products.current_stock`, which is still a single global number (Phase 3b, the `branch_stock`
table, hasn't shipped yet). There's no branch dimension to filter these by yet without
building 3b first. Scoping just the "last 30 days sold" correlated subqueries in
`profitMarginReport` while leaving `stock_value`/`potential_profit` company-wide would create
a report where the two halves don't relate to the same scope — worse than leaving it alone.
These four become branch-scopable as a natural side effect once 3b ships.

## 6i/6n. Audit trail — built

**Scope decision, made up front per the earlier flag:** logging is deliberately limited to
actions that change **money or access**, not routine data entry. A normal sale or purchase
isn't logged here — that history already lives in, and is fully reconstructable from, the
`sales`/`purchases` tables themselves. Logging everything would make the log useless for
finding anything that actually matters; logging too little defeats the purpose. What's
logged:

| Action | Where |
|---|---|
| `sale.void` | `SaleController::voidSale()` |
| `purchase.void` | `PurchaseController::voidPurchase()` — **worth knowing**: voiding a purchase deletes the row and its related records outright (pre-existing behavior, not something this phase changed) rather than marking it voided the way a sale is. The audit entry is the only remaining record that purchase ever existed. |
| `user.create`, `user.update` (role/status before-after), `user.deactivate`, `user.activate` | `UserController.php` |
| `role.create`, `role.permissions_changed` (**added/removed diff** — the single most security-relevant entry in the app), `role.delete` | `RoleController.php` |
| `branch.create`, `branch.update` (active status before/after) | `BranchController.php` |
| `account.create`, `account.update` (active status before/after) | `FinancialAccountsController.php` |
| `settings.update` | `SettingsController.php` |
| `user.switch_branch` | `AccountController::switchBranch()` |
| `user.session_kicked` | `enforceSingleSession()` in `functions.php` — logged just before the older session is wiped, since the log write needs to happen while `$_SESSION` still has the user's identity in it |

**Deliberately not logged separately:** account transfers (`FinancialAccountsController::executeTransfer()`).
The `account_transfers` table already records amount, charges, notes, user, and timestamp for
every transfer — it already *is* an audit-quality record for that specific action, so a
duplicate `audit_log` entry would just be the same fact twice in two places.

**Built:**

| File | Change |
|---|---|
| `database/migrations/2026_08_10_audit_trail.sql` (new) | `audit_log` table; `audit.view` permission (admin by default). |
| `app/helpers/functions.php` (updated) | `logAudit($action, $entityType, $entityId, $details)` — never throws, so a logging failure can never block the real action it's describing. |
| `app/controllers/AuditController.php` (new) | Read-only viewer. Filterable (user, action, entity type, date range), paginated. Branch-scoped via the same `branchScopeSql()` used everywhere else — and since system-wide entries (role/permission changes, settings) get `branch_id = NULL`, they're automatically excluded for branch-scoped viewers by ordinary SQL `NULL NOT IN (...)` semantics, with no extra logic needed for that distinction. |
| `app/views/audit/index.php` (new) | The viewer screen. |
| `public/index.php` (updated) | `/audit` added to the permission map and dispatch table. |
| `app/views/layout/header.php` (updated) | "Audit Log" added to the Administration dropdown/section (desktop + mobile), gated by `can('audit.view')`. |
| 8 controllers (`SaleController`, `PurchaseController`, `UserController`, `RoleController`, `BranchController`, `FinancialAccountsController`, `SettingsController`, `AccountController`) + `functions.php` | `logAudit()` calls added at each mutation point listed above. |

**Not built / open for later:** no retention policy or archiving (the table will grow
indefinitely — worth revisiting once there's real usage data to know how fast); no export
(CSV) for the audit log itself, unlike every other report; no email/alert on specific
high-risk entries (e.g. a role losing `users.manage`). None of these were asked for — noting
them so they're a deliberate choice to defer, not an oversight.

## 7d. Phase 3a testing checklist

- [ ] Run both new migrations against a **copy** of a client DB first.
- [ ] Turn on `addon_multi_branch` (or set `plan = 'enterprise'`) and confirm "Branches" and
      the switcher stay hidden until you do.
- [ ] Create a second branch. Assign an existing staff user to both branches with Branch 2
      as primary; confirm their next login (or `unset($_SESSION['user_data'])` moment) shows
      Branch 2 as active, and the switcher appears with both options.
- [ ] As that user, complete a sale; confirm it's recorded against Branch 2 (`sales.branch_id`).
- [ ] Switch to Branch 1 via the header switcher; complete another sale; confirm it lands on
      Branch 1.
- [ ] Set a second user to branch-scoped + assigned only to Branch 1. Confirm they see only
      Branch 1's sales in `/sales`, the daily summary card only reflects Branch 1, and
      opening a Branch 2 sale's URL directly (view/edit/receipt/pay/void) returns "not found"
      rather than the sale.
- [ ] Set that same user's scope to "Company-wide" and confirm they now see both branches'
      sales without changing their branch assignment.
- [ ] Void a sale that was recorded at a non-active branch (e.g. an admin voiding a Branch 2
      sale while their own active branch is Branch 1); confirm the stock-return movement is
      attributed to Branch 2, not Branch 1.
- [ ] Try to deactivate the only active branch — confirm it's blocked with a clear message.
- [ ] As a branch-scoped user, open Receivables/Payables and confirm the partial-view banner
      appears and the totals match what's actually visible (not the customer's/supplier's
      full balance). Confirm the CSV export shows the same numbers and the same note.
- [ ] As a company-wide user, confirm those same reports show full totals with no banner.
- [ ] Compare Sales Report, Top Selling, and Profit & Loss on screen vs. their CSV exports
      for a branch-scoped user — confirm the numbers match exactly in both places.
- [ ] Confirm Stock Valuation, Low Stock, Dead Stock, and Profit Margin reports still show
      the same (unscoped) figures regardless of viewer — this is expected until Phase 3b.

## 7e. Audit trail testing checklist

- [ ] Run the migration against a **copy** of a client DB first.
- [ ] Void a sale and a purchase; confirm both appear in `/audit` with the right details, and
      confirm the voided purchase's audit entry is genuinely the only remaining trace of it
      (since the row itself gets deleted).
- [ ] Edit a role's permissions (add one, remove one); confirm the entry shows exactly which
      permission was added and which was removed, not just "role was changed."
- [ ] Deactivate and reactivate a user; confirm both show up with correct before/after status.
- [ ] As a branch-scoped user, confirm `/audit` shows only entries tied to their branch, and
      that company-wide entries (role/permission changes, settings) are invisible to them.
- [ ] As a company-wide user, confirm they see everything, including other branches' entries.
- [ ] Log in from a second browser to trigger a forced kickout; confirm `user.session_kicked`
      appears in the log.
- [ ] Confirm a deliberately broken audit_log insert (e.g. temporarily rename the table) does
      **not** prevent the underlying action (a sale void, a user update) from completing —
      `logAudit()` should fail silently.
- [ ] Run `cron/archive_audit_log.php` manually once via CLI (`php cron/archive_audit_log.php`)
      against a copy of a client DB with some old test entries (backdate a few rows' `created_at`
      past 365 days first) — confirm they move to `audit_log_archive` and disappear from `/audit`
      by default.
- [ ] Run the script a second time immediately after — confirm it reports "nothing to do" and
      doesn't error or double-archive anything.
- [ ] Check "Include archived" on `/audit` and confirm the backdated entries reappear.
- [ ] Export the audit log both with and without "Include archived" checked; confirm the CSV
      row counts match what's shown on screen in each case.
- [ ] Set up the actual cPanel cron job once you're ready for this to run unattended, and
      confirm it appears as expected in cPanel's cron job list.

## 6o. Audit log: archiving, 1-year retention, CSV export

**Archiving, not deleting.** A same-structure `audit_log_archive` table holds entries once
they age out, so nothing is actually lost — just moved out of the table the app queries by
default, keeping day-to-day filtering fast as the log grows indefinitely.

**Retention mechanism:** `cron/archive_audit_log.php`, a standalone script (bootstraps only
the DB connection, not the full app/session framework, since it runs outside a web request)
that moves anything older than 365 days from `audit_log` into `audit_log_archive`, then
deletes it from the live table. Idempotent — safe to run daily or however often, since
`INSERT IGNORE` means a re-run can't double-archive a row.

**This needs to actually be scheduled to do anything** — the script won't run itself. Set it
up once via cPanel → Cron Jobs, as a **command**, not a URL:
```
php /home/YOURUSER/path/to/ibs-sales-app-v2/cron/archive_audit_log.php
```
This does *not* need SSH/Terminal access — cPanel's Cron Jobs feature runs the command
directly; that's a separate permission from Terminal access. Daily is a reasonable schedule.
If your specific host's cron only supports "visit a URL" rather than running a command
directly, this script would need a small adaptation (a shared-secret query-parameter check)
— flag it if that turns out to be the case for whichever host you end up on.

**The archive is still reachable, not cold storage.** An "Include archived (1+ year old)"
checkbox on `/audit` (and carried through to the export) UNIONs both tables when checked —
the common case stays fast (live table only), but nothing requires a direct database query
to look back further than a year.

**CSV export:** `exportAuditLog()` in `ExportController.php`, filtered identically to the
on-screen viewer (including the archive toggle), gated by the same `audit.view` permission
as the viewer itself. **One thing worth deciding:** like every other export in this app, it's
still blocked by the blanket `/export` → `imports_exports` (Growth+) plan gate — meaning a
Core-plan admin can see the Export button (since they have `audit.view`) but get an
"upgrade required" page when they click it. I kept this consistent with how every other
export already works rather than carving out a one-off exception, but audit-log export is
arguably more of a compliance feature than a "power user" one — let me know if Core should
be exempted from that gate specifically.

**Built:**

| File | Change |
|---|---|
| `database/migrations/2026_08_11_audit_log_archive.sql` (new) | `audit_log_archive` table — same shape as `audit_log` plus `archived_at`; `id` is a plain (not auto-increment) primary key, since archived rows keep their original ID rather than getting a new one. |
| `cron/archive_audit_log.php` (new) | The retention script described above. |
| `app/controllers/AuditController.php` (updated) | `include_archived` toggle (UNIONs both tables when set); builds the export URL passed to the view. |
| `app/views/audit/index.php` (updated) | Include-archived checkbox; Export CSV button. |
| `app/controllers/ExportController.php` (updated) | New `audit-log` export type + `exportAuditLog()`, mirroring the viewer's exact filter logic. |

## 6p. Phase 3b: branch_stock — the schema-breaking part

This was the part flagged from the start as the biggest single piece of work in the whole
engagement: `products.current_stock` had been a single global number referenced roughly 190
times across 11 controllers and 15 views. Rewriting all 190 wasn't necessary — the design
decision that made this tractable was confirmed with you before writing any code (see the
scoping conversation): **`branch_stock` becomes the real per-branch source of truth,
`products.current_stock` stays in the schema as an auto-maintained total across all
branches**, kept in sync by application code (not a DB trigger — this app has never used
triggers or stored procedures, and staying consistent with that all-PHP pattern was judged
safer than introducing a new one, especially given variable trigger support across
shared-hosting MySQL configurations).

### Two-tier helper design

Four functions in `functions.php` are now the *only* correct way to touch stock:
- `getBranchStock($productId, $branchId = null)` — read a specific branch's quantity
- `adjustBranchStock($db, $productId, $branchId, $delta)` — relative change (sales, purchases)
- `setBranchStock($db, $productId, $branchId, $newQuantity)` — absolute set (manual adjustment, CSV import)
- `recalcProductTotalStock($db, $productId)` — called automatically by the two above; never needs calling directly except for a one-off repair

Nothing in the app should ever `UPDATE products SET current_stock = ...` or write to
`branch_stock` directly again — every write path listed below now goes through these.

### Which numbers stay company-wide, and why

Not every stock-adjacent figure became branch-specific — some genuinely shouldn't:
- **Weighted-average cost, cost price, selling price, margin** — cost accounting is a
  company-wide concept; a product's cost basis doesn't differ by which shelf it's on. These
  stayed on `products`, untouched, computed exactly as before.
- **Reorder level** — per your decision earlier, stays global on the product rather than
  per-branch.
- **Catalog/reference pages** (Products list/detail, Categories, Suppliers) — these show a
  company-wide stock total by design, the same way the product catalog itself is shared
  across branches (§5.2). This is a different kind of page from the *operational* ones below.

### Which pages became branch-specific, and at what scope

Two different scoping rules were applied depending on what a page is for:

- **Operational pages** (drive real stock actions) use the viewer's **active branch only**:
  POS (product search, cart validation, sale completion), Stock Management dashboard,
  stock-in/out/adjustment forms, Low Stock Alerts. This matches how these pages already
  worked — write actions post to the active branch, so the numbers shown must be that same
  branch's numbers, or someone could act on a quantity that isn't actually there.
- **Read-only overview pages** use the viewer's **visible branches** (all of them for
  company-wide, just their own for branch-scoped) — same rule as receivables/payables from
  3a: Dashboard summary tiles, and all four reports below.

### The four reports deferred from Phase 3a — now built

`stockValuationReport`, `lowStockReport`, `deadStockReport`, `profitMarginReport` (and their
matching CSV exports) were explicitly left undone in 3a because `branch_stock` didn't exist
yet. All four are now branch-scoped, following the same principle used throughout: **the
export always recomputes the same way the screen does**, never reusing a cached/passed-in
number, so the two can never show different figures for the same filters.

One real subtlety surfaced while doing this: filters like "low stock only" or "critical
severity" had been simple `WHERE current_stock <= reorder_level` conditions — fine when
stock was one column. Once stock became `SUM(branch_stock.quantity)` across potentially
several visible branches, that condition **had to move from `WHERE` to `HAVING`**, or it
would evaluate against individual per-branch rows *before* they're summed, giving wrong
results for any viewer with more than one visible branch. This was fixed consistently
across all four reports and their exports.

### Bugs found and fixed along the way (not scope creep — found while touching this code)

- **A real overselling risk**: POS's stock validation checked
  `products.current_stock` (the company-wide total) instead of the branch's actual
  quantity — a cashier at a branch with zero stock of something could still sell it, as long
  as *another* branch had enough. Fixed in both sale creation and the equivalent
  purchase-quantity-reduction validation.
- **A pre-existing param-binding bug** in the stock valuation report's "Top 10 Most
  Valuable Items" query — it referenced `$where` (which can contain `?` placeholders from
  category/supplier filters) but never passed the matching `$params` array. Would have
  thrown a param-count mismatch the moment someone filtered by category or supplier. Unrelated
  to branch work, just found while rewriting the surrounding query.
- **Two undefined-variable bugs**, same pattern, in `PurchaseController` and
  `ImportController`'s cost-history logging — both were leftover references to a variable
  name from before the "split company-wide cost from branch-specific quantity" refactor.
  `php -l` can't catch these (valid syntax, just references an undefined variable at
  runtime), so after finding the first one I went back and manually re-verified every other
  place the same refactor pattern was applied (`updatePurchase`, `voidPurchase`) rather than
  assuming they were fine — both of those checked out clean.

### Built

| File | Change |
|---|---|
| `database/migrations/2026_08_12_phase3b_branch_stock.sql` (new) | `branch_stock` table; seeds every product's current total to Main Branch, zero elsewhere. **Read the warning in the migration file before running on a client already using a 2nd branch** — stock was never tracked per-branch before this, so that client needs a manual stock count per location after running it; there's no historical data to reconstruct it from. |
| `app/helpers/functions.php` (updated) | The four helper functions above. |
| `app/controllers/ProductController.php` (updated) | `searchProducts()` (the shared endpoint used by POS/stock forms/purchases/distributor) now branch-aware — one fix, cascades everywhere. Opening-stock creation now seeds `branch_stock` instead of setting the column directly. |
| `app/controllers/SaleController.php` (updated) | Stock validation, sale completion, and void all branch-aware; void restores to the *sale's own* branch, not the voider's current one. Quick-products grid fixed too. |
| `app/controllers/PurchaseController.php` (updated) | Purchase creation, update (quantity diffing), and void all branch-aware, with cost calculations correctly kept company-wide throughout. Item-listing/product-picker queries on the view/edit/create pages fixed too. |
| `app/controllers/StockController.php` (updated) | Stock-in, stock-out, and adjustment all rewired to the branch_stock helpers; the Stock Management dashboard and Low Stock Alerts page both rebuilt to show the active branch's real numbers, with a "showing: [branch]" label. |
| `app/controllers/DashboardController.php` (updated) | Stock value tile, out-of-stock/low-stock counts, and the top-10 low-stock list all now sum across visible branches. |
| `app/controllers/ReportsController.php`, `app/controllers/ExportController.php` (updated) | All four deferred reports + their exports, as described above. |
| `app/controllers/DistributorController.php` (updated) | Product picker branch-aware; stock-movement ledger entries now log the branch's actual before/after stock (the delivery itself stays net-zero by design — no actual quantity change). |
| `app/controllers/ImportController.php` (updated) | Both CSV import types (product catalog, purchases) now go through the branch_stock helpers instead of writing to `products.current_stock` directly. |
| 6 view files (`stock/index.php`, `stock/alerts.php`, `reports/stock_valuation.php`, `reports/low_stock.php`, `reports/dead_stock.php`, `reports/profit_margin.php`) | "Showing: [branch/scope]" labels added for clarity. |

## 7f. Phase 3b testing checklist

- [ ] Run the migration against a **copy** of a client DB first — and if that client already
      has a 2nd branch in active use, walk through the manual stock recount warning with them
      before running it.
- [ ] At a single-branch install, confirm nothing looks different anywhere — this is the most
      important check, since the vast majority of installs are single-branch and should see
      zero behavior change.
- [ ] Create a 2nd branch, add some stock to it via stock-in, and confirm POS at that branch
      only shows/sells what's actually there — try to oversell past the branch's actual
      quantity and confirm it's blocked, even if the *other* branch has plenty.
- [ ] Complete a sale, then void it; confirm stock returns to the branch the sale actually
      happened at (test this specifically as an admin whose *active* branch differs from the
      sale's branch, to catch the exact bug this was designed to prevent).
- [ ] Do the same void test for a purchase.
- [ ] Run a purchase that adds stock to a product with existing stock elsewhere; confirm the
      weighted-average cost updates correctly (company-wide) while only the purchasing
      branch's quantity changes.
- [ ] Check Stock Management, Low Stock Alerts, and all four reports (stock valuation, low
      stock, dead stock, profit margin) as both a branch-scoped and a company-wide viewer;
      confirm the numbers differ appropriately and that each report's CSV export matches its
      on-screen numbers exactly.
- [ ] Specifically test the "low stock only" / "critical severity" filter checkboxes on a
      multi-branch-visible (company-wide) viewer — this is the filter that had to move from
      WHERE to HAVING; confirm it's still returning the right set of products.
- [ ] Import a product CSV with a `current_stock` column at a branch other than Main Branch;
      confirm the stock lands on the importing branch, not Main Branch.
- [ ] Import a purchase CSV; confirm both the stock quantity (branch-specific) and the
      weighted-average cost (company-wide) update correctly.

## 8. Open decisions for later (not blocking anything now)

- Self-service plan upgrade UI — revisit if clients start asking for it.
- `branch_product_prices` override table — only if a client needs per-branch pricing.
- Whether staff can belong to more than one branch (would need a pivot table instead of
  a single `users.branch_id`) — decide when designing the Phase 3 login/POS flow.
