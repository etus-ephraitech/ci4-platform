<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Ephraitech\Platform\Libraries\Dashboards;

class DashboardController extends BaseModuleController
{
    public function index(?string $board = null): string|RedirectResponse
    {
        if (! can('dashboard.view')) {
            $this->response->setStatusCode(403);

            return $this->page('Ephraitech\Platform\Views\dashboard\no_access', 'Welcome', ['user' => user()]);
        }

        $boards = Dashboards::visibleWidgets();
        $all    = Dashboards::all();
        $open   = Dashboards::allowedBoards();

        if ($board === null) {
            $board = Dashboards::landing($boards);
        } elseif (! in_array($board, $open, true) || Dashboards::onlyPersonal($boards, $board)) {
            return redirect()->route('core.dashboard');
        }

        $switcher = [];

        foreach ($open as $key) {
            if (! Dashboards::onlyPersonal($boards, $key)) {
                $switcher[$key] = $all[$key];
            }
        }

        return $this->page('Ephraitech\Platform\Views\dashboard\index', ($all[$board]['title'] ?? 'Dashboard'), [
            'widgets'  => $boards[$board] ?? [],
            'board'    => $board,
            'switcher' => $switcher,
            'user'     => user(),
        ]);
    }
}
