<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ephraitech\Platform\Tenancy\TenantColumn;

/**
 * Setting values declared by modules, per tenant ('' for single installs
 * and global settings).
 */
class CreatePlatformSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            ...TenantColumn::field(),
            'group_name'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'setting_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'value'       => ['type' => 'TEXT', 'null' => true],
            'updated_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['tenant_id', 'group_name', 'setting_key']);
        $this->forge->createTable('platform_settings', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('platform_settings', true);
    }
}
