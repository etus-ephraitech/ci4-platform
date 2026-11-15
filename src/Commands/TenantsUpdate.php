<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use RuntimeException;

/**
 *     php spark tenants:update acme --plan=pro --status=active
 */
class TenantsUpdate extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'tenants:update';
    protected $description = 'Changes a workspace\'s name, plan, status or domain.';
    protected $usage       = 'tenants:update <slug> [--name=...] [--plan=...] [--status=...] [--domain=...|--domain=none]';
    protected $options     = [
        '--name'   => 'New name.',
        '--plan'   => 'New plan.',
        '--status' => 'active, trial, suspended or cancelled.',
        '--domain' => 'Own domain; "none" removes it.',
    ];

    public function run(array $params): int
    {
        $slug = (string) ($params[0] ?? '');

        if ($slug === '' || service('tenancy')->find($slug) === null) {
            CLI::error($slug === '' ? 'Name the workspace.' : "No tenant '{$slug}'.");

            return EXIT_USER_INPUT;
        }

        $changes = [];

        foreach (['name', 'plan', 'status', 'domain'] as $field) {
            $value = $this->option($field);

            if (is_string($value) && $value !== '') {
                $changes[$field] = $field === 'domain' && $value === 'none' ? null : $value;
            }
        }

        if ($changes === []) {
            CLI::error('Nothing to change. Give --name, --plan, --status or --domain.');

            return EXIT_USER_INPUT;
        }

        try {
            $diff = service('tenancy')->update($slug, $changes);
        } catch (RuntimeException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        if ($diff === []) {
            CLI::write('No changes.');

            return EXIT_SUCCESS;
        }

        helper('platform');
        audit('Core', 'tenant.updated', 'Changed workspace ' . $slug . ' from the command line', 'tenant', $slug, $diff);

        foreach ($diff as $field => $change) {
            CLI::write("{$field}: " . ($change['from'] ?? '—') . ' → ' . ($change['to'] ?? '—'));
        }

        return EXIT_SUCCESS;
    }
}
