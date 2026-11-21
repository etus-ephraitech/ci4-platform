<?php

declare(strict_types=1);

return [
    'name'        => 'Crm',
    'title'       => 'Clients',
    'requires'    => ['Core'],
    'permissions' => ['crm.view' => 'See clients', 'crm.manage' => 'Change clients'],
    'matrix'      => ['admin' => ['crm.view', 'crm.manage'], 'user' => ['crm.view'], 'nobody' => ['crm.view']],
    'menu'        => [['label' => 'Clients', 'route' => 'crm.index', 'icon' => 'bi-people', 'permission' => 'crm.view', 'group' => 'Sales']],
    'sequences'   => ['client' => ['prefix' => 'CL', 'padding' => 3, 'reset' => 'never']],
    'settings'    => [
        'crm'      => ['title' => 'Clients', 'fields' => ['greeting' => ['default' => 'Hello']]],
        'branding' => ['title' => 'Branding', 'scope' => 'global', 'fields' => ['footer' => ['default' => 'Made with care']]],
    ],
    'extends'     => [
        'crm.client.api'      => [['call' => 'Modules\Crm\Models\ClientModel::countFor', 'key' => 'count']],
        'layout.banners'      => [['cell' => 'Modules\Crm\Cells\Banner::app']],
        'layout.auth.banners' => [['cell' => 'Modules\Crm\Cells\Banner::auth']],
    ],
];
