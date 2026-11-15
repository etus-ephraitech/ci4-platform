<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use Closure;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Answers a DataTables server-side request from a query builder.
 *
 * The builder is used as given: for tenant-scoped tables add
 * ->where('crm_clients.tenant_id', tenant_key()) yourself.
 *
 *     return $this->response->setJSON(
 *         (new DataTable($builder, $this->request))
 *             ->searchable(['crm_clients.name', 'crm_clients.code'])
 *             ->orderable([0 => 'crm_clients.code', 1 => 'crm_clients.name'])
 *             ->defaultOrder('crm_clients.id', 'desc')
 *             ->format(fn (array $row): array => [...])
 *             ->response()
 *     );
 *
 * Column indexes in orderable() match the table's column order in the view.
 */
class DataTable
{
    private const MAX_LENGTH = 200;

    /**
     * @var list<string>
     */
    private array $searchable = [];

    /**
     * @var array<int, string>
     */
    private array $orderable = [];

    private string $defaultColumn = 'id';

    private string $defaultDirection = 'desc';

    private ?Closure $formatter = null;

    private ?Closure $preparer = null;

    public function __construct(
        private readonly BaseBuilder $builder,
        private readonly IncomingRequest $request,
    ) {
    }

    /**
     * @param list<string> $columns qualified column names matched with LIKE
     */
    public function searchable(array $columns): static
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param array<int, string> $columns view column index => qualified column
     */
    public function orderable(array $columns): static
    {
        $this->orderable = $columns;

        return $this;
    }

    public function defaultOrder(string $column, string $direction = 'desc'): static
    {
        $this->defaultColumn    = $column;
        $this->defaultDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * Called once with the page's raw rows before they are formatted, to
     * load related data for all of them in one query (roles, totals).
     *
     * @param Closure(list<array<string, mixed>>): void $preparer
     */
    public function prepare(Closure $preparer): static
    {
        $this->preparer = $preparer;

        return $this;
    }

    /**
     * @param Closure(array<string, mixed>): array<int|string, mixed> $formatter
     */
    public function format(Closure $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<int|string, mixed>>}
     */
    public function response(): array
    {
        $total = (clone $this->builder)->countAllResults();

        $filteredBuilder = clone $this->builder;
        $term            = trim((string) ($this->request->getGet('search')['value'] ?? ''));

        if ($term !== '' && $this->searchable !== []) {
            $filteredBuilder->groupStart();

            foreach ($this->searchable as $i => $column) {
                $i === 0
                    ? $filteredBuilder->like($column, $term)
                    : $filteredBuilder->orLike($column, $term);
            }

            $filteredBuilder->groupEnd();
        }

        $filtered = (clone $filteredBuilder)->countAllResults();

        $order     = $this->request->getGet('order')[0] ?? null;
        $column    = is_array($order) ? ($this->orderable[(int) ($order['column'] ?? -1)] ?? null) : null;
        $direction = is_array($order) && strtolower((string) ($order['dir'] ?? '')) === 'asc' ? 'asc' : 'desc';

        $column === null
            ? $filteredBuilder->orderBy($this->defaultColumn, $this->defaultDirection)
            : $filteredBuilder->orderBy($column, $direction);

        $start  = max(0, (int) $this->request->getGet('start'));
        $length = (int) $this->request->getGet('length');
        $length = $length < 1 ? 25 : min($length, self::MAX_LENGTH);

        $rows = $filteredBuilder->limit($length, $start)->get()->getResultArray();

        if ($this->preparer !== null) {
            ($this->preparer)($rows);
        }

        $data = $this->formatter === null ? $rows : array_map($this->formatter, $rows);

        return [
            'draw'            => (int) $this->request->getGet('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => array_values($data),
        ];
    }
}
