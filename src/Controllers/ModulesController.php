<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * What is installed and what is switched on here.
 *
 *   - Single install: people with modules.manage switch optional modules
 *     on and off for the whole install.
 *   - Multi-tenant, inside a workspace: read-only (the plan decides; the
 *     platform owner changes it under Workspaces).
 *   - Multi-tenant, central: the catalogue, with what each module needs.
 */
class ModulesController extends BaseModuleController
{
    public function index(): string
    {
        $registry  = service('moduleRegistry');
        $tenancy   = service('tenancy');
        $decisions = service('moduleGate')->decisions();
        $modules   = [];

        foreach ($registry->all() as $name => $module) {
            $modules[$name] = $module + [
                'on'         => $decisions[$name]['on'] ?? false,
                'reason'     => $decisions[$name]['source'] ?? '',
                'dependents' => $registry->dependentsOf($name),
            ];
        }

        return $this->page('Ephraitech\Platform\Views\modules\index', 'Modules', [
            'modules'      => $modules,
            'notInstalled' => $registry->notInstalled(),
            'unmet'        => $registry->unmetRequirements(),
            'canSwitch'    => ! $tenancy->isMulti() && can('modules.manage'),
            'multi'        => $tenancy->isMulti(),
            'tenant'       => $tenancy->current(),
        ], [['Modules']]);
    }

    public function update(string $module): RedirectResponse
    {
        if (service('tenancy')->isMulti()) {
            throw PageNotFoundException::forPageNotFound('Modules are switched per workspace under Workspaces.');
        }

        $registry = service('moduleRegistry');

        if (! $registry->has($module)) {
            throw PageNotFoundException::forPageNotFound('No module named "' . esc($module) . '".');
        }

        $state   = (string) $this->request->getPost('state');
        $enabled = match ($state) {
            'on'    => true,
            'off'   => false,
            default => null,
        };

        try {
            $also = service('moduleGate')->setState($module, $enabled, null, user_id());
        } catch (RuntimeException $e) {
            return redirect()->route('core.modules')->with('error', $e->getMessage());
        }

        $title = $registry->get($module)['title'];
        $on    = service('moduleGate')->enabled($module);

        audit('Core', 'module.' . ($enabled === null ? 'default' : ($enabled ? 'on' : 'off')), ($on ? 'Switched on ' : 'Switched off ') . $title, 'module', $module, [
            'enabled' => ['from' => null, 'to' => $enabled === null ? 'default' : ($enabled ? 'on' : 'off')],
        ]);

        $message = $title . ($on ? ' is on.' : ' is off.');

        if ($also !== []) {
            $message .= ' This also changed: ' . implode(', ', $also) . '.';
        }

        return redirect()->route('core.modules')->with('message', $message);
    }
}
