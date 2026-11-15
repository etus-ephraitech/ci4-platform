<?php
/**
 * @var CodeIgniter\View\View $this
 * @var string                $term
 * @var list<array{type: string, title: string, subtitle: string, url: string, icon: string}> $results
 */
$grouped = [];
foreach ($results as $result) {
    $grouped[$result['type']][] = $result;
}
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('content') ?>
<form class="eos-search-page" method="get" action="<?= url_to('core.search') ?>" role="search">
    <label class="visually-hidden" for="search-page-q">Search</label>
    <input class="form-control form-control-lg" id="search-page-q" type="search" name="q" value="<?= esc($term, 'attr') ?>" placeholder="Clients, leads, quotations…" autofocus>
    <button class="btn btn-primary btn-lg" type="submit">Search</button>
</form>

<?php if (mb_strlen($term) < 2): ?>
    <div class="eos-empty">
        <i class="bi bi-search" aria-hidden="true"></i>
        <p>Type at least two letters: a name, code, phone number or email.</p>
    </div>
<?php elseif ($results === []): ?>
    <div class="eos-empty">
        <i class="bi bi-search" aria-hidden="true"></i>
        <p>Nothing matches “<?= esc($term) ?>”. Check the spelling, or search by code or phone number.</p>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-4">
        <?php foreach ($grouped as $type => $rows): ?>
            <section class="eos-panel" aria-labelledby="sr-<?= esc(md5($type), 'attr') ?>">
                <header class="eos-panel-head">
                    <h2 id="sr-<?= esc(md5($type), 'attr') ?>"><?= esc($type) ?></h2>
                    <span class="eos-count"><?= count($rows) ?></span>
                </header>
                <ul class="eos-result-list">
                    <?php foreach ($rows as $row): ?>
                        <li>
                            <i class="bi <?= esc($row['icon'], 'attr') ?>" aria-hidden="true"></i>
                            <div>
                                <a href="<?= esc($row['url'], 'attr') ?>" class="fw-semibold"><?= esc($row['title']) ?></a>
                                <?php if ($row['subtitle'] !== ''): ?><small class="d-block eos-muted"><?= esc($row['subtitle']) ?></small><?php endif ?>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            </section>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
