<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;

/**
 * Lists installed modules and whether each is on, and why.
 *
 *     php spark modules:list                 # single install, or central
 *     php spark modules:list --tenant=acme   # one workspace
 */
class ModulesList extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'modules:list';
    protected $description = 'Lists modules: installed or not, on or off (per tenant), and what they need.';
    protected $usage       = 'modules:list [--tenant=<slug>]';
    protected $options     = ['--tenant' => 'Multi-tenant: show the state for this workspace.'];

    public function run(array $params): int
    {
        $registry = service('moduleRegistry');
        $tenancy  = service('tenancy');
        $tenant   = $this->option('tenant');

        if (is_string($tenant) && $tenant !== '') {
            if (! $tenancy->isMulti()) {
                CLI::error('--tenant needs a multi-tenant install (Config\Auth::$tenancy = "multi").');

                return EXIT_USER_INPUT;
            }

            if ($tenancy->find($tenant) === null) {
                CLI::error("No tenant '{$tenant}'.");

                return EXIT_USER_INPUT;
            }
        } else {
            $tenant = null;
        }

        $decisions = service('moduleGate')->decisions($tenant);
        $rows      = [];

        foreach ($registry->all() as $name => $module) {
            $on     = $decisions[$name]['on'] ?? false;
            $rows[] = [
                $name,
                $module['version'],
                $on ? CLI::color('on', 'green') : CLI::color('off', 'yellow'),
                $decisions[$name]['source'] ?? '',
                $module['requires'] === [] ? '' : implode(', ', $module['requires']),
                $module['source'],
            ];
        }

        foreach ($registry->notInstalled() as $name => $reason) {
            $rows[] = [$name, '', CLI::color('not installed', 'red'), $reason, '', ''];
        }

        CLI::write($tenancy->isMulti() ? ($tenant === null ? 'Multi-tenant, no workspace (central):' : "Workspace {$tenant}:") : 'Single install:', 'light_gray');
        CLI::table($rows, ['Module', 'Version', 'State', 'Why', 'Needs', 'Source']);

        foreach ($registry->unmetRequirements() as $problem) {
            CLI::error('Missing: ' . $problem);
        }

        return EXIT_SUCCESS;
    }
}
