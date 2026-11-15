<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module;

/**
 * Reads and checks a module.php manifest.
 *
 * A manifest returns an array. Every key is optional except that 'name',
 * when given, must match the module's folder (or its composer.json key).
 *
 *   name, title, description, version, order   identity and sort order
 *   requires     list of modules this one needs; it is switched off without them
 *   always_on    true for modules that can never be switched off (Core, Users)
 *   default      single install: on (true, the default) or off until switched on
 *   central      multi-tenant: also available with no tenant selected (platform admin)
 *   permissions  permission => description           synced by auth:sync
 *   matrix       role => list of permissions         grants added to those roles
 *   menu         sidebar entries                     label, route, icon, permission, group, order
 *   quick        "New" menu entries                  label, route, icon, permission
 *   settings     group => title, description, scope ('tenant'|'global'), permission, fields
 *   sequences    key => prefix, padding, reset       document numbers
 *   boards       key => title, icon, permission, roles, order   dashboards this module adds
 *   widgets      dashboard panels                    cell, permission, width, order, dashboards, params
 *   search       list of "Class::method" providers   global search
 *   extends      point => list of contributions      UI slots ('cell') and API data ('call')
 *   landing      list of "Class::method"             return a board key for the signed-in user, or null
 *   api          base, version, description          advertised to API clients in /api/v1/platform/context
 */
final class Manifest
{
    public const DEFAULTS = [
        'name'        => '',
        'title'       => '',
        'description' => '',
        'version'     => '1.0.0',
        'order'       => 100,
        'requires'    => [],
        'always_on'   => false,
        'default'     => true,
        'central'     => false,
        'permissions' => [],
        'matrix'      => [],
        'menu'        => [],
        'quick'       => [],
        'settings'    => [],
        'sequences'   => [],
        'boards'      => [],
        'widgets'     => [],
        'search'      => [],
        'extends'     => [],
        'landing'     => [],
        'api'         => [],
    ];

    /**
     * Load and normalise a module's manifest.
     *
     * @param array{name: string, path: string, namespace: string, source: string, manifest: string} $module
     *
     * @return array<string, mixed>
     */
    public static function load(array $module): array
    {
        $raw = (static fn (string $file): mixed => require $file)($module['manifest']);

        if (! is_array($raw)) {
            throw new ModuleException("{$module['manifest']} must return an array.");
        }

        return self::normalise($raw, $module);
    }

    /**
     * @param array<string, mixed>                                                                 $raw
     * @param array{name: string, path: string, namespace: string, source: string, manifest: string} $module
     *
     * @return array<string, mixed>
     */
    public static function normalise(array $raw, array $module): array
    {
        $name    = $module['name'];
        $unknown = array_diff(array_keys($raw), array_keys(self::DEFAULTS));

        if ($unknown !== []) {
            throw new ModuleException("Module {$name}: unknown manifest key(s) " . implode(', ', $unknown) . '. Check the spelling against ' . self::class . '.');
        }

        $m = array_merge(self::DEFAULTS, $raw);

        if ($m['name'] !== '' && $m['name'] !== $name) {
            throw new ModuleException("Module {$name}: module.php declares name '{$m['name']}'; it must match the folder name.");
        }

        $m['name']      = $name;
        $m['title']     = (string) ($m['title'] !== '' ? $m['title'] : self::humanise($name));
        $m['path']      = $module['path'];
        $m['namespace'] = $module['namespace'];
        $m['source']    = $module['source'];
        $m['version']   = (string) $m['version'];
        $m['order']     = (int) $m['order'];
        $m['always_on'] = in_array($name, ModuleLocator::BUILT_IN, true) || (bool) $m['always_on'];
        $m['default']   = (bool) $m['default'];
        $m['central']   = (bool) $m['central'];

        $m['requires'] = self::stringList($m['requires'], $name, 'requires');

        foreach ($m['requires'] as $required) {
            if (preg_match(ModuleLocator::NAME_PATTERN, $required) !== 1) {
                throw new ModuleException("Module {$name}: 'requires' lists '{$required}', which is not a module name.");
            }
        }

        $m['permissions'] = self::permissions($m['permissions'], $name);
        $m['matrix']      = self::matrix($m['matrix'], $name);
        $m['menu']        = self::entries($m['menu'], $name, 'menu', ['label', 'route'], [
            'icon' => 'bi-circle', 'permission' => null, 'group' => 'General', 'order' => 100,
        ]);
        $m['quick'] = self::entries($m['quick'], $name, 'quick', ['label', 'route'], [
            'icon' => 'bi-plus-lg', 'permission' => null,
        ]);
        $m['settings']  = self::settings($m['settings'], $name);
        $m['sequences'] = self::sequences($m['sequences'], $name);
        $m['boards']    = self::boards($m['boards'], $name);
        $m['widgets']   = self::widgets($m['widgets'], $name);
        $m['search']    = self::callables($m['search'], $name, 'search');
        $m['landing']   = self::callables($m['landing'], $name, 'landing');
        $m['extends']   = self::extensions($m['extends'], $name);
        $m['api']       = self::api($m['api'], $name);

        return $m;
    }

