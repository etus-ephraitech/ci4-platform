<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use CodeIgniter\Cache\CacheInterface;
use Ephraitech\Platform\Models\SettingModel;
use Ephraitech\Platform\Module\ModuleGate;
use Ephraitech\Platform\Module\ModuleRegistry;
use Ephraitech\Platform\Tenancy\Tenancy;
use InvalidArgumentException;
use Throwable;

/**
 * Settings declared by modules (module.php 'settings') and stored in
 * platform_settings. Unsaved settings fall back to their declared default.
 *
 *     setting('company.name');                         // helper
 *     service('platformSettings')->get('company.name');
 *
 * Scope:
 *   - single install: every setting is stored once (tenant '').
 *   - multi-tenant: a group with 'scope' => 'tenant' (the default) is
 *     stored per tenant; 'scope' => 'global' groups are stored once and
 *     only changed by the platform owner with no tenant selected.
 *     Reading a tenant setting with no tenant selected gives the default.
 *
 * Values are cached per tenant until the next save.
 */
class Settings
{
    private const CACHE_PREFIX = 'platform_settings_';

    /**
     * @var array<string, array<string, array<string, ?string>>> tenant key => group => key => stored value
     */
    private array $stored = [];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleGate $gate,
        private readonly Tenancy $tenancy,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Every declared group, switched-off modules included.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return $this->registry->settings();
    }

    /**
     * Groups that can be changed here and now. Single install: groups of
     * switched-on modules. Multi-tenant: inside a workspace, its groups for
     * modules on there; with no workspace, the global groups of every
     * installed module. Sorted by 'order', then title.
     *
     * @return array<string, array<string, mixed>>
     */
    public function editableGroups(): array
    {
        $multi  = $this->tenancy->isMulti();
        $inside = $this->tenancy->has();
        $groups = array_filter($this->definitions(), function (array $group) use ($multi, $inside): bool {
            if (! $multi) {
                return $this->gate->enabled($group['module']);
            }

            // Global groups are the platform owner's, for every installed
            // module (tenants cannot change them). Tenant groups only where
            // the module is on for that tenant.
            if ($group['scope'] === 'global') {
                return ! $inside;
            }

            return $inside && $this->gate->enabled($group['module']);
        });

        uasort($groups, static fn (array $a, array $b): int => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $groups;
    }

    /**
     * Read one setting as "group.key".
     */
    public function get(string $path, mixed $default = null): mixed
    {
        [$group, $key] = $this->split($path);
        $definitions   = $this->definitions();
        $tenant        = $this->storageKey($definitions[$group]['scope'] ?? 'tenant');

        if ($tenant !== null) {
            $stored = $this->stored($tenant);

            if (array_key_exists($group, $stored) && array_key_exists($key, $stored[$group])) {
                return $stored[$group][$key];
            }
        }

        if (isset($definitions[$group]['fields'][$key]) && array_key_exists('default', $definitions[$group]['fields'][$key]) && $definitions[$group]['fields'][$key]['default'] !== null) {
            return $definitions[$group]['fields'][$key]['default'];
        }

        return $default;
    }

    /**
     * Every field in a group with its current value (stored or default).
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$group])) {
            throw new InvalidArgumentException("Unknown settings group '{$group}'.");
        }

        $values = [];

        foreach (array_keys($definitions[$group]['fields']) as $key) {
            $values[$key] = $this->get("{$group}.{$key}");
        }

        return $values;
    }

    /**
     * Save declared fields of one group. Unknown keys are ignored.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, array{from: mixed, to: mixed}> what actually changed
     */
    public function save(string $group, array $values, ?int $userId = null): array
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$group])) {
            throw new InvalidArgumentException("Unknown settings group '{$group}'.");
        }

        $tenant = $this->storageKey($definitions[$group]['scope']);

        if ($tenant === null) {
            throw new InvalidArgumentException("Settings group '{$group}' belongs to each workspace; select a workspace to change it.");
        }

        $current = $this->group($group);
        $model   = model(SettingModel::class, false);
        $changes = [];

        $model->db->transStart();

        foreach ($current as $key => $old) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $new = $values[$key] === null ? null : (string) $values[$key];

            if ((string) $old === (string) $new) {
                continue;
            }

            $existing = $model->where('tenant_id', $tenant)->where('group_name', $group)->where('setting_key', $key)->first();

            if ($existing === null) {
                $model->insert(['tenant_id' => $tenant, 'group_name' => $group, 'setting_key' => $key, 'value' => $new, 'updated_by' => $userId]);
            } else {
                $model->update($existing['id'], ['value' => $new, 'updated_by' => $userId]);
            }

            $changes[$key] = ['from' => $old, 'to' => $new];
        }

        $model->db->transComplete();

        if ($changes !== []) {
            $this->flush($tenant);
        }

        return $changes;
    }

    /**
     * How many fields of a group are still empty.
     */
    public function emptyCount(string $group): int
    {
        return count(array_filter($this->group($group), static fn ($value): bool => $value === null || $value === ''));
    }

    public function flush(?string $tenant = null): void
    {
        if ($tenant === null) {
            $this->stored = [];

            try {
                $this->cache->deleteMatching(self::CACHE_PREFIX . '*');
            } catch (Throwable) {
            }

            return;
        }

        unset($this->stored[$tenant]);
        $this->cache->delete(self::CACHE_PREFIX . ($tenant === '' ? '_' : $tenant));
    }

    // ------------------------------------------------------------------

    /**
     * Where a group's values live: '' (single install, or global groups),
     * the tenant slug, or null for a tenant group with no tenant selected.
     */
    private function storageKey(string $scope): ?string
    {
        if (! $this->tenancy->isMulti() || $scope === 'global') {
            return '';
        }

        return $this->tenancy->has() ? $this->tenancy->key() : null;
    }

    /**
     * @return array<string, array<string, ?string>>
     */
    private function stored(string $tenant): array
    {
        if (isset($this->stored[$tenant])) {
            return $this->stored[$tenant];
        }

        $cacheKey = self::CACHE_PREFIX . ($tenant === '' ? '_' : $tenant);
        $cached   = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $this->stored[$tenant] = $cached;
        }

        $stored = [];

        try {
            foreach (model(SettingModel::class, false)->where('tenant_id', $tenant)->findAll() as $row) {
                $stored[$row['group_name']][$row['setting_key']] = $row['value'];
            }
        } catch (Throwable) {
            // Before the platform migrations have run: defaults only, not cached.
            return [];
        }

        $this->cache->save($cacheKey, $stored, 3600);

        return $this->stored[$tenant] = $stored;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $path): array
    {
        $parts = explode('.', $path, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException("Setting paths look like 'group.key'; got '{$path}'.");
        }

        return [$parts[0], $parts[1]];
    }
}
