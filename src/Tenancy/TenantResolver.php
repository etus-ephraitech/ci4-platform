<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

use CodeIgniter\HTTP\IncomingRequest;
use Ephraitech\Platform\Config\Platform;
use Ephraitech\Platform\Config\PlatformConfig;
use Throwable;

/**
 * Works out which tenant a request is for (multi-tenant installs only).
 *
 * Sources are tried in Config\Platform::$tenantSources order:
 *
 *   domain   <slug>.<baseDomain>, or a tenant's own domain. The platform's
 *            own hosts (app.baseURL, baseDomain, centralHosts) are central.
 *            Any other host that matches no tenant is an error, so a
 *            mistyped subdomain or a forged Host header never falls through.
 *   header   the X-Tenant-ID header (Config\Auth::$tenantHeader), for API
 *            clients. A bearer token bound to a tenant implies that tenant;
 *            a header naming a different one is refused.
 *   session  the workspace picked under /workspaces, only when the request
 *            already carries a session cookie (API calls never start one).
 */
final class TenantResolver
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly ?Platform $config = null,
    ) {
    }

    public function resolve(IncomingRequest $request): TenantResolution
    {
        $config = $this->config ?? PlatformConfig::get();
        $bound  = $this->boundTenant($request);

        foreach ($config->tenantSources as $source) {
            $result = match ($source) {
                'domain'  => $this->fromDomain($request, $config),
                'header'  => $this->fromHeader($request, $bound),
                'session' => $this->fromSession($request, $config),
                default   => null,
            };

            if ($result !== null) {
                return $this->checkBinding($result, $bound);
            }
        }

        if ($bound !== null) {
            $tenant = $this->tenancy->find($bound);

            return $tenant === null
                ? TenantResolution::error('unknown_tenant', 'The tenant this key belongs to no longer exists.', 'token')
                : TenantResolution::found($tenant, 'token');
        }

        return TenantResolution::none();
    }

    /**
     * Whether the host is the platform's own (no tenant).
     */
    public function isCentralHost(string $host, ?Platform $config = null): bool
    {
        $config ??= $this->config ?? PlatformConfig::get();
        $host     = strtolower($host);

        $appHost = strtolower((string) parse_url((string) config('App')->baseURL, PHP_URL_HOST));

        return in_array($host, array_map('strtolower', $config->centralHosts), true)
            || ($config->baseDomain !== '' && $host === strtolower($config->baseDomain))
            || ($appHost !== '' && $host === $appHost);
    }

    // ------------------------------------------------------------------

    private function fromDomain(IncomingRequest $request, Platform $config): ?TenantResolution
    {
        $host = strtolower($request->getUri()->getHost());

        if ($host === '' || $this->isCentralHost($host, $config)) {
            return null;
        }

        $base = strtolower($config->baseDomain);

        if ($base !== '' && str_ends_with($host, '.' . $base)) {
            $slug = substr($host, 0, -strlen('.' . $base));

            if (str_contains($slug, '.')) {
                return TenantResolution::error('unknown_tenant', 'There is no workspace at this address.', 'domain');
            }

            $tenant = $this->tenancy->find($slug);

            return $tenant === null
                ? TenantResolution::error('unknown_tenant', 'There is no workspace at this address.', 'domain')
                : TenantResolution::found($tenant, 'domain');
        }

        if ($config->customDomains) {
            try {
                $tenant = model(\Ephraitech\Platform\Models\TenantModel::class, false)->findByDomain($host);
            } catch (Throwable) {
                $tenant = null;
            }

            // Any host that is not the platform's own must be a workspace's:
            // never serve (or build links on) an unknown Host header.
            return $tenant === null
                ? TenantResolution::error('unknown_tenant', 'There is no workspace at this address.', 'domain')
                : TenantResolution::found($tenant, 'domain');
        }

        return null;
    }

    private function fromHeader(IncomingRequest $request, ?string $bound): ?TenantResolution
    {
        $header = trim($request->getHeaderLine(PlatformConfig::auth()->tenantHeader));

        if ($header === '') {
            return null;
        }

        $tenant = $this->tenancy->find($header);

        return $tenant === null
            ? TenantResolution::error('unknown_tenant', 'There is no tenant "' . $header . '".', 'header')
            : TenantResolution::found($tenant, 'header');
    }

    private function fromSession(IncomingRequest $request, Platform $config): ?TenantResolution
    {
        $cookie = config('Session')->cookieName;

        if ($request->getCookie($cookie) === null) {
            return null;
        }

        $slug = session()->get($config->tenantSessionKey);

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $tenant = $this->tenancy->find($slug);

        if ($tenant === null) {
            session()->remove($config->tenantSessionKey);

            return null;
        }

        return TenantResolution::found($tenant, 'session');
    }

    /**
     * The tenant the request's bearer token is bound to, if any.
     */
    private function boundTenant(IncomingRequest $request): ?string
    {
        if (! str_starts_with(strtolower($request->getHeaderLine('Authorization')), 'bearer ')) {
            return null;
        }

        try {
            return service('auth')->token()->token()?->tenant();
        } catch (Throwable) {
            return null;
        }
    }

    private function checkBinding(TenantResolution $result, ?string $bound): TenantResolution
    {
        if ($bound !== null && $result->tenant !== null && (string) $result->tenant['slug'] !== $bound) {
            return TenantResolution::error('tenant_mismatch', 'This token is not valid for the requested tenant.', $result->source);
        }

        return $result;
    }
}
