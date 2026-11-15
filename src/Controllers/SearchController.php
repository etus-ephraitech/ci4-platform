<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Ephraitech\Platform\Libraries\GlobalSearch;

class SearchController extends BaseModuleController
{
    public function index(): string
    {
        $term = $this->term();

        return $this->page('Ephraitech\Platform\Views\search\index', 'Search', [
            'term'    => $term,
            'results' => $this->search()->find($term, 25),
        ], [['Search']]);
    }

    /**
     * Live results for the top-bar search box.
     */
    public function quick(): ResponseInterface
    {
        return $this->response->setJSON([
            'results' => $this->search()->find($this->term(), 5),
        ]);
    }

    private function search(): GlobalSearch
    {
        return new GlobalSearch(service('moduleRegistry'), service('moduleGate'));
    }

    private function term(): string
    {
        return mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
    }
}
