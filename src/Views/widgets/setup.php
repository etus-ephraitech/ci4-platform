<?php
/**
 * @var list<array{done: bool, label: string, hint: string, url: ?string}> $steps
 * @var int $completed
 */
$total = count($steps);
?>
<section class="eos-panel h-100" aria-labelledby="setup-title">
    <header class="eos-panel-head">
        <h2 id="setup-title">Getting set up</h2>
        <span class="eos-count"><?= $completed ?> of <?= $total ?> done</span>
    </header>
    <div class="eos-progress" role="progressbar" aria-label="Setup progress" aria-valuenow="<?= $completed ?>" aria-valuemin="0" aria-valuemax="<?= $total ?>">
        <span style="width: <?= $total > 0 ? round($completed / $total * 100) : 0 ?>%"></span>
    </div>
    <ol class="eos-steps">
        <?php foreach ($steps as $step): ?>
            <li class="<?= $step['done'] ? 'is-done' : '' ?>">
                <i class="bi <?= $step['done'] ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
                <div>
                    <?php if (! $step['done'] && $step['url'] !== null): ?>
                        <a href="<?= esc($step['url'], 'attr') ?>"><?= esc($step['label']) ?></a>
                    <?php else: ?>
                        <span><?= esc($step['label']) ?></span>
                    <?php endif ?>
                    <?php if (! $step['done']): ?>
                        <small><?= esc($step['hint']) ?></small>
                    <?php endif ?>
                </div>
                <span class="visually-hidden"><?= $step['done'] ? 'Done' : 'Not done' ?></span>
            </li>
        <?php endforeach ?>
    </ol>
</section>
