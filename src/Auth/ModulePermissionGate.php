<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Auth;

use Ephraitech\Auth\Authorization\PermissionGateInterface;
use Throwable;

/**
 * ci4-auth permission gate backed by the module gate: a permission
 * declared by a module is refused while that module is switched off for
 * the tenant being checked. Set it in app/Config/Auth.php:
 *
 *     public ?string $permissionGate = \Ephraitech\Platform\Auth\ModulePermissionGate::class;
 *
 * deploy:check fails when it is missing, because without it a switched-off
 * module's permissions still pass can() (its routes are still closed by
 * the platform filter, but services and API code checking can() are not).
 */
final class ModulePermissionGate implements PermissionGateInterface
{
    public function allows(string $permission, string $tenantId): bool
    {
        try {
            $gate = service('moduleGate');
        } catch (Throwable $e) {
            log_message('critical', 'Platform: module gate unavailable, refusing {permission}: {message}', ['permission' => $permission, 'message' => $e->getMessage()]);

            return false;
        }

        // ci4-auth passes the tenant it is checking: '' in a single install,
        // or for a global (no tenant) check in a multi-tenant one.
        return $gate->allowsPermission($permission, $tenantId);
    }
}
