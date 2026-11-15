<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Users routes. Each names its guard and permission in one filter
 * (can:session,x), so a route cannot lose its sign-in check by an edit.
 *
 * @var RouteCollection $routes
 */
$routes->group('admin', ['namespace' => 'Ephraitech\Platform\Users\Controllers'], static function (RouteCollection $routes): void {
    $can = static fn (string $permission): string => 'can:session,' . $permission;

    $routes->get('staff', 'StaffController::index', ['as' => 'users.staff', 'filter' => $can('users.view')]);
    $routes->get('staff/data', 'StaffController::data', ['as' => 'users.staff.data', 'filter' => $can('users.view')]);
    $routes->get('staff/new', 'StaffController::new', ['as' => 'users.staff.new', 'filter' => $can('users.create')]);
    $routes->post('staff', 'StaffController::create', ['as' => 'users.staff.create', 'filter' => $can('users.create')]);
    $routes->get('staff/(:num)', 'StaffController::show/$1', ['as' => 'users.staff.show', 'filter' => $can('users.view')]);
    $routes->get('staff/(:num)/edit', 'StaffController::edit/$1', ['as' => 'users.staff.edit', 'filter' => $can('users.update')]);
    $routes->post('staff/(:num)', 'StaffController::update/$1', ['as' => 'users.staff.update', 'filter' => $can('users.update')]);
    $routes->post('staff/(:num)/roles', 'StaffController::roles/$1', ['as' => 'users.staff.roles', 'filter' => $can('roles.manage')]);
    $routes->post('staff/(:num)/status', 'StaffController::status/$1', ['as' => 'users.staff.status', 'filter' => $can('users.update')]);
    $routes->post('staff/(:num)/password', 'StaffController::password/$1', ['as' => 'users.staff.password', 'filter' => $can('users.update')]);
    $routes->post('staff/(:num)/sign-out', 'StaffController::signOut/$1', ['as' => 'users.staff.signout', 'filter' => $can('tokens.manage')]);
    $routes->post('staff/(:num)/permission', 'StaffController::permission/$1', ['as' => 'users.staff.permission', 'filter' => $can('roles.manage')]);
    $routes->post('staff/(:num)/remove', 'StaffController::remove/$1', ['as' => 'users.staff.remove', 'filter' => $can('users.update')]);
    $routes->post('staff/(:num)/delete', 'StaffController::delete/$1', ['as' => 'users.staff.delete', 'filter' => $can('users.delete')]);

    $routes->get('roles', 'RolesController::index', ['as' => 'users.roles', 'filter' => $can('roles.manage')]);
    $routes->post('roles', 'RolesController::create', ['as' => 'users.roles.create', 'filter' => $can('roles.manage')]);
    $routes->get('roles/([a-z0-9_-]+)', 'RolesController::edit/$1', ['as' => 'users.roles.edit', 'filter' => $can('roles.manage')]);
    $routes->post('roles/([a-z0-9_-]+)', 'RolesController::save/$1', ['as' => 'users.roles.save', 'filter' => $can('roles.manage')]);
    $routes->post('roles/([a-z0-9_-]+)/reset', 'RolesController::reset/$1', ['as' => 'users.roles.reset', 'filter' => $can('roles.manage')]);
    $routes->post('roles/([a-z0-9_-]+)/rename', 'RolesController::rename/$1', ['as' => 'users.roles.rename', 'filter' => $can('roles.manage')]);
    $routes->post('roles/([a-z0-9_-]+)/delete', 'RolesController::delete/$1', ['as' => 'users.roles.delete', 'filter' => $can('roles.manage')]);
});
