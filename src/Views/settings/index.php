<?php
/**
 * @var CodeIgniter\View\View $this
 * @var array<string, array<string, mixed>> $groups
 * @var string $current
 * @var Ephraitech\Platform\Forms\SettingsForm $form
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-12 col-lg-3">
        <nav class="eos-subnav" aria-label="Settings sections">
            <?php foreach ($groups as $key => $group): ?>
                <a href="<?= url_to('core.settings.group', $key) ?>" class="<?= $key === $current ? 'is-active' : '' ?>"<?= $key === $current ? ' aria-current="page"' : '' ?>>
                    <?= esc($group['title']) ?>
                </a>
            <?php endforeach ?>
        </nav>
    </div>
    <div class="col-12 col-lg-9">
        <section class="eos-panel" aria-labelledby="settings-title">
            <header class="eos-panel-head eos-panel-head-stack">
                <h2 id="settings-title"><?= esc($groups[$current]['title']) ?></h2>
                <?php if ($groups[$current]['description'] !== ''): ?>
                    <p><?= esc($groups[$current]['description']) ?></p>
                <?php endif ?>
            </header>
            <div class="eos-form">
                <?= $form ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
