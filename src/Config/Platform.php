<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Config;

use CodeIgniter\Config\BaseConfig;
use Ephraitech\Platform\Module\Resolvers\ManifestDefaultResolver;
use Ephraitech\Platform\Module\Resolvers\PlanResolver;
use Ephraitech\Platform\Module\Resolvers\StoredStateResolver;

/**
 * Platform settings. Override in app/Config/Platform.php:
 *
 *     namespace Config;
 *     class Platform extends \Ephraitech\Platform\Config\Platform { ... }
 *
 * Single tenant or multi-tenant is NOT set here: it follows
 * Config\Auth::$tenancy ('single' or 'multi'), so authorization and the
 * platform can never disagree.
 *
 * Installed modules are chosen in .env, because they are read at boot
 * before any config class exists:
 *     platform.modulesOff  = Crm, Sales      (never installed)
 *     platform.modulesOnly = Crm, Sales      (only these, plus Core and Users)
 *     platform.modulePaths = modules         (folders holding app modules)
 */
class Platform extends BaseConfig
{
    // ------------------------------------------------------------------
    // Product
    // ------------------------------------------------------------------

    /**
     * Product name: browser titles, the sign-in page and emails, until a
     * company (single) or workspace (multi) name is set.
     */
    public string $appName = 'Platform';

    /**
     * One line under the name on the sign-in page.
     */
    public string $tagline = 'Everything your team runs on, in one place.';

    /**
     * Browser theme colour and the sidebar background.
     */
    public string $themeColor = '#021f5e';

    /**
     * Logo files, as paths under public/. NULL uses the platform's default
     * mark (vendor/platform/brand/...). Keep your own in public/assets/brand.
     *
     * @var array{logo: ?string, logoOnDark: ?string, mark: ?string, markOnDark: ?string}
     */
    public array $brand = [
        'logo'       => null,
        'logoOnDark' => null,
        'mark'       => null,
        'markOnDark' => null,
    ];

    /**
     * Where platform:publish copies the CSS, JS and default brand files,
     * under public/.
     */
    public string $assetPath = 'vendor/platform';

    // ------------------------------------------------------------------
    // Navigation
    // ------------------------------------------------------------------

    /**
     * Sidebar sections shown first, in this order.
     *
     * @var list<string>
     */
    public array $menuGroupsFirst = ['Overview'];

    /**
     * Sidebar sections shown last, in this order. Sections not listed in
     * either list are shown between them, alphabetically.
     *
     * @var list<string>
     */
    public array $menuGroupsLast = ['Administration'];

    /**
     * Roles never given or listed on the Staff screens, e.g. ['client']
     * for client-portal accounts managed elsewhere. People whose only
     * roles are these do not appear under Staff.
     *
     * @var list<string>
     */
    public array $staffExcludedRoles = [];

    /**
     * Board a person lands on when nothing else applies (see Dashboards).
     */
    public string $defaultBoard = 'home';

    // ------------------------------------------------------------------
    // Module gate
    // ------------------------------------------------------------------

    /**
     * Resolvers deciding whether an installed module is on, asked in
     * order; the first that answers (true or false) wins. Always-on modules
     * and missing requirements are handled by the gate itself.
     *
     *   single  one install per client: the admin's switches (Settings →
     *           Modules), else the manifest's 'default'.
     *   multi   per-tenant overrides (add-ons, trials, with an expiry),
     *           else the tenant's plan.
     *
     * Add your own (implementing ModuleResolver) to decide from a billing
     * system, feature flags or a licence file.
     *
     * @var array{single: list<class-string>, multi: list<class-string>}
     */
    public array $resolvers = [
        'single' => [StoredStateResolver::class, ManifestDefaultResolver::class],
        'multi'  => [StoredStateResolver::class, PlanResolver::class],
    ];

    /**
     * Seconds the switched-on module list of a tenant is cached. Changes
     * made through the platform clear it at once.
     */
    public int $gateCacheTtl = 300;

    // ------------------------------------------------------------------
    // Multi-tenant
    // ------------------------------------------------------------------

    /**
     * Plans: which modules a tenant on each plan gets. '*' means every
     * installed module. Always-on modules (Core, Users) are included anyway.
     *
     * @var array<string, array{title: string, modules: list<string>, description?: string}>
     */
    public array $plans = [
        'standard' => ['title' => 'Standard', 'modules' => ['*'], 'description' => 'Every installed module.'],
    ];

    /**
     * Plan given to new tenants.
     */
    public string $defaultPlan = 'standard';

    /**
     * How a request finds its tenant, tried in order:
     *
     *   domain   the host: <slug>.<baseDomain>, or a tenant's own domain
     *   header   Config\Auth::$tenantHeader (X-Tenant-ID), for API clients;
     *            a token bound to a tenant implies that tenant
     *   session  the workspace a signed-in person picked under /workspaces
     *
     * @var list<'domain'|'header'|'session'>
     */
    public array $tenantSources = ['domain', 'header', 'session'];

    /**
     * Domain under which tenants get subdomains, e.g. 'app.example.com'
     * for acme.app.example.com. Empty: no subdomains.
     */
    public string $baseDomain = '';

    /**
     * Hosts that never belong to a tenant: the platform's own admin and
     * sign-in host. The bare baseDomain is always central.
     *
     * @var list<string>
     */
    public array $centralHosts = [];

    /**
     * Let tenants use their own domain (platform_tenants.domain).
     */
    public bool $customDomains = true;

    /**
     * Session key holding the workspace picked under /workspaces.
     */
    public string $tenantSessionKey = 'platform_tenant';

    /**
     * Role given to the person who creates a tenant (tenants:create
     * --owner, or the Tenants screen). Must exist in Config\Auth::$roles.
     */
    public string $tenantOwnerRole = 'owner';

    // ------------------------------------------------------------------
    // API
    // ------------------------------------------------------------------

    /**
     * Prefix of the platform's own API routes and of module API routes
     * generated by make:module --api.
     */
    public string $apiPrefix = 'api/v1';

    /**
     * Largest page size API list endpoints accept.
     */
    public int $apiMaxPerPage = 100;

    // ------------------------------------------------------------------
    // Deploy
    // ------------------------------------------------------------------

    /**
     * PHP extensions deploy:check requires.
     *
     * @var list<string>
     */
    public array $requiredExtensions = ['intl', 'mbstring', 'mysqli', 'curl', 'json', 'dom', 'openssl', 'fileinfo'];

    /**
     * Writable folders deploy:check checks, under writable/.
     *
     * @var list<string>
     */
    public array $writableFolders = ['cache', 'logs', 'session', 'uploads'];
}
