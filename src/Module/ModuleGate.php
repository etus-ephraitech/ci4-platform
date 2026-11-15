<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use Ephraitech\Platform\Config\Platform;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Models\ModuleStateModel;
use Ephraitech\Platform\Module\Resolvers\ModuleResolver;
use Ephraitech\Platform\Module\Resolvers\ResolverContext;
use Ephraitech\Platform\Tenancy\Tenancy;
use RuntimeException;
use Throwable;

/**
 * The one answer to "is this module switched on, here and now?".
 *
 *     service('moduleGate')->enabled('Crm');            // current request / tenant
 *     service('moduleGate')->enabled('Crm', 'acme');    // a given tenant
 *     module_enabled('Crm');                             // helper
 *
 * Menus, quick actions, search, settings, dashboards and extensions only
 * show what comes from switched-on modules; the platform filter answers
 * 404 for routes of switched-off modules; and ci4-auth's permission gate
 * (ModulePermissionGate) refuses their permissions, so can() is false for
 * them everywhere, the owner and API tokens included.
 *
 * How a module's state is decided, for one tenant ('' = single install
 * or no tenant):
 *   1. not installed (.disabled, platform.modulesOff/Only): never on;
 *   2. always-on (Core, Users, 'always_on' => true): on;
 *   3. multi-tenant with no tenant (central): on only if 'central' => true;
 *   4. otherwise the resolvers in Config\Platform::$resolvers, in order;
 *   5. finally, a module whose 'requires' are not all on is switched off.
 */
final class ModuleGate
{
    private const CACHE_PREFIX = 'platform_gate_';

    /**
     * @var array<string, array<string, array{on: bool, source: string}>> tenant key => module => decision
     */
    private array $memo = [];

