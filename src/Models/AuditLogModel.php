<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Models;

use CodeIgniter\Model;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Tenancy\TenantScoped;

/**
 * The audit log, kept per tenant ('' in a single install and for the
 * platform owner's own actions in a multi-tenant one).
 */
class AuditLogModel extends Model
{
    use TenantScoped;

    protected $table            = 'platform_audit_logs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'tenant_id', 'user_id', 'module', 'action', 'subject_type', 'subject_id',
        'description', 'changes', 'ip_address', 'user_agent',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Central actions are logged with tenant ''.
     */
    protected function tenantRequired(): bool
    {
        return false;
    }

    /**
     * Audit rows joined to the acting person's name, newest first,
     * narrowed by the filters on the audit log page.
     *
     * @param array{module?: ?string, user_id?: ?string, from?: ?string, to?: ?string, q?: ?string, subject_type?: ?string, subject_id?: ?string} $filters
     */
    public function filtered(array $filters): static
    {
        $users = PlatformConfig::auth()->table('users');

        $this->select("{$this->table}.*, {$users}.first_name, {$users}.last_name")
            ->join($users, "{$users}.id = {$this->table}.user_id", 'left')
            ->orderBy("{$this->table}.id", 'DESC');

        if (! empty($filters['module'])) {
            $this->where("{$this->table}.module", $filters['module']);
        }

        if (! empty($filters['user_id']) && ctype_digit((string) $filters['user_id'])) {
            $this->where("{$this->table}.user_id", (int) $filters['user_id']);
        }

        if (! empty($filters['subject_type'])) {
            $this->where("{$this->table}.subject_type", $filters['subject_type']);
        }

        if (! empty($filters['subject_id'])) {
            $this->where("{$this->table}.subject_id", (string) $filters['subject_id']);
        }

        if (! empty($filters['from'])) {
            $this->where("{$this->table}.created_at >=", $filters['from'] . ' 00:00:00');
        }

        if (! empty($filters['to'])) {
            $this->where("{$this->table}.created_at <=", $filters['to'] . ' 23:59:59');
        }

        if (! empty($filters['q'])) {
            $this->groupStart()
                ->like("{$this->table}.description", $filters['q'])
                ->orLike("{$this->table}.subject_id", $filters['q'])
                ->groupEnd();
        }

        return $this;
    }
}
