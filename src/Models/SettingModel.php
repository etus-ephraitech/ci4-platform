<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Models;

use CodeIgniter\Model;

/**
 * Stored setting values. tenant_id '' holds single-install settings and,
 * in a multi-tenant install, the global ones; other rows belong to a tenant.
 */
class SettingModel extends Model
{
    protected $table            = 'platform_settings';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = ['tenant_id', 'group_name', 'setting_key', 'value', 'updated_by'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
}
