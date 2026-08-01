<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Statement - <?= e($customer['full_name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }

        .statement {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }

        .statement-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .info-box {
            flex: 1;
        }

        .info-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .balance-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
        }

        .balance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .balance-row:last-child {
            margin-bottom: 0;
        }

        .balance-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .balance-value {
            font-size: 16px;
            font-weight: bold;
        }

        .balance-value.positive {
            color: #28a745;
        }

        .balance-value.negative {
            color: #dc3545;
        }

        .balance-value.neutral {
            color: #6c757d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 13px;
        }

        thead {
            background: #f8f9fa;
            border-top: 2px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }

        th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        th.right {
            text-align: right;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e9ecef;
            color: #333;
        }

        td.right {
            text-align: right;
        }

        td.center {
            text-align: center;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .debit {
            color: #dc3545;
            font-weight: 600;
        }

        .credit {
            color: #28a745;
            font-weight: 600;
        }

        .running-balance {
            font-weight: 600;
            color: #333;
        }

        .summary {
            margin-top: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-row:last-child {
            border-bottom: none;
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 16px;
            font-weight: bold;
        }

        .summary-label {
            color: #666;
        }

        .summary-value {
            font-weight: 600;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            font-size: 11px;
            color: #999;
        }

        .no-transactions {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .statement {
                box-shadow: none;
            }

            .no-print {
                display: none;
            }

            tr {
                page-break-inside: avoid;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>

    <button onclick="window.print()" class="print-button no-print">🖨️ Print Statement</button>

    <div class="statement">
        <!-- Company Header -->
        <div class="header">
            <div class="company-info">
                <div class="company-name"><?= e($settings['store_name'] ?? 'Company Name') ?></div>
                <div class="company-details">
                    <?php if (!empty($settings['store_address'])): ?>
                        <?= nl2br(e($settings['store_address'])) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($settings['store_phone'])): ?>
                        Tel: <?= e($settings['store_phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($settings['store_email'])): ?>
                        | Email: <?= e($settings['store_email']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="statement-title">Customer Statement</div>
        </div>

        <!-- Customer & Period Info -->
        <div class="info-row">
            <div class="info-box">
                <div class="info-label">Customer</div>
                <div class="info-value"><?= e($customer['full_name']) ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Account Code</div>
                <div class="info-value"><?= e($customer['customer_code']) ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Phone</div>
                <div class="info-value"><?= e($customer['phone']) ?></div>
            </div>
        </div>

        <div class="info-row">
            <div class="info-box">
                <div class="info-label">Statement Period</div>
                <div class="info-value">
                    <?= date('d M Y', strtotime($dateFrom)) ?> to <?= date('d M Y', strtotime($dateTo)) ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Statement Date</div>
                <div class="info-value"><?= date('d M Y') ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Credit Limit</div>
                <div class="info-value"><?= formatMoney($customer['credit_limit']) ?></div>
            </div>
        </div>

        <!-- Balance Summary -->
        <div class="balance-box">
            <div class="balance-row">
                <span class="balance-label">Opening Balance:</span>
                <span class="balance-value <?= $openingBalance < 0 ? 'negative' : ($openingBalance > 0 ? 'positive' : 'neutral') ?>">
                    <?= formatMoney(abs($openingBalance)) ?> <?= $openingBalance < 0 ? '(Dr)' : ($openingBalance > 0 ? '(Cr)' : '') ?>
                </span>
            </div>
            <div class="balance-row">
                <span class="balance-label">Closing Balance:</span>
                <span class="balance-value <?= $closingBalance < 0 ? 'negative' : ($closingBalance > 0 ? 'positive' : 'neutral') ?>">
                    <?= formatMoney(abs($closingBalance)) ?> <?= $closingBalance < 0 ? '(Dr)' : ($closingBalance > 0 ? '(Cr)' : '') ?>
                </span>
            </div>
        </div>

        <!-- Transactions Table -->
        <?php if (empty($transactions)): ?>
            <div class="no-transactions">
                No transactions found for the selected period.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="right">Debit (<?= CURRENCY_HOLDER ?>)</th>
                        <th class="right">Credit (<?= CURRENCY_HOLDER ?>)</th>
                        <th class="right">Balance (<?= CURRENCY_HOLDER ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr style="background: #f8f9fa; font-weight: 600;">
                        <td><?= date('d M Y', strtotime($dateFrom)) ?></td>
                        <td>Opening Balance</td>
                        <td class="right">—</td>
                        <td class="right">—</td>
                        <td class="right running-balance">
                            <?= formatMoney(abs($openingBalance)) ?> <?= $openingBalance < 0 ? '(Dr)' : ($openingBalance > 0 ? '(Cr)' : '') ?>
                        </td>
                    </tr>

                    <!-- Transaction Rows -->
                    <?php
                    $runningBalance = $openingBalance;
                    $transactionLabels = [
                        'deposit' => 'Deposit',
                        'payment' => 'Payment',
                        'sale' => 'Sale',
                        'refund' => 'Refund',
                        'adjustment' => 'Adjustment'
                    ];

                    foreach ($transactions as $t):
                        $isDebit = in_array($t['transaction_type'], ['sale']);
                        $debit = $isDebit ? abs($t['amount']) : 0;
                        $credit = !$isDebit && $t['transaction_type'] !== 'adjustment' ? abs($t['amount']) : 0;

                        // Description
                        $description = $transactionLabels[$t['transaction_type']] ?? $t['transaction_type'];
                        if (!empty($t['sale_number'])) {
                            $description .= ' - ' . $t['sale_number'];
                        }
                        if (!empty($t['notes']) && $t['transaction_type'] === 'adjustment') {
                            $description .= ' - ' . $t['notes'];
                        }
                    ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                            <td><?= e($description) ?></td>
                            <td class="right <?= $debit > 0 ? 'debit' : '' ?>">
                                <?= $debit > 0 ? number_format($debit, 2) : '—' ?>
                            </td>
                            <td class="right <?= $credit > 0 ? 'credit' : '' ?>">
                                <?= $credit > 0 ? number_format($credit, 2) : '—' ?>
                            </td>
                            <td class="right running-balance">
                                <?= formatMoney(abs($t['balance_after'])) ?> <?= $t['balance_after'] < 0 ? '(Dr)' : ($t['balance_after'] > 0 ? '(Cr)' : '') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Closing Balance Row -->
                    <tr style="background: #f8f9fa; font-weight: 600;">
                        <td><?= date('d M Y', strtotime($dateTo)) ?></td>
                        <td>Closing Balance</td>
                        <td class="right">—</td>
                        <td class="right">—</td>
                        <td class="right running-balance">
                            <?= formatMoney(abs($closingBalance)) ?> <?= $closingBalance < 0 ? '(Dr)' : ($closingBalance > 0 ? '(Cr)' : '') ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Summary -->
            <div class="summary">
                <div class="summary-row">
                    <span class="summary-label">Total Debits (Purchases):</span>
                    <span class="summary-value debit"><?= formatMoney($totalDebits) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Credits (Payments):</span>
                    <span class="summary-value credit"><?= formatMoney($totalCredits) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Net Movement:</span>
                    <span class="summary-value"><?= formatMoney(abs($totalCredits - $totalDebits)) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Amount <?= $closingBalance < 0 ? 'Owed by Customer' : ($closingBalance > 0 ? 'Credit Balance' : 'Balance') ?>:</span>
                    <span class="summary-value <?= $closingBalance < 0 ? 'negative' : ($closingBalance > 0 ? 'positive' : 'neutral') ?>">
                        <?= formatMoney(abs($closingBalance)) ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated statement and does not require a signature.</p>
            <p>For any queries, please contact us at <?= e($settings['company_phone'] ?? 'N/A') ?></p>
            <p style="margin-top: 10px;">Generated on <?= date('d M Y \a\t H:i') ?></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); };
    </script>

</body>

</html>