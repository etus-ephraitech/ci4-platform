<?php
/**
 * The workspaces a signed-in person belongs to.
 *
 * @var CodeIgniter\View\View             $this
 * @var list<array<string, mixed>>        $workspaces tenant rows + usable, url, current
 * @var bool                              $switchHere one shared domain: switch with a POST
 * @var array<string, mixed>|null         $current
 * @var bool                              $central    holds a platform-wide role
 */
helper(['platform', 'url', 'form']);
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\auth') ?>
<?= $this->section('content') ?>
<div class="eos-auth-head">
    <h1>Your workspaces</h1>
    <p>Signed in as <?= esc(display_name(user())) ?>. Pick where to work.</p>
</div>

<?= $this->include('Ephraitech\Platform\Views\partials\flash') ?>

<?php if ($workspaces === []): ?>
    <p>You are not a member of any workspace yet. Ask the person who invited you to add you.</p>
<?php else: ?>
    <ul class="eos-workspaces">
        <?php foreach ($workspaces as $ws): ?>
            <li class="<?= $ws['current'] ? 'is-current' : '' ?>">
                <span>
                    <strong><?= esc($ws['name']) ?></strong>
                    <small><?= $ws['usable'] ? esc($ws['slug']) : esc(Ephraitech\Platform\Models\TenantModel::blockedReason($ws)) ?></small>
                </span>
                <?php if (! $ws['usable']): ?>
                    <span class="badge text-bg-secondary">Unavailable</span>
                <?php elseif ($ws['current']): ?>
                    <a class="btn btn-sm btn-primary" href="<?= url_to('core.dashboard') ?>">Continue</a>
                <?php elseif ($switchHere): ?>
                    <form action="<?= url_to('core.workspaces.enter', $ws['slug']) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-primary" type="submit">Open</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-primary" href="<?= esc($ws['url'], 'attr') ?>">Open</a>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<div class="d-flex flex-wrap gap-2 mt-4">
    <?php if ($central): ?>
        <?php if ($current !== null && $switchHere): ?>
            <form action="<?= url_to('core.workspaces.leave') ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary" type="submit">Platform admin</button>
            </form>
        <?php elseif ($current === null): ?>
            <a class="btn btn-outline-secondary" href="<?= url_to('core.dashboard') ?>">Platform admin</a>
        <?php endif ?>
    <?php endif ?>
    <form action="<?= url_to('auth.logout') ?>" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary" type="submit">Sign out</button>
    </form>
</div>
<?= $this->endSection() ?>
