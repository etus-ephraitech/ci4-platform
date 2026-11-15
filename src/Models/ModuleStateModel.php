<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * Module switches set by people: in a single install the admin's on/off
 * (tenant_id ''), in a multi-tenant install a tenant's add-ons and
 * exclusions on top of its plan, optionally until a date (trials).
 */
class ModuleStateModel extends Model
{
    protected $table            = 'platform_module_states';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = ['tenant_id', 'module', 'enabled', 'expires_at', 'note', 'changed_by'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';

    /**
     * Current (not expired) switches of one tenant, by module.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forTenant(string $tenantId): array
    {
        $now = Time::now()->toDateTimeString();
        $out = [];

        foreach ($this->where('tenant_id', $tenantId)->groupStart()->where('expires_at', null)->orWhere('expires_at >', $now)->groupEnd()->findAll() as $row) {
            $out[(string) $row['module']] = $row;
        }

        return $out;
    }

    /**
     * Every switch of one tenant, expired ones included, by module.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allForTenant(string $tenantId): array
    {
        $out = [];

        foreach ($this->where('tenant_id', $tenantId)->findAll() as $row) {
            $out[(string) $row['module']] = $row;
        }

        return $out;
    }
}
