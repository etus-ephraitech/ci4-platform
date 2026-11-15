<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Parent of every module's web controller. Loads the platform helpers and
 * renders views inside the main layout with a page title and breadcrumbs.
 *
 *     return $this->page('Modules\Crm\Views\clients\index', 'Clients', ['rows' => $rows], [['Clients']]);
 *
 * Before any page runs, the event 'platform.page' fires with the
 * controller. Listeners may throw a RedirectException, for example to send
 * client-portal accounts to the portal instead of staff screens.
 */
abstract class BaseModuleController extends Controller
{
    /**
     * @var list<string>
     */
    protected $helpers = ['platform', 'url', 'form'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        Events::trigger('platform.page', $this);
    }

    /**
     * @param array<string, mixed>                $data
     * @param list<array{0: string, 1?: ?string}> $breadcrumbs label, optional URL
     */
    protected function page(string $view, string $title, array $data = [], array $breadcrumbs = []): string
    {
        return view($view, array_merge($data, [
            'pageTitle'   => $title,
            'breadcrumbs' => $breadcrumbs,
        ]));
    }
}
