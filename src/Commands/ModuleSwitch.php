<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * Shared by modules:on, modules:off and modules:reset.
 */
abstract class ModuleSwitch extends BaseCommand
{
    use ReadsOptions;

    protected $group   = 'Platform';
    protected $options = [
        '--tenant' => 'Multi-tenant: the workspace (required there).',
        '--until'  => 'Only until this date (YYYY-MM-DD), e.g. for a trial.',
        '--note'   => 'Why, kept with the switch.',
    ];

    /**
     * true = on, false = off, null = back to the default / plan.
     */
    abstract protected function state(): ?bool;

    public function run(array $params): int
    {
        $module = (string) ($params[0] ?? '');

        if ($module === '' || ! service('moduleRegistry')->has($module)) {
            CLI::error($module === '' ? 'Name the module, e.g. ' . $this->name . ' Crm.' : "No installed module '{$module}'. See php spark modules:list.");

            return EXIT_USER_INPUT;
        }

        $tenant = $this->option('tenant');
        $tenant = is_string($tenant) && $tenant !== '' ? $tenant : null;

        if ($tenant !== null && service('tenancy')->find($tenant) === null) {
            CLI::error("No tenant '{$tenant}'.");

            return EXIT_USER_INPUT;
        }

        $until = $this->option('until');
        $until = is_string($until) && $until !== '' ? $until : null;

        if ($until !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) !== 1) {
            CLI::error('--until takes a date as YYYY-MM-DD.');

            return EXIT_USER_INPUT;
        }

        $note = $this->option('note');

        try {
            $also = service('moduleGate')->setState(
                $module,
                $this->state(),
                $tenant,
                null,
                $until === null ? null : Time::parse($until . ' 23:59:59')->toDateTimeString(),
                is_string($note) ? $note : null,
            );
        } catch (RuntimeException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        $on = service('moduleGate')->enabled($module, $tenant);

        helper('platform');
        audit('Core', 'module.cli', $module . ' switched ' . ($this->state() === null ? 'back to default' : ($this->state() ? 'on' : 'off')) . ' from the command line' . ($tenant ? ' for ' . $tenant : ''), 'module', $module);

        CLI::write($module . ' is now ' . ($on ? CLI::color('on', 'green') : CLI::color('off', 'yellow')) . ($tenant ? " for {$tenant}" : '') . ($until ? " until {$until}" : '') . '.');

        if ($also !== []) {
            CLI::write('Also changed: ' . implode(', ', $also) . '.', 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
