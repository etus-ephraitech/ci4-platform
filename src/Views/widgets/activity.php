<?php
/**
 * @var list<array<string, mixed>> $rows
 */
?>
<section class="eos-panel h-100" aria-labelledby="activity-title">
    <header class="eos-panel-head">
        <h2 id="activity-title">Recent activity</h2>
        <a href="<?= url_to('core.audit') ?>">Full audit log</a>
    </header>
    <?php if ($rows === []): ?>
        <p class="eos-muted mb-0">No activity recorded yet. Changes to settings and records will be listed here.</p>
    <?php else: ?>
        <ul class="eos-activity">
            <?php foreach ($rows as $row): ?>
                <li>
                    <span class="eos-avatar eos-avatar-sm" aria-hidden="true"><?= esc(initials(display_name($row))) ?></span>
                    <div>
                        <p><strong><?= esc(display_name($row)) ?></strong> <?= esc(lcfirst($row['description'])) ?></p>
                        <small><?= esc($row['module']) ?>, <time datetime="<?= esc($row['created_at'], 'attr') ?>"><?= esc(date('j M, H:i', strtotime((string) $row['created_at']))) ?></time></small>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
