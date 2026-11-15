<?php
/**
 * Sign in (replaces ci4-auth's login view; same fields and routes).
 *
 * @var CodeIgniter\View\View  $this
 * @var string                 $authLayout
 * @var string                 $authSection
 * @var array<string, string>  $authViews
 * @var string                 $loginLabel
 * @var array<string, string>  $errors
 * @var array<string, mixed>   $old
 * @var bool                   $canReset
 * @var bool                   $canRegister
 */
$errors ??= [];
$old    ??= [];
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="eos-auth-head">
    <h1>Sign in</h1>
    <p>Welcome back. Use the email address or phone number on your account.</p>
</div>

<?= $this->include($authViews['messages']) ?>

<form method="post" action="<?= url_to('auth.login.attempt') ?>" novalidate data-eos-auth-form>
    <?= csrf_field() ?>

    <div class="eos-auth-field">
        <label for="login" class="form-label"><?= esc($loginLabel) ?></label>
        <div class="eos-auth-input">
            <i class="bi bi-person" aria-hidden="true"></i>
            <input type="text" id="login" name="login"
                class="form-control<?= isset($errors['login']) ? ' is-invalid' : '' ?>"
                value="<?= esc($old['login'] ?? '') ?>"
                autocomplete="username" autocapitalize="none" spellcheck="false"
                placeholder="name@company.com" required autofocus
                <?= isset($errors['login']) ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>>
        </div>
        <?php if (isset($errors['login'])): ?>
            <div class="eos-auth-error" id="login-error"><?= esc($errors['login']) ?></div>
        <?php endif ?>
    </div>

    <div class="eos-auth-field">
        <div class="eos-auth-label-row">
            <label for="password" class="form-label">Password</label>
            <?php if ($canReset): ?>
                <a href="<?= url_to('auth.forgot') ?>">Forgot password?</a>
            <?php endif ?>
        </div>
        <div class="eos-auth-input">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <input type="password" id="password" name="password"
                class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                autocomplete="current-password" required data-eos-password
                <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
            <button type="button" class="eos-auth-reveal" data-eos-reveal="password" aria-label="Show password" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        <div class="eos-auth-caps" data-eos-caps="password" hidden>
            <i class="bi bi-capslock-fill" aria-hidden="true"></i> Caps Lock is on
        </div>
        <?php if (isset($errors['password'])): ?>
            <div class="eos-auth-error" id="password-error"><?= esc($errors['password']) ?></div>
        <?php endif ?>
    </div>

    <button type="submit" class="btn btn-primary eos-auth-submit" data-eos-busy="Signing in…">Sign in</button>
</form>

<?php if ($canRegister): ?>
    <p class="eos-auth-alt">No account yet? <a href="<?= url_to('auth.register') ?>">Create one</a></p>
<?php endif ?>
<?= $this->endSection() ?>
