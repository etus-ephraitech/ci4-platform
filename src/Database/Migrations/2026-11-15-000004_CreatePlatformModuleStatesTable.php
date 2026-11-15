<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ephraitech\Platform\Tenancy\TenantColumn;

/**
 * Module switches set by people: a single install's on/off (tenant ''),
 * a tenant's add-ons and exclusions on top of its plan.
 */
class CreatePlatformModuleStatesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            ...TenantColumn::field(),
            'module'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'enabled'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'note'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'changed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['tenant_id', 'module']);
        $this->forge->createTable('platform_module_states', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('platform_module_states', true);
    }
}
