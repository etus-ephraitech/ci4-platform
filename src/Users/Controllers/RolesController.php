<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Controllers\BaseModuleController;
use Ephraitech\Platform\Users\Libraries\PermissionCatalog;
use Ephraitech\Platform\Users\Libraries\RoleCatalog;
use Ephraitech\Platform\Users\Libraries\RoleEditor;
use RuntimeException;

/**
 * Roles and what each may do, edited in the app.
 *
 * Built-in roles start from the grants in each module's module.php; once
 * a role is edited here, `php spark auth:sync` leaves it as edited until
 * it is reset to defaults. New roles can be created here too.
 */
class RolesController extends BaseModuleController
{
    public function index(): string
    {
        $editor  = new RoleEditor();
        $catalog = new PermissionCatalog();
        $roles   = $editor->overview();
        $current = [];

        foreach (array_keys($roles) as $role) {
            $current[$role] = $editor->granted($role);
        }

        return $this->page('Ephraitech\Platform\Users\Views\roles\index', 'Roles and permissions', [
            'roles'       => $roles,
            'current'     => $current,
            'superRole'   => PlatformConfig::auth()->superRole,
            'grouped'     => $catalog->grouped(),
            'canEdit'     => $editor->canEdit(),
            'inWorkspace' => service('tenancy')->isMulti() && service('tenancy')->has(),
        ], [['Roles']]);
    }

    public function edit(string $role): string
    {
        $editor = new RoleEditor();
        $roles  = $editor->overview();

        if (! $editor->canEdit() || ! isset($roles[$role]) || ! $roles[$role]['editable']) {
            throw PageNotFoundException::forPageNotFound('That role does not exist or cannot be edited.');
        }

        return $this->page('Ephraitech\Platform\Users\Views\roles\edit', 'Role: ' . $roles[$role]['title'], [
            'key'      => $role,
            'role'     => $roles[$role],
            'granted'  => $editor->granted($role),
            'defaults' => $editor->defaults($role),
            'grouped'  => (new PermissionCatalog())->grouped(),
            'known'    => $editor->permissionNames(),
        ], [['Roles', url_to('users.roles')], [$roles[$role]['title']]]);
    }

    public function save(string $role): RedirectResponse
    {
        $title = RoleCatalog::all()[$role]['title'] ?? $role;

        try {
            $change = (new RoleEditor())->save($role, array_values((array) ($this->request->getPost('permissions') ?? [])), user_id());
        } catch (RuntimeException $e) {
            return redirect()->route('users.roles.edit', [$role])->with('error', $e->getMessage());
        }

        if ($change['added'] === [] && $change['removed'] === []) {
            return redirect()->route('users.roles.edit', [$role])->with('message', 'No changes to save.');
        }

        audit('Users', 'role.permissions', 'Changed what ' . $title . ' can do', 'role', null, [
            'added'   => ['from' => '', 'to' => implode(', ', $change['added'])],
            'removed' => ['from' => implode(', ', $change['removed']), 'to' => ''],
        ]);

        return redirect()->route('users.roles.edit', [$role])->with('message', $title . ' saved: ' . count($change['added']) . ' added, ' . count($change['removed']) . ' removed. It applies from each person’s next page.');
    }

    public function reset(string $role): RedirectResponse
    {
        $title = RoleCatalog::all()[$role]['title'] ?? $role;

        try {
            (new RoleEditor())->reset($role);
        } catch (RuntimeException $e) {
            return redirect()->route('users.roles.edit', [$role])->with('error', $e->getMessage());
        }

        audit('Users', 'role.reset', 'Reset ' . $title . ' to the modules’ defaults', 'role', null);

        return redirect()->route('users.roles.edit', [$role])->with('message', $title . ' is back on its defaults and follows module updates again.');
    }

    public function create(): RedirectResponse
    {
        try {
            $name = (new RoleEditor())->create(
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('description'),
                (string) $this->request->getPost('copy_from') ?: null,
                user_id()
            );
        } catch (RuntimeException $e) {
            return redirect()->route('users.roles')->withInput()->with('error', $e->getMessage());
        }

        audit('Users', 'role.created', 'Created role ' . (string) $this->request->getPost('title'), 'role', null);

        return redirect()->route('users.roles.edit', [$name])->with('message', 'Role created. Tick what it may do, then give it to people on their page under Staff.');
    }

    public function rename(string $role): RedirectResponse
    {
        try {
            (new RoleEditor())->rename($role, (string) $this->request->getPost('title'), (string) $this->request->getPost('description'));
        } catch (RuntimeException $e) {
            return redirect()->route('users.roles.edit', [$role])->with('error', $e->getMessage());
        }

        audit('Users', 'role.renamed', 'Renamed role ' . $role . ' to ' . (string) $this->request->getPost('title'), 'role', null);

        return redirect()->route('users.roles.edit', [$role])->with('message', 'Role renamed.');
    }

    public function delete(string $role): RedirectResponse
    {
        $title = RoleCatalog::all()[$role]['title'] ?? $role;

        try {
            (new RoleEditor())->delete($role);
        } catch (RuntimeException $e) {
            return redirect()->route('users.roles.edit', [$role])->with('error', $e->getMessage());
        }

        audit('Users', 'role.deleted', 'Deleted role ' . $title, 'role', null);

        return redirect()->route('users.roles')->with('message', 'Role ' . $title . ' deleted.');
    }
}
