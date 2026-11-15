<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tenants (workspaces). Created in single installs too, where it stays
 * empty, so an install can move to multi-tenant later without new tables.
 */
class CreatePlatformTenantsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'slug'          => ['type' => 'VARCHAR', 'constraint' => 64],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'plan'          => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'domain'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'trial_ends_at' => ['type' => 'DATETIME', 'null' => true],
            'owner_user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'meta'          => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addUniqueKey('domain');
        $this->forge->addKey('status');
        $this->forge->createTable('platform_tenants', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('platform_tenants', true);
    }
}
