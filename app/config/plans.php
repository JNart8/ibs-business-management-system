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
            // Multi-branch is NOT gated here — it's no longer purely a
            // plan-tier feature. Every Enterprise client gets it
            // automatically, but it can also be sold to a Growth client
            // as a paid add-on. See hasMultiBranch() in functions.php,
            // which checks `plan = 'enterprise' OR settings.addon_multi_branch`.
        ],
    ],

];
