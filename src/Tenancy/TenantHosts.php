<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

use Ephraitech\Auth\Config\ConfigResolver;
use Ephraitech\Platform\Config\PlatformConfig;
use Throwable;

/**
 * Lets CodeIgniter accept a workspace's own host for the request.
 *
 * CodeIgniter ignores a Host header that is not in Config\App::$allowedHostnames
 * and builds every URL from app.baseURL, which would send people on
 * acme.app.example.com back to the central host with every link. In
 * app/Config/App.php:
 *
 *     public function __construct()
 *     {
 *         parent::__construct();
 *         $this->allowedHostnames = [...$this->allowedHostnames, ...\Ephraitech\Platform\Tenancy\TenantHosts::forRequest()];
 *     }
 *
 * Only multi-tenant installs using the 'domain' tenant source are affected.
 * A host is accepted when it is <one label>.<baseDomain>, or (with
 * $customDomains) any other host: the platform filter then answers 404
 * unless a workspace has that domain, so an unknown Host header never
 * gets a page, a link or an email built on it.
 */
final class TenantHosts
{
    /**
     * @return list<string>
     */
    public static function forRequest(?string $httpHost = null): array
    {
        $httpHost ??= $_SERVER['HTTP_HOST'] ?? null;

        if (! is_string($httpHost) || $httpHost === '') {
            return [];
        }

        $host = strtolower(explode(':', $httpHost, 2)[0]);

        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            return [];
        }

        try {
            // Runs while Config\App is being built: read the tenancy mode
            // without ci4-auth's module permission discovery.
            if (! config(ConfigResolver::resolvedClass())->isMultiTenant()) {
                return [];
            }

            $config = PlatformConfig::get();
        } catch (Throwable) {
            return [];
        }

        if (! in_array('domain', $config->tenantSources, true)) {
            return [];
        }

        $base = strtolower($config->baseDomain);

        if ($base !== '' && str_ends_with($host, '.' . $base) && ! str_contains(substr($host, 0, -strlen('.' . $base)), '.')) {
            return [$host];
        }

        if ($config->customDomains && $host !== $base && ! in_array($host, array_map('strtolower', $config->centralHosts), true)) {
            return [$host];
        }

        return [];
    }
}
