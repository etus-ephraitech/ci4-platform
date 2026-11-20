<?php
/**
 * Why a workspace cannot be used: unknown, suspended, trial over, or the
 * signed-in person is not a member. Shown by the platform filter.
 *
 * @var CodeIgniter\View\View     $this
 * @var string                    $title
 * @var string                    $message
 * @var array<string, mixed>|null $tenant
 * @var bool|null                 $signOut
 */
helper(['platform', 'url', 'form']);
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\auth') ?>
<?= $this->section('content') ?>
<div class="eos-auth-head">
    <h1><?= esc($title) ?></h1>
    <p><?= esc($message) ?></p>
</div>
<?php if (! empty($tenant['name'])): ?>
    <p class="eos-muted">Workspace: <strong><?= esc($tenant['name']) ?></strong></p>
<?php endif ?>
<p>If you think this is a mistake, contact whoever manages your account.</p>
<div class="d-flex flex-wrap gap-2 mt-4">
    <?php // A page, so the session decides who is signed in (not a bearer token). ?>
    <?php if (auth()->session()->check()): ?>
        <?php if (service('tenancy')->isMulti()): ?>
            <a class="btn btn-primary" href="<?= url_to('core.workspaces') ?>">Your workspaces</a>
        <?php endif ?>
        <form action="<?= url_to('auth.logout') ?>" method="post">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit">Sign out</button>
        </form>
    <?php else: ?>
        <a class="btn btn-primary" href="<?= url_to('auth.login') ?>">Sign in</a>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
