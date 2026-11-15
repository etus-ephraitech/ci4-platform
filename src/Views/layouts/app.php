<?php
/**
 * Main application layout.
 *
 * Sections a page may fill: content (required), actions (buttons beside
 * the page title), styles (extra <head> tags), scripts (extra scripts).
 *
 * @var CodeIgniter\View\View $this
 * @var string                $pageTitle
 * @var list<array>           $breadcrumbs
 */
helper(['platform', 'url', 'form']);

$platform      = Ephraitech\Platform\Config\PlatformConfig::get();
$pageTitle   ??= $platform->appName;
$breadcrumbs ??= [];
$user          = user();
$name          = display_name($user);
$quick         = platform_quick_actions();
$tenancy       = service('tenancy');
$workspace     = $tenancy->current();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= esc($platform->themeColor, 'attr') ?>">
    <meta name="csrf-header" content="<?= esc(csrf_header()) ?>">
    <meta name="csrf-hash" content="<?= esc(csrf_hash()) ?>">
    <title><?= esc($pageTitle) ?> · <?= esc(platform_name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= brand_asset('mark') ?>">

    <script>
        // Apply the saved sidebar state before first paint to avoid a jump.
        try { if (localStorage.getItem('eos-sidebar') === 'collapsed') { document.documentElement.classList.add('eos-collapsed'); } } catch (e) {}
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <link href="<?= platform_asset('css/platform.css') ?>" rel="stylesheet">
    <style>:root { --eos-navy-deep: <?= preg_match('/^#[0-9a-fA-F]{3,8}$/', $platform->themeColor) === 1 ? $platform->themeColor : '#021f5e' ?>; }</style>
    <?= $this->renderSection('styles') ?>
</head>
<body class="eos">

<a class="eos-skip" href="#main">Skip to content</a>

<?= $this->include('Ephraitech\Platform\Views\partials\sidebar') ?>

<div class="eos-shell">
    <header class="eos-topbar">
        <button class="eos-icon-btn d-lg-none" type="button" data-eos-toggle="sidebar" aria-controls="eos-sidebar" aria-expanded="false" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>

        <form class="eos-search" action="<?= url_to('core.search') ?>" method="get" role="search" data-eos-search="<?= esc(url_to('core.search.quick'), 'attr') ?>">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="eos-search-q">Search</label>
            <input id="eos-search-q" type="search" name="q" placeholder="Search" autocomplete="off"
                   role="combobox" aria-expanded="false" aria-controls="eos-search-results" aria-autocomplete="list">
            <kbd class="d-none d-md-inline">/</kbd>
            <div id="eos-search-results" class="eos-search-results" role="listbox" hidden></div>
        </form>

        <div class="eos-topbar-end">
            <?php if ($tenancy->isMulti()): ?>
                <a class="eos-workspace" href="<?= url_to('core.workspaces') ?>" title="Switch workspace">
                    <i class="bi bi-buildings" aria-hidden="true"></i>
                    <span class="d-none d-md-inline"><?= esc($workspace['name'] ?? 'Platform admin') ?></span>
                    <span class="visually-hidden d-md-none"><?= esc($workspace['name'] ?? 'Platform admin') ?> — switch workspace</span>
                </a>
            <?php endif ?>
            <?php if ($quick !== []): ?>
                <div class="dropdown">
                    <button class="btn btn-primary eos-create" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline ms-1">New</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($quick as $action): ?>
                            <li><a class="dropdown-item" href="<?= esc($action['url'], 'attr') ?>"><i class="bi <?= esc($action['icon'], 'attr') ?> me-2"></i><?= esc($action['label']) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endif ?>

            <div class="dropdown">
                <button class="eos-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= esc($name, 'attr') ?>">
                    <span class="eos-avatar" aria-hidden="true"><?= esc(initials($name)) ?></span>
                    <span class="eos-user-text d-none d-md-flex">
                        <strong><?= esc($name) ?></strong>
                        <?php if ($user?->job_title): ?><small><?= esc($user->job_title) ?></small><?php endif ?>
                    </span>
                    <i class="bi bi-chevron-down small d-none d-md-inline"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-body-secondary"><?= esc($user?->getEmail() ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= url_to('auth.password') ?>"><i class="bi bi-key me-2"></i>Change password</a></li>
                    <?php if ($tenancy->isMulti()): ?>
                        <li><a class="dropdown-item" href="<?= url_to('core.workspaces') ?>"><i class="bi bi-buildings me-2"></i>Switch workspace</a></li>
                    <?php endif ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="<?= url_to('auth.logout') ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main id="main" class="eos-main" tabindex="-1">
        <div class="eos-page-head">
            <div class="eos-heading">
                <?php if ($breadcrumbs !== []): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                                <?php $last = $i === array_key_last($breadcrumbs); ?>
                                <li class="breadcrumb-item<?= $last ? ' active' : '' ?>"<?= $last ? ' aria-current="page"' : '' ?>>
                                    <?php if (! $last && ! empty($crumb[1])): ?>
                                        <a href="<?= esc($crumb[1], 'attr') ?>"><?= esc($crumb[0]) ?></a>
                                    <?php else: ?>
                                        <?= esc($crumb[0]) ?>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ol>
                    </nav>
                <?php endif ?>
                <h1><?= esc($pageTitle) ?></h1>
            </div>
            <div class="eos-actions"><?= $this->renderSection('actions') ?></div>
        </div>

        <?= $this->include('Ephraitech\Platform\Views\partials\flash') ?>
        <?= $this->renderSection('content') ?>
    </main>

    <footer class="eos-footer">
        <span><?= esc(platform_name()) ?></span>
        <span class="eos-footer-motto"><?= esc((string) setting('company.tagline', '')) ?></span>
    </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script src="<?= platform_asset('js/platform.js') ?>"></script>
<script src="<?= platform_asset('js/tables.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
