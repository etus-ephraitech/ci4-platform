<?php
/**
 * @var CodeIgniter\View\View $this
 * @var list<array>           $widgets
 * @var ?Ephraitech\Auth\Entities\User $user
 * @var string                $board
 * @var array<string, array{title: string, icon: string}> $switcher
 */
$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first    = $user?->first_name ?: display_name($user);
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('content') ?>
<section class="eos-welcome" aria-label="Welcome">
    <h2><?= esc($greeting) ?>, <?= esc($first) ?>.</h2>
    <?php $tagline = trim((string) setting('company.tagline', '')); ?>
    <p><?= esc(date('l j F Y')) ?>.<?= $tagline !== '' ? ' ' . esc(rtrim($tagline, '.')) . '.' : '' ?></p>
    <img class="eos-welcome-mark" src="<?= brand_asset('markOnDark') ?>" alt="" aria-hidden="true">
</section>

<?php if (count($switcher) > 1): ?>
    <nav class="eos-board-switch" aria-label="Dashboards">
        <?php foreach ($switcher as $key => $meta): ?>
            <a href="<?= url_to('core.dashboard.board', $key) ?>" class="<?= $key === $board ? 'is-active' : '' ?>"<?= $key === $board ? ' aria-current="page"' : '' ?>><i class="bi <?= esc($meta['icon'], 'attr') ?>" aria-hidden="true"></i> <?= esc($meta['title']) ?></a>
        <?php endforeach ?>
    </nav>
<?php endif ?>

<?php if ($widgets === []): ?>
    <div class="eos-empty">
        <i class="bi bi-grid-1x2" aria-hidden="true"></i>
        <p>Nothing to show yet. Panels appear here as modules are added and you are given access to them.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($widgets as $widget): ?>
            <?php
            // A panel with nothing to say (e.g. no approvals waiting) returns an empty string: skip its column.
            $html = (string) ($widget['params'] === [] ? view_cell($widget['cell']) : view_cell($widget['cell'], $widget['params']));
            if (trim($html) === '') {
                continue;
            }
            ?>
            <div class="col-12 col-xl-<?= (int) $widget['width'] ?>">
                <?= $html ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
