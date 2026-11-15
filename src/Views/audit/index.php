<?php
/**
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $rows
 * @var CodeIgniter\Pager\Pager $pager
 * @var array<string, ?string> $filters
 * @var list<string> $modules
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('content') ?>
<form class="eos-filters" method="get" action="<?= url_to('core.audit') ?>" role="search">
    <div>
        <label class="form-label" for="f-q">Search</label>
        <input class="form-control" id="f-q" name="q" type="search" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Description or record">
    </div>
    <div>
        <label class="form-label" for="f-module">Module</label>
        <select class="form-select" id="f-module" name="module">
            <option value="">All modules</option>
            <?php foreach ($modules as $module): ?>
                <option value="<?= esc($module, 'attr') ?>"<?= ($filters['module'] ?? null) === $module ? ' selected' : '' ?>><?= esc($module) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="f-from">From</label>
        <input class="form-control" id="f-from" name="from" type="date" value="<?= esc($filters['from'] ?? '', 'attr') ?>">
    </div>
    <div>
        <label class="form-label" for="f-to">To</label>
        <input class="form-control" id="f-to" name="to" type="date" value="<?= esc($filters['to'] ?? '', 'attr') ?>">
    </div>
    <div class="eos-filters-actions">
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-link" href="<?= url_to('core.audit') ?>">Clear</a>
    </div>
</form>

<section class="eos-panel">
    <?php if ($rows === []): ?>
        <p class="eos-muted mb-0">No entries match these filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table eos-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Who</th>
                        <th scope="col">What happened</th>
                        <th scope="col">Module</th>
                        <th scope="col">Changes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $changes = $row['changes'] ? json_decode((string) $row['changes'], true) : []; ?>
                        <tr>
                            <td class="text-nowrap font-tabular"><time datetime="<?= esc($row['created_at'], 'attr') ?>"><?= esc(date('j M Y, H:i', strtotime((string) $row['created_at']))) ?></time></td>
                            <td class="text-nowrap"><?= esc(display_name($row)) ?></td>
                            <td>
                                <?= esc($row['description']) ?>
                                <?php if ($row['subject_type']): ?>
                                    <small class="d-block eos-muted"><?= esc($row['subject_type']) ?> <?= esc((string) $row['subject_id']) ?></small>
                                <?php endif ?>
                            </td>
                            <td><?= esc($row['module']) ?></td>
                            <td>
                                <?php if (is_array($changes) && $changes !== []): ?>
                                    <details>
                                        <summary><?= count($changes) ?> field<?= count($changes) === 1 ? '' : 's' ?></summary>
                                        <dl class="eos-changes">
                                            <?php foreach ($changes as $field => $change): ?>
                                                <dt><?= esc((string) $field) ?></dt>
                                                <dd>
                                                    <del><?= esc(is_scalar($change['from'] ?? null) ? (string) $change['from'] : '—') ?></del>
                                                    <ins><?= esc(is_scalar($change['to'] ?? null) ? (string) $change['to'] : '—') ?></ins>
                                                </dd>
                                            <?php endforeach ?>
                                        </dl>
                                    </details>
                                <?php else: ?>
                                    <span class="eos-muted">—</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3"><?= $pager->links('default', 'default_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
