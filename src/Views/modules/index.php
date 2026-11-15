<?php
/**
 * @var CodeIgniter\View\View                  $this
 * @var array<string, array<string, mixed>>    $modules   manifest + on, reason, dependents
 * @var array<string, string>                  $notInstalled
 * @var list<string>                           $unmet
 * @var bool                                   $canSwitch single install and modules.manage
 * @var bool                                   $multi
 * @var array<string, mixed>|null              $tenant
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>
<?= $this->section('content') ?>

<?php if ($multi): ?>
    <div class="alert alert-info" role="status">
        <?php if ($tenant !== null): ?>
            The modules of <strong><?= esc($tenant['name']) ?></strong> come from its plan. To change them, contact whoever manages the platform.
        <?php else: ?>
            With no workspace selected only platform modules are on. Each workspace gets modules from its plan, with exceptions set under <a href="<?= url_to('core.tenants') ?>">Workspaces</a>.
        <?php endif ?>
    </div>
<?php endif ?>

<?php if ($unmet !== []): ?>
    <div class="alert alert-warning" role="alert">
        <strong>Missing modules.</strong> <?= esc(implode('; ', $unmet)) ?>. Install the missing modules, or the ones that need them stay off.
    </div>
<?php endif ?>

<section class="eos-panel" aria-labelledby="installed-title">
    <header class="eos-panel-head">
        <h2 id="installed-title">Installed</h2>
        <span class="eos-count"><?= count(array_filter($modules, static fn (array $m): bool => $m['on'])) ?> of <?= count($modules) ?> on</span>
    </header>
    <div class="table-responsive">
        <table class="table eos-table mb-0 align-middle">
            <thead>
                <tr>
                    <th scope="col">Module</th>
                    <th scope="col">State</th>
                    <th scope="col">Needs</th>
                    <th scope="col" class="text-end">Version</th>
                    <?php if ($canSwitch): ?><th scope="col" class="text-end">Change</th><?php endif ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $name => $module): ?>
                    <tr>
                        <td>
                            <strong><?= esc($module['title']) ?></strong>
                            <?php if ($module['description'] !== ''): ?><br><small class="eos-muted"><?= esc($module['description']) ?></small><?php endif ?>
                        </td>
                        <td>
                            <span class="eos-module-state">
                                <span class="badge <?= $module['on'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $module['on'] ? 'On' : 'Off' ?></span>
                                <small class="eos-muted"><?= esc($module['reason']) ?></small>
                            </span>
                        </td>
                        <td class="eos-module-needs">
                            <?= $module['requires'] === [] ? '—' : esc(implode(', ', $module['requires'])) ?>
                            <?php if ($module['dependents'] !== []): ?><br>Needed by <?= esc(implode(', ', $module['dependents'])) ?><?php endif ?>
                        </td>
                        <td class="text-end font-tabular"><?= esc($module['version']) ?></td>
                        <?php if ($canSwitch): ?>
                            <td class="text-end">
                                <?php if ($module['always_on']): ?>
                                    <small class="eos-muted">Always on</small>
                                <?php else: ?>
                                    <form class="eos-switch-form justify-content-end" action="<?= url_to('core.modules.update', $name) ?>" method="post"
                                          <?= $module['on'] && $module['dependents'] !== [] ? 'data-eos-confirm="Switching ' . esc($module['title'], 'attr') . ' off also switches off ' . esc(implode(', ', $module['dependents']), 'attr') . '."' : '' ?>>
                                        <?= csrf_field() ?>
                                        <label class="visually-hidden" for="state-<?= esc($name, 'attr') ?>">State of <?= esc($module['title']) ?></label>
                                        <select class="form-select form-select-sm" id="state-<?= esc($name, 'attr') ?>" name="state">
                                            <option value="on"<?= $module['on'] && $module['reason'] !== 'default' ? ' selected' : '' ?>>On</option>
                                            <option value="off"<?= ! $module['on'] && $module['reason'] !== 'default' ? ' selected' : '' ?>>Off</option>
                                            <option value="default"<?= $module['reason'] === 'default' ? ' selected' : '' ?>>Default (<?= $module['default'] ? 'on' : 'off' ?>)</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Apply</button>
                                    </form>
                                <?php endif ?>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($notInstalled !== []): ?>
    <section class="eos-panel mt-4" aria-labelledby="not-installed-title">
        <header class="eos-panel-head eos-panel-head-stack">
            <h2 id="not-installed-title">Not installed</h2>
            <p>These modules are present but left out of this install, so none of their pages, tables or permissions exist.</p>
        </header>
        <ul class="list-group list-group-flush">
            <?php foreach ($notInstalled as $name => $reason): ?>
                <li class="list-group-item"><strong><?= esc($name) ?></strong> <small class="eos-muted">— <?= esc($reason) ?></small></li>
            <?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
