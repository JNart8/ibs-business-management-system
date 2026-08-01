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
| Financial accounts (+ internal transfers) | — | ✅ | ✅ |
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
custom roles and toggle per-feature checkboxes. Safe to build without touching anything
in Phase 1/1b — it only adds new tables.

**Status:** not started (Phase 2).

### 5.7 Multi-branch build scope

Confirmed as a **full build**: separate stock/sales/purchases per branch, plus transfers.
Flagged as its own project phase (not a quick flag-flip) because `products.current_stock`
is currently a single global integer, not a per-location table — every controller that
reads or writes stock needs to change, not just the schema. Recommendation stands: don't
bundle this into Phase 1/2, build it once there's a real Enterprise client to build against.

## 6. What's actually been built (Phase 1 + 1b)

| File | Change |
|---|---|
| `database/migrations/2026_08_01_phase1_plans_and_branches.sql` | `settings.plan`; `branches` table seeded with "Main Branch"; `branch_id` (default 1) on `sales`, `purchases`, `stock_movements`, `expenses`. |
| `database/migrations/2026_08_02_phase1b_accounts_users_branch.sql` | `accounts.branch_id` (nullable, default 1 — set to `NULL` later to mark an account as shared/company-wide); `users.branch_id` (nullable, default `NULL` — unused until Phase 3 ships a branch-aware login/POS flow). |
| `app/config/plans.php` | Single source of truth for Core/Growth/Enterprise feature lists and limits. |
| `app/helpers/functions.php` | `currentPlan()`, `currentPlanDefinition()`, `planAllows()`, `withinUserLimit()`, `planUpgradeNotice()`. |
| `public/index.php` | Plan-gating block (403s a route if the current plan doesn't include its feature), next to the existing role-restriction block. |
| `app/views/layout/header.php` | Desktop + mobile nav hide items the current plan doesn't include. |
| `app/controllers/UserController.php` | Blocks creating a new user once `max_users` is hit. |
| `app/views/settings/index.php` | Read-only "Current plan" panel. |

**Not yet built:** anything that *reads* `branch_id` to actually scope behavior — today
every table has the column, but every query still behaves as if there's one branch,
because there is only one branch per install right now. That's intentional — see §5.1.

## 7. Roadmap

| Phase | Scope | Risk / dependency |
|---|---|---|
| **1** ✅ | Plan tiers, feature gating, route/nav enforcement, user limits | Shipped |
| **1b** ✅ | `accounts.branch_id`, `users.branch_id` scaffolding | Shipped |
| **2** | `permissions` / `role_permissions` tables + Enterprise role editor | Additive only, no existing schema touched — safe anytime |
| **3** | Multi-branch build: `branch_stock` replacing `products.current_stock`, branch-aware POS/reporting, inter-branch transfers, customer-credit branch settlement (§5.5) | Needs a real Enterprise client to design against; biggest single phase |

## 8. Open decisions for later (not blocking anything now)

- Self-service plan upgrade UI — revisit if clients start asking for it.
- `branch_product_prices` override table — only if a client needs per-branch pricing.
- Whether staff can belong to more than one branch (would need a pivot table instead of
  a single `users.branch_id`) — decide when designing the Phase 3 login/POS flow.
