<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module;

/**
 * Finds module folders and decides which are installed.
 *
 * Runs at boot, before CodeIgniter's config, services or helpers exist
 * (app/Config/Autoload.php calls it through ModuleAutoloader), so it reads
 * nothing but the filesystem and the process environment.
 *
 * Where modules come from:
 *   - built-in:  Core and Users, shipped inside this package;
 *   - app:       every <Name>/module.php under ROOTPATH/modules, or the
 *                folders listed in platform.modulePaths, namespace Modules\<Name>;
 *   - package:   Composer packages that declare modules in composer.json:
 *                    "extra": {"ephraitech-platform": {"modules": {
 *                        "Billing": {"path": "src", "namespace": "Acme\\Billing"}
 *                    }}}
 *
 * Which are installed (switched on for the whole install):
 *   - a module folder holding an empty .disabled file is not installed;
 *   - platform.modulesOff = "Crm, Sales"  in .env: these are not installed;
 *   - platform.modulesOnly = "Crm, Sales" in .env: only these (plus Core
 *     and Users) are installed.
 *
 * A module that is not installed has no namespace, routes, commands,
 * migrations or permissions. Modules switched off per tenant or in the
 * admin screen stay installed; the ModuleGate hides them per request.
 */
final class ModuleLocator
{
    /**
     * Modules that are always installed and can never be switched off.
     */
    public const BUILT_IN = ['Core', 'Users'];

    public const NAME_PATTERN = '/^[A-Z][A-Za-z0-9]*$/';

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private static array $cache = [];

    /**
     * @param string|null          $rootPath     Project root; ROOTPATH when null.
     * @param array<string, mixed> $env          Environment values; the process environment when null.
     * @param string|null          $composerPath vendor/autoload.php; COMPOSER_PATH when null.
     */
    public function __construct(
        private readonly ?string $rootPath = null,
        private readonly ?array $env = null,
        private readonly ?string $composerPath = null,
    ) {
    }

    /**
     * Every module found, installed or not, sorted by name.
     *
     * @return list<array{name: string, path: string, namespace: string, source: string, manifest: string, installed: bool, reason: ?string}>
     */
    public function all(): array
    {
        $key = $this->cacheKey();

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $found = [];

        foreach ($this->builtIn() + $this->appModules() + $this->packageModules() as $name => $module) {
            $found[$name] = $module;
        }

        $off  = $this->listFromEnv('platform.modulesOff');
        $only = $this->listFromEnv('platform.modulesOnly');

        foreach ($found as $name => $module) {
            $reason = null;

            if (! in_array($name, self::BUILT_IN, true)) {
                if (is_file($module['path'] . '.disabled')) {
                    $reason = 'switched off with a .disabled file';
                } elseif (in_array($name, $off, true)) {
                    $reason = 'listed in platform.modulesOff';
                } elseif ($only !== [] && ! in_array($name, $only, true)) {
                    $reason = 'not listed in platform.modulesOnly';
                }
            }

            $found[$name]['installed'] = $reason === null;
            $found[$name]['reason']    = $reason;
        }

        ksort($found);

        return self::$cache[$key] = array_values($found);
    }

