<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Config;

use Ephraitech\Auth\Config\AuthPermissions as BaseAuthPermissions;
use Ephraitech\Auth\Config\ConfigResolver;

/**
 * Feeds every installed module's manifest permissions and role grants
 * into ci4-auth, which discovers this class (Config/AuthPermissions.php
 * in a known namespace) and merges it into Config\Auth.
 *
 * So module.php stays the single place a module declares access, and
 * auth:sync, can(), the role editor and config validation all see it.
 *
 * Grants to roles the app does not define in Config\Auth are dropped (and
 * logged) instead of breaking config validation: a module written for
 * another product may name roles this one does not have.
 *
 * Installed modules are synced even when switched off for some tenants;
 * the permission gate refuses them at check time. That keeps one
 * permission catalogue for every tenant and stable permission ids.
 */
class AuthPermissions extends BaseAuthPermissions
{
    /**
     * Grants dropped because the role is not defined: role => permissions.
     *
     * @var array<string, list<string>>
     */
    public static array $droppedGrants = [];

    public function __construct()
    {
        $registry          = service('moduleRegistry');
        $this->permissions = $registry->permissions();

        $roles = array_keys(config(ConfigResolver::resolvedClass())->roles);

        foreach ($registry->matrix() as $role => $grants) {
            if (in_array($role, $roles, true)) {
                $this->matrix[$role] = $grants;

                continue;
            }

            self::$droppedGrants[$role] = $grants;
            log_message('warning', 'Platform: modules grant {permissions} to role "{role}", which Config\Auth does not define; the grants are ignored.', [
                'permissions' => implode(', ', $grants),
                'role'        => $role,
            ]);
        }
    }
}
