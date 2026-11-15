<?php
/**
 * @var CodeIgniter\View\View                                         $this
 * @var list<array<string, mixed>>                                    $tenants
 * @var CodeIgniter\Pager\Pager                                       $pager
 * @var array<string, array{title: string, modules: list<string>}>    $plans
 * @var array<string, string>                                         $statuses
 * @var string                                                        $status
 * @var string                                                        $q
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>
<?= $this->section('actions') ?>
<a class="btn btn-primary" href="<?= url_to('core.tenants.new') ?>"><i class="bi bi-plus-lg me-1"></i>New workspace</a>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form class="row g-2 mb-3" method="get" action="<?= url_to('core.tenants') ?>" role="search">
    <div class="col-12 col-md-6">
        <label class="visually-hidden" for="tenant-q">Search workspaces</label>
        <input class="form-control" id="tenant-q" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Name, address or domain">
    </div>
    <div class="col-8 col-md-4">
        <label class="visually-hidden" for="tenant-status">Status</label>
        <select class="form-select" id="tenant-status" name="status">
            <option value="">Any status</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= esc($key, 'attr') ?>"<?= $status === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-4 col-md-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
</form>

<section class="eos-panel">
    <div class="table-responsive">
        <table class="table eos-table mb-0 align-middle">
            <thead>
                <tr>
                    <th scope="col">Workspace</th>
                    <th scope="col">Address</th>
                    <th scope="col">Plan</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tenants === []): ?>
                    <tr><td colspan="5" class="text-center eos-muted py-4">No workspaces<?= $q !== '' || $status !== '' ? ' match' : ' yet' ?>.</td></tr>
                <?php endif ?>
                <?php foreach ($tenants as $t): ?>
                    <tr>
                        <td><a class="fw-semibold" href="<?= url_to('core.tenants.show', $t['slug']) ?>"><?= esc($t['name']) ?></a></td>
                        <td class="font-tabular"><?= esc($t['domain'] ?: $t['slug']) ?></td>
                        <td><?= esc($plans[$t['plan']]['title'] ?? $t['plan'] . ' (unknown plan)') ?></td>
                        <td>
                            <?php $usable = Ephraitech\Platform\Models\TenantModel::isUsable($t); ?>
                            <span class="badge <?= $usable ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= esc($statuses[$t['status']] ?? $t['status']) ?></span>
                            <?php if ($t['status'] === 'trial' && $t['trial_ends_at']): ?><small class="eos-muted ms-1">until <?= esc(substr((string) $t['trial_ends_at'], 0, 10)) ?></small><?php endif ?>
                        </td>
                        <td class="font-tabular"><?= esc(substr((string) $t['created_at'], 0, 10)) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>
