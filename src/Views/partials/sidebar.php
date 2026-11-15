<?php
/**
 * @var CodeIgniter\View\View $this
 */
$menu   = platform_menu();
$brand  = Ephraitech\Platform\Config\PlatformConfig::get()->brand;
$name   = platform_name();
?>
<nav id="eos-sidebar" class="eos-sidebar" aria-label="Main">
    <div class="eos-brand">
        <a href="<?= url_to('core.dashboard') ?>" class="eos-brand-link" aria-label="<?= esc($name, 'attr') ?> dashboard">
            <?php if (! empty($brand['logoOnDark'])): ?>
                <img class="eos-brand-full" src="<?= brand_asset('logoOnDark') ?>" alt="" width="160" height="31">
            <?php else: ?>
                <span class="eos-brand-full eos-brand-name"><img src="<?= brand_asset('markOnDark') ?>" alt="" width="28" height="28"><span><?= esc($name) ?></span></span>
            <?php endif ?>
            <img class="eos-brand-mark" src="<?= brand_asset('markOnDark') ?>" alt="" width="32" height="32">
        </a>
        <button class="eos-collapse d-none d-lg-inline-flex" type="button" data-eos-collapse aria-label="Collapse menu" aria-pressed="false">
            <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
        </button>
    </div>

    <div class="eos-nav">
        <?php foreach ($menu as $group => $items): ?>
            <p class="eos-nav-group"><?= esc($group) ?></p>
            <ul>
                <?php foreach ($items as $item): ?>
                    <?php $active = menu_is_active($item['url']); ?>
                    <li>
                        <a href="<?= esc($item['url'], 'attr') ?>" class="eos-nav-link<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?> data-label="<?= esc($item['label'], 'attr') ?>">
                            <i class="bi <?= esc($item['icon'], 'attr') ?>" aria-hidden="true"></i>
                            <span><?= esc($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endforeach ?>
    </div>

    <div class="eos-sidebar-foot">
        <p class="eos-sidebar-tagline"><?= esc((string) setting('company.tagline', Ephraitech\Platform\Config\PlatformConfig::get()->tagline)) ?></p>
    </div>
</nav>
<div class="eos-backdrop" data-eos-toggle="sidebar" hidden></div>
