<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= e($sale['sale_number']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #000;
            max-width: 80mm;
            margin: 0 auto;
            padding: 8px;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .large {
            font-size: 14px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }

        .total {
            font-size: 16px;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            @page {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <!-- IBS Logo -->
    <div class="center" style="margin-bottom: 8px;">
        <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png"
            alt="<?= APP_NAME ?>"
            style="width: 60px; height: auto;">
    </div>

    <!-- Store Info -->
    <div class="center">
        <div class="bold large"><?= e($settings['store_name'] ?? APP_NAME) ?></div>
        <?php if (!empty($settings['store_address'])): ?>
            <div><?= e($settings['store_address']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['store_phone'])): ?>
            <div>Tel: <?= e($settings['store_phone']) ?></div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <!-- Sale Info -->
    <div class="row">
        <span>Receipt:</span>
        <span class="bold"><?= e($sale['sale_number']) ?></span>
    </div>
    <div class="row">
        <span>Date:</span>
        <span><?= formatDate($sale['sale_date'], 'd M Y H:i') ?></span>
    </div>
    <div class="row">
        <span>Customer:</span>
        <span><?= e($sale['customer_name']) ?></span>
    </div>
    <?php if (!empty($sale['cashier_name'])): ?>
        <div class="row">
            <span>Cashier:</span>
            <span><?= e($sale['cashier_name']) ?></span>
        </div>
    <?php endif; ?>

    <div class="divider"></div>

    <!-- Items -->
    <?php foreach ($items as $item): ?>
        <div class="bold"><?= e($item['product_name']) ?></div>
        <div class="row">
            <span><?= $item['quantity'] ?> x <?= CURRENCY_HOLDER ?> <?= number_format($item['unit_price'], 2) ?>
                <?php if (($item['discount_amount'] ?? 0) > 0 || ($item['discount_percent'] ?? 0) > 0): ?>
                    <?= ($item['discount_type'] ?? 'percentage') === 'flat'
                        ? '(-' . formatMoney($item['discount_amount']) . ')'
                        : "(-{$item['discount_percent']}%)" ?>
                <?php endif; ?></span>
            <span><?= CURRENCY_HOLDER ?> <?= number_format($item['line_total'], 2) ?></span>
        </div>
    <?php endforeach; ?>

    <div class="divider"></div>

    <!-- Totals -->
    <div class="row">
        <span>Subtotal</span>
        <span><?= formatMoney($sale['subtotal']) ?></span>
    </div>
    <?php if ($sale['discount_amount'] > 0): ?>
        <div class="row">
            <span>Discount<?= ($sale['discount_type'] ?? 'percentage') === 'percentage' ? ' (' . e($sale['discount_percent']) . '%)' : '' ?></span>
            <span>- <?= formatMoney($sale['discount_amount']) ?></span>
        </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="row total">
        <span>TOTAL</span>
        <span><?= formatMoney($sale['total_amount']) ?></span>
    </div>

    <div class="row">
        <span>Paid (<?= ucfirst($sale['payment_method']) ?>)</span>
        <span><?= formatMoney($sale['amount_paid']) ?></span>
    </div>
    <?php if ($sale['amount_due'] > 0): ?>
        <div class="row bold">
            <span>BALANCE DUE</span>
            <span><?= formatMoney($sale['amount_due']) ?></span>
        </div>
    <?php else: ?>
        <?php $change = floatval($sale['amount_paid']) - floatval($sale['total_amount']); ?>
        <?php if ($change > 0): ?>
            <div class="row">
                <span>Change</span>
                <span><?= formatMoney($change) ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="center">
        <span class="badge"><?= strtoupper($sale['payment_status']) ?></span>
        <div style="margin-top: 8px;">Thank you for your purchase!</div>
        <div style="margin-top: 4px; font-size: 10px;"><?= date('Y') ?> <?= APP_NAME ?></div>
    </div>

    <!-- Print button (hidden on print) -->
    <div class="center no-print" style="margin-top: 20px;">
        <button onclick="window.print()"
            style="background:#2563eb;color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-size:14px;">
            🖨 Print
        </button>
        <button onclick="window.close()"
            style="background:#6b7280;color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-size:14px;margin-left:8px;">
            Close
        </button>
    </div>

    <script>
        // Auto-print on load
        window.onload = function() {
            // Small delay to let page render
            setTimeout(() => window.print(), 400);
        };
    </script>
</body>

</html>
