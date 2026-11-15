<?php
/**
 * Change password while signed in (replaces ci4-auth's change_password view;
 * same fields and routes).
 *
 * @var CodeIgniter\View\View  $this
 * @var string                 $authLayout
 * @var string                 $authSection
 * @var array<string, string>  $authViews
 * @var list<string>           $passwordRules
 * @var array<string, string>  $errors
 */
$errors        ??= [];
$passwordRules ??= [];
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<a class="eos-auth-back" href="<?= site_url('/') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to the dashboard</a>

<div class="eos-auth-head">
    <h1>Change your password</h1>
    <p>Enter your current password, then choose a new one.</p>
</div>

<?= $this->include($authViews['messages']) ?>

<form method="post" action="<?= url_to('auth.password.update') ?>" novalidate data-eos-auth-form>
    <?= csrf_field() ?>

    <div class="eos-auth-field">
        <label for="current_password" class="form-label">Current password</label>
        <div class="eos-auth-input">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <input type="password" id="current_password" name="current_password"
                class="form-control<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>"
                autocomplete="current-password" required autofocus data-eos-password
                <?= isset($errors['current_password']) ? 'aria-invalid="true" aria-describedby="current_password-error"' : '' ?>>
            <button type="button" class="eos-auth-reveal" data-eos-reveal="current_password" aria-label="Show password" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        <?php if (isset($errors['current_password'])): ?>
            <div class="eos-auth-error" id="current_password-error"><?= esc($errors['current_password']) ?></div>
        <?php endif ?>
    </div>

    <div class="eos-auth-field">
        <label for="password" class="form-label">New password</label>
        <div class="eos-auth-input">
            <i class="bi bi-key" aria-hidden="true"></i>
            <input type="password" id="password" name="password"
                class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                autocomplete="new-password" required data-eos-password
                aria-describedby="password-rules<?= isset($errors['password']) ? ' password-error' : '' ?>">
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
        <?php if ($passwordRules !== []): ?>
            <ul id="password-rules" class="eos-auth-rules">
                <?php foreach ($passwordRules as $rule): ?>
                    <li><?= esc($rule) ?></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>

    <div class="eos-auth-field">
        <label for="password_confirm" class="form-label">Confirm new password</label>
        <div class="eos-auth-input">
            <i class="bi bi-key" aria-hidden="true"></i>
            <input type="password" id="password_confirm" name="password_confirm"
                class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                autocomplete="new-password" required data-eos-password data-eos-match="password"
                <?= isset($errors['password_confirm']) ? 'aria-invalid="true" aria-describedby="password_confirm-error"' : '' ?>>
        </div>
        <div class="eos-auth-error" data-eos-mismatch="password_confirm" hidden>The two passwords don't match yet.</div>
        <?php if (isset($errors['password_confirm'])): ?>
            <div class="eos-auth-error" id="password_confirm-error"><?= esc($errors['password_confirm']) ?></div>
        <?php endif ?>
    </div>

    <button type="submit" class="btn btn-primary eos-auth-submit" data-eos-busy="Saving…">Change password</button>
</form>
<?= $this->endSection() ?>
