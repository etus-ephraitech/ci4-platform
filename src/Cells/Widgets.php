<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Cells;

use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Models\AuditLogModel;

/**
 * Core dashboard widgets, rendered with view_cell() by the dashboard.
 */
class Widgets
{
    /**
     * Steps still needed before the install (or workspace) is ready for daily use.
     */
    public function setup(): string
    {
        helper(['platform', 'url']);

        $settings = service('platformSettings');
        $registry = service('moduleRegistry');
        $tenancy  = service('tenancy');
        $auth     = PlatformConfig::auth();
        $db       = db_connect($auth->DBGroup);

        if ($tenancy->isMulti() && $tenancy->has()) {
            $people = $db->table($auth->table('user_roles'))->select('user_id')->distinct()->where('tenant_id', $tenancy->key())->countAllResults();
        } else {
            $people = $db->table($auth->table('users'))->where('deleted_at', null)->countAllResults();
        }

        $companyEditable = isset($settings->editableGroups()['company']);

        $steps = [
            [
                'done'  => ! $companyEditable || $settings->emptyCount('company') === 0,
                'label' => 'Complete the company profile',
                'hint'  => 'Shown on documents, emails and the sign-in page.',
                'url'   => $companyEditable ? url_to('core.settings.group', 'company') : null,
            ],
            [
                'done'  => $people > 1,
                'label' => 'Add the team',
                'hint'  => 'Create an account for each person and give them a role.',
                'url'   => can('users.create') ? url_to('users.staff.new') : null,
            ],
            [
                'done'  => $registry->unmetRequirements() === [],
                'label' => 'Resolve module requirements',
                'hint'  => implode('; ', $registry->unmetRequirements()) ?: 'All installed modules have what they need.',
                'url'   => can('modules.view') ? url_to('core.modules') : null,
            ],
        ];

        return view('Ephraitech\Platform\Views\widgets\setup', [
            'steps'     => $steps,
            'completed' => count(array_filter($steps, static fn (array $step): bool => $step['done'])),
        ]);
    }

    /**
     * The latest entries in the audit log.
     */
    public function activity(): string
    {
        helper(['platform', 'url']);

        $rows = model(AuditLogModel::class, false)->filtered([])->findAll(8);

        return view('Ephraitech\Platform\Views\widgets\activity', ['rows' => $rows]);
    }

    /**
     * Installed modules and whether each is on here, plus modules not installed.
     */
    public function modules(): string
    {
        helper(['platform', 'url']);

        $registry  = service('moduleRegistry');
        $decisions = service('moduleGate')->decisions();

        return view('Ephraitech\Platform\Views\widgets\modules', [
            'modules'      => $registry->all(),
            'decisions'    => $decisions,
            'notInstalled' => $registry->notInstalled(),
        ]);
    }
}
