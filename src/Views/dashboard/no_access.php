<?php
/**
 * Signed in, but without dashboard.view: no role has been given yet.
 *
 * @var CodeIgniter\View\View             $this
 * @var ?Ephraitech\Auth\Entities\User    $user
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>
<?= $this->section('content') ?>
<div class="eos-empty">
    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
    <p><strong>Your account is ready, but it has no access yet.</strong></p>
    <p>Ask whoever manages accounts to give you a role. Once they do, your dashboard appears here.</p>
</div>
<?= $this->endSection() ?>
