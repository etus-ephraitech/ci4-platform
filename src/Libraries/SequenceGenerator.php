<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Ephraitech\Platform\Module\ModuleRegistry;
use Ephraitech\Platform\Tenancy\Tenancy;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gap-free, concurrency-safe document numbers declared by modules
 * (module.php 'sequences'), counted per tenant:
 *
 *     'sequences' => ['invoice' => ['prefix' => 'INV', 'padding' => 4, 'reset' => 'yearly']]
 *
 *     next_number('invoice');   // INV-2026-0001
 *
 * reset: 'yearly' (INV-2026-0001), 'monthly' (INV-202611-0001) or 'never' (INV-0001).
 *
 * MySQL: LAST_INSERT_ID(expr) hands each request its own number without a
 * table lock. PostgreSQL: UPDATE ... RETURNING. SQLite: update and read in
 * one transaction (SQLite serialises writers). Call it inside
 * the transaction that saves the document, so a failed save does not
 * burn a number.
 */
class SequenceGenerator
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Tenancy $tenancy,
        private readonly BaseConnection $db,
    ) {
    }

    public function next(string $name): string
    {
        $definition = $this->definition($name);
        $period     = $this->period($definition['reset']);
        $tenant     = $this->tenancy->key();
        $table      = $this->db->prefixTable('platform_sequences');
        $now        = Time::now()->toDateTimeString();

        $this->db->query(
            $this->insertIgnore($table) . ' (tenant_id, name, period, last_number, created_at, updated_at) VALUES (?, ?, ?, 0, ?, ?)' . $this->onConflictIgnore(),
            [$tenant, $name, $period, $now, $now]
        );

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                "UPDATE {$table} SET last_number = LAST_INSERT_ID(last_number + 1), updated_at = ? WHERE tenant_id = ? AND name = ? AND period = ?",
                [$now, $tenant, $name, $period]
            );
            $number = (int) ($this->db->query('SELECT LAST_INSERT_ID() AS n')->getRowArray()['n'] ?? 0);
        } elseif ($this->db->DBDriver === 'Postgre') {
            $row = $this->db->query(
                "UPDATE {$table} SET last_number = last_number + 1, updated_at = ? WHERE tenant_id = ? AND name = ? AND period = ? RETURNING last_number",
                [$now, $tenant, $name, $period]
            )->getRowArray();
            $number = (int) ($row['last_number'] ?? 0);
        } else {
            // SQLite (and others): writers are serialised, so the update and
            // the read inside one transaction see the same number. (SQLite's
            // RETURNING is not used: reading its result re-runs the statement.)
            $this->db->transStart();
            $this->db->query(
                "UPDATE {$table} SET last_number = last_number + 1, updated_at = ? WHERE tenant_id = ? AND name = ? AND period = ?",
                [$now, $tenant, $name, $period]
            );
            $row = $this->db->query(
                "SELECT last_number FROM {$table} WHERE tenant_id = ? AND name = ? AND period = ?",
                [$tenant, $name, $period]
            )->getRowArray();
            $this->db->transComplete();
            $number = (int) ($row['last_number'] ?? 0);
        }

        if ($number < 1) {
            throw new RuntimeException("Could not allocate the next '{$name}' number.");
        }

        return $this->format($definition, $period, $number);
    }

    /**
     * The number next() would return, without allocating it (previews only).
     */
    public function peek(string $name): string
    {
        $definition = $this->definition($name);
        $period     = $this->period($definition['reset']);

        $row = $this->db->table('platform_sequences')
            ->select('last_number')
            ->where('tenant_id', $this->tenancy->key())
            ->where('name', $name)
            ->where('period', $period)
            ->get()
            ->getRowArray();

        return $this->format($definition, $period, ((int) ($row['last_number'] ?? 0)) + 1);
    }

    // ------------------------------------------------------------------

    private function insertIgnore(string $table): string
    {
        return match ($this->db->DBDriver) {
            'MySQLi'  => "INSERT IGNORE INTO {$table}",
            'SQLite3' => "INSERT OR IGNORE INTO {$table}",
            default   => "INSERT INTO {$table}",
        };
    }

    private function onConflictIgnore(): string
    {
        return $this->db->DBDriver === 'Postgre' ? ' ON CONFLICT DO NOTHING' : '';
    }

    /**
     * @return array{prefix: string, padding: int, reset: string}
     */
    private function definition(string $name): array
    {
        $sequences = $this->registry->sequences();

        if (! isset($sequences[$name])) {
            throw new InvalidArgumentException("No sequence named '{$name}'. Declare it in a module's module.php.");
        }

        return $sequences[$name];
    }

    private function period(string $reset): string
    {
        return match ($reset) {
            'yearly'  => Time::now()->format('Y'),
            'monthly' => Time::now()->format('Ym'),
            'never'   => '',
            default   => throw new InvalidArgumentException("Sequence reset must be yearly, monthly or never; got '{$reset}'."),
        };
    }

    /**
     * @param array{prefix: string, padding: int, reset: string} $definition
     */
    private function format(array $definition, string $period, int $number): string
    {
        $parts = [$definition['prefix']];

        if ($period !== '') {
            $parts[] = $period;
        }

        $parts[] = str_pad((string) $number, $definition['padding'], '0', STR_PAD_LEFT);

        return implode('-', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
