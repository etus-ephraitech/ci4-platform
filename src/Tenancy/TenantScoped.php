<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

use CodeIgniter\Database\BaseResult;

/**
 * Keeps a model's rows inside the current tenant.
 *
 *     class ClientModel extends \CodeIgniter\Model
 *     {
 *         use \Ephraitech\Platform\Tenancy\TenantScoped;
 *         protected $table = 'crm_clients';
 *     }
 *
 * The table needs a tenant_id column (TenantColumn::add() in a migration).
 * Every find, first, findAll, paginate, countAllResults, insert, update and
 * delete made through the model is limited to the current tenant, and new
 * rows get the tenant's id. In a single install the tenant id is always
 * '' and the scope changes nothing, so the same module code runs in both.
 *
 * In a multi-tenant install with no tenant selected, a scoped model
 * refuses to run (TenantRequiredException) unless it overrides
 * tenantRequired() to return false, in which case it works on the central
 * ('') rows. Override tenantField() for a column other than tenant_id.
 *
 * To work across tenants on purpose (reports for the platform
 * owner, a cron job), call withoutTenantScope() for the next query.
 *
 * Queries written with $model->builder() or db_connect() directly are NOT
 * scoped: add ->where('tenant_id', tenant_key()) yourself.
 */
trait TenantScoped
{
    private bool $tenantScopeOff = false;

    /**
     * Column holding the tenant id. Override to use another.
     */
    protected function tenantField(): string
    {
        return 'tenant_id';
    }

    /**
     * Refuse to query with no tenant selected (multi-tenant only).
     * Override to return false for models that also keep central rows.
     */
    protected function tenantRequired(): bool
    {
        return true;
    }

    /**
     * Skip the tenant scope for the next query only.
     */
    public function withoutTenantScope(): static
    {
        $this->tenantScopeOff = true;

        return $this;
    }

    /**
     * The tenant id rows of this model are written with now.
     */
    public function currentTenantKey(): string
    {
        $tenancy = service('tenancy');
        $key     = $tenancy->key();

        if ($key === '' && $tenancy->isMulti() && $this->tenantRequired()) {
            throw TenantRequiredException::forModel(static::class);
        }

        return $key;
    }

    public function countAllResults(bool $reset = true, bool $test = false)
    {
        $this->applyTenantScope($reset);

        return parent::countAllResults($reset, $test);
    }

    protected function doFind(bool $singleton, $id = null)
    {
        $this->applyTenantScope();

        return parent::doFind($singleton, $id);
    }

    protected function doFindAll(?int $limit = null, int $offset = 0)
    {
        $this->applyTenantScope();

        return parent::doFindAll($limit, $offset);
    }

    protected function doFirst()
    {
        $this->applyTenantScope();

        return parent::doFirst();
    }

    protected function doInsert(array $row)
    {
        $row = $this->stampTenant($row);

        return parent::doInsert($row);
    }

    protected function doInsertBatch(?array $set = null, ?bool $escape = null, int $batchSize = 100, bool $testing = false)
    {
        if (is_array($set)) {
            $set = array_map(fn (array $row): array => $this->stampTenant($row), $set);
        }

        return parent::doInsertBatch($set, $escape, $batchSize, $testing);
    }

    protected function doUpdate($id = null, $row = null): bool
    {
        if (is_array($row)) {
            unset($row[$this->tenantField()]);
        }

        $this->applyTenantScope();

        return parent::doUpdate($id, $row);
    }

    protected function doUpdateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false)
    {
        if (is_array($set)) {
            $set = array_map(function (array $row): array {
                unset($row[$this->tenantField()]);

                return $row;
            }, $set);
        }

        $this->applyTenantScope();

        return parent::doUpdateBatch($set, $index, $batchSize, $returnSQL);
    }

    protected function doDelete($id = null, bool $purge = false)
    {
        $this->applyTenantScope();

        return parent::doDelete($id, $purge);
    }

    protected function doPurgeDeleted()
    {
        $this->applyTenantScope();

        return parent::doPurgeDeleted();
    }

    protected function doOnlyDeleted()
    {
        $this->applyTenantScope();

        parent::doOnlyDeleted();
    }

    // ------------------------------------------------------------------

    private function applyTenantScope(bool $reset = true): void
    {
        if ($this->tenantScopeOff) {
            if ($reset) {
                $this->tenantScopeOff = false;
            }

            return;
        }

        $this->builder()->where($this->table . '.' . $this->tenantField(), $this->currentTenantKey());
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function stampTenant(array $row): array
    {
        if ($this->tenantScopeOff && array_key_exists($this->tenantField(), $row)) {
            $this->tenantScopeOff = false;

            return $row;
        }

        $this->tenantScopeOff  = false;
        $row[$this->tenantField()] = $this->currentTenantKey();

        return $row;
    }
}
