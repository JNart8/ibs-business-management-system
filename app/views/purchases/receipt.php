<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Receipt - <?= e($purchase['purchase_number']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
            background: #f5f5f5;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .receipt-content {
            padding: 40px;
        }

        /* Header */
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid #2563eb;
            margin-bottom: 30px;
        }

        .header img {
            height: 60px;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 18px;
            color: #6b7280;
            font-weight: 600;
        }

        .header .company-info {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            background: #f9fafb;
        }

        .info-box h3 {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .info-box .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .info-box .label {
            color: #6b7280;
        }

        .info-box .value {
            font-weight: 600;
            color: #1f2937;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead {
            background: #f3f4f6;
        }

        .items-table th {
            padding: 12px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
        }

        .items-table th.text-center {
            text-align: center;
        }

        .items-table th.text-right {
            text-align: right;
        }

        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }

        .items-table td.text-center {
            text-align: center;
        }

        .items-table td.text-right {
            text-align: right;
        }

        .items-table tbody tr:hover {
            background: #f9fafb;
        }

        .items-table .product-name {
            font-weight: 600;
            color: #1f2937;
        }

        .items-table .product-sku {
            font-size: 11px;
            color: #9ca3af;
        }

        /* Totals */
        .totals {
            margin-left: auto;
            width: 350px;
            margin-bottom: 30px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .totals-row.subtotal {
            border-bottom: 1px solid #e5e7eb;
        }

        .totals-row.total {
            border-top: 2px solid #2563eb;
            padding-top: 12px;
            margin-top: 8px;
            font-size: 18px;
            font-weight: bold;
        }

        .totals-row .label {
            color: #6b7280;
        }

        .totals-row .value {
            font-weight: 600;
            color: #1f2937;
        }

        .totals-row.total .value {
            color: #2563eb;
        }

        /* Payment Info */
        .payment-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
        }

        .payment-info h3 {
            font-size: 14px;
            color: #0369a1;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-item .label {
            font-size: 12px;
            color: #075985;
        }

        .payment-item .value {
            font-weight: 600;
            font-size: 14px;
            color: #0c4a6e;
        }

        .payment-item.status .value {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-partial {
            background: #fef3c7;
            color: #92400e;
        }

        .status-unpaid {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Notes */
        .notes {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
        }

        .notes h3 {
            font-size: 14px;
            color: #92400e;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .notes p {
            font-size: 13px;
            color: #78350f;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            border-top: 2px solid #e5e7eb;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .footer .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
            margin-bottom: 20px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            margin-top: 60px;
            padding-top: 8px;
            font-weight: 600;
            color: #4b5563;
        }

        /* Print Styles */
        @media print {
            body {
                padding: 0;
                background: white;
            }

            .receipt-container {
                box-shadow: none;
            }

            .no-print {
                display: none !important;
            }

            .receipt-content {
                padding: 20px;
            }
        }

        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .print-button:hover {
            background: #1d4ed8;
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body>

    <!-- Print Button -->
    <button onclick="window.print()" class="print-button no-print">
        🖨️ Print Receipt
    </button>

    <div class="receipt-container">
        <div class="receipt-content">

            <!-- Header -->
            <div class="header">
                <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png" alt="<?= APP_NAME ?>">
                <h1><?= e($settings['store_name'] ?? APP_NAME) ?></h1>
                <div class="subtitle">GOODS RECEIVED NOTE</div>
                <div class="company-info">
                    <?php if (!empty($settings['store_address'])): ?>
                        <?= e($settings['store_address']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($settings['store_phone'])): ?>
                        Tel: <?= e($settings['store_phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($settings['store_email'])): ?>
                        | Email: <?= e($settings['store_email']) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <!-- Purchase Details -->
                <div class="info-box">
                    <h3>Purchase Details</h3>
                    <div class="info-row">
                        <span class="label">Purchase #:</span>
                        <span class="value" style="font-family: monospace;"><?= e($purchase['purchase_number']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date:</span>
                        <span class="value"><?= date('d M Y, H:i', strtotime($purchase['purchase_date'])) ?></span>
                    </div>
                    <?php if (!empty($purchase['invoice_number'])): ?>
                        <div class="info-row">
                            <span class="label">Supplier Invoice:</span>
                            <span class="value" style="font-family: monospace;"><?= e($purchase['invoice_number']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="label">Created By:</span>
                        <span class="value"><?= e($purchase['created_by'] ?? 'Staff') ?></span>
                    </div>
                </div>

                <!-- Supplier Details -->
                <div class="info-box">
                    <h3>Supplier Information</h3>
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span class="value"><?= e($purchase['company_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Code:</span>
                        <span class="value" style="font-family: monospace;"><?= e($purchase['supplier_code']) ?></span>
                    </div>
                    <?php if (!empty($purchase['contact_name'])): ?>
                        <div class="info-row">
                            <span class="label">Contact:</span>
                            <span class="value"><?= e($purchase['contact_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($purchase['supplier_phone'])): ?>
                        <div class="info-row">
                            <span class="label">Phone:</span>
                            <span class="value"><?= e($purchase['supplier_phone']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 40%;">Product</th>
                        <th class="text-center" style="width: 15%;">Quantity</th>
                        <th class="text-right" style="width: 15%;">Unit Cost</th>
                        <th class="text-center" style="width: 10%;">Disc.</th>
                        <th class="text-right" style="width: 15%;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $index = 1; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td>
                                <div class="product-name"><?= e($item['product_name']) ?></div>
                                <div class="product-sku">SKU: <?= e($item['sku']) ?></div>
                            </td>
                            <td class="text-center">
                                <strong><?= formatQty($item['quantity']) ?></strong> <?= e($item['unit'] ?? 'pcs') ?>
                            </td>
                            <td class="text-right" style="font-family: monospace;">
                                <?= formatMoney($item['unit_cost']) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['discount_percent'] > 0): ?>
                                    <span style="color: #16a34a; font-weight: 600;">
                                        <?= number_format($item['discount_percent'], 1) ?>%
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right" style="font-family: monospace; font-weight: 600;">
                                <?= formatMoney($item['line_total']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals">
                <div class="totals-row subtotal">
                    <span class="label">Subtotal:</span>
                    <span class="value" style="font-family: monospace;">
                        <?= formatMoney($purchase['subtotal']) ?>
                    </span>
                </div>

                <?php if ($purchase['discount_amount'] > 0): ?>
                    <div class="totals-row">
                        <span class="label">Discount (<?= number_format($purchase['discount_percent'], 1) ?>%):</span>
                        <span class="value" style="color: #16a34a; font-family: monospace;">
                            - <?= formatMoney($purchase['discount_amount']) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($purchase['vat_amount'] > 0): ?>
                    <div class="totals-row">
                        <span class="label">VAT (<?= number_format($purchase['vat_percent'], 1) ?>%):</span>
                        <span class="value" style="color: #ea580c; font-family: monospace;">
                            + <?= formatMoney($purchase['vat_amount']) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="totals-row total">
                    <span class="label">TOTAL:</span>
                    <span class="value" style="font-family: monospace;">
                        <?= formatMoney($purchase['total_amount']) ?>
                    </span>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="payment-info">
                <h3>💳 Payment Information</h3>
                <div class="payment-grid">
                    <div class="payment-item">
                        <span class="label">Payment Method:</span>
                        <span class="value"><?= ucfirst($purchase['payment_method']) ?></span>
                    </div>
                    <div class="payment-item">
                        <span class="label">Amount Paid:</span>
                        <span class="value" style="color: #16a34a; font-family: monospace;">
                            <?= formatMoney($purchase['amount_paid']) ?>
                        </span>
                    </div>
                    <div class="payment-item">
                        <span class="label">Balance Due:</span>
                        <span class="value" style="color: #dc2626; font-family: monospace;">
                            <?= formatMoney($purchase['amount_due']) ?>
                        </span>
                    </div>
                    <div class="payment-item status">
                        <span class="label">Status:</span>
                        <span class="value status-<?= $purchase['payment_status'] ?>">
                            <?= ucfirst($purchase['payment_status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if (!empty($purchase['notes'])): ?>
                <div class="notes">
                    <h3>📝 Notes</h3>
                    <p><?= nl2br(e($purchase['notes'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer">
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line">Supplier Signature</div>
                        <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">
                            Date: _________________
                        </div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line">Received By</div>
                        <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">
                            Date: _________________
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <p style="font-size: 11px; color: #9ca3af;">
                        This is a computer-generated document. No signature is required.
                    </p>
                    <p style="font-size: 11px; color: #9ca3af; margin-top: 5px;">
                        Printed on <?= date('d M Y, H:i') ?> | Purchase #<?= e($purchase['purchase_number']) ?>
                    </p>
                    <p style="font-size: 10px; color: #d1d5db; margin-top: 10px;">
                        <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Auto-print on load (optional - comment out if not desired)
        // window.addEventListener('load', function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // });

        // Keyboard shortcut for printing
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>

</body>

</html>