<?php
/**
 * Choose a new password (replaces ci4-auth's reset view; same fields and routes).
 * $mode is 'link' (opened from the emailed link), 'code' (typed code) or 'expired'.
 *
 * @var CodeIgniter\View\View  $this
 * @var string                 $authLayout
 * @var string                 $authSection
 * @var array<string, string>  $authViews
 * @var string                 $mode
 * @var string|null            $selector
 * @var string|null            $code
 * @var string                 $loginLabel
 * @var int                    $codeLength
 * @var list<string>           $passwordRules
 * @var array<string, string>  $errors
 * @var array<string, mixed>   $old
 */
$errors        ??= [];
$old           ??= [];
$passwordRules ??= [];
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<?php if ($mode === 'expired'): ?>
    <div class="eos-auth-head">
        <span class="eos-auth-icon is-warning" aria-hidden="true"><i class="bi bi-hourglass-bottom"></i></span>
        <h1>This link has expired</h1>
        <p>Reset links work once and only for a short time. Ask for a new one to carry on.</p>
    </div>
    <a href="<?= url_to('auth.forgot') ?>" class="btn btn-primary eos-auth-submit">Send me a new code</a>
    <p class="eos-auth-alt"><a href="<?= url_to('auth.login') ?>">Back to sign in</a></p>
<?php else: ?>
    <a class="eos-auth-back" href="<?= url_to('auth.login') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to sign in</a>

    <div class="eos-auth-head">
        <h1><?= $mode === 'link' ? 'Choose a new password' : 'Reset your password' ?></h1>
        <p><?= $mode === 'link' ? 'Pick a password you have not used here before.' : 'Enter the code we sent you, then choose a new password.' ?></p>
    </div>

    <?= $this->include($authViews['messages']) ?>

    <form method="post" action="<?= url_to('auth.reset.update') ?>" novalidate data-eos-auth-form>
        <?= csrf_field() ?>

        <?php if ($mode === 'link'): ?>
            <input type="hidden" name="s" value="<?= esc($selector, 'attr') ?>">
            <input type="hidden" name="c" value="<?= esc($code, 'attr') ?>">
        <?php else: ?>
            <div class="eos-auth-field">
                <label for="login" class="form-label"><?= esc($loginLabel) ?></label>
                <div class="eos-auth-input">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <input type="text" id="login" name="login"
                        class="form-control<?= isset($errors['login']) ? ' is-invalid' : '' ?>"
                        value="<?= esc($old['login'] ?? '') ?>"
                        autocomplete="username" autocapitalize="none" spellcheck="false" required
                        <?= isset($errors['login']) ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>>
                </div>
                <?php if (isset($errors['login'])): ?>
                    <div class="eos-auth-error" id="login-error"><?= esc($errors['login']) ?></div>
                <?php endif ?>
            </div>

            <div class="eos-auth-field">
                <label for="code" class="form-label">Code</label>
                <input type="text" id="code" name="code"
                    class="form-control eos-auth-code<?= isset($errors['code']) ? ' is-invalid' : '' ?>"
                    inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                    maxlength="<?= (int) $codeLength ?>" placeholder="<?= esc(str_repeat('•', (int) $codeLength), 'attr') ?>" required
                    <?= isset($errors['code']) ? 'aria-invalid="true" aria-describedby="code-error"' : '' ?>>
                <?php if (isset($errors['code'])): ?>
                    <div class="eos-auth-error" id="code-error"><?= esc($errors['code']) ?></div>
                <?php endif ?>
            </div>
        <?php endif ?>

        <div class="eos-auth-field">
            <label for="password" class="form-label">New password</label>
            <div class="eos-auth-input">
                <i class="bi bi-lock" aria-hidden="true"></i>
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
                <i class="bi bi-lock" aria-hidden="true"></i>
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

        <button type="submit" class="btn btn-primary eos-auth-submit" data-eos-busy="Saving…">Save new password</button>
    </form>

    <?php if ($mode === 'code'): ?>
        <p class="eos-auth-alt">Didn't get a code? <a href="<?= url_to('auth.forgot') ?>">Send another</a></p>
    <?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
