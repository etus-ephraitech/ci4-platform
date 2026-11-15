<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Libraries;

use Ephraitech\Platform\Config\PlatformConfig;

/**
 * Every permission grouped by where it comes from: "Platform" for those
 * in app/Config/Auth.php, then one group per module. Inside a workspace,
 * modules switched off for it are left out.
 */
class PermissionCatalog
{
    /**
     * @return array<string, array<string, string>> group title => [permission => description]
     */
    public function grouped(): array
    {
        $config   = PlatformConfig::auth();
        $registry = service('moduleRegistry');
        $byModule = [];
        $claimed  = [];

        $gate     = service('moduleGate');
        // Platform owner (multi-tenant, no workspace): every installed module,
        // since roles are shared by all workspaces. Elsewhere: what is on here.
        $showAll  = service('tenancy')->isMulti() && ! service('tenancy')->has();

        foreach ($registry->all() as $module) {
            if ($module['permissions'] === []) {
                continue;
            }

            $claimed += $module['permissions'];

            if (! $showAll && ! $gate->enabled($module['name'])) {
                continue;
            }

            $byModule[$module['title']] = $module['permissions'];
        }

        $platform = array_diff_key($config->permissions, $claimed);
        ksort($platform);

        return ['Platform' => $platform] + $byModule;
    }

    /**
     * @return array<string, string> permission => description
     */
    public function all(): array
    {
        return PlatformConfig::auth()->permissions;
    }

    /**
     * Whether a role's grants (with wildcards) include a permission.
     *
     * @param list<string> $grants
     */
    public function covers(array $grants, string $permission): bool
    {
        foreach ($grants as $grant) {
            if ($grant === '*' || $grant === $permission) {
                return true;
            }

            if (str_ends_with($grant, '.*') && str_starts_with($permission, substr($grant, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
