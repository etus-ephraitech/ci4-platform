<?php
/**
 * @var CodeIgniter\View\View                $this
 * @var array<string, array<string, mixed>>  $roles
 * @var array<string, list<string>>          $current
 * @var ?string                              $superRole
 * @var array<string, array<string, string>> $grouped
 * @var bool                                 $canEdit   role definitions can be changed here
 * @var bool                                 $inWorkspace
 */
$excluded   = Ephraitech\Platform\Config\PlatformConfig::get()->staffExcludedRoles;
// Platform-wide roles (support staff) are not a workspace's to give.
$central    = $inWorkspace ? Ephraitech\Platform\Config\PlatformConfig::get()->centralOnlyRoles : [];
$excluded   = [...$excluded, ...$central];
$roles      = array_filter($roles, static fn (string $k): bool => ! in_array($k, $central, true), ARRAY_FILTER_USE_KEY);
$staffRoles = array_filter($roles, static fn (array $r, string $k): bool => ! in_array($k, $excluded, true), ARRAY_FILTER_USE_BOTH);
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('actions') ?>
<a class="btn btn-outline-secondary" href="<?= url_to('users.staff') ?>"><i class="bi bi-people me-1"></i>Staff</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <p class="mb-3">Give each person the role for their job. Menus, pages and dashboards follow from the permissions, so people only see what their work needs.<?= $canEdit ? ' Open a role to change what it may do.' : '' ?> For a one-off exception, grant an extra permission on the person’s page instead.</p>
        <?php if ($inWorkspace): ?>
            <p class="small eos-muted">Roles are the same in every workspace and are defined by the platform owner. You choose who has which role here.</p>
        <?php endif ?>
        <div class="eos-role-cards">
            <?php foreach ($roles as $key => $role): ?>
                <article class="eos-panel">
                    <header class="eos-panel-head mb-1">
                        <h2>
                            <?php if ($canEdit && $role['editable']): ?><a href="<?= url_to('users.roles.edit', $key) ?>"><?= esc($role['title']) ?></a><?php else: ?><?= esc($role['title']) ?><?php endif ?>
                        </h2>
                        <a href="<?= url_to('users.staff') ?>?role=<?= esc($key, 'attr') ?>" class="eos-count"><?= (int) $role['people'] ?> <?= (int) $role['people'] === 1 ? 'person' : 'people' ?></a>
                    </header>
                    <p class="eos-muted small mb-2"><?= esc($role['description']) ?></p>
                    <p class="small mb-0">
                        <?= $key === $superRole ? 'Every permission' : (int) $role['permissions'] . ' permission' . ((int) $role['permissions'] === 1 ? '' : 's') ?>
                        <?php if (! $role['system']): ?> · <span class="eos-pill eos-pill-info">Made here</span><?php elseif ($role['edited']): ?> · <span class="eos-pill eos-pill-gold">Edited</span><?php endif ?>
                    </p>
                </article>
            <?php endforeach ?>
        </div>
    </div>
    <?php if ($canEdit): ?>
    <div class="col-12 col-xl-4">
        <form action="<?= url_to('users.roles.create') ?>" method="post" class="eos-panel eos-next">
            <?= csrf_field() ?>
            <h2 class="eos-section-title">New role</h2>
            <p class="small eos-muted">For a job the built-in roles do not fit, e.g. "Front desk" or "Store keeper".</p>
            <label class="form-label" for="nr-title">Name</label>
            <input class="form-control mb-2" id="nr-title" name="title" required maxlength="100" value="<?= esc((string) old('title'), 'attr') ?>">
            <label class="form-label" for="nr-desc">What it is for</label>
            <input class="form-control mb-2" id="nr-desc" name="description" maxlength="255" value="<?= esc((string) old('description'), 'attr') ?>">
            <label class="form-label" for="nr-copy">Start from</label>
            <select class="form-select mb-3" id="nr-copy" name="copy_from">
                <option value="">Nothing ticked</option>
                <?php foreach ($roles as $key => $role): ?>
                    <?php if ($key === $superRole || in_array($key, $excluded, true)) { continue; } ?>
                    <option value="<?= esc($key, 'attr') ?>"><?= esc($role['title']) ?></option>
                <?php endforeach ?>
            </select>
            <button class="btn btn-primary" type="submit">Create role</button>
        </form>
    </div>
    <?php endif ?>
</div>

<section class="eos-panel" aria-labelledby="matrix-title">
    <h2 class="eos-section-title" id="matrix-title">Who can do what now</h2>
    <div class="table-responsive eos-matrix-wrap">
        <table class="table eos-table eos-table-keep eos-matrix align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Permission</th>
                    <?php foreach ($staffRoles as $key => $role): ?>
                        <th scope="col" class="text-center"><span><?php if ($canEdit && $role['editable']): ?><a href="<?= url_to('users.roles.edit', $key) ?>"><?= esc($role['title']) ?></a><?php else: ?><?= esc($role['title']) ?><?php endif ?></span></th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <?php foreach ($grouped as $group => $permissions): ?>
                <?php if ($permissions === []) { continue; } ?>
                <tbody>
                    <tr class="eos-matrix-group"><th scope="rowgroup" colspan="<?= count($staffRoles) + 1 ?>"><?= esc($group) ?></th></tr>
                    <?php foreach ($permissions as $permission => $description): ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= esc($description) ?><code class="d-block small"><?= esc($permission) ?></code></th>
                            <?php foreach ($staffRoles as $key => $role): ?>
                                <td class="text-center">
                                    <?php if ($key === $superRole || in_array($permission, $current[$key] ?? [], true)): ?>
                                        <i class="bi bi-check-lg eos-matrix-on" aria-label="Yes"></i>
                                    <?php else: ?>
                                        <span class="eos-matrix-off" aria-label="No">·</span>
                                    <?php endif ?>
                                </td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            <?php endforeach ?>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
