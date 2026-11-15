<?php
/**
 * Verify an email address or phone number (replaces ci4-auth's verify view;
 * same fields and routes).
 *
 * @var CodeIgniter\View\View  $this
 * @var string                 $authLayout
 * @var string                 $authSection
 * @var array<string, string>  $authViews
 * @var string                 $type        email|phone
 * @var string                 $destination masked address or number
 * @var int                    $codeLength
 * @var int                    $resendIn    seconds until a new code may be sent
 * @var array<string, string>  $errors
 */
$errors ??= [];
$isPhone = $type === 'phone';
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="eos-auth-head">
    <span class="eos-auth-icon" aria-hidden="true"><i class="bi <?= $isPhone ? 'bi-phone' : 'bi-envelope-open' ?>"></i></span>
    <h1>Verify your <?= $isPhone ? 'phone number' : 'email address' ?></h1>
    <p>Enter the <?= (int) $codeLength ?>-digit code we sent to <strong><?= esc($destination) ?></strong>.</p>
</div>

<?= $this->include($authViews['messages']) ?>

<form method="post" action="<?= url_to('auth.verify.confirm') ?>" novalidate data-eos-auth-form>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= esc($type, 'attr') ?>">

    <div class="eos-auth-field">
        <label for="code" class="form-label">Verification code</label>
        <input type="text" id="code" name="code"
            class="form-control eos-auth-code<?= isset($errors['code']) ? ' is-invalid' : '' ?>"
            inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
            maxlength="<?= (int) $codeLength ?>" placeholder="<?= esc(str_repeat('•', (int) $codeLength), 'attr') ?>" required autofocus
            <?= isset($errors['code']) ? 'aria-invalid="true" aria-describedby="code-error"' : '' ?>>
        <?php if (isset($errors['code'])): ?>
            <div class="eos-auth-error" id="code-error"><?= esc($errors['code']) ?></div>
        <?php endif ?>
    </div>

    <button type="submit" class="btn btn-primary eos-auth-submit" data-eos-busy="Checking…">Verify</button>
</form>

<form method="post" action="<?= url_to('auth.verify.resend') ?>" class="eos-auth-alt">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= esc($type, 'attr') ?>">
    <?php if ($resendIn > 0): ?>
        <span data-eos-countdown="<?= (int) $resendIn ?>">You can ask for a new code in <b><?= (int) $resendIn ?></b> seconds.</span>
        <button type="submit" class="btn btn-link p-0 align-baseline" disabled data-eos-countdown-target>Send a new code</button>
    <?php else: ?>
        Didn't get it? <button type="submit" class="btn btn-link p-0 align-baseline">Send a new code</button>
    <?php endif ?>
</form>
<?= $this->endSection() ?>
