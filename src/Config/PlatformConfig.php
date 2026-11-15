<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Config;

use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Config\ConfigResolver;

/**
 * Returns the configs the application actually defines.
 *
 * Since CodeIgniter 4.4, config(Package\Config\X::class) returns exactly
 * the package class and skips app/Config/X.php. Every platform class asks
 * here instead, so an app's Config\Platform (extending the package one)
 * is always the one used. Auth config comes from ci4-auth's own resolver.
 */
final class PlatformConfig
{
    public const APP_CLASS = 'Config\Platform';

    public static function get(): Platform
    {
        /** @var Platform $config */
        $config = config(self::resolvedClass());

        return $config;
    }

    /**
     * @return class-string<Platform>
     */
    public static function resolvedClass(): string
    {
        return class_exists(self::APP_CLASS) && is_subclass_of(self::APP_CLASS, Platform::class)
            ? self::APP_CLASS
            : Platform::class;
    }

    /**
     * Why app/Config/Platform.php is ignored, or null when it is used or absent.
     */
    public static function ignoredAppClassReason(): ?string
    {
        if (! class_exists(self::APP_CLASS) || is_subclass_of(self::APP_CLASS, Platform::class)) {
            return null;
        }

        return self::APP_CLASS . ' exists but does not extend ' . Platform::class . ', so the platform ignores it.';
    }

    public static function auth(): Auth
    {
        return ConfigResolver::resolve();
    }

    public static function isMultiTenant(): bool
    {
        return self::auth()->isMultiTenant();
    }
}
