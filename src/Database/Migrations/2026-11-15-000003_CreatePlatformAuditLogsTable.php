<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ephraitech\Platform\Tenancy\TenantColumn;

/**
 * Who changed what, per tenant.
 */
class CreatePlatformAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            ...TenantColumn::field(),
            'user_id'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'module'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'action'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'subject_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'subject_id'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'description'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'changes'      => ['type' => 'TEXT', 'null' => true],
            'ip_address'   => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['tenant_id', 'created_at']);
        $this->forge->addKey('user_id');
        $this->forge->addKey(['module', 'action']);
        $this->forge->addKey(['subject_type', 'subject_id']);
        $this->forge->createTable('platform_audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('platform_audit_logs', true);
    }
}
