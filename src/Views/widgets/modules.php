<?php
/**
 * @var array<string, array<string, mixed>>               $modules
 * @var array<string, array{on: bool, source: string}>    $decisions
 * @var array<string, string>                             $notInstalled
 */
$on = count(array_filter($decisions, static fn (array $d): bool => $d['on']));
?>
<section class="eos-panel" aria-labelledby="modules-title">
    <header class="eos-panel-head">
        <h2 id="modules-title">Modules</h2>
        <span class="eos-count"><?= $on ?> of <?= count($modules) ?> on<?= $notInstalled !== [] ? ', ' . count($notInstalled) . ' not installed' : '' ?></span>
    </header>
    <div class="table-responsive">
        <table class="table eos-table mb-0">
            <thead>
                <tr>
                    <th scope="col">Module</th>
                    <th scope="col">What it covers</th>
                    <th scope="col">State</th>
                    <th scope="col" class="text-end">Version</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $name => $module): ?>
                    <?php $d = $decisions[$name] ?? ['on' => false, 'source' => '']; ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($module['title']) ?></td>
                        <td class="eos-muted"><?= esc($module['description']) ?></td>
                        <td>
                            <span class="badge <?= $d['on'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $d['on'] ? 'On' : 'Off' ?></span>
                            <small class="eos-muted ms-1"><?= esc($d['source']) ?></small>
                        </td>
                        <td class="text-end font-tabular"><?= esc($module['version']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php foreach ($notInstalled as $name => $reason): ?>
                    <tr class="eos-muted">
                        <td><?= esc($name) ?></td>
                        <td colspan="3">Not installed: <?= esc($reason) ?>.</td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php if (can('modules.view')): ?>
        <footer class="eos-panel-foot"><a href="<?= url_to('core.modules') ?>">Manage modules</a></footer>
    <?php endif ?>
</section>
