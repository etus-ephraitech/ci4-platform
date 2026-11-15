<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use Ephraitech\Platform\Models\TenantModel;

class TenantsList extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'tenants:list';
    protected $description = 'Lists workspaces with their plan and status.';
    protected $usage       = 'tenants:list [--status=active|trial|suspended|cancelled]';
    protected $options     = ['--status' => 'Only workspaces with this status.'];

    public function run(array $params): int
    {
        if (! service('tenancy')->isMulti()) {
            CLI::write('Single install: there are no workspaces.', 'yellow');

            return EXIT_SUCCESS;
        }

        $model  = model(TenantModel::class, false);
        $status = $this->option('status');

        if (is_string($status) && isset(TenantModel::STATUSES[$status])) {
            $model->where('status', $status);
        }

        $rows = [];

        foreach ($model->orderBy('slug', 'asc')->findAll() as $t) {
            $rows[] = [
                $t['slug'],
                $t['name'],
                $t['plan'],
                TenantModel::isUsable($t) ? CLI::color($t['status'], 'green') : CLI::color($t['status'], 'yellow'),
                (string) ($t['trial_ends_at'] ?? ''),
                (string) ($t['domain'] ?? ''),
            ];
        }

        if ($rows === []) {
            CLI::write('No workspaces yet. Create one: php spark tenants:create <slug> --name="..."');

            return EXIT_SUCCESS;
        }

        CLI::table($rows, ['Slug', 'Name', 'Plan', 'Status', 'Trial ends', 'Domain']);

        return EXIT_SUCCESS;
    }
}
