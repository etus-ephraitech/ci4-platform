<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Http\Respond;
use Ephraitech\Platform\Models\TenantModel;
use Throwable;

/**
 * The platform's global "before" filter. List it first in
 * app/Config/Filters.php $globals['before'] (deploy:check fails without it):
 *
 *     public array $globals = ['before' => ['platform', 'csrf' => ['except' => ['api/*']]], 'after' => []];
 *
 * On every request, before any route filter (auth:, can:) runs:
 *
 *   1. Multi-tenant: find the tenant (domain, header, token, session) and
 *      enter it, so ci4-auth and tenant-scoped models use it. Unknown
 *      tenants get 404; suspended or expired ones a page saying so; a
 *      token bound to another tenant 403.
 *   2. The module gate: when the matched controller belongs to a module
 *      that is switched off here, answer 404 (JSON for API calls).
 *   3. Multi-tenant: a signed-in person (session or bearer token) who has
 *      no role or permission in the tenant is refused.
 *   4. Multi-tenant, no tenant selected: a signed-in person with no
 *      platform-wide role who opens a module page is taken into their only
 *      workspace, or to the workspace picker.
 *
 * Routes whose controller is outside every module (sign-in pages, closures)
 * are never gated; use the 'module:Name' filter on those that should be.
 */
final class PlatformFilter implements FilterInterface
{
    /**
     * @param array<int, string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $tenancy = service('tenancy');

        if ($tenancy->isMulti()) {
            $resolution = service('tenantResolver')->resolve($request);

            if ($resolution->failed()) {
                return $resolution->error === 'tenant_mismatch'
                    ? Respond::json(403, 'tenant_mismatch', (string) $resolution->message)
                    : Respond::notFound($request, (string) $resolution->message);
            }

            if ($resolution->tenant !== null) {
                if (! TenantModel::isUsable($resolution->tenant)) {
                    return Respond::blocked($request, 403, 'tenant_unavailable', 'Workspace unavailable', TenantModel::blockedReason($resolution->tenant), [
                        'tenant' => $resolution->tenant,
                    ]);
                }

                $tenancy->enter($resolution->tenant);
            }
        }

        $controller = service('router')->controllerName();
        $module     = is_string($controller) ? service('moduleGate')->moduleForClass($controller) : null;

        if ($tenancy->isMulti() && ! $tenancy->has() && $module !== null && ! Respond::wantsJson($request)
            && ! (is_string($controller) && str_ends_with($controller, '\\WorkspacesController'))) {
            $redirect = $this->pickWorkspace($request);

            if ($redirect !== null) {
                return $redirect;
            }
        }

        if ($module !== null && ! service('moduleGate')->enabled($module)) {
            return Respond::notFound($request, 'This page is not available.');
        }

        if ($tenancy->isMulti() && $tenancy->has()) {
            $userId = $this->currentUserId($request);

            if ($userId !== null && ! $tenancy->belongs($userId, $tenancy->key())) {
                return $this->notAMember($request, $resolution->source ?? null);
            }
        }

        return null;
    }

    /**
     * @param array<int, string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    // ------------------------------------------------------------------

    /**
     * The signed-in person, without starting a session for API calls.
     */
    private function currentUserId(IncomingRequest $request): ?int
    {
        try {
            if (str_starts_with(strtolower($request->getHeaderLine('Authorization')), 'bearer ')) {
                return service('auth')->token()->id();
            }

            if ($request->getCookie(config('Session')->cookieName) !== null) {
                return service('auth')->session()->id();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * A signed-in person with no workspace selected: enter their only
     * workspace, or show the picker. Platform-wide role holders stay central.
     */
    private function pickWorkspace(IncomingRequest $request): ?ResponseInterface
    {
        if ($request->getCookie(config('Session')->cookieName) === null) {
            return null;
        }

        try {
            $userId = service('auth')->session()->id();
        } catch (Throwable) {
            return null;
        }

        if ($userId === null || service('authAuthorizer')->rolesFor($userId, '') !== []) {
            return null;
        }

        $tenancy = service('tenancy');
        $usable  = array_values(array_filter($tenancy->tenantsFor($userId), static fn (array $t): bool => TenantModel::isUsable($t)));

        if (count($usable) === 1) {
            $url    = $tenancy->urlFor($usable[0]);
            $picker = url_to('core.workspaces');

            if ($url !== $picker) {
                // The workspace has its own address (subdomain or domain).
                return redirect()->to($url);
            }

            session()->set(PlatformConfig::get()->tenantSessionKey, $usable[0]['slug']);

            return redirect()->to((string) current_url(true));
        }

        return redirect()->route('core.workspaces');
    }

    private function notAMember(IncomingRequest $request, ?string $source): ResponseInterface
    {
        if (Respond::wantsJson($request)) {
            return Respond::json(403, 'no_tenant_access', 'You do not have access to this workspace.');
        }

        if ($source === 'session') {
            session()->remove(PlatformConfig::get()->tenantSessionKey);
            service('tenancy')->leave();

            return redirect()->route('core.workspaces')->with('error', 'You no longer have access to that workspace. Pick another.');
        }

        return Respond::blocked($request, 403, 'no_tenant_access', 'No access', 'You are signed in, but not as a member of this workspace.', [
            'tenant'  => service('tenancy')->current(),
            'signOut' => true,
        ]);
    }
}