    public static function humanise(string $name): string
    {
        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $name));
    }

    // ------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $module, string $key): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: '{$key}' must be a list.");
        }

        return array_values(array_map('strval', $value));
    }

    /**
     * @return array<string, string>
     */
    private static function permissions(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'permissions' must be permission => description.");
        }

        $out = [];

        foreach ($value as $permission => $description) {
            if (! is_string($permission) || preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/', $permission) !== 1) {
                throw new ModuleException("Module {$module}: permission '{$permission}' must be dotted lowercase, e.g. 'invoices.create'.");
            }

            $out[$permission] = (string) $description;
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function matrix(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'matrix' must be role => list of permissions.");
        }

        $out = [];

        foreach ($value as $role => $grants) {
            $out[(string) $role] = self::stringList($grants, $module, "matrix.{$role}");
        }

        return $out;
    }

    /**
     * @param list<string>         $required
     * @param array<string, mixed> $defaults
     *
     * @return list<array<string, mixed>>
     */
    private static function entries(mixed $value, string $module, string $key, array $required, array $defaults): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: '{$key}' must be a list.");
        }

        $out = [];

        foreach (array_values($value) as $i => $entry) {
            if (! is_array($entry)) {
                throw new ModuleException("Module {$module}: {$key}[{$i}] must be an array.");
            }

            foreach ($required as $field) {
                if (! isset($entry[$field]) || ! is_string($entry[$field]) || $entry[$field] === '') {
                    throw new ModuleException("Module {$module}: {$key}[{$i}] needs '{$field}'.");
                }
            }

            $entry           = array_merge($defaults, $entry);
            $entry['module'] = $module;
            $out[]           = $entry;
        }

        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function settings(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'settings' must be group => definition.");
        }

        $out = [];

        foreach ($value as $group => $definition) {
            if (! is_string($group) || preg_match('/^[a-z][a-z0-9_]*$/', $group) !== 1) {
                throw new ModuleException("Module {$module}: settings group '{$group}' must be lowercase letters, digits and '_'.");
            }

            $scope = (string) ($definition['scope'] ?? 'tenant');

            if (! in_array($scope, ['tenant', 'global'], true)) {
                throw new ModuleException("Module {$module}: settings group '{$group}' has scope '{$scope}'; use 'tenant' or 'global'.");
            }

            $fields = [];

            foreach ((array) ($definition['fields'] ?? []) as $key => $field) {
                if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
                    throw new ModuleException("Module {$module}: setting '{$group}.{$key}' must be lowercase letters, digits and '_'.");
                }

                $fields[$key] = array_merge(['label' => self::humanise(ucfirst(str_replace('_', ' ', $key))), 'type' => 'text', 'default' => null], (array) $field);
            }

            $out[$group] = [
                'title'       => (string) ($definition['title'] ?? ucfirst(str_replace('_', ' ', $group))),
                'description' => (string) ($definition['description'] ?? ''),
                'permission'  => array_key_exists('permission', (array) $definition) ? $definition['permission'] : 'settings.manage',
                'scope'       => $scope,
                'order'       => (int) ($definition['order'] ?? 100),
                'fields'      => $fields,
                'module'      => $module,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{prefix: string, padding: int, reset: string, module: string}>
     */
    private static function sequences(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'sequences' must be key => definition.");
        }

        $out = [];

        foreach ($value as $key => $definition) {
            $reset = (string) ($definition['reset'] ?? 'yearly');

            if (! in_array($reset, ['yearly', 'monthly', 'never'], true)) {
                throw new ModuleException("Module {$module}: sequence '{$key}' reset must be yearly, monthly or never.");
            }

            $out[(string) $key] = [
                'prefix'  => (string) ($definition['prefix'] ?? strtoupper(substr((string) $key, 0, 3))),
                'padding' => max(1, min(12, (int) ($definition['padding'] ?? 4))),
                'reset'   => $reset,
                'module'  => $module,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function boards(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'boards' must be key => definition.");
        }

        $out = [];

        foreach ($value as $key => $board) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_-]*$/', $key) !== 1) {
                throw new ModuleException("Module {$module}: board key '{$key}' must be lowercase.");
            }

            $out[$key] = [
                'title'      => (string) ($board['title'] ?? ucfirst($key)),
                'icon'       => (string) ($board['icon'] ?? 'bi-grid'),
                'permission' => $board['permission'] ?? null,
                'roles'      => array_values(array_map('strval', (array) ($board['roles'] ?? []))),
                'order'      => (int) ($board['order'] ?? 100),
                'module'     => $module,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function widgets(mixed $value, string $module): array
    {
        $out = [];

        foreach (self::entries($value, $module, 'widgets', ['cell'], []) as $widget) {
            $out[] = [
                'cell'       => $widget['cell'],
                'permission' => $widget['permission'] ?? null,
                'width'      => max(3, min(12, (int) ($widget['width'] ?? 6))),
                'order'      => (int) ($widget['order'] ?? 100),
                'dashboards' => array_values(array_map('strval', (array) ($widget['dashboards'] ?? ['home']))),
                'params'     => (array) ($widget['params'] ?? []),
                'module'     => $module,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function callables(mixed $value, string $module, string $key): array
    {
        $out = [];

        foreach (self::stringList($value, $module, $key) as $callable) {
            if (preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*(::[A-Za-z_][A-Za-z0-9_]*)?$/', $callable) !== 1) {
                throw new ModuleException("Module {$module}: '{$key}' entry '{$callable}' must look like Class::method.");
            }

            $out[] = ltrim($callable, '\\');
        }

        return $out;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function extensions(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'extends' must be point => list of contributions.");
        }

        $out = [];

        foreach ($value as $point => $items) {
            if (! is_string($point) || preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $point) !== 1) {
                throw new ModuleException("Module {$module}: extension point '{$point}' must be dotted lowercase, e.g. 'crm.client.panels'.");
            }

            foreach (array_values((array) $items) as $i => $item) {
                $hasCell = isset($item['cell']) && is_string($item['cell']);
                $hasCall = isset($item['call']) && is_string($item['call']);

                if ($hasCell === $hasCall) {
                    throw new ModuleException("Module {$module}: extends.{$point}[{$i}] needs exactly one of 'cell' (a view cell for pages) or 'call' (a Class::method returning data for the API).");
                }

                $out[$point][] = [
                    'cell'       => $hasCell ? $item['cell'] : null,
                    'call'       => $hasCall ? ltrim($item['call'], '\\') : null,
                    'key'        => (string) ($item['key'] ?? strtolower($module)),
                    'title'      => (string) ($item['title'] ?? Manifest::humanise($module)),
                    'icon'       => (string) ($item['icon'] ?? 'bi-puzzle'),
                    'permission' => $item['permission'] ?? null,
                    'order'      => (int) ($item['order'] ?? 100),
                    'module'     => $module,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{base?: string, version?: string, description?: string}
     */
    private static function api(mixed $value, string $module): array
    {
        if (! is_array($value)) {
            throw new ModuleException("Module {$module}: 'api' must be an array with base, version and description.");
        }

        if ($value === []) {
            return [];
        }

        return [
            'base'        => trim((string) ($value['base'] ?? ''), '/'),
            'version'     => (string) ($value['version'] ?? 'v1'),
            'description' => (string) ($value['description'] ?? ''),
        ];
    }
}
