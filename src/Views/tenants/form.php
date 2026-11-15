<?php
/**
 * @var CodeIgniter\View\View                 $this
 * @var Ephraitech\Platform\Forms\TenantForm  $form
 * @var string|null                           $error
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>
<?= $this->section('content') ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php endif ?>
<section class="eos-panel">
    <div class="eos-form"><?= $form ?></div>
</section>
<?= $this->endSection() ?>
