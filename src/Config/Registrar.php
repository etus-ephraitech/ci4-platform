<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Config;

use Ephraitech\Platform\Filters\ModuleFilter;
use Ephraitech\Platform\Filters\PlatformFilter;

/**
 * Discovered by CodeIgniter: adds the platform's filter aliases to
 * Config\Filters.
 *
 * Only aliases: CodeIgniter merges registrar arrays one level deep, so a
 * registrar returning 'globals' would replace the app's own global
 * filters. The 'platform' global filter is listed in app/Config/Filters.php
 * instead, where it is visible, and deploy:check makes sure it is there.
 */
class Registrar
{
    /**
     * @return array{aliases: array<string, class-string>}
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'platform' => PlatformFilter::class,
                'module'   => ModuleFilter::class,
            ],
        ];
    }
}
