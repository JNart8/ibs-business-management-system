<?php

/**
 * Plan / Tier Definitions
 * ------------------------------------------------------------
 * Single source of truth for what each plan unlocks. Do not
 * scatter plan checks elsewhere — add a feature key here, then
 * gate it with planAllows('feature_key') wherever it's needed
 * (routing in public/index.php, menus in header.php, etc).
 *
 * "shared hosting/backups" and "priority support" are NOT listed
 * here on purpose — they're business/pricing terms, not things
 * the app can gate. Keep those on the pricing page only.
 */

return [

    'core' => [
        'label'        => 'Core',
        'max_users'    => 3,
        'max_branches' => 1,
        'features'     => [
            'sales',
            'purchases',
            'inventory',
            'basic_reports',
            'financial_accounts',
        ],
    ],

    'growth' => [
        'label'        => 'Growth',
        'max_users'    => 8,
        'max_branches' => 1,
        'features'     => [
            'sales',
            'purchases',
            'inventory',
            'basic_reports',
            'advanced_reports',
            'imports_exports',
            'suspense',
            'financial_accounts',
            'manage_financial_accounts',
            'expenses',
        ],
    ],

    'enterprise' => [
        'label'        => 'Enterprise',
        'max_users'    => PHP_INT_MAX,
        'max_branches' => PHP_INT_MAX,
        'features'     => [
            'sales',
            'purchases',
            'inventory',
            'basic_reports',
            'advanced_reports',
            'imports_exports',
            'suspense',
            'financial_accounts',
            'manage_financial_accounts',
            'expenses',
            'distributor',
            'advanced_permissions',
            // 'multi_branch' intentionally NOT included yet.
            // Multi-branch is a Phase 3 build (separate stock/sales/
            // purchases per branch + transfers). The `branches` table
            // and branch_id columns exist as scaffolding (Phase 1),
            // but there is no UI yet to add a 2nd branch or scope
            // records to it. Add 'multi_branch' here only once that
            // feature actually ships.
        ],
    ],

];