    /**
     * @var list<ModuleResolver>|null
     */
    private ?array $resolvers = null;

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Tenancy $tenancy,
        private readonly CacheInterface $cache,
        private readonly ?Platform $config = null,
    ) {
    }

    public function enabled(string $module, ?string $tenant = null): bool
    {
        return ($this->decisions($tenant)[$module]['on'] ?? false) === true;
    }

    /**
     * Names of switched-on modules, in manifest order.
     *
     * @return list<string>
     */
    public function enabledModules(?string $tenant = null): array
    {
        return array_keys(array_filter($this->decisions($tenant), static fn (array $d): bool => $d['on']));
    }

    /**
     * Each installed module's state and why, for admin screens and modules:list.
     *
     * @return array<string, array{on: bool, source: string}>
     */
    public function decisions(?string $tenant = null): array
    {
        $key = $this->tenantKey($tenant);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $cacheKey = self::CACHE_PREFIX . ($key === '' ? '_' : $key);
        $cached   = $this->config()->gateCacheTtl > 0 ? $this->cache->get($cacheKey) : null;

        if (is_array($cached) && array_keys($cached) === array_keys($this->registry->all())) {
            return $this->memo[$key] = $cached;
        }

        $decisions = $this->compute($key);

        if ($this->config()->gateCacheTtl > 0) {
            $this->cache->save($cacheKey, $decisions, $this->config()->gateCacheTtl);
        }

        return $this->memo[$key] = $decisions;
    }

    /**
     * Whether a permission may be granted at all: false when the module
     * that declares it is switched off. Permissions declared in Config\Auth
     * itself belong to no module and are always allowed.
     */
    public function allowsPermission(string $permission, ?string $tenant = null): bool
    {
        $module = $this->registry->moduleForPermission($permission);

        return $module === null || $this->enabled($module, $tenant);
    }

    /**
     * The module owning a controller (or any class), or null.
     */
    public function moduleForClass(?string $class): ?string
    {
        return $class === null || $class === '' ? null : $this->registry->moduleForClass($class);
    }

    /**
     * Switch a module on or off for a tenant ('' or null in a single install),
     * or clear the switch with $enabled = null so the default or plan decides again.
     *
     * @return list<string> other modules that change state as a result (requirements)
     */
    public function setState(string $module, ?bool $enabled, ?string $tenant = null, ?int $userId = null, ?string $expiresAt = null, ?string $note = null): array
    {
        $manifest = $this->registry->get($module);
        $key      = $this->tenantKey($tenant);

        if ($manifest['always_on']) {
            throw new RuntimeException("{$module} is always on and cannot be switched.");
        }

        if ($this->tenancy->isMulti() && $key === '') {
            throw new RuntimeException('In a multi-tenant install modules are switched per tenant. Name the tenant.');
        }

        if ($expiresAt !== null && Time::parse($expiresAt)->isBefore(Time::now())) {
            throw new RuntimeException('The end date is in the past.');
        }

        $before = $this->decisions($key);
        $model  = model(ModuleStateModel::class, false);
        $row    = $model->where('tenant_id', $key)->where('module', $module)->first();

        if ($enabled === null) {
            if ($row !== null) {
                $model->delete($row['id']);
            }
        } else {
            $data = [
                'tenant_id'  => $key,
                'module'     => $module,
                'enabled'    => $enabled ? 1 : 0,
                'expires_at' => $expiresAt,
                'note'       => $note === null ? null : mb_substr($note, 0, 255),
                'changed_by' => $userId,
            ];

            $row === null ? $model->insert($data) : $model->update($row['id'], $data);
        }

        $this->flush($key);
        $after = $this->decisions($key);

        $knockOn = [];

        foreach ($after as $name => $decision) {
            if ($name !== $module && ($before[$name]['on'] ?? false) !== $decision['on']) {
                $knockOn[] = $name;
            }
        }

        Events::trigger('platform.module.changed', [
            'module'   => $module,
            'enabled'  => $enabled,
            'tenant'   => $key,
            'on'       => $after[$module]['on'],
            'also'     => $knockOn,
            'user_id'  => $userId,
            'expires'  => $expiresAt,
        ]);

        return $knockOn;
    }

    /**
     * Forget cached decisions for one tenant ('' for single/central), or all.
     */
    public function flush(?string $tenant = null): void
    {
        if ($tenant === null) {
            $this->memo = [];

            try {
                $this->cache->deleteMatching(self::CACHE_PREFIX . '*');
            } catch (Throwable) {
                // Handlers without pattern deletion: entries expire after gateCacheTtl.
            }

            return;
        }

        unset($this->memo[$tenant]);
        $this->cache->delete(self::CACHE_PREFIX . ($tenant === '' ? '_' : $tenant));
    }

    /**
     * Forget per-request decisions (the tenant changed mid-request).
     */
    public function forgetRequestCache(): void
    {
        $this->memo = [];
    }

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    // ------------------------------------------------------------------

    private function tenantKey(?string $tenant): string
    {
        if (! $this->tenancy->isMulti()) {
            return '';
        }

        return $tenant ?? $this->tenancy->key();
    }

    /**
     * @return array<string, array{on: bool, source: string}>
     */
    private function compute(string $key): array
    {
        $multi   = $this->tenancy->isMulti();
        $modules = $this->registry->all();
        $tenant  = $key !== '' ? $this->tenancy->find($key) : null;

        try {
            $states = model(ModuleStateModel::class, false)->forTenant($key);
        } catch (Throwable) {
            // Before the platform migrations have run.
            $states = [];
        }

        $context   = new ResolverContext($key, $tenant, $multi, $states, $this->config());
        $decisions = [];

        foreach ($modules as $name => $manifest) {
            if ($manifest['always_on']) {
                $decisions[$name] = ['on' => true, 'source' => 'always on'];

                continue;
            }

            if ($multi && $tenant === null) {
                $decisions[$name] = $manifest['central']
                    ? ['on' => true, 'source' => 'central']
                    : ['on' => false, 'source' => $key === '' ? 'no tenant selected' : 'unknown tenant'];

                continue;
            }

            $decisions[$name] = ['on' => false, 'source' => 'no resolver decided'];

            foreach ($this->resolvers($multi) as $resolver) {
                $answer = $resolver->decide($name, $manifest, $context);

                if ($answer !== null) {
                    $decisions[$name] = ['on' => $answer, 'source' => $resolver->label()];

                    break;
                }
            }
        }

        do {
            $changed = false;

            foreach ($decisions as $name => $decision) {
                if (! $decision['on']) {
                    continue;
                }

                foreach ($modules[$name]['requires'] as $required) {
                    if (! ($decisions[$required]['on'] ?? false)) {
                        $decisions[$name] = ['on' => false, 'source' => "needs {$required}"];
                        $changed          = true;

                        break;
                    }
                }
            }
        } while ($changed);

        return $decisions;
    }

    /**
     * @return list<ModuleResolver>
     */
    private function resolvers(bool $multi): array
    {
        if ($this->resolvers !== null) {
            return $this->resolvers;
        }

        $classes = $this->config()->resolvers[$multi ? 'multi' : 'single'] ?? [];
        $out     = [];

        foreach ($classes as $class) {
            if (! is_subclass_of($class, ModuleResolver::class)) {
                throw new RuntimeException("Config\\Platform::\$resolvers lists {$class}, which does not implement " . ModuleResolver::class . '.');
            }

            $out[] = new $class();
        }

        return $this->resolvers = $out;
    }

    private function config(): Platform
    {
        return $this->config ?? PlatformConfig::get();
    }
}
