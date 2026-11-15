<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Ephraitech\Platform\Config\PlatformConfig;

/**
 * Core routes, discovered by CodeIgniter from this package's namespace.
 *
 * Every route names its guard and permission in one filter
 * ('can:session,x' or 'can:token,x'): ci4-auth's can: filter signs the
 * person in with that guard first, so a route can never lose its
 * authentication by an edit to a second filter.
 *
 * @var RouteCollection $routes
 */
$web = static fn (string $permission): array => ['filter' => 'can:session,' . $permission];
$api = static fn (string $permission): array => ['filter' => 'can:token,' . $permission];

$routes->group('', ['namespace' => 'Ephraitech\Platform\Controllers'], static function (RouteCollection $routes) use ($web): void {
    // Signed in is enough: the controller shows a "no access yet" page to
    // people without dashboard.view, instead of can: sending them to the
    // forbidden redirect, which is usually this very page.
    $routes->get('/', 'DashboardController::index', ['as' => 'core.dashboard', 'filter' => 'auth:session']);
    $routes->get('dashboard/(:segment)', 'DashboardController::index/$1', ['as' => 'core.dashboard.board'] + $web('dashboard.view'));

    $routes->get('search', 'SearchController::index', ['as' => 'core.search'] + $web('dashboard.view'));
    $routes->get('search/quick', 'SearchController::quick', ['as' => 'core.search.quick'] + $web('dashboard.view'));

    $routes->get('settings', 'SettingsController::index', ['as' => 'core.settings'] + $web('settings.manage'));
    $routes->get('settings/(:segment)', 'SettingsController::index/$1', ['as' => 'core.settings.group'] + $web('settings.manage'));
    $routes->post('settings/(:segment)', 'SettingsController::update/$1', ['as' => 'core.settings.update'] + $web('settings.manage'));

    $routes->get('audit-log', 'AuditLogController::index', ['as' => 'core.audit'] + $web('audit.view'));

    $routes->get('modules', 'ModulesController::index', ['as' => 'core.modules'] + $web('modules.view'));
    $routes->post('modules/([A-Z][A-Za-z0-9]*)', 'ModulesController::update/$1', ['as' => 'core.modules.update'] + $web('modules.manage'));

    if (PlatformConfig::isMultiTenant()) {
        // Workspaces a signed-in person belongs to, and switching between them.
        $routes->get('workspaces', 'WorkspacesController::index', ['as' => 'core.workspaces', 'filter' => 'auth:session']);
        $routes->post('workspaces/([a-z0-9-]+)', 'WorkspacesController::enter/$1', ['as' => 'core.workspaces.enter', 'filter' => 'auth:session']);
        $routes->post('workspaces-leave', 'WorkspacesController::leave', ['as' => 'core.workspaces.leave', 'filter' => 'auth:session']);

        // The platform owner's tenant administration (central only).
        $routes->get('tenants', 'TenantsController::index', ['as' => 'core.tenants'] + $web('tenants.manage'));
        $routes->get('tenants/new', 'TenantsController::new', ['as' => 'core.tenants.new'] + $web('tenants.manage'));
        $routes->post('tenants', 'TenantsController::create', ['as' => 'core.tenants.create'] + $web('tenants.manage'));
        $routes->get('tenants/([a-z0-9-]+)', 'TenantsController::show/$1', ['as' => 'core.tenants.show'] + $web('tenants.manage'));
        $routes->post('tenants/([a-z0-9-]+)', 'TenantsController::update/$1', ['as' => 'core.tenants.update'] + $web('tenants.manage'));
        $routes->post('tenants/([a-z0-9-]+)/modules', 'TenantsController::modules/$1', ['as' => 'core.tenants.modules'] + $web('tenants.manage'));
    }
});

$prefix = trim(PlatformConfig::get()->apiPrefix, '/') . '/platform';

$routes->group($prefix, ['namespace' => 'Ephraitech\Platform\Controllers\Api'], static function (RouteCollection $routes) use ($api): void {
    $routes->get('context', 'PlatformController::context', ['as' => 'api.platform.context', 'filter' => 'auth:token']);
    $routes->get('modules', 'PlatformController::modules', ['as' => 'api.platform.modules', 'filter' => 'auth:token']);
    $routes->get('search', 'PlatformController::search', ['as' => 'api.platform.search'] + $api('dashboard.view'));
});
