<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Laravel Repositories
    |--------------------------------------------------------------------------
    |
    | List of Liberu Laravel repositories that can be auto-provisioned
    | in the control panel. Each repository includes metadata for display
    | and installation.
    |
    */

    'repositories' => [
        [
            'name' => 'Accounting',
            'slug' => 'accounting',
            'repository' => 'liberu-accounting/accounting-laravel',
            'description' => 'Accounting and invoicing features tailored for Laravel applications.',
            'icon' => 'heroicon-o-calculator',
            'category' => 'Business',
        ],
        [
            'name' => 'Automation',
            'slug' => 'automation',
            'repository' => 'liberu-automation/automation-laravel',
            'description' => 'Automation tooling and workflow integrations for Laravel projects.',
            'icon' => 'heroicon-o-cog',
            'category' => 'Tools',
        ],
        [
            'name' => 'Billing',
            'slug' => 'billing',
            'repository' => 'liberu-billing/billing-laravel',
            'description' => 'Subscription and billing management integrations (payments, invoices).',
            'icon' => 'heroicon-o-credit-card',
            'category' => 'Business',
        ],
        [
            'name' => 'Boilerplate',
            'slug' => 'boilerplate',
            'repository' => 'liberusoftware/boilerplate',
            'description' => 'Core starter and shared utilities used across Liberu projects.',
            'icon' => 'heroicon-o-cube',
            'category' => 'Core',
        ],
        [
            'name' => 'Browser Game',
            'slug' => 'browser-game',
            'repository' => 'liberu-browser-game/browser-game-laravel',
            'description' => 'Example Laravel-based browser game platform and mechanics.',
            'icon' => 'heroicon-o-puzzle',
            'category' => 'Entertainment',
        ],
        [
            'name' => 'CMS',
            'slug' => 'cms',
            'repository' => 'liberu-cms/cms-laravel',
            'description' => 'Content management features and modular page administration.',
            'icon' => 'heroicon-o-document-text',
            'category' => 'Content',
        ],
        [
            'name' => 'Control Panel',
            'slug' => 'control-panel',
            'repository' => 'liberu-control-panel/control-panel-laravel',
            'description' => 'Administration/control-panel components for managing services.',
            'icon' => 'heroicon-o-server',
            'category' => 'Core',
        ],
        [
            'name' => 'CRM',
            'slug' => 'crm',
            'repository' => 'liberu-crm/crm-laravel',
            'description' => 'Customer relationship management features and integrations.',
            'icon' => 'heroicon-o-users',
            'category' => 'Business',
        ],
        [
            'name' => 'E-commerce',
            'slug' => 'ecommerce',
            'repository' => 'liberu-ecommerce/ecommerce-laravel',
            'description' => 'E-commerce storefront, product and order management.',
            'icon' => 'heroicon-o-shopping-cart',
            'category' => 'Business',
        ],
        [
            'name' => 'Genealogy',
            'slug' => 'genealogy',
            'repository' => 'liberu-genealogy/genealogy-laravel',
            'description' => 'Family tree and genealogy features built on Laravel.',
            'icon' => 'heroicon-o-academic-cap',
            'category' => 'Specialized',
        ],
        [
            'name' => 'Maintenance',
            'slug' => 'maintenance',
            'repository' => 'liberu-maintenance/maintenance-laravel',
            'description' => 'Scheduling, tracking and reporting for maintenance tasks.',
            'icon' => 'heroicon-o-wrench',
            'category' => 'Tools',
        ],
        [
            'name' => 'Real Estate',
            'slug' => 'real-estate',
            'repository' => 'liberu-real-estate/real-estate-laravel',
            'description' => 'Property listings and real-estate management features.',
            'icon' => 'heroicon-o-home',
            'category' => 'Business',
        ],
        [
            'name' => 'Social Network',
            'slug' => 'social-network',
            'repository' => 'liberu-social-network/social-network-laravel',
            'description' => 'Social features, profiles, feeds and user interactions.',
            'icon' => 'heroicon-o-share',
            'category' => 'Social',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default PHP Version
    |--------------------------------------------------------------------------
    |
    | Default PHP version to use for new Laravel applications.
    |
    */

    'default_php_version' => '8.2',

    /*
    |--------------------------------------------------------------------------
    | Supported PHP Versions
    |--------------------------------------------------------------------------
    |
    | List of PHP versions supported for Laravel applications.
    |
    */

    'php_versions' => [
        '8.1' => 'PHP 8.1',
        '8.2' => 'PHP 8.2',
        '8.3' => 'PHP 8.3',
        '8.4' => 'PHP 8.4',
    ],

    /*
    |--------------------------------------------------------------------------
    | Installation Path
    |--------------------------------------------------------------------------
    |
    | Default installation path for Laravel applications relative to domain root.
    |
    */

    'default_install_path' => '/public_html',

    /*
    |--------------------------------------------------------------------------
    | GitHub Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL for GitHub repositories.
    |
    */

    'github_base_url' => 'https://github.com',
];
