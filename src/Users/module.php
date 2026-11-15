<?php

declare(strict_types=1);

/*
 * Users: people and roles, as screens over ephraitech/ci4-auth. Always on.
 *
 * Single install: every account. Multi-tenant: the members of the current
 * workspace (accounts are shared, roles are given per workspace), or every
 * account for the platform owner with no workspace selected.
 */
return [
    'name'        => 'Users',
    'title'       => 'People and roles',
    'description' => 'Accounts, roles, extra permissions, password resets and sign-outs.',
    'version'     => '1.0.0',
    'order'       => 1,
    'always_on'   => true,
    'central'     => true,
    'requires'    => ['Core'],

    'permissions' => [
        'users.view'    => 'See people and their access',
        'users.create'  => 'Add people',
        'users.update'  => 'Change people’s details, status and passwords',
        'users.delete'  => 'Delete accounts',
        'roles.manage'  => 'Give roles and extra permissions, and edit roles',
        'tokens.manage' => 'Sign people out of apps and API keys',
    ],

    'matrix' => [
        'owner' => ['users.*', 'roles.manage', 'tokens.manage'],
        'admin' => ['users.view', 'users.create', 'users.update', 'roles.manage'],
    ],

    'menu' => [
        ['label' => 'Staff', 'route' => 'users.staff', 'icon' => 'bi-people', 'permission' => 'users.view', 'group' => 'Administration', 'order' => 10],
        ['label' => 'Roles', 'route' => 'users.roles', 'icon' => 'bi-shield-lock', 'permission' => 'roles.manage', 'group' => 'Administration', 'order' => 20],
    ],

    'quick' => [
        ['label' => 'Person', 'route' => 'users.staff.new', 'icon' => 'bi-person-plus', 'permission' => 'users.create'],
    ],

    'search' => ['Ephraitech\Platform\Users\Libraries\Search::find'],
];
