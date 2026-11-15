<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use CodeIgniter\Model;
use Ephraitech\Platform\Tenancy\TenantScoped;

class ClientModel extends Model
{
    use TenantScoped;

    protected $table         = 'crm_clients';
    protected $returnType    = 'array';
    protected $allowedFields = ['name'];
    protected $useTimestamps = false;

    public function countFor(): int
    {
        return $this->countAllResults();
    }
}
