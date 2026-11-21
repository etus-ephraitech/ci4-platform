<?php
/**
 * Pages of a list: "Showing 26–50 of 512", then Previous, the pages
 * around this one (with the first and last), Next. Use it as the app's
 * full pager in app/Config/Pager.php:
 *
 *     'default_full' => 'Ephraitech\Platform\Views\pager\full',
 *
 * @var CodeIgniter\Pager\PagerRenderer $pager
 */
$pager->setSurroundCount(2);

if ($pager->getPageCount() <= 1) {
    return;
}

$current = $pager->getCurrentPageNumber();
$last    = $pager->getPageCount();
$total   = $pager->getTotal();
$links   = $pager->links();
$numbers = array_map(static fn (array $link): int => (int) $link['title'], $links);
?>
<nav class="eos-pager" aria-label="Pages">
    <?php if ($total !== null && $pager->getPerPageStart() !== null): ?>
        <p class="eos-pager-info mb-0">Showing <?= number_format((int) $pager->getPerPageStart()) ?>–<?= number_format((int) $pager->getPerPageEnd()) ?> of <?= number_format($total) ?></p>
    <?php else: ?>
        <p class="eos-pager-info mb-0">Page <?= $current ?> of <?= $last ?></p>
    <?php endif ?>

    <ul class="pagination">
        <?php if ($pager->hasPreviousPage()): ?>
            <li><a href="<?= esc($pager->getPreviousPage(), 'attr') ?>" rel="prev"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Previous page</span></a></li>
        <?php else: ?>
            <li class="disabled"><span aria-hidden="true"><i class="bi bi-chevron-left"></i></span></li>
        <?php endif ?>

        <?php if ($numbers !== [] && min($numbers) > 1): ?>
            <li><a href="<?= esc($pager->getFirst(), 'attr') ?>" aria-label="Page 1">1</a></li>
            <?php if (min($numbers) > 2): ?><li class="disabled"><span>…</span></li><?php endif ?>
        <?php endif ?>

        <?php foreach ($links as $link): ?>
            <li<?= $link['active'] ? ' class="active"' : '' ?>>
                <a href="<?= esc($link['uri'], 'attr') ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?> aria-label="Page <?= esc((string) $link['title'], 'attr') ?>"><?= esc((string) $link['title']) ?></a>
            </li>
        <?php endforeach ?>

        <?php if ($numbers !== [] && max($numbers) < $last): ?>
            <?php if (max($numbers) < $last - 1): ?><li class="disabled"><span>…</span></li><?php endif ?>
            <li><a href="<?= esc($pager->getLast(), 'attr') ?>" aria-label="Page <?= $last ?>"><?= $last ?></a></li>
        <?php endif ?>

        <?php if ($pager->hasNextPage()): ?>
            <li><a href="<?= esc($pager->getNextPage(), 'attr') ?>" rel="next"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Next page</span></a></li>
        <?php else: ?>
            <li class="disabled"><span aria-hidden="true"><i class="bi bi-chevron-right"></i></span></li>
        <?php endif ?>
    </ul>
</nav>
