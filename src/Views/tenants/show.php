<?php
/**
 * @var CodeIgniter\View\View                                   $this
 * @var array<string, mixed>                                    $tenant
 * @var Ephraitech\Platform\Forms\TenantForm                    $form
 * @var array<string, array<string, mixed>>                     $modules  manifest + on, reason, switch
 * @var array{title: string, modules: list<string>}|null        $plan
 * @var list<array{id: int, name: string, email: ?string, roles: list<string>}> $members
 * @var string                                                  $url
 * @var bool                                                    $usable
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>
<?= $this->section('actions') ?>
<?php if ($usable && str_starts_with($url, 'http')): ?>
    <a class="btn btn-outline-secondary" href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open</a>
<?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($plan === null): ?>
    <div class="alert alert-warning" role="alert">This workspace is on plan "<?= esc($tenant['plan']) ?>", which no longer exists in Config\Platform. It only has the always-on modules until you pick a plan.</div>
<?php endif ?>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <section class="eos-panel h-100" aria-labelledby="details-title">
            <header class="eos-panel-head"><h2 id="details-title">Details</h2><span class="eos-count font-tabular"><?= esc($tenant['slug']) ?></span></header>
            <div class="eos-form"><?= $form ?></div>
        </section>
    </div>
    <div class="col-12 col-xl-7">
        <section class="eos-panel h-100" aria-labelledby="members-title">
            <header class="eos-panel-head"><h2 id="members-title">Members</h2><span class="eos-count"><?= count($members) ?></span></header>
            <div class="table-responsive">
                <table class="table eos-table mb-0">
                    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Roles</th></tr></thead>
                    <tbody>
                        <?php if ($members === []): ?>
                            <tr><td colspan="3" class="text-center eos-muted py-4">Nobody yet. The owner adds people from inside the workspace.</td></tr>
                        <?php endif ?>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($m['name']) ?><?= (int) $tenant['owner_user_id'] === $m['id'] ? ' <span class="badge text-bg-light">Owner</span>' : '' ?></td>
                                <td><?= esc($m['email'] ?? '') ?></td>
                                <td><?= esc(implode(', ', $m['roles'])) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<section class="eos-panel mt-4" id="modules" aria-labelledby="ws-modules-title">
    <header class="eos-panel-head eos-panel-head-stack">
        <h2 id="ws-modules-title">Modules</h2>
        <p>On or off by plan<?= $plan !== null ? ' (' . esc($plan['title']) . ')' : '' ?>. Switch a single module on (an add-on or a trial) or off for this workspace, optionally until a date; "Plan" removes the exception.</p>
    </header>
    <div class="table-responsive">
        <table class="table eos-table mb-0 align-middle">
            <thead><tr><th scope="col">Module</th><th scope="col">State</th><th scope="col">Exception</th><th scope="col" class="text-end">Change</th></tr></thead>
            <tbody>
                <?php foreach ($modules as $name => $module): ?>
                    <tr>
                        <td><strong><?= esc($module['title']) ?></strong><?= $module['requires'] !== [] ? '<br><small class="eos-muted">Needs ' . esc(implode(', ', $module['requires'])) . '</small>' : '' ?></td>
                        <td>
                            <span class="badge <?= $module['on'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $module['on'] ? 'On' : 'Off' ?></span>
                            <small class="eos-muted ms-1"><?= esc($module['reason']) ?></small>
                        </td>
                        <td>
                            <?php if ($module['switch'] !== null): ?>
                                <?php $expired = $module['switch']['expires_at'] !== null && strtotime((string) $module['switch']['expires_at']) < time(); ?>
                                <?= $module['switch']['enabled'] ? 'On' : 'Off' ?><?= $module['switch']['expires_at'] ? ($expired ? ', ended ' : ' until ') . esc(substr((string) $module['switch']['expires_at'], 0, 10)) : '' ?>
                                <?php if (! empty($module['switch']['note'])): ?><br><small class="eos-muted"><?= esc($module['switch']['note']) ?></small><?php endif ?>
                            <?php else: ?>
                                <span class="eos-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td class="text-end">
                            <?php if ($module['always_on']): ?>
                                <small class="eos-muted">Always on</small>
                            <?php else: ?>
                                <form class="eos-switch-form justify-content-end" method="post" action="<?= url_to('core.tenants.modules', $tenant['slug']) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="module" value="<?= esc($name, 'attr') ?>">
                                    <label class="visually-hidden" for="ws-state-<?= esc($name, 'attr') ?>">State of <?= esc($module['title']) ?></label>
                                    <select class="form-select form-select-sm" id="ws-state-<?= esc($name, 'attr') ?>" name="state">
                                        <option value="plan">Plan</option>
                                        <option value="on"<?= ($module['switch']['enabled'] ?? null) === 1 || ($module['switch']['enabled'] ?? null) === '1' ? ' selected' : '' ?>>On</option>
                                        <option value="off"<?= ($module['switch']['enabled'] ?? null) === 0 || ($module['switch']['enabled'] ?? null) === '0' ? ' selected' : '' ?>>Off</option>
                                    </select>
                                    <label class="visually-hidden" for="ws-until-<?= esc($name, 'attr') ?>">Until</label>
                                    <input class="form-control form-control-sm" type="date" id="ws-until-<?= esc($name, 'attr') ?>" name="until" title="Until (optional)">
                                    <label class="visually-hidden" for="ws-note-<?= esc($name, 'attr') ?>">Note</label>
                                    <input class="form-control form-control-sm" type="text" id="ws-note-<?= esc($name, 'attr') ?>" name="note" maxlength="255" placeholder="Note">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Apply</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
