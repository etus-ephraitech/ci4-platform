<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use Throwable;

/**
 * Runs every migration in the right order:
 *
 *   1. ephraitech/ci4-auth (users, roles, tokens),
 *   2. installed modules in dependency order (Core first, then each module
 *      after the modules it requires),
 *   3. everything else (the app, other packages).
 *
 * Modules that are not installed are skipped. Switching a module off for
 * a tenant does not affect migrations: every tenant shares the tables.
 *
 *     php spark platform:migrate
 */
class PlatformMigrate extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'platform:migrate';
    protected $description = 'Runs auth, module (in dependency order) and app migrations.';
    protected $usage       = 'platform:migrate [-g <group>]';
    protected $options     = ['-g' => 'Database group to migrate. Default: the default group.'];

    public function run(array $params): int
    {
        $runner   = service('migrations');
        $registry = service('moduleRegistry');
        $group    = $this->option('g');

        if (is_string($group) && $group !== '') {
            $runner->setGroup($group);
        }

        $namespaces = ['Ephraitech\Auth'];

        foreach ($registry->dependencyOrder() as $name) {
            $namespaces[] = $registry->get($name)['namespace'];
        }

        try {
            foreach ($namespaces as $namespace) {
                CLI::write('Migrating ' . CLI::color($namespace, 'green'));
                $runner->setNamespace($namespace);

                if (! $runner->latest()) {
                    CLI::error("Migrations of {$namespace} failed.");

                    return EXIT_ERROR;
                }
            }

            CLI::write('Migrating ' . CLI::color('everything else', 'green'));
            $runner->setNamespace(null);

            if (! $runner->latest()) {
                CLI::error('Remaining migrations failed.');

                return EXIT_ERROR;
            }
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        foreach ($runner->getCliMessages() as $message) {
            CLI::write($message);
        }

        CLI::write('Done. Next: php spark auth:sync (permissions and roles).', 'green');

        return EXIT_SUCCESS;
    }
}
