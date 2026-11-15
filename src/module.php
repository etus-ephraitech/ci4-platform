<?php

declare(strict_types=1);

/*
 * Core: the platform module. Always on.
 *
 * Every module ships a module.php like this one; see
 * Ephraitech\Platform\Module\Manifest for every key.
 *
 * Roles named in 'matrix' and 'boards' are the starter's generic roles
 * (owner, admin, member). Grants to roles an app does not define are
 * ignored, so apps with other roles grant these permissions in their own
 * Config\Auth::$matrix.
 */
return [
    'name'        => 'Core',
    'title'       => 'Core platform',
    'description' => 'Dashboards, settings, document numbering, search, the audit log, modules and workspaces.',
    'version'     => '1.0.0',
    'order'       => 0,
    'always_on'   => true,
    'central'     => true,

    'permissions' => [
        'dashboard.view'  => 'Open the dashboard and search',
        'settings.manage' => 'Change settings',
        'audit.view'      => 'View the audit log',
        'modules.view'    => 'See which modules are installed and switched on',
        'modules.manage'  => 'Switch modules on and off (single install)',
        'tenants.manage'  => 'Create workspaces and change their plan, status and modules (multi-tenant, platform owner)',
    ],

    'matrix' => [
        'owner'  => ['dashboard.view', 'settings.manage', 'audit.view', 'modules.view'],
        'admin'  => ['dashboard.view', 'settings.manage', 'audit.view', 'modules.view'],
        'member' => ['dashboard.view'],
    ],

    'menu' => [
        ['label' => 'Dashboard', 'route' => 'core.dashboard', 'icon' => 'bi-grid-1x2', 'permission' => 'dashboard.view', 'group' => 'Overview', 'order' => 0],
        ['label' => 'Workspaces', 'route' => 'core.tenants', 'icon' => 'bi-buildings', 'permission' => 'tenants.manage', 'group' => 'Administration', 'order' => 60],
        ['label' => 'Modules', 'route' => 'core.modules', 'icon' => 'bi-puzzle', 'permission' => 'modules.view', 'group' => 'Administration', 'order' => 70],
        ['label' => 'Settings', 'route' => 'core.settings', 'icon' => 'bi-sliders', 'permission' => 'settings.manage', 'group' => 'Administration', 'order' => 80],
        ['label' => 'Audit log', 'route' => 'core.audit', 'icon' => 'bi-journal-text', 'permission' => 'audit.view', 'group' => 'Administration', 'order' => 90],
    ],

    'settings' => [
        'company' => [
            'title'       => 'Company profile',
            'description' => 'Shown on documents, emails and the sign-in page.',
            'order'       => 0,
            'fields'      => [
                'name'    => ['label' => 'Company name', 'type' => 'text', 'default' => '', 'rules' => 'permit_empty|max_length[150]'],
                'tagline' => ['label' => 'Tagline', 'type' => 'text', 'default' => '', 'rules' => 'permit_empty|max_length[150]'],
                'email'   => ['label' => 'Email', 'type' => 'email', 'default' => '', 'rules' => 'permit_empty|valid_email|max_length[150]'],
                'phone'   => ['label' => 'Phone', 'type' => 'tel', 'default' => '', 'rules' => 'permit_empty|max_length[30]'],
                'website' => ['label' => 'Website', 'type' => 'url', 'default' => '', 'rules' => 'permit_empty|valid_url_strict|max_length[150]'],
                'address' => ['label' => 'Physical address', 'type' => 'textarea', 'default' => '', 'rules' => 'permit_empty|max_length[300]'],
                'tax_id'  => ['label' => 'Tax ID', 'type' => 'text', 'default' => '', 'rules' => 'permit_empty|max_length[30]', 'help' => 'For example the TIN printed on tax invoices.'],
            ],
        ],
        'finance' => [
            'title'       => 'Money',
            'description' => 'Currency used when amounts are shown.',
            'order'       => 10,
            'fields'      => [
                'currency' => ['label' => 'Currency', 'type' => 'select', 'default' => 'UGX', 'rules' => 'required|in_list[UGX,KES,TZS,RWF,USD,EUR,GBP]', 'options' => [
                    'UGX' => 'Uganda shilling (UGX)',
                    'KES' => 'Kenyan shilling (KES)',
                    'TZS' => 'Tanzanian shilling (TZS)',
                    'RWF' => 'Rwandan franc (RWF)',
                    'USD' => 'US dollar (USD)',
                    'EUR' => 'Euro (EUR)',
                    'GBP' => 'Pound sterling (GBP)',
                ]],
            ],
        ],
        'dashboards' => [
            'title'       => 'Dashboards',
            'description' => 'Where people land when none of their roles has a dashboard of its own.',
            'order'       => 90,
            'fields'      => [
                'default' => ['label' => 'Default dashboard', 'type' => 'select', 'default' => 'home', 'options' => 'Ephraitech\Platform\Libraries\Dashboards::options', 'rules' => 'required'],
            ],
        ],
    ],

    'boards' => [
        'home'     => ['title' => 'My day', 'icon' => 'bi-sun', 'order' => 0],
        'overview' => ['title' => 'Overview', 'icon' => 'bi-speedometer2', 'permission' => 'settings.manage', 'roles' => ['owner', 'admin'], 'order' => 10],
    ],

    'widgets' => [
        ['cell' => 'Ephraitech\Platform\Cells\Widgets::setup', 'permission' => 'settings.manage', 'width' => 5, 'order' => 0, 'dashboards' => ['overview']],
        ['cell' => 'Ephraitech\Platform\Cells\Widgets::activity', 'permission' => 'audit.view', 'width' => 7, 'order' => 10, 'dashboards' => ['overview']],
        ['cell' => 'Ephraitech\Platform\Cells\Widgets::modules', 'permission' => 'modules.view', 'width' => 12, 'order' => 90, 'dashboards' => ['overview']],
    ],

    'api' => [
        'base'        => 'api/v1/platform',
        'version'     => 'v1',
        'description' => 'Context (who, which workspace, which modules and permissions), modules and search.',
    ],
];
