<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Config;

use CodeIgniter\Config\BaseService;
use Ephraitech\Platform\Libraries\AuditTrail;
use Ephraitech\Platform\Libraries\Extensions;
use Ephraitech\Platform\Libraries\SequenceGenerator;
use Ephraitech\Platform\Libraries\Settings;
use Ephraitech\Platform\Module\ModuleGate;
use Ephraitech\Platform\Module\ModuleRegistry;
use Ephraitech\Platform\Tenancy\Tenancy;
use Ephraitech\Platform\Tenancy\TenantResolver;

/**
 * Platform services:
 *
 *   service('moduleRegistry')    installed modules and what they contribute
 *   service('moduleGate')        which modules are switched on here
 *   service('tenancy')           the current tenant; tenant management
 *   service('tenantResolver')    finds a request's tenant
 *   service('platformSettings')  settings declared by modules, per tenant
 *   service('sequences')         gap-free document numbers, per tenant
 *   service('auditTrail')        who changed what
 *   service('extensions')        what modules contribute to each other's pages and API
 */
class Services extends BaseService
{
    public static function moduleRegistry(bool $getShared = true): ModuleRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('moduleRegistry');
        }

        return new ModuleRegistry();
    }

    public static function moduleGate(bool $getShared = true): ModuleGate
    {
        if ($getShared) {
            return static::getSharedInstance('moduleGate');
        }

        return new ModuleGate(service('moduleRegistry'), service('tenancy'), service('cache'));
    }

    public static function tenancy(bool $getShared = true): Tenancy
    {
        if ($getShared) {
            return static::getSharedInstance('tenancy');
        }

        return new Tenancy(service('authTenant'));
    }

    public static function tenantResolver(bool $getShared = true): TenantResolver
    {
        if ($getShared) {
            return static::getSharedInstance('tenantResolver');
        }

        return new TenantResolver(service('tenancy'));
    }

    public static function platformSettings(bool $getShared = true): Settings
    {
        if ($getShared) {
            return static::getSharedInstance('platformSettings');
        }

        return new Settings(service('moduleRegistry'), service('moduleGate'), service('tenancy'), service('cache'));
    }

    public static function sequences(bool $getShared = true): SequenceGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('sequences');
        }

        return new SequenceGenerator(service('moduleRegistry'), service('tenancy'), db_connect());
    }

    public static function auditTrail(bool $getShared = true): AuditTrail
    {
        if ($getShared) {
            return static::getSharedInstance('auditTrail');
        }

        return new AuditTrail(service('request'), service('tenancy'));
    }

    public static function extensions(bool $getShared = true): Extensions
    {
        if ($getShared) {
            return static::getSharedInstance('extensions');
        }

        return new Extensions(service('moduleRegistry'), service('moduleGate'));
    }
}
