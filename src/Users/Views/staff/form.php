<?php
/**
 * @var CodeIgniter\View\View                 $this
 * @var Ephraitech\Platform\Users\Forms\StaffForm         $form
 * @var ?Ephraitech\Auth\Entities\User        $user
 * @var ?string                               $error
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('content') ?>
<section class="eos-panel eos-form-panel">
    <?php if (! empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif ?>
    <div class="eos-form eos-form-wide"><?= $form ?></div>
</section>
<?= $this->endSection() ?>
