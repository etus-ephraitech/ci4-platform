<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ephraitech\Platform\Tenancy\TenantColumn;

/**
 * Last document number handed out per tenant, sequence and period.
 */
class CreatePlatformSequencesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            ...TenantColumn::field(),
            'name'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'period'      => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => ''],
            'last_number' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['tenant_id', 'name', 'period']);
        $this->forge->createTable('platform_sequences', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('platform_sequences', true);
    }
}
