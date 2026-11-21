<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

use CodeIgniter\Events\Events;
use Ephraitech\Auth\Authorization\TenantContext;
use Ephraitech\Platform\Config\Platform;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Models\TenantModel;
use RuntimeException;
use Throwable;

/**
 * The tenant of the current request, and tenant management.
 *
 *     service('tenancy')->isMulti();     // Config\Auth::$tenancy === 'multi'
 *     service('tenancy')->current();     // tenant row, or null (single install, or no tenant picked)
 *     service('tenancy')->key();         // its slug, or '' — the value of every tenant_id column
 *
 * In a single install there is never a tenant and key() is always ''.
 * In a multi-tenant install a request with no tenant is "central": the
 * platform owner's own screens (tenants, plans, global settings).
 *
 * Entering a tenant also sets ci4-auth's TenantContext, so can() and the
 * role checks use that tenant's assignments.
 */
final class Tenancy
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $current = null;

    /**
     * @var array<string, array<string, mixed>|false>
     */
    private array $bySlug = [];

    public function __construct(
        private readonly TenantContext $authTenant,
        private readonly ?Platform $config = null,
    ) {
    }

    public function isMulti(): bool
    {
        return PlatformConfig::isMultiTenant();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        return $this->current;
    }

    public function key(): string
    {
        return $this->isMulti() && $this->current !== null ? (string) $this->current['slug'] : '';
    }

    public function has(): bool
    {
        return $this->current !== null;
    }

    /**
     * Make a tenant the current one for the rest of the request.
     *
     * @param array<string, mixed> $tenant
     */
    public function enter(array $tenant): void
    {
        if (! $this->isMulti()) {
            throw new RuntimeException('Tenants exist only when Config\Auth::$tenancy is "multi".');
        }

        $this->current                       = $tenant;
        $this->bySlug[(string) $tenant['slug']] = $tenant;
        $this->authTenant->set((string) $tenant['slug']);

        service('moduleGate')->forgetRequestCache();

        Events::trigger('platform.tenant.entered', $tenant);
    }

    /**
     * Back to no tenant (central).
     */
    public function leave(): void
    {
        $this->current = null;
        $this->authTenant->clear();
        service('moduleGate')->forgetRequestCache();
    }

    /**
     * Run a callback inside a tenant, then return to the previous one.
     * For commands, queues and cron jobs that work across tenants.
     *
     * @template T
     *
     * @param array<string, mixed>|string $tenant row or slug
     * @param callable(): T               $callback
     *
     * @return T
     */
    public function runAs(array|string $tenant, callable $callback): mixed
    {
        $row = is_string($tenant) ? $this->find($tenant) : $tenant;

        if ($row === null) {
            throw new RuntimeException("No tenant '" . (is_string($tenant) ? $tenant : '?') . "'.");
        }

        $previous = $this->current;
        $this->enter($row);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->leave() : $this->enter($previous);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        $slug = strtolower(trim($slug));

        if ($slug === '' || ! $this->isMulti()) {
            return null;
        }

        if (! array_key_exists($slug, $this->bySlug)) {
            try {
                $this->bySlug[$slug] = model(TenantModel::class, false)->findBySlug($slug) ?? false;
            } catch (Throwable $e) {
                log_message('error', 'Platform: could not read tenant {slug}: {message}', ['slug' => $slug, 'message' => $e->getMessage()]);

                return null;
            }
        }

        return $this->bySlug[$slug] === false ? null : $this->bySlug[$slug];
    }

    /**
     * Whether a person has any role or permission in a tenant, global
     * assignments included (so the platform owner belongs everywhere).
     */
    public function belongs(int $userId, string $slug): bool
    {
        $authz = service('authAuthorizer');

        return $authz->rolesFor($userId, $slug) !== [] || $authz->permissionsFor($userId, $slug) !== [];
    }

    /**
     * Tenants a person can work in, by name. Holders of a global role
     * (e.g. the platform owner) can open every usable tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function tenantsFor(int $userId): array
    {
        if (! $this->isMulti()) {
            return [];
        }

        $model  = model(TenantModel::class, false);
        $global = service('authAuthorizer')->rolesFor($userId, '') !== [];

        if ($global) {
            return $model->orderBy('name', 'asc')->findAll();
        }

        $auth  = PlatformConfig::auth();
        $db    = db_connect($auth->DBGroup);
        $slugs = array_unique([
            ...array_column($db->table($auth->table('user_roles'))->select('tenant_id')->where('user_id', $userId)->where('tenant_id !=', '')->get()->getResultArray(), 'tenant_id'),
            ...array_column($db->table($auth->table('user_permissions'))->select('tenant_id')->where('user_id', $userId)->where('tenant_id !=', '')->get()->getResultArray(), 'tenant_id'),
        ]);

        if ($slugs === []) {
            return [];
        }

        return $model->whereIn('slug', array_values($slugs))->orderBy('name', 'asc')->findAll();
    }

    /**
     * Address of a tenant: its own domain, its subdomain, or (one shared
     * domain) the workspace picker, where entering is a POST.
     *
     * @param array<string, mixed> $tenant
     */
    public function urlFor(array $tenant, string $path = ''): string
    {
        $config = $this->config();
        $path   = ltrim($path, '/');
        $scheme = parse_url((string) config('App')->baseURL, PHP_URL_SCHEME) ?: 'https';

        if (in_array('domain', $config->tenantSources, true)) {
            if ($config->customDomains && ! empty($tenant['domain'])) {
                return $scheme . '://' . $tenant['domain'] . '/' . $path;
            }

            if ($config->baseDomain !== '') {
                // Keep a port in app.baseURL (e.g. php spark serve on
                // :8080 in development), so links stay on the same server.
                $port = parse_url((string) config('App')->baseURL, PHP_URL_PORT);

                return $scheme . '://' . $tenant['slug'] . '.' . $config->baseDomain . (is_int($port) ? ':' . $port : '') . '/' . $path;
            }
        }

        // One domain for everyone: the workspace is picked on /workspaces.
        return url_to('core.workspaces');
    }

    /**
     * A named route on the platform's own host (app.baseURL), whatever host
     * the request came in on. Pages that only work outside a workspace
     * (the picker, a support desk) are linked and redirected to with it,
     * so that from acme.app.example.com they do not land back on the
     * workspace's subdomain.
     */
    public function centralUrl(string $route, int|string ...$params): string
    {
        $url  = (string) url_to($route, ...$params);
        $base = parse_url((string) config('App')->baseURL);

        if (! is_array($base) || empty($base['host'])) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return ($base['scheme'] ?? 'https') . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '')
            . (string) parse_url($url, PHP_URL_PATH) . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    /**
     * Create a tenant and, optionally, make someone its owner.
     *
     * @param array<string, mixed> $extra domain, trial_ends_at, meta
     *
     * @return array<string, mixed> the new tenant
     */
    public function create(string $slug, string $name, ?string $plan = null, ?int $ownerUserId = null, array $extra = []): array
    {
        if (! $this->isMulti()) {
            throw new RuntimeException('Tenants exist only when Config\Auth::$tenancy is "multi".');
        }

        $config = $this->config();
        $slug   = strtolower(trim($slug));
        $name   = trim($name);
        $plan ??= $config->defaultPlan;

        if (! TenantModel::validSlug($slug)) {
            throw new RuntimeException("'{$slug}' cannot be used as a workspace address: use 1 to 63 lowercase letters, digits and hyphens, not starting or ending with a hyphen, and not a reserved word.");
        }

        if ($name === '' || mb_strlen($name) > 150) {
            throw new RuntimeException('Give the workspace a name of up to 150 characters.');
        }

        if (! isset($config->plans[$plan])) {
            throw new RuntimeException("Unknown plan '{$plan}'. Plans are listed in Config\\Platform::\$plans.");
        }

        $model = model(TenantModel::class, false);

        if ($model->withDeleted()->where('slug', $slug)->countAllResults() > 0) {
            throw new RuntimeException("The workspace address '{$slug}' is taken.");
        }

        $domain = isset($extra['domain']) ? strtolower(trim((string) $extra['domain'])) : null;

        if ($domain !== null && $domain !== '' && $model->where('domain', $domain)->countAllResults() > 0) {
            throw new RuntimeException("The domain '{$domain}' already belongs to another workspace.");
        }

        $db = $model->db;
        $db->transException(true)->transStart();

        $id = $model->insert([
            'slug'          => $slug,
            'name'          => $name,
            'plan'          => $plan,
            'status'        => (string) ($extra['status'] ?? (empty($extra['trial_ends_at']) ? 'active' : 'trial')),
            'domain'        => $domain ?: null,
            'trial_ends_at' => $extra['trial_ends_at'] ?? null,
            'owner_user_id' => $ownerUserId,
            'meta'          => isset($extra['meta']) ? json_encode($extra['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        if ($ownerUserId !== null) {
            service('authAuthorizer')->assignRole($ownerUserId, $config->tenantOwnerRole, $slug);
        }

        $db->transComplete();

        $tenant = $model->find($id);
        unset($this->bySlug[$slug]);

        Events::trigger('platform.tenant.created', $tenant);

        return $tenant;
    }

    /**
     * Change a tenant's plan, status, name or domain.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function update(string $slug, array $changes): array
    {
        $tenant = $this->find($slug) ?? throw new RuntimeException("No tenant '{$slug}'.");
        $config = $this->config();
        $allowed = array_intersect_key($changes, array_flip(['name', 'plan', 'status', 'domain', 'trial_ends_at']));

        if (isset($allowed['plan']) && ! isset($config->plans[$allowed['plan']])) {
            throw new RuntimeException("Unknown plan '{$allowed['plan']}'.");
        }

        if (isset($allowed['status']) && ! isset(TenantModel::STATUSES[$allowed['status']])) {
            throw new RuntimeException("Unknown status '{$allowed['status']}'.");
        }

        if (array_key_exists('domain', $allowed)) {
            $allowed['domain'] = $allowed['domain'] === null || trim((string) $allowed['domain']) === '' ? null : strtolower(trim((string) $allowed['domain']));

            if ($allowed['domain'] !== null && model(TenantModel::class, false)->where('domain', $allowed['domain'])->where('id !=', $tenant['id'])->countAllResults() > 0) {
                throw new RuntimeException("The domain '{$allowed['domain']}' already belongs to another workspace.");
            }
        }

        $diff = [];

        foreach ($allowed as $field => $value) {
            if ((string) ($tenant[$field] ?? '') !== (string) ($value ?? '')) {
                $diff[$field] = ['from' => $tenant[$field] ?? null, 'to' => $value];
            }
        }

        if ($diff !== []) {
            model(TenantModel::class, false)->update($tenant['id'], array_map(static fn (array $d): mixed => $d['to'], $diff));
            unset($this->bySlug[$slug]);
            service('moduleGate')->flush($slug);
            Events::trigger('platform.tenant.updated', $this->find($slug), $diff);
        }

        return $diff;
    }

    private function config(): Platform
    {
        return $this->config ?? PlatformConfig::get();
    }
}