    /**
     * Installed modules only.
     *
     * @return list<array{name: string, path: string, namespace: string, source: string, manifest: string, installed: bool, reason: ?string}>
     */
    public function installed(): array
    {
        return array_values(array_filter($this->all(), static fn (array $m): bool => $m['installed']));
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    // ------------------------------------------------------------------

    /**
     * @return array<string, array<string, mixed>>
     */
    private function builtIn(): array
    {
        $src = dirname(__DIR__) . DIRECTORY_SEPARATOR;

        return [
            'Core' => $this->entry('Core', $src, 'Ephraitech\Platform', 'built-in'),
            'Users' => $this->entry('Users', $src . 'Users' . DIRECTORY_SEPARATOR, 'Ephraitech\Platform\Users', 'built-in'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function appModules(): array
    {
        $root    = $this->root();
        $paths   = $this->listFromEnv('platform.modulePaths', false) ?: ['modules'];
        $modules = [];

        foreach ($paths as $relative) {
            $dir = $this->isAbsolute($relative) ? $relative : $root . $relative;
            $dir = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR;

            foreach (glob($dir . '*', GLOB_ONLYDIR) ?: [] as $folder) {
                $name = basename($folder);

                if (preg_match(self::NAME_PATTERN, $name) !== 1 || ! is_file($folder . DIRECTORY_SEPARATOR . 'module.php')) {
                    continue;
                }

                if (in_array($name, self::BUILT_IN, true)) {
                    throw new ModuleException("modules/{$name} uses a name reserved for a built-in platform module. Rename the folder.");
                }

                if (isset($modules[$name])) {
                    throw new ModuleException("Module '{$name}' was found twice: in {$modules[$name]['path']} and in {$folder}.");
                }

                $modules[$name] = $this->entry($name, $folder . DIRECTORY_SEPARATOR, 'Modules\\' . $name, 'app');
            }
        }

        return $modules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function packageModules(): array
    {
        $composer = $this->composerPath ?? (defined('COMPOSER_PATH') ? COMPOSER_PATH : $this->root() . 'vendor/autoload.php');
        $file     = dirname($composer) . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';

        if (! is_file($file)) {
            return [];
        }

        $data     = json_decode((string) file_get_contents($file), true);
        $packages = is_array($data) ? ($data['packages'] ?? $data) : [];
        $modules  = [];

        foreach ($packages as $package) {
            $declared = $package['extra']['ephraitech-platform']['modules'] ?? null;

            if (! is_array($declared) || ! isset($package['install-path'])) {
                continue;
            }

            $base = realpath(dirname($file) . DIRECTORY_SEPARATOR . $package['install-path']);

            if ($base === false) {
                continue;
            }

            foreach ($declared as $name => $spec) {
                $name      = (string) $name;
                $path      = rtrim($base . DIRECTORY_SEPARATOR . ltrim((string) ($spec['path'] ?? ''), '\\/'), '\\/') . DIRECTORY_SEPARATOR;
                $namespace = trim((string) ($spec['namespace'] ?? ''), '\\');

                if (preg_match(self::NAME_PATTERN, $name) !== 1 || $namespace === '' || ! is_file($path . 'module.php')) {
                    throw new ModuleException("Package {$package['name']} declares module '{$name}' without a StudlyCase name, a namespace or a module.php at {$path}.");
                }

                if (in_array($name, self::BUILT_IN, true)) {
                    throw new ModuleException("Package {$package['name']} declares module '{$name}', a name reserved for a built-in platform module.");
                }

                $modules[$name] = $this->entry($name, $path, $namespace, 'package:' . $package['name']);
            }
        }

        foreach (array_intersect_key($modules, $this->appModules()) as $name => $module) {
            throw new ModuleException("Module '{$name}' exists in modules/ and in {$module['source']}. Remove one of them.");
        }

        return $modules;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $name, string $path, string $namespace, string $source): array
    {
        return [
            'name'      => $name,
            'path'      => $path,
            'namespace' => $namespace,
            'source'    => $source,
            'manifest'  => $path . 'module.php',
            'installed' => true,
            'reason'    => null,
        ];
    }

    /**
     * A comma-separated environment value as a list.
     *
     * @return list<string>
     */
    private function listFromEnv(string $key, bool $names = true): array
    {
        $raw = $this->env !== null
            ? ($this->env[$key] ?? null)
            : ($_ENV[$key] ?? $_SERVER[$key] ?? (getenv($key) === false ? null : getenv($key)));

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== ''));

        return $names ? array_values(array_filter($items, static fn (string $v): bool => preg_match(self::NAME_PATTERN, $v) === 1)) : $items;
    }

    private function root(): string
    {
        $root = $this->rootPath ?? (defined('ROOTPATH') ? ROOTPATH : getcwd() . DIRECTORY_SEPARATOR);

        return rtrim($root, '\\/') . DIRECTORY_SEPARATOR;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function cacheKey(): string
    {
        return md5(serialize([$this->root(), $this->env, $this->composerPath,
            $this->env === null ? [$_ENV['platform.modulesOff'] ?? getenv('platform.modulesOff'), $_ENV['platform.modulesOnly'] ?? getenv('platform.modulesOnly'), $_ENV['platform.modulePaths'] ?? getenv('platform.modulePaths')] : null]));
    }
}
