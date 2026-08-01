Version 2

Supplier purchase feature update

Database Changes:
New Tables:

purchases - Purchase records
purchase_items - Line items
supplier_transactions - Payment tracking
product_cost_history - Cost changes log

Modified Tables:
products:

average_cost - Weighted moving average
last_purchase_cost - Most recent cost
last_purchase_date - Last purchase date

suppliers:

current_balance - Amount owed/credit
credit_limit - Max credit allowed
total_purchases - Lifetime total

Smart Features:

Auto Cost Updates - Weighted average recalculated every purchase
Cost History - Track all cost changes over time
Stock Valuation - Real-time inventory value
Supplier Credits - Track who you owe
Partial Payments - Pay suppliers in installments
VAT Support - Handle tax properly
Discount Support - Item-level & purchase-level

🎯 Purchase Workflow

1. Navigate to Purchases → Create Purchase
2. Select Supplier
3. Add Products:
   - Search/scan product
   - Enter quantity
   - Enter unit cost
   - Apply item discount (optional)
4. Review Cart
5. Apply purchase-level discount (optional)
6. Add VAT percentage (if applicable)
7. Enter invoice number (optional)
8. Select payment method
9. Enter amount paid
10. Add notes (optional)
11. Complete Purchase

Result:
✅ Purchase recorded
✅ Stock updated
✅ Costs recalculated
✅ Supplier balance updated
✅ Receipt/GRN generated
