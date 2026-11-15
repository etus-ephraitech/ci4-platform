<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ephraitech\Platform\Config\PlatformConfig;

/**
 * Name and job title on the users table owned by ephraitech/ci4-auth.
 * Allow them in app/Config/Auth.php: $userAllowedFields = ['first_name', 'last_name', 'job_title'].
 * Columns the app already added are left alone. Needs the ci4-auth
 * migrations first (platform:migrate runs them first).
 */
class AddProfileFieldsToUsers extends Migration
{
    private const COLUMNS = [
        'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        'last_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        'job_title'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
    ];

    public function up(): void
    {
        $table    = PlatformConfig::auth()->table('users');
        $existing = $this->db->getFieldNames($table);
        $add      = array_diff_key(self::COLUMNS, array_flip($existing));

        if ($add !== []) {
            $this->forge->addColumn($table, $add);
        }
    }

    /**
     * Leaves the columns in place: they hold people's names, and the app
     * may have had them before this migration ran.
     */
    public function down(): void
    {
    }
}
