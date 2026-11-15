<?php
/**
 * Flash messages on the sign-in pages (replaces ci4-auth's _messages view).
 * The package flashes 'message' for good news and 'error' for problems.
 */
$message = session('message');
$error   = session('error');
?>
<?php if (is_string($message) && $message !== ''): ?>
    <div class="eos-auth-alert is-success" role="status">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <span><?= esc($message) ?></span>
    </div>
<?php endif ?>

<?php if (is_string($error) && $error !== ''): ?>
    <div class="eos-auth-alert is-error" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <span><?= esc($error) ?></span>
    </div>
<?php endif ?>
