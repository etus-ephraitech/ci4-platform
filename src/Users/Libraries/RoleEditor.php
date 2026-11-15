<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Libraries;

use Ephraitech\Auth\Authorization\PermissionMatcher;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Platform\Config\PlatformConfig;
use RuntimeException;

/**
 * Roles and what each may do, edited in the app, on ci4-auth's RoleManager.
 *
 * A built-in role (in Config\Auth, with grants from module manifests)
 * starts on its defaults. Once edited here ci4-auth marks it app-managed,
 * so `php spark auth:sync` leaves it as edited; "reset to defaults" puts
 * the defaults back and hands it to auth:sync again. Roles made here live
 * only in the database.
 *
 * Roles are the same in every workspace of a multi-tenant install, so
 * definitions are edited only with no workspace selected (canEdit()).
 * Who holds which role is per workspace, on the Staff screens.
 */
class RoleEditor
{
    private Auth $config;

    public function __construct()
    {
        $this->config = PlatformConfig::auth();
    }

    /**
     * Whether role definitions may be changed here.
     */
    public function canEdit(): bool
    {
        return ! service('tenancy')->isMulti() || ! service('tenancy')->has();
    }

    /**
     * Every role with what the screens need.
     *
     * @return array<string, array{title: string, description: string, system: bool, people: int, permissions: int, edited: bool, editable: bool, synced: bool}>
     */
    public function overview(): array
    {
        $db     = db_connect($this->config->DBGroup);
        $tenant = service('tenancy')->key();
        $counts = [];

        foreach ($db->table($this->config->table('user_roles') . ' ur')
            ->select('r.name, COUNT(DISTINCT ur.user_id) AS n')
            ->join($this->config->table('roles') . ' r', 'r.id = ur.role_id')
            ->where('ur.tenant_id', $tenant)
            ->groupBy('r.name')
            ->get()->getResultArray() as $row) {
            $counts[$row['name']] = (int) $row['n'];
        }

        $managed = [];

        foreach (service('authRoles')->all() as $role) {
            $managed[(string) $role->name] = $role->isAppManaged();
        }

        $out = [];

        foreach (RoleCatalog::all() as $name => $role) {
            $out[$name] = [
                'title'       => $role['title'],
                'description' => $role['description'],
                'system'      => $role['system'],
                'people'      => $counts[$name] ?? 0,
                'permissions' => count($this->granted($name)),
                'edited'      => $role['system'] && ($managed[$name] ?? false),
                'editable'    => $name !== $this->config->superRole,
                'synced'      => $role['system'] && array_key_exists($name, $this->config->matrix),
            ];
        }

        return $out;
    }

    /**
     * Permissions a role has now. The super role has all of them.
     *
     * @return list<string>
     */
    public function granted(string $role): array
    {
        if ($role === $this->config->superRole) {
            return $this->permissionNames();
        }

        try {
            return service('authRoles')->permissionsOf($role);
        } catch (AuthException) {
            return [];
        }
    }

    /**
     * Permissions the modules and Config\Auth give a built-in role, with
     * wildcards expanded.
     *
     * @return list<string>
     */
    public function defaults(string $role): array
    {
        $grants = $this->config->matrix[$role] ?? [];

        return array_values(array_filter(
            $this->permissionNames(),
            static fn (string $p): bool => PermissionMatcher::anyMatches($grants, $p)
        ));
    }

    /**
     * Replace what a role may do.
     *
     * @param list<string> $permissions
     *
     * @return array{added: list<string>, removed: list<string>}
     */
    public function save(string $role, array $permissions, ?int $userId): array
    {
        $this->assertEditable($role);

        $known       = array_flip($this->permissionNames());
        $permissions = array_values(array_unique(array_filter(array_map('strval', $permissions), static fn (string $p): bool => isset($known[$p]))));

        try {
            $change = service('authRoles')->setPermissions($role, $permissions);
        } catch (AuthException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        RoleCatalog::forget();

        return $change;
    }

    /**
     * Put a built-in role back on its defaults and let auth:sync manage it again.
     */
    public function reset(string $role): void
    {
        $this->assertEditable($role);

        if (! RoleCatalog::all()[$role]['system']) {
            throw new RuntimeException('Roles made here have no defaults to go back to.');
        }

        try {
            service('authRoles')->setPermissions($role, $this->config->matrix[$role] ?? []);

            $definition = $this->config->roles[$role] ?? null;

            if ($definition !== null) {
                service('authRoles')->updateRole($role, (string) $definition['title'], (string) ($definition['description'] ?? ''));
            }

            service('authRoles')->release($role);
        } catch (AuthException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        RoleCatalog::forget();
    }

    /**
     * Create a role, optionally starting from another role's permissions.
     *
     * @return string the new role's name
     */
    public function create(string $title, string $description, ?string $copyFrom, ?int $userId): string
    {
        $this->assertCanEdit();

        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 100) {
            throw new RuntimeException('Give the role a name of up to 100 characters.');
        }

        $name = $this->nameFor($title);

        if ($copyFrom !== null && $copyFrom !== '' && ! RoleCatalog::exists($copyFrom)) {
            throw new RuntimeException('The role to start from does not exist.');
        }

        $permissions = $copyFrom ? $this->granted($copyFrom) : [];

        try {
            service('authRoles')->createRole($name, $title, trim($description) ?: null, $permissions);
        } catch (AuthException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        RoleCatalog::forget();

        return $name;
    }

    public function rename(string $role, string $title, string $description): void
    {
        $this->assertCanEdit();

        $roles = RoleCatalog::all();

        if (! isset($roles[$role]) || $roles[$role]['system']) {
            throw new RuntimeException('Built-in roles keep their names. Only roles made here can be renamed.');
        }

        try {
            service('authRoles')->updateRole($role, $title, trim($description));
        } catch (AuthException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        RoleCatalog::forget();
    }

    public function delete(string $role): void
    {
        $this->assertCanEdit();

        $roles = RoleCatalog::all();

        if (! isset($roles[$role]) || $roles[$role]['system']) {
            throw new RuntimeException('Built-in roles cannot be deleted here. Remove them from Config\Auth and run "php spark auth:sync --prune".');
        }

        try {
            service('authRoles')->deleteRole($role);
        } catch (AuthException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        RoleCatalog::forget();
    }

    /**
     * Every permission in the database, sorted.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $names = array_column(
            db_connect($this->config->DBGroup)->table($this->config->table('permissions'))->select('name')->orderBy('name', 'asc')->get()->getResultArray(),
            'name'
        );

        return array_values(array_map('strval', $names));
    }

    // ------------------------------------------------------------------

    private function assertCanEdit(): void
    {
        if (! $this->canEdit()) {
            throw new RuntimeException('Roles are the same in every workspace and are changed by the platform owner.');
        }
    }

    private function assertEditable(string $role): void
    {
        $this->assertCanEdit();

        if (! RoleCatalog::exists($role)) {
            throw new RuntimeException('That role does not exist.');
        }

        if ($role === $this->config->superRole) {
            throw new RuntimeException('The super role passes every check; there is nothing to edit.');
        }
    }

    /**
     * A free role name from a title: "Front desk" => "front-desk", "front-desk-2", ...
     */
    private function nameFor(string $title): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
        $base = $base === '' ? 'role' : substr($base, 0, 50);
        $name = $base;
        $i    = 2;

        while (RoleCatalog::exists($name)) {
            $name = $base . '-' . $i++;
        }

        return $name;
    }
}
