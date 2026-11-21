<?php
/**
 * Layout for the ephraitech/ci4-auth pages (sign in, password reset,
 * verification, change password). Set as $viewLayout in app/Config/Auth.php;
 * the pages themselves are in Ephraitech\Platform\Views\auth ($views there).
 *
 * In a multi-tenant install the workspace's name is shown when the
 * sign-in page is opened on a workspace's own address.
 *
 * Extension point 'layout.auth.banners': notices above the form (for
 * example that the system is down for maintenance). Nobody is signed in.
 *
 * Wide screens: brand panel on the left, form on the right.
 * Tablets: a narrower brand panel. Phones: a navy brand bar above the form.
 *
 * @var CodeIgniter\View\View $this
 */
helper(['platform', 'url']);

$platform = Ephraitech\Platform\Config\PlatformConfig::get();
$section  = $authSection ?? 'content';
$company  = platform_name();
$tagline  = trim((string) setting('company.tagline', '')) ?: $platform->tagline;
$email   = trim((string) setting('company.email', ''));
$phone   = trim((string) setting('company.phone', ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= esc($platform->themeColor, 'attr') ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle ?? 'Sign in') ?> · <?= esc($company) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= brand_asset('mark') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= platform_asset('css/platform.css') ?>" rel="stylesheet">
    <style>:root { --eos-navy-deep: <?= preg_match('/^#[0-9a-fA-F]{3,8}$/', $platform->themeColor) === 1 ? $platform->themeColor : '#021f5e' ?>; }</style>
</head>
<body class="eos eos-auth">
    <aside class="eos-auth-brand">
        <?php if (! empty($platform->brand['logoOnDark'])): ?>
            <img class="eos-auth-logo" src="<?= brand_asset('logoOnDark') ?>" alt="<?= esc($company, 'attr') ?>" width="220" height="43">
        <?php else: ?>
            <p class="eos-auth-logo eos-brand-name"><img src="<?= brand_asset('markOnDark') ?>" alt="" width="40" height="40"><span><?= esc($company) ?></span></p>
        <?php endif ?>
        <div class="eos-auth-copy">
            <p class="eos-auth-tagline"><?= esc($tagline) ?></p>
            <?php if ($company !== $platform->appName): ?>
                <p class="eos-auth-sub"><?= esc($platform->appName) ?></p>
            <?php endif ?>
        </div>
        <img class="eos-auth-watermark" src="<?= brand_asset('markOnDark') ?>" alt="" aria-hidden="true">
    </aside>

    <main class="eos-auth-panel">
        <header class="eos-auth-mobilebar">
            <?php if (! empty($platform->brand['logoOnDark'])): ?>
                <img src="<?= brand_asset('logoOnDark') ?>" alt="<?= esc($company, 'attr') ?>" width="168" height="33">
            <?php else: ?>
                <span class="eos-brand-name"><img src="<?= brand_asset('markOnDark') ?>" alt="" width="28" height="28"><span><?= esc($company) ?></span></span>
            <?php endif ?>
        </header>

        <div class="eos-auth-card">
            <?= slot('layout.auth.banners') ?>
            <?= $this->renderSection($section) ?>
        </div>

        <footer class="eos-auth-foot">
            <?php if ($email !== '' || $phone !== ''): ?>
                <p>
                    Trouble signing in? Contact
                    <?php if ($email !== ''): ?><a href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a><?php endif ?>
                    <?php if ($email !== '' && $phone !== ''): ?> or <?php endif ?>
                    <?php if ($phone !== ''): ?><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $phone), 'attr') ?>"><?= esc($phone) ?></a><?php endif ?>.
                </p>
            <?php endif ?>
            <p>&copy; <?= date('Y') ?> <?= esc($company) ?></p>
        </footer>
    </main>

    <script src="<?= platform_asset('js/auth.js') ?>" defer></script>
</body>
</html>
