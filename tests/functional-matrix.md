# Full functional regression script

Use three accounts (`admin`, `staff`, `cashier`) and a freshly initialized,
disposable database. For every form test: submit valid data, required fields
missing, malformed numeric/date data, duplicate unique fields, an invalid CSRF
token, and a repeated submission. Confirm errors are readable, entered values
are retained, and no partial database writes remain after a failure.

## Access, account, and routing

1. Visit every protected route logged out; confirm redirect to login and return
   to the original route after successful authentication.
2. Log in with correct and incorrect credentials; exercise lockout threshold,
   locked/inactive users, redirect-after-login, logout, and session expiry.
3. Verify admin has full access; staff cannot access user administration;
   cashier can access dashboard/POS/sales/account but receives 403 for each
   restricted route group. Verify unknown paths return 404.
4. View own profile and change password. Check wrong current password, short
   password, confirmation mismatch, successful login with the new password,
   and rejection of the old password.
5. As admin create, edit, deactivate/delete, reactivate, unlock, and reset each
   role of user. Prevent duplicate usernames and unsafe self-deletion.

## Catalog and parties

6. Create, list/filter/page, view, edit, toggle, and delete categories. Verify a
   category used by a product cannot be removed in a way that corrupts data.
7. Create products with manual and generated SKU, barcode, prices, cost, unit,
   reorder level, and category. Search by name/SKU/barcode; edit/view/delete;
   verify duplicates and products referenced by movements/sales are handled.
8. Create, search, page, view, edit, toggle, and delete suppliers. Record cash,
   mobile-money and bank deposits with explicit accounts; verify supplier and
   account balances plus transaction audit rows.
9. Create, search, page, view, edit, and delete customers. Adjust credit limit;
   record, edit, and delete deposits; verify account/customer balances and
   statement date filters. Test customers with sales cannot be orphaned.

## Inventory, purchasing, and sales

10. Stock in and stock out a product; adjust upward/downward; reject zero,
    negative, unknown-product, and excessive stock-out quantities. Verify stock
    totals, movement type/reference/user, movement filters, per-product history,
    low-stock and out-of-stock alerts.
11. Complete cash and credit purchases with one/multiple items, item/order
    discounts, VAT, invoice number, partial/full/zero payment and each payment
    account. Verify purchase/item rows, stock, movements, weighted average and
    last cost/history, supplier balance/totals, account balance, and audit rows.
12. View, filter, page and print a purchase. Add partial/final supplier payment;
    reject overpayment. Edit items/quantities/costs/payment and verify all stock,
    cost and balance deltas. Void once; reject repeat void and confirm exact
    reversal without deleting the audit trail.
13. Complete POS cash and credit sales with one/multiple items, barcode/search,
    customer/walk-in, discounts, VAT, partial/full payment and each payment
    account. Reject empty cart, stale price, insufficient stock, invalid totals,
    and customer credit-limit excess. Verify sale/items, stock/movements,
    customer/account balances, cost/profit fields and transaction rows.
14. View, filter, page and print sales. Add partial/final customer payments;
    reject overpayment. Edit items/quantities/price/payment and verify all stock
    and financial deltas. Void once; reject repeat void and confirm exact
    reversal. Complete distributor delivery and verify linked purchase/sale,
    inventory, party balances and duplicate-submit protection atomically.

## Finance and operations

15. Create each account type and reject invalid/duplicate data. Deposit and
    withdraw through business workflows; verify before/after balances and user,
    reference, notes, filters and pagination in account/global transactions.
16. Transfer between active accounts; reject same-account, zero/negative,
    excessive, inactive or missing accounts. Verify two balanced audit entries
    and atomic rollback on either-side failure.
17. Create and delete expenses using every payment method/account. Verify the
    withdrawal, balance, reference and reversal; reject insufficient funds and
    invalid categories/amounts.
18. Create, edit, resolve and delete suspense entries for deposit/withdrawal.
    Resolve to valid business/account targets, reject excessive/duplicate
    resolution, and verify source/destination balances and audit history.

## Import, export, dashboards, and reports

19. Download every import template. Preview and process categories, products,
    suppliers, customers and purchases in insert/update modes. Test empty,
    wrong-type, oversized, missing-header, reordered-header, quoted-comma,
    duplicate, unknown-reference and mixed-validity CSV files. Confirm reported
    row counts/errors and transaction rollback rules.
20. Export categories, products, suppliers, customers, sales, transactions,
    stocks, stock valuation, receivables, payables, sales report, profit/loss,
    top-selling, low/dead stock and profit margin. Check empty/non-empty results,
    filters, escaping, filenames, headers, column order and totals in the file.
21. For every report test today, yesterday, this/last week, this/last month,
    quarter/year and custom inclusive boundaries. Cross-check totals against the
    underlying sales, purchases, payments, costs, stock and party balances;
    exercise search, sort, aging and pagination filters.
22. Cross-check dashboard KPIs, recent activity, chart periods and low-stock
    indicators after each sale, purchase, payment, expense and void operation.

## Cross-cutting quality checks

23. Enter HTML/script payloads in every text field and verify output escaping.
    Attempt SQL metacharacters in every search/filter/login field. Upload files
    with forged extension/MIME and paths. Confirm no executable content or SQL
    error is exposed.
24. Open two sessions and submit concurrent sale/purchase/payment/transfer
    operations against the same records. Confirm no negative stock, lost update,
    double payment, duplicate number or unbalanced account is possible.
25. Test zero, very large, and decimal rounding boundaries for quantity, price,
    discount, VAT and payment. Confirm currency is consistently two decimals and
    database totals equal displayed/printed/exported totals.
26. Check all list pages at zero, one, exactly 20, and 21+ rows; verify filters
    persist across pagination. Exercise mobile/desktop layouts, keyboard-only
    operation, focus/labels, print receipts and browser back/refresh behavior.

For every scenario, retain the request inputs and verify affected rows before
and after. A scenario passes only when UI result, database state, account/party
balances, stock, and audit trails all agree.
