-- Run once on existing installations before deploying the application changes.
ALTER TABLE settings
    ADD COLUMN sale_discount_type ENUM('percentage', 'flat')
    NOT NULL DEFAULT 'percentage' AFTER tax_rate;

ALTER TABLE sales
    ADD COLUMN discount_type ENUM('percentage', 'flat')
    NOT NULL DEFAULT 'percentage' AFTER subtotal;

ALTER TABLE sale_items
    ADD COLUMN discount_type ENUM('percentage', 'flat')
    NOT NULL DEFAULT 'percentage' AFTER unit_price,
    ADD COLUMN discount_amount DECIMAL(12,2)
    NOT NULL DEFAULT 0.00 AFTER discount_percent;

UPDATE sale_items
SET discount_amount = (quantity * unit_price) * (discount_percent / 100)
WHERE discount_percent > 0 AND discount_amount = 0;
