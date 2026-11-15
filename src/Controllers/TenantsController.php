<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Auth\Exceptions\RegistrationException;
use Ephraitech\Auth\Models\UserModel;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Forms\TenantForm;
use Ephraitech\Platform\Models\ModuleStateModel;
use Ephraitech\Platform\Models\TenantModel;
use RuntimeException;

/**
 * Multi-tenant, central only: the platform owner's workspace
 * administration. Create workspaces, change their plan and status, and
 * switch single modules on or off for one workspace (add-ons, trials).
 */
class TenantsController extends BaseModuleController
{
    public function index(): string
    {
        $this->centralOnly();

        $status = (string) $this->request->getGet('status');
        $model  = model(TenantModel::class, false);

        if (isset(TenantModel::STATUSES[$status])) {
            $model->where('status', $status);
        }

        $q = trim((string) $this->request->getGet('q'));

        if ($q !== '') {
            $model->groupStart()->like('name', $q)->orLike('slug', $q)->orLike('domain', $q)->groupEnd();
        }

        $tenants = $model->orderBy('name', 'asc')->paginate(30);

        return $this->page('Ephraitech\Platform\Views\tenants\index', 'Workspaces', [
            'tenants'  => $tenants,
            'pager'    => $model->pager,
            'plans'    => PlatformConfig::get()->plans,
            'status'   => $status,
            'q'        => $q,
            'statuses' => TenantModel::STATUSES,
        ], [['Workspaces']]);
    }

    public function new(): string
    {
        $this->centralOnly();

        return $this->formPage($this->form(true)->fill(['plan' => PlatformConfig::get()->defaultPlan, 'status' => 'active']), null);
    }

    public function create(): string|RedirectResponse
    {
        $this->centralOnly();

        $form = $this->form(true);

        if (! $form->validate()) {
            return $this->formPage($form, null);
        }

        $data    = $form->validated();
        $tenancy = service('tenancy');

        try {
            $tenant = $tenancy->create((string) $data['slug'], (string) $data['name'], (string) $data['plan'], null, [
                'status'        => (string) $data['status'],
                'domain'        => $data['domain'] ?? null,
                'trial_ends_at' => $this->endOfDay($data['trial_ends_at'] ?? null),
            ]);
        } catch (RuntimeException $e) {
            return $this->formPage($form, null, $e->getMessage());
        }

        $message = 'Workspace created.';
        $email   = trim((string) ($data['owner_email'] ?? ''));

        if ($email !== '') {
            try {
                $message .= ' ' . $this->attachOwner($tenant, $email, trim((string) ($data['owner_name'] ?? '')));
            } catch (RuntimeException|AuthException|RegistrationException $e) {
                $message .= ' The owner could not be added: ' . $e->getMessage() . ' Add them from inside the workspace.';
            }
        }

        audit('Core', 'tenant.created', 'Created workspace ' . $tenant['name'] . ' (' . $tenant['slug'] . ') on plan ' . $tenant['plan'], 'tenant', $tenant['slug']);

        return redirect()->route('core.tenants.show', [$tenant['slug']])->with('message', $message);
    }

    public function show(string $slug): string
    {
        $this->centralOnly();

        $tenant   = $this->tenantOr404($slug);
        $registry = service('moduleRegistry');
        $gate     = service('moduleGate');
        $states   = model(ModuleStateModel::class, false)->allForTenant($slug);
        $decided  = $gate->decisions($slug);
        $modules  = [];

        foreach ($registry->all() as $name => $module) {
            $modules[$name] = $module + [
                'on'     => $decided[$name]['on'] ?? false,
                'reason' => $decided[$name]['source'] ?? '',
                'switch' => $states[$name] ?? null,
            ];
        }

        $form = $this->form(false)->fill([
            'name'          => $tenant['name'],
            'plan'          => $tenant['plan'],
            'status'        => $tenant['status'],
            'domain'        => $tenant['domain'],
            'trial_ends_at' => $tenant['trial_ends_at'] ? substr((string) $tenant['trial_ends_at'], 0, 10) : null,
        ])->action(url_to('core.tenants.update', $slug));

        return $this->page('Ephraitech\Platform\Views\tenants\show', $tenant['name'], [
            'tenant'  => $tenant,
            'form'    => $form,
            'modules' => $modules,
            'plan'    => PlatformConfig::get()->plans[$tenant['plan']] ?? null,
            'members' => $this->members($slug),
            'url'     => service('tenancy')->urlFor($tenant),
            'usable'  => TenantModel::isUsable($tenant),
        ], [['Workspaces', url_to('core.tenants')], [$tenant['name']]]);
    }

    public function update(string $slug): string|RedirectResponse
    {
        $this->centralOnly();

        $tenant = $this->tenantOr404($slug);
        $form   = $this->form(false);

        if (! $form->validate()) {
            return redirect()->route('core.tenants.show', [$slug])->withInput()->with('errors', $form->getErrors());
        }

        $data = $form->validated();

        try {
            $diff = service('tenancy')->update($slug, [
                'name'          => (string) $data['name'],
                'plan'          => (string) $data['plan'],
                'status'        => (string) $data['status'],
                'domain'        => $data['domain'] ?? null,
                'trial_ends_at' => $this->endOfDay($data['trial_ends_at'] ?? null),
            ]);
        } catch (RuntimeException $e) {
            return redirect()->route('core.tenants.show', [$slug])->with('error', $e->getMessage());
        }

        if ($diff !== []) {
            audit('Core', 'tenant.updated', 'Changed workspace ' . $tenant['name'], 'tenant', $slug, $diff);
        }

        return redirect()->route('core.tenants.show', [$slug])->with('message', $diff === [] ? 'No changes to save.' : 'Workspace saved.');
    }

