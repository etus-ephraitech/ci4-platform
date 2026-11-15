<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module;

/**
 * Namespaces of installed modules, for app/Config/Autoload.php:
 *
 *     public function __construct()
 *     {
 *         require_once COMPOSER_PATH;
 *         $this->psr4 += \Ephraitech\Platform\Module\ModuleAutoloader::namespaces();
 *         parent::__construct();
 *     }
 *
 * Registering a module's namespace is what lets CodeIgniter discover its
 * Config/Routes.php, Config/Registrar.php, Config/Events.php, Commands/
 * and Database/Migrations/. Modules that are not installed are left out,
 * so none of that is loaded for them.
 */
final class ModuleAutoloader
{
    /**
     * @return array<string, string> namespace => path
     */
    public static function namespaces(?ModuleLocator $locator = null): array
    {
        $namespaces = [];

        foreach (($locator ?? new ModuleLocator())->installed() as $module) {
            $namespaces[$module['namespace']] = $module['path'];
        }

        ksort($namespaces);

        return $namespaces;
    }
}
