<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

use RuntimeException;

/**
 * A tenant-scoped model was used with no tenant selected in a
 * multi-tenant install, where it would otherwise read or write every
 * tenant's rows.
 */
final class TenantRequiredException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self(
            "{$model} keeps rows per tenant, but no tenant is selected for this request. "
            . 'Resolve a tenant first (platform filter, tenancy()->runAs()), or call withoutTenantScope() to work across tenants on purpose.'
        );
    }
}
