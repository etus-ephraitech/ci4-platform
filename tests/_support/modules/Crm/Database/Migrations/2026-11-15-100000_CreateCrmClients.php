<?php

declare(strict_types=1);

namespace Modules\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ephraitech\Platform\Tenancy\TenantColumn;

class CreateCrmClients extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'   => ['type' => 'INTEGER', 'auto_increment' => true],
            ...TenantColumn::field(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('crm_clients', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('crm_clients', true);
    }
}
