<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Models\TenantModel;

/**
 * Multi-tenant: the workspaces a signed-in person belongs to, and
 * switching between them (one shared domain), or links to each
 * workspace's own address (subdomains, own domains).
 */
class WorkspacesController extends BaseModuleController
{
    public function index(): string
    {
        $tenancy    = service('tenancy');
        $workspaces = [];

        foreach ($tenancy->tenantsFor((int) user_id()) as $tenant) {
            $workspaces[] = $tenant + [
                'usable'  => TenantModel::isUsable($tenant),
                'url'     => $tenancy->urlFor($tenant),
                'current' => $tenancy->key() === $tenant['slug'],
            ];
        }

        $config = PlatformConfig::get();

        return view('Ephraitech\Platform\Views\workspaces\index', [
            'pageTitle'  => 'Your workspaces',
            'workspaces' => $workspaces,
            'switchHere' => ! in_array('domain', $config->tenantSources, true) || ($config->baseDomain === '' && ! $config->customDomains),
            'current'    => $tenancy->current(),
            'central'    => service('authAuthorizer')->rolesFor((int) user_id(), '') !== [],
        ]);
    }

    public function enter(string $slug): RedirectResponse
    {
        $tenancy = service('tenancy');
        $tenant  = $tenancy->find($slug);

        if ($tenant === null || ! $tenancy->belongs((int) user_id(), $slug)) {
            return redirect()->route('core.workspaces')->with('error', 'You are not a member of that workspace.');
        }

        if (! TenantModel::isUsable($tenant)) {
            return redirect()->route('core.workspaces')->with('error', TenantModel::blockedReason($tenant));
        }

        session()->set(PlatformConfig::get()->tenantSessionKey, $tenant['slug']);
        session()->regenerate();

        return redirect()->route('core.dashboard')->with('message', 'You are working in ' . $tenant['name'] . '.');
    }

    public function leave(): RedirectResponse
    {
        session()->remove(PlatformConfig::get()->tenantSessionKey);

        // On a workspace's subdomain the picker is on the platform's host.
        return redirect()->to(service('tenancy')->centralUrl('core.workspaces'));
    }
}
