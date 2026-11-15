<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * Tenants (workspaces) of a multi-tenant install.
 *
 * The slug is the tenant identifier everywhere else: ci4-auth role
 * assignments, tokens bound to a tenant, the tenant_id column of
 * tenant-scoped tables, and the subdomain (<slug>.<baseDomain>).
 */
class TenantModel extends Model
{
    public const STATUSES = [
        'active'    => 'Active',
        'trial'     => 'Trial',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
    ];

    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * Slugs that would clash with hosts or routes the platform uses.
     */
    public const RESERVED_SLUGS = ['www', 'api', 'app', 'admin', 'mail', 'static', 'assets', 'cdn', 'status', 'help', 'support', 'docs', 'login', 'workspaces'];

    protected $table            = 'platform_tenants';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = ['slug', 'name', 'plan', 'status', 'domain', 'trial_ends_at', 'owner_user_id', 'meta'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->where('slug', strtolower(trim($slug)))->first();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByDomain(string $host): ?array
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        $row = $this->where('domain', $host)->first();

        return is_array($row) ? $row : null;
    }

    /**
     * Whether people may work in this tenant now.
     *
     * @param array<string, mixed> $tenant
     */
    public static function isUsable(array $tenant): bool
    {
        return match ((string) $tenant['status']) {
            'active' => true,
            'trial'  => empty($tenant['trial_ends_at']) || Time::parse((string) $tenant['trial_ends_at'])->isAfter(Time::now()),
            default  => false,
        };
    }

    /**
     * Why a tenant may not be used, for the page shown instead.
     *
     * @param array<string, mixed> $tenant
     */
    public static function blockedReason(array $tenant): string
    {
        return match ((string) $tenant['status']) {
            'trial'     => 'The trial of this workspace has ended.',
            'suspended' => 'This workspace is suspended.',
            'cancelled' => 'This workspace has been closed.',
            default     => 'This workspace is not available.',
        };
    }

    public static function validSlug(string $slug): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1 && ! in_array($slug, self::RESERVED_SLUGS, true);
    }
}
