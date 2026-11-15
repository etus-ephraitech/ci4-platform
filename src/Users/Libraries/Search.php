<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Libraries;

use Ephraitech\Platform\Config\PlatformConfig;

/**
 * Global search provider for people. Registered in module.php. Inside a
 * workspace only its members are found.
 */
class Search
{
    /**
     * @return list<array<string, string>>
     */
    public function find(string $term, int $limit): array
    {
        if (! can('users.view')) {
            return [];
        }

        helper(['url', 'platform']);

        $config = PlatformConfig::auth();
        $users  = $config->table('users');
        $ids    = $config->table('identities');

        $db      = db_connect($config->DBGroup);
        $builder = $db->table($users . ' u');

        if (service('tenancy')->isMulti() && service('tenancy')->has()) {
            $ur     = $db->prefixTable($config->table('user_roles'));
            $up     = $db->prefixTable($config->table('user_permissions'));
            $tenant = $db->escape(service('tenancy')->key());
            $builder->where("(EXISTS (SELECT 1 FROM {$ur} m WHERE m.user_id = u.id AND m.tenant_id = {$tenant}) OR EXISTS (SELECT 1 FROM {$up} m WHERE m.user_id = u.id AND m.tenant_id = {$tenant}))", null, false);
        }

        $rows = $builder
            ->select('u.id, u.first_name, u.last_name, u.job_title, u.status, MAX(CASE WHEN i.type = \'email\' THEN i.identifier END) AS email', false)
            ->join($ids . ' i', 'i.user_id = u.id', 'left')
            ->where('u.deleted_at', null)
            ->groupStart()
                ->like('u.first_name', $term)
                ->orLike('u.last_name', $term)
                ->orLike('u.job_title', $term)
                ->orGroupStart()->whereIn('i.type', ['email', 'phone'])->like('i.identifier', $term)->groupEnd()
            ->groupEnd()
            ->groupBy('u.id, u.first_name, u.last_name, u.job_title, u.status')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $r): array => [
            'id'       => (string) $r['id'],
            'type'     => 'Person',
            'title'    => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ((string) $r['email'] ?: 'User #' . $r['id']),
            'subtitle' => implode(' · ', array_filter([$r['job_title'], $r['email'], $r['status'] !== 'active' ? ucfirst((string) $r['status']) : null])),
            'url'      => url_to('users.staff.show', $r['id']),
            'icon'     => 'bi-person-badge',
        ], $rows);
    }
}
