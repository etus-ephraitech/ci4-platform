<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Libraries;

use Ephraitech\Platform\Config\PlatformConfig;
use Throwable;

/**
 * Every role there is: the built-in ones from app/Config/Auth.php and
 * the ones created on the Roles page (stored only in the database).
 */
final class RoleCatalog
{
    /**
     * @var array<string, array{title: string, description: string, system: bool}>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<string, array{title: string, description: string, system: bool}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $config = PlatformConfig::auth();
        $roles  = [];

        foreach ($config->roles as $name => $role) {
            $roles[$name] = ['title' => (string) $role['title'], 'description' => (string) ($role['description'] ?? ''), 'system' => true];
        }

        try {
            foreach (db_connect()->table($config->table('roles'))->select('name, title, description')->orderBy('title', 'asc')->get()->getResultArray() as $r) {
                if (! isset($roles[$r['name']])) {
                    $roles[$r['name']] = ['title' => (string) $r['title'], 'description' => (string) ($r['description'] ?? ''), 'system' => false];
                }
            }
        } catch (Throwable) {
            // Database not ready: built-in roles only.
        }

        return self::$cache = $roles;
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    private function __construct()
    {
    }
}
