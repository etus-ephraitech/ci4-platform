<?php
$flash = [
    'success' => session('message'),
    'danger'  => session('error'),
    'warning' => session('warning'),
];
?>
<?php foreach ($flash as $type => $text): ?>
    <?php if (is_string($text) && $text !== ''): ?>
        <div class="alert alert-<?= $type ?> alert-dismissible fade show" role="<?= $type === 'danger' ? 'alert' : 'status' ?>">
            <?= esc($text) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif ?>
<?php endforeach ?>
<?php if (is_array(session('errors')) && session('errors') !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            <?php foreach (session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