    /**
     * Switch one module on or off for this workspace, or back to its plan.
     */
    public function modules(string $slug): RedirectResponse
    {
        $this->centralOnly();

        $tenant = $this->tenantOr404($slug);
        $module = (string) $this->request->getPost('module');
        $state  = (string) $this->request->getPost('state');
        $until  = trim((string) $this->request->getPost('until'));
        $note   = trim((string) $this->request->getPost('note'));

        if (! service('moduleRegistry')->has($module)) {
            return redirect()->route('core.tenants.show', [$slug])->with('error', 'Unknown module.');
        }

        $enabled = match ($state) {
            'on'    => true,
            'off'   => false,
            default => null,
        };

        if ($until !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) !== 1) {
            return redirect()->route('core.tenants.show', [$slug])->with('error', 'Give the end date as a date.');
        }

        try {
            $also = service('moduleGate')->setState($module, $enabled, $slug, user_id(), $this->endOfDay($until ?: null), $note ?: null);
        } catch (RuntimeException $e) {
            return redirect()->to(url_to('core.tenants.show', $slug) . '#modules')->with('error', $e->getMessage());
        }

        $label = $enabled === null ? 'back to the plan' : ($enabled ? 'on' : 'off');

        audit('Core', 'tenant.module', 'Switched ' . $module . ' ' . $label . ' for ' . $tenant['name'] . ($until !== '' ? ' until ' . $until : ''), 'tenant', $slug, [
            $module => ['from' => null, 'to' => $label],
        ]);

        $message = $module . ' is ' . (service('moduleGate')->enabled($module, $slug) ? 'on' : 'off') . ' for ' . $tenant['name'] . '.';

        if ($also !== []) {
            $message .= ' This also changed: ' . implode(', ', $also) . '.';
        }

        return redirect()->to(url_to('core.tenants.show', $slug) . '#modules')->with('message', $message);
    }

    // ------------------------------------------------------------------

    private function centralOnly(): void
    {
        if (service('tenancy')->has()) {
            throw PageNotFoundException::forPageNotFound('Workspaces are managed with no workspace selected.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantOr404(string $slug): array
    {
        return service('tenancy')->find($slug) ?? throw PageNotFoundException::forPageNotFound('No workspace "' . esc($slug) . '".');
    }

    private function form(bool $isNew): TenantForm
    {
        $config = PlatformConfig::get();

        return new TenantForm(array_map(static fn (array $p): string => $p['title'], $config->plans), $isNew, $config->baseDomain);
    }

    private function formPage(TenantForm $form, ?array $tenant, ?string $error = null): string
    {
        $form->action(url_to('core.tenants.create'));

        return $this->page('Ephraitech\Platform\Views\tenants\form', 'New workspace', [
            'form'  => $form,
            'error' => $error,
        ], [['Workspaces', url_to('core.tenants')], ['New']]);
    }

    /**
     * Make an existing account the owner, or create one and email a link.
     *
     * @param array<string, mixed> $tenant
     */
    private function attachOwner(array $tenant, string $email, string $name): string
    {
        $config = PlatformConfig::get();
        $user   = model(UserModel::class, false)->findByIdentifier('email', $email);
        $note   = '';

        if ($user === null) {
            [$first, $last] = array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, null);

            $user = service('authRegistrar')->register(['email' => $email], null, array_filter([
                'first_name' => $first ?: null,
                'last_name'  => $last ?: null,
            ]), [
                'roles'            => [],
                'verified'         => ['email'],
                'status'           => 'active',
                // They choose a password from the emailed link.
                'passwordOptional' => true,
            ]);

            service('authPasswordReset')->request(
                $email,
                $this->request->getIPAddress(),
                static fn (string $selector, string $code): string => url_to('auth.reset') . '?' . http_build_query(['s' => $selector, 'c' => $code])
            );

            $note = ' A link to choose a password was emailed to ' . $email . '.';
        }

        service('authAuthorizer')->assignRole($user, $config->tenantOwnerRole, (string) $tenant['slug']);
        model(TenantModel::class, false)->update($tenant['id'], ['owner_user_id' => $user->id]);

        return display_name($user) . ' is its owner.' . $note;
    }

    /**
     * People with a role in this workspace (not counting global roles).
     *
     * @return list<array{id: int, name: string, email: ?string, roles: list<string>}>
     */
    private function members(string $slug): array
    {
        $auth  = PlatformConfig::auth();
        $db    = db_connect($auth->DBGroup);
        $rows  = $db->table($auth->table('user_roles') . ' ur')
            ->select('ur.user_id, r.name')
            ->join($auth->table('roles') . ' r', 'r.id = ur.role_id')
            ->where('ur.tenant_id', $slug)
            ->get()->getResultArray();

        $roles = [];

        foreach ($rows as $row) {
            $roles[(int) $row['user_id']][] = (string) $row['name'];
        }

        if ($roles === []) {
            return [];
        }

        $out = [];

        foreach (model(UserModel::class, false)->whereIn('id', array_keys($roles))->findAll() as $user) {
            $out[] = ['id' => (int) $user->id, 'name' => display_name($user), 'email' => $user->getEmail(), 'roles' => $roles[(int) $user->id]];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    private function endOfDay(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        return Time::parse(substr(trim($date), 0, 10) . ' 23:59:59')->toDateTimeString();
    }
}
