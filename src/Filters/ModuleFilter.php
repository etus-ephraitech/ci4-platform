<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Ephraitech\Platform\Http\Respond;

/**
 * 404 unless every named module is switched on here.
 *
 *     'filter' => ['module:Crm', 'can:session,crm.view']
 *     'filter' => ['module:Crm,Sales', ...]               // both needed
 *
 * The platform filter already gates every route whose controller lives in
 * a module. Use this one for routes outside modules (app controllers,
 * closures) that depend on a module.
 */
final class ModuleFilter implements FilterInterface
{
    /**
     * @param array<int, string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $gate = service('moduleGate');

        foreach ((array) $arguments as $module) {
            $module = trim((string) $module);

            if ($module !== '' && ! $gate->enabled($module)) {
                return Respond::notFound($request, 'This page is not available.');
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
}
