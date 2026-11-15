<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

use CodeIgniter\Database\Forge;

/**
 * The tenant_id column for tenant-scoped tables, in migrations:
 *
 *     $this->forge->addField([
 *         'id'   => [...],
 *         ...TenantColumn::field(),
 *         'name' => [...],
 *     ]);
 *     $this->forge->addKey('tenant_id');
 *     $this->forge->addUniqueKey(['tenant_id', 'code']);   // unique per tenant
 *
 * or, for an existing table: TenantColumn::add($this->forge, 'crm_clients');
 *
 * The column is VARCHAR(64) defaulting to '' (ci4-auth's tenant id
 * format), so single installs need no tenant and can become multi-tenant
 * later by filling it in.
 */
final class TenantColumn
{
    public const NAME = 'tenant_id';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function field(string $name = self::NAME): array
    {
        return [$name => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '', 'null' => false]];
    }

    /**
     * Add the column and its index to an existing table.
     */
    public static function add(Forge $forge, string $table, string $name = self::NAME): void
    {
        $db = $forge->getConnection();

        if (! in_array($name, $db->getFieldNames($table), true)) {
            $forge->addColumn($table, self::field($name));
        }

        $index = $table . '_' . $name;
        $exists = false;

        foreach ($db->getIndexData($table) as $key => $info) {
            if ($key === $index) {
                $exists = true;
            }
        }

        if (! $exists) {
            $db->query('CREATE INDEX ' . $db->escapeIdentifiers($db->DBPrefix . $index) . ' ON ' . $db->escapeIdentifiers($db->prefixTable($table)) . ' (' . $db->escapeIdentifiers($name) . ')');
        }
    }
}
