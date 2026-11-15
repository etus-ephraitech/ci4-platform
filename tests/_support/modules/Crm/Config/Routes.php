<?php

declare(strict_types=1);

/** @var CodeIgniter\Router\RouteCollection $routes */
$routes->get('crm', '\Modules\Crm\Controllers\Clients::index', ['as' => 'crm.index', 'filter' => 'can:session,crm.view']);
$routes->get('api/v1/crm/ping', '\Modules\Crm\Controllers\Clients::ping', ['as' => 'api.crm.ping', 'filter' => 'can:token,crm.view']);
