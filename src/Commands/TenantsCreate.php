<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use CodeIgniter\I18n\Time;
use Ephraitech\Auth\Models\UserModel;
use RuntimeException;

/**
 * Creates a workspace (multi-tenant installs).
 *
 *     php spark tenants:create acme --name="Acme Ltd" --plan=standard --owner=jane@acme.co.ug --trial-days=14
 *
 * The owner must already have an account (php spark auth:user:create).
 */
class TenantsCreate extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'tenants:create';
    protected $description = 'Creates a workspace, optionally with an owner and a trial.';
    protected $usage       = 'tenants:create <slug> --name="..." [--plan=...] [--owner=email] [--domain=...] [--trial-days=N]';
    protected $arguments   = ['slug' => 'Workspace address: lowercase letters, digits and hyphens.'];
    protected $options     = [
        '--name'       => 'Workspace name (required).',
        '--plan'       => 'Plan from Config\Platform::$plans. Default: the default plan.',
        '--owner'      => 'Email of an existing account to make the owner.',
        '--domain'     => 'The workspace\'s own domain, if any.',
        '--trial-days' => 'Start as a trial ending after this many days.',
    ];

    public function run(array $params): int
    {
        $tenancy = service('tenancy');

        if (! $tenancy->isMulti()) {
            CLI::error('Workspaces exist only in multi-tenant installs: set $tenancy = \'multi\' in app/Config/Auth.php.');

            return EXIT_ERROR;
        }

        $slug = (string) ($params[0] ?? CLI::prompt('Workspace address (slug)', null, 'required'));
        $name = (string) ($this->option('name') ?: CLI::prompt('Workspace name', null, 'required'));
        $plan = $this->option('plan');

        $ownerId = null;
        $email   = $this->option('owner');

        if (is_string($email) && $email !== '') {
            $owner = model(UserModel::class, false)->findByIdentifier('email', $email);

            if ($owner === null) {
                CLI::error("No account with the email {$email}. Create it first: php spark auth:user:create --email {$email}");

                return EXIT_USER_INPUT;
            }

            $ownerId = (int) $owner->id;
        }

        $days  = (int) ($this->option('trial-days') ?: 0);
        $extra = [
            'domain'        => $this->option('domain') ?: null,
            'trial_ends_at' => $days > 0 ? Time::now()->addDays($days)->setTime(23, 59, 59)->toDateTimeString() : null,
        ];

        try {
            $tenant = $tenancy->create($slug, $name, is_string($plan) && $plan !== '' ? $plan : null, $ownerId, $extra);
        } catch (RuntimeException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        helper('platform');
        audit('Core', 'tenant.created', 'Created workspace ' . $tenant['name'] . ' (' . $tenant['slug'] . ') from the command line', 'tenant', $tenant['slug']);

        CLI::write("Workspace {$tenant['slug']} created on plan {$tenant['plan']}" . ($ownerId ? ", owned by {$email}" : '') . ($days > 0 ? ", trial until {$tenant['trial_ends_at']}" : '') . '.', 'green');
        CLI::write('Address: ' . $tenancy->urlFor($tenant));

        return EXIT_SUCCESS;
    }
}
