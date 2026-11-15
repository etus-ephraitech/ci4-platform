<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Ephraitech\Platform\Libraries\GlobalSearch;
use Ephraitech\Platform\Libraries\Navigation;

/**
 * The platform's own API: everything a mobile app, SPA or integration
 * needs to adapt to the install, tenant and person it is talking to.
 *
 *   GET {apiPrefix}/platform/context   who, which workspace, modules on, permissions, menu
 *   GET {apiPrefix}/platform/modules   installed modules with their state here
 *   GET {apiPrefix}/platform/search?q= global search across switched-on modules
 *
 * Multi-tenant clients send X-Tenant-ID (or use a token bound to a tenant).
 */
class PlatformController extends BaseApiController
{
    public function context(): ResponseInterface
    {
        $user    = user();
        $tenancy = service('tenancy');
        $tenant  = $tenancy->current();
        $gate    = service('moduleGate');
        $modules = [];

        foreach (service('moduleRegistry')->all() as $name => $module) {
            if ($gate->enabled($name)) {
                $modules[] = $this->moduleSummary($module, true);
            }
        }

        $menu = [];

        foreach (Navigation::menu() as $group => $items) {
            foreach ($items as $item) {
                $menu[] = ['group' => $group, 'label' => $item['label'], 'icon' => $item['icon'], 'url' => $item['url'], 'module' => $item['module']];
            }
        }

        return $this->respondData([
            'user' => [
                'id'        => (int) $user->id,
                'name'      => display_name($user),
                'email'     => $user->getEmail(),
                'job_title' => $user->job_title ?? null,
                'roles'     => service('authAuthorizer')->rolesFor($user),
            ],
            'tenancy'     => $tenancy->isMulti() ? 'multi' : 'single',
            'tenant'      => $tenant === null ? null : [
                'slug'   => $tenant['slug'],
                'name'   => $tenant['name'],
                'plan'   => $tenant['plan'],
                'status' => $tenant['status'],
            ],
            'name'        => platform_name(),
            'modules'     => $modules,
            'permissions' => service('authAuthorizer')->permissionsFor($user),
            'menu'        => $menu,
            'quick'       => array_map(static fn (array $a): array => ['label' => $a['label'], 'icon' => $a['icon'], 'url' => $a['url'], 'module' => $a['module']], Navigation::quickActions()),
        ]);
    }

    public function modules(): ResponseInterface
    {
        $gate      = service('moduleGate');
        $decisions = $gate->decisions();
        $out       = [];

        foreach (service('moduleRegistry')->all() as $name => $module) {
            $out[] = $this->moduleSummary($module, $decisions[$name]['on'] ?? false) + ['reason' => $decisions[$name]['source'] ?? ''];
        }

        return $this->respondData($out);
    }

    public function search(): ResponseInterface
    {
        $term    = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $limit   = max(1, min(25, (int) ($this->request->getGet('limit') ?? 10)));
        $results = (new GlobalSearch(service('moduleRegistry'), service('moduleGate')))->find($term, $limit);

        return $this->respondData($results, ['q' => $term]);
    }

    /**
     * @param array<string, mixed> $module
     *
     * @return array<string, mixed>
     */
    private function moduleSummary(array $module, bool $on): array
    {
        return [
            'name'        => $module['name'],
            'title'       => $module['title'],
            'description' => $module['description'],
            'version'     => $module['version'],
            'on'          => $on,
            'api'         => $module['api'] === [] ? null : $module['api'] + ['url' => base_url($module['api']['base'])],
        ];
    }
}
