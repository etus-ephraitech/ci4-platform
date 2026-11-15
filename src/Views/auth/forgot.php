<?php
/**
 * Forgot password (replaces ci4-auth's forgot view; same fields and routes).
 *
 * @var CodeIgniter\View\View  $this
 * @var string                 $authLayout
 * @var string                 $authSection
 * @var array<string, string>  $authViews
 * @var string                 $loginLabel
 * @var array<string, string>  $errors
 * @var array<string, mixed>   $old
 */
$errors ??= [];
$old    ??= [];
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<a class="eos-auth-back" href="<?= url_to('auth.login') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to sign in</a>

<div class="eos-auth-head">
    <h1>Reset your password</h1>
    <p>Enter your email address or phone number. We'll send you a code to choose a new password.</p>
</div>

<?= $this->include($authViews['messages']) ?>

<form method="post" action="<?= url_to('auth.forgot.send') ?>" novalidate data-eos-auth-form>
    <?= csrf_field() ?>

    <div class="eos-auth-field">
        <label for="login" class="form-label"><?= esc($loginLabel) ?></label>
        <div class="eos-auth-input">
            <i class="bi bi-person" aria-hidden="true"></i>
            <input type="text" id="login" name="login"
                class="form-control<?= isset($errors['login']) ? ' is-invalid' : '' ?>"
                value="<?= esc($old['login'] ?? '') ?>"
                autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
                <?= isset($errors['login']) ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>>
        </div>
        <?php if (isset($errors['login'])): ?>
            <div class="eos-auth-error" id="login-error"><?= esc($errors['login']) ?></div>
        <?php endif ?>
    </div>

    <button type="submit" class="btn btn-primary eos-auth-submit" data-eos-busy="Sending…">Send reset code</button>
</form>
<?= $this->endSection() ?>
