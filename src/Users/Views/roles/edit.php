<?php
/**
 * @var CodeIgniter\View\View                $this
 * @var string                               $key
 * @var array<string, mixed>                 $role
 * @var list<string>                         $granted
 * @var list<string>                         $defaults
 * @var array<string, array<string, string>> $grouped
 * @var list<string>                         $known
 */
$hasDefaults = $role['system'] && $role['synced'];
$changed     = $hasDefaults && $granted !== $defaults;
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('actions') ?>
<a class="btn btn-outline-secondary" href="<?= url_to('users.roles') ?>"><i class="bi bi-arrow-left me-1"></i>All roles</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <form action="<?= url_to('users.roles.save', $key) ?>" method="post" class="eos-panel" id="role-form">
            <?= csrf_field() ?>
            <header class="eos-panel-head">
                <h2><?= esc($role['title']) ?></h2>
                <span class="eos-count"><span data-count><?= count($granted) ?></span> of <?= count($known) ?> ticked</span>
            </header>
            <p class="small eos-muted"><?= esc($role['description']) ?></p>
            <?php if ($hasDefaults): ?>
                <p class="small"><span class="eos-pill eos-pill-success">Default</span> marks what the modules give this role. <?= $role['edited'] ? 'This role has been edited, so <code>php spark auth:sync</code> leaves it as it is.' : 'Saving here stops <code>php spark auth:sync</code> from resetting it.' ?></p>
            <?php endif ?>

            <?php foreach ($grouped as $group => $permissions): ?>
                <?php if ($permissions === []) { continue; } ?>
                <fieldset class="mb-3" data-group>
                    <legend class="h6 d-flex align-items-center gap-2 mb-2">
                        <?= esc($group) ?>
                        <button class="btn btn-sm btn-link p-0 small" type="button" data-all>all</button>
                        <button class="btn btn-sm btn-link p-0 small" type="button" data-none>none</button>
                    </legend>
                    <div class="row g-1">
                        <?php foreach ($permissions as $permission => $description): ?>
                            <?php
                            $on  = in_array($permission, $granted, true);
                            $def = in_array($permission, $defaults, true);
                            $id  = 'p-' . str_replace('.', '-', $permission);
                            ?>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= esc($permission, 'attr') ?>" id="<?= esc($id, 'attr') ?>"<?= $on ? ' checked' : '' ?>>
                                    <label class="form-check-label small" for="<?= esc($id, 'attr') ?>">
                                        <?= esc($description) ?>
                                        <?php if ($hasDefaults && $def): ?><span class="eos-pill eos-pill-success ms-1">Default</span><?php endif ?>
                                        <?php if ($hasDefaults && $on !== $def): ?><span class="eos-pill eos-pill-gold ms-1"><?= $on ? 'Added' : 'Removed' ?></span><?php endif ?>
                                        <code class="d-block"><?= esc($permission) ?></code>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </fieldset>
            <?php endforeach ?>

            <div class="d-flex flex-wrap gap-2 border-top pt-3">
                <button class="btn btn-primary" type="submit" data-eos-confirm="Save what <?= esc($role['title'], 'attr') ?> may do? Everyone with this role gets the change on their next page." data-eos-confirm-label="Save">Save permissions</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column gap-4">
        <section class="eos-panel">
            <h2 class="eos-section-title">About this role</h2>
            <dl class="eos-details">
                <dt>Key</dt><dd><code><?= esc($key) ?></code></dd>
                <dt>People</dt><dd><a href="<?= url_to('users.staff') ?>?role=<?= esc($key, 'attr') ?>"><?= (int) $role['people'] ?></a></dd>
                <dt>Source</dt><dd><?= $role['system'] ? 'Built in' : 'Made here' ?><small><?= $role['system'] ? ($role['edited'] ? 'Edited here; kept on sync' : 'Follows the modules’ defaults') : 'Only in the database' ?></small></dd>
            </dl>
        </section>

        <?php if ($changed || $role['edited']): ?>
            <?php if ($hasDefaults): ?>
                <form action="<?= url_to('users.roles.reset', $key) ?>" method="post" class="eos-panel" data-eos-confirm="Put <?= esc($role['title'], 'attr') ?> back to the modules’ defaults? Your changes to it are lost." data-eos-confirm-label="Reset">
                    <?= csrf_field() ?>
                    <h2 class="eos-section-title">Reset</h2>
                    <p class="small">Go back to what the modules give this role, and follow module updates again.</p>
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Reset to defaults</button>
                </form>
            <?php endif ?>
        <?php endif ?>

        <?php if (! $role['system']): ?>
            <form action="<?= url_to('users.roles.rename', $key) ?>" method="post" class="eos-panel">
                <?= csrf_field() ?>
                <h2 class="eos-section-title">Name</h2>
                <input class="form-control form-control-sm mb-2" name="title" required maxlength="100" value="<?= esc($role['title'], 'attr') ?>" aria-label="Role name">
                <input class="form-control form-control-sm mb-2" name="description" maxlength="255" value="<?= esc($role['description'], 'attr') ?>" aria-label="What it is for">
                <button class="btn btn-sm btn-outline-primary" type="submit">Save name</button>
            </form>
            <form action="<?= url_to('users.roles.delete', $key) ?>" method="post" class="eos-panel" data-eos-confirm="Delete the role <?= esc($role['title'], 'attr') ?>?" data-eos-confirm-label="Delete">
                <?= csrf_field() ?>
                <p class="small mb-2">Only possible when nobody holds it.</p>
                <button class="btn btn-sm btn-outline-danger" type="submit">Delete role</button>
            </form>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var form = document.getElementById('role-form');
    var count = form.querySelector('[data-count]');
    function recount() { count.textContent = form.querySelectorAll('input[name="permissions[]"]:checked').length; }
    form.addEventListener('change', recount);
    form.querySelectorAll('[data-group]').forEach(function (group) {
        group.querySelector('[data-all]').addEventListener('click', function () { group.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = true; }); recount(); });
        group.querySelector('[data-none]').addEventListener('click', function () { group.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; }); recount(); });
    });
})();
</script>
<?= $this->endSection() ?>
