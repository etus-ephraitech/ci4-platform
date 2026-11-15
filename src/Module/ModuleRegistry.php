<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module;

/**
 * Every installed module's manifest, and what the modules contribute to
 * the platform: permissions, role grants, menu entries, settings, document
 * sequences, dashboards, widgets, search providers and extensions.
 *
 * This is the install-level view: it answers "what exists in this
 * install". Whether a module is switched on for the current request or
 * tenant is the ModuleGate's job; consumers ask the gate before showing
 * anything a module contributes.
 *
 * Needs no CodeIgniter config or services, so Config\Auth and the auth
 * permission bridge can use it while configuration is still being built.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private static array $cache = [];

    /**
     * @var array<string, string>|null permission => module
     */
    private ?array $permissionOwners = null;

    /**
     * @var array<string, string>|null namespace prefix => module, longest first
     */
    private ?array $namespaceOwners = null;

    public function __construct(private readonly ModuleLocator $locator = new ModuleLocator())
    {
    }

    /**
     * Installed modules keyed by name, sorted by 'order' then name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $key = spl_object_id($this->locator) . ':' . md5(serialize(array_column($this->locator->installed(), 'path', 'name')));

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $modules = [];

        foreach ($this->locator->installed() as $module) {
            $modules[$module['name']] = Manifest::load($module);
        }

        uasort($modules, static fn (array $a, array $b): int => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);

        return self::$cache[$key] = $modules;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        return $this->all()[$name] ?? throw new ModuleException("Module '{$name}' is not installed.");
    }

    /**
     * Modules found on disk or in packages that are not installed, with why.
     *
     * @return array<string, string> name => reason
     */
    public function notInstalled(): array
    {
        $out = [];

        foreach ($this->locator->all() as $module) {
            if (! $module['installed']) {
                $out[$module['name']] = (string) $module['reason'];
            }
        }

        return $out;
    }

    /**
     * Requirements of installed modules that are not installed, as
     * "Module needs Other".
     *
     * @return list<string>
     */
    public function unmetRequirements(): array
    {
        $modules  = $this->all();
        $problems = [];

        foreach ($modules as $name => $module) {
            foreach ($module['requires'] as $required) {
                if (! isset($modules[$required])) {
                    $problems[] = "{$name} needs {$required}";
                }
            }
        }

        return $problems;
    }

    /**
     * Installed modules in dependency order: every module after the
     * modules it requires. Used for migrations.
     *
     * @return list<string>
     */
    public function dependencyOrder(): array
    {
        $modules = $this->all();
        $done    = [];
        $visit   = static function (string $name, array $path) use (&$visit, &$done, $modules): void {
            if (isset($done[$name]) || ! isset($modules[$name])) {
                return;
            }

            if (in_array($name, $path, true)) {
                throw new ModuleException('Modules require each other in a circle: ' . implode(' → ', [...$path, $name]) . '.');
            }

            foreach ($modules[$name]['requires'] as $required) {
                $visit($required, [...$path, $name]);
            }

            $done[$name] = true;
        };

        foreach (array_keys($modules) as $name) {
            $visit($name, []);
        }

        return array_keys($done);
    }

    /**
     * Installed modules that need $module, directly or through others.
     *
     * @return list<string>
     */
    public function dependentsOf(string $module): array
    {
        $found   = [];
        $pending = [$module];

        while ($pending !== []) {
            $current = array_shift($pending);

            foreach ($this->all() as $name => $manifest) {
                if (in_array($current, $manifest['requires'], true) && ! in_array($name, $found, true)) {
                    $found[]   = $name;
                    $pending[] = $name;
                }
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @return array<string, string> permission => description
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->all() as $name => $module) {
            foreach ($module['permissions'] as $permission => $description) {
                if (isset($permissions[$permission])) {
                    throw new ModuleException("Permission '{$permission}' is declared by more than one module (again in {$name}).");
                }

                $permissions[$permission] = $description;
            }
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * Role => permissions contributed by modules, merged across modules.
     *
     * @return array<string, list<string>>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach ($this->all() as $module) {
            foreach ($module['matrix'] as $role => $grants) {
                $matrix[$role] = array_values(array_unique([...($matrix[$role] ?? []), ...$grants]));
            }
        }

        return $matrix;
    }

    /**
     * The module that declares a permission, or null for permissions
     * declared in Config\Auth itself.
     */
    public function moduleForPermission(string $permission): ?string
    {
        if ($this->permissionOwners === null) {
            $this->permissionOwners = [];

            foreach ($this->all() as $name => $module) {
                foreach (array_keys($module['permissions']) as $p) {
                    $this->permissionOwners[$p] = $name;
                }
            }
        }

        return $this->permissionOwners[$permission] ?? null;
    }

    /**
     * The module a class belongs to, by namespace (longest match), or null.
     */
    public function moduleForClass(string $class): ?string
    {
        if ($this->namespaceOwners === null) {
            $owners = [];

            foreach ($this->all() as $name => $module) {
                $owners[$module['namespace'] . '\\'] = $name;
            }

            uksort($owners, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $this->namespaceOwners = $owners;
        }

        $class = ltrim($class, '\\');

        foreach ($this->namespaceOwners as $prefix => $name) {
            if (str_starts_with($class, $prefix)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function menu(): array
    {
        return $this->collect('menu');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quickActions(): array
    {
        return $this->collect('quick');
    }

    /**
     * @return array<string, array<string, mixed>> group => definition (with 'module')
     */
    public function settings(): array
    {
        return $this->collectKeyed('settings', 'Settings group');
    }

    /**
     * @return array<string, array{prefix: string, padding: int, reset: string, module: string}>
     */
    public function sequences(): array
    {
        return $this->collectKeyed('sequences', 'Sequence');
    }

    /**
     * @return array<string, array<string, mixed>> board key => definition, sorted by order
     */
    public function boards(): array
    {
        $boards = $this->collectKeyed('boards', 'Dashboard');
        uasort($boards, static fn (array $a, array $b): int => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $boards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function widgets(): array
    {
        $widgets = $this->collect('widgets');
        usort($widgets, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $widgets;
    }

    /**
     * @return list<array{call: string, module: string}>
     */
    public function searchProviders(): array
    {
        return $this->callablesOf('search');
    }

    /**
     * @return list<array{call: string, module: string}>
     */
    public function landingProviders(): array
    {
        return $this->callablesOf('landing');
    }

    /**
     * Contributions to one extension point, sorted by order.
     *
     * @return list<array<string, mixed>>
     */
    public function extensions(string $point): array
    {
        $items = [];

        foreach ($this->all() as $module) {
            foreach ($module['extends'][$point] ?? [] as $item) {
                $items[] = $item;
            }
        }

        usort($items, static fn (array $a, array $b): int => [$a['order'], $a['module']] <=> [$b['order'], $b['module']]);

        return $items;
    }

    /**
     * Every extension point any module contributes to.
     *
     * @return list<string>
     */
    public function extensionPoints(): array
    {
        $points = [];

        foreach ($this->all() as $module) {
            $points = [...$points, ...array_keys($module['extends'])];
        }

        $points = array_values(array_unique($points));
        sort($points);

        return $points;
    }

    /**
     * Forget cached manifests (tests, make:module).
     */
    public static function flush(): void
    {
        self::$cache = [];
        ModuleLocator::flush();
    }

    // ------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function collect(string $key): array
    {
        $items = [];

        foreach ($this->all() as $module) {
            foreach ($module[$key] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function collectKeyed(string $key, string $label): array
    {
        $out = [];

        foreach ($this->all() as $name => $module) {
            foreach ($module[$key] as $itemKey => $item) {
                if (isset($out[$itemKey])) {
                    throw new ModuleException("{$label} '{$itemKey}' is declared by {$out[$itemKey]['module']} and again by {$name}.");
                }

                $out[$itemKey] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<array{call: string, module: string}>
     */
    private function callablesOf(string $key): array
    {
        $out = [];

        foreach ($this->all() as $name => $module) {
            foreach ($module[$key] as $call) {
                $out[] = ['call' => $call, 'module' => $name];
            }
        }

        return $out;
    }
}
