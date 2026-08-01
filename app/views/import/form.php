<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-4xl mx-auto">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Import <?= ucfirst($type) ?></h1>
            <p class="text-gray-500 text-sm mt-1">Upload a CSV file to bulk import records</p>
        </div>
        <a href="<?= BASE_URL ?>/<?= $type ?>"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
            ← Back to <?= ucfirst($type) ?>
        </a>
    </div>

    <?= flashMessage() ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Upload Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">

                <form action="<?= BASE_URL ?>/import/<?= $type ?>"
                    method="POST"
                    enctype="multipart/form-data"
                    x-data="{ fileName: '' }">

                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="preview">

                    <!-- File Upload -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            CSV File <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition">
                            <input type="file"
                                name="csv_file"
                                accept=".csv"
                                required
                                @change="fileName = $el.files[0]?.name || ''"
                                class="hidden"
                                id="csv-upload">
                            <label for="csv-upload" class="cursor-pointer">
                                <div class="text-4xl mb-2">📄</div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <span class="text-blue-600 font-semibold">Click to upload</span>
                                    or drag and drop
                                </div>
                                <div class="text-xs text-gray-400">CSV files only (max 5MB)</div>
                            </label>
                        </div>
                        <div x-show="fileName" class="mt-2 text-sm text-gray-600">
                            📁 Selected: <strong x-text="fileName"></strong>
                        </div>
                    </div>

                    <!-- Import Mode -->
                    <?php if (in_array($type, ['categories', 'suppliers', 'customers'])): ?>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            If Duplicate Found
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="import_mode" value="skip" checked class="mt-1">
                                <div>
                                    <div class="font-medium text-sm">Skip Duplicate</div>
                                    <div class="text-xs text-gray-500">Leave existing record unchanged, don't import this row</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="import_mode" value="update" class="mt-1">
                                <div>
                                    <div class="font-medium text-sm">Update Existing</div>
                                    <div class="text-xs text-gray-500">Overwrite existing record with new data from CSV</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="import_mode" value="skip">
                    <?php endif; ?>

                    <!-- Submit -->
                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold transition">
                        Preview Import →
                    </button>

                </form>
            </div>
        </div>

        <!-- Instructions -->
        <div class="lg:col-span-1 space-y-4">

            <!-- Download Template -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-2">📥 Download Template</h3>
                <p class="text-sm text-gray-600 mb-3">Get the correct CSV format</p>
                <a href="<?= BASE_URL ?>/import/<?= $type ?>/template"
                    download="<?= $type ?>_template.csv"
                    class="block w-full bg-green-600 hover:bg-green-700 text-white text-center py-2 rounded-lg text-sm font-semibold transition">
                    Download Template
                </a>
            </div>

            <!-- Required Fields -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-2">📋 Required Columns</h3>
                <div class="text-sm space-y-1">
                    <?php
                    $required = [
                        'categories' => ['name'],
                        'products' => ['sku', 'name', 'category', 'supplier', 'selling_price'],
                        'suppliers' => ['company_name'],
                        'customers' => ['full_name', 'phone'],
                        'purchases' => ['supplier', 'sku', 'quantity', 'unit_cost']
                    ];
                    ?>
                    <?php foreach ($required[$type] as $field): ?>
                        <div class="flex items-center gap-2">
                            <span class="text-red-500">*</span>
                            <code class="bg-white px-2 py-0.5 rounded text-xs"><?= $field ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Optional Fields -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-2">Optional Columns</h3>
                <div class="text-sm space-y-1 text-gray-600">
                    <?php
                    $optional = [
                        'categories' => ['None - name is all you need'],
                        'products' => ['cost_price', 'current_stock', 'reorder_level', 'unit', 'barcode'],
                        'suppliers' => ['contact_name', 'phone', 'email', 'address'],
                        'customers' => ['email', 'address', 'credit_limit'],
                        'purchases' => ['invoice_number', 'payment_method', 'payment_account', 'purchase_date', 'notes', 'discount_percent', 'vat_percent']
                    ];
                    ?>
                    <?php foreach ($optional[$type] as $field): ?>
                        <div class="text-xs">• <?= $field ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tips -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-2">💡 Tips</h3>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• Use the template to avoid errors</li>
                    <li>• First row must be column headers</li>
                    <li>• Remove any empty rows</li>
                    <?php if ($type === 'products'): ?>
                        <li>• SKU must be unique</li>
                        <li>• Prices should be numbers (no currency symbols)</li>
                        <li>• <strong>Create categories and suppliers first</strong> - they must exist before importing products</li>
                        <li>• Category and supplier names must match exactly</li>
                    <?php endif; ?>
                    <?php if ($type === 'customers'): ?>
                        <li>• Phone must be unique</li>
                    <?php endif; ?>
                    <?php if ($type === 'purchases'): ?>
                        <li>• <strong>Invoice Number grouping</strong>: Rows sharing same supplier and invoice_number will group into a single invoice.</li>
                        <li>• Leave <strong>invoice_number</strong> empty if you want each row imported as separate purchase transaction.</li>
                        <li>• <strong>payment_method</strong> can be credit, cash, mobile, bank, cheque. Defaults to credit.</li>
                        <li>• <strong>payment_account</strong>: Account name to pay from. If empty, defaults to the primary account for the payment method.</li>
                        <li>• <strong>purchase_date</strong> format: YYYY-MM-DD HH:MM:SS or left empty for now.</li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>