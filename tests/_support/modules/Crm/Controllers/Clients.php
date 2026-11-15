<?php

declare(strict_types=1);

namespace Modules\Crm\Controllers;

use CodeIgniter\Controller;

class Clients extends Controller
{
    protected $helpers = ['platform'];

    public function index(): string
    {
        return 'crm index for [' . tenant_key() . ']';
    }

    public function ping(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON(['pong' => tenant_key()]);
    }
}
