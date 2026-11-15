<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use Ephraitech\Platform\Module\ModuleGate;
use Ephraitech\Platform\Module\ModuleRegistry;
use Throwable;

/**
 * Extension points: how modules add to each other without depending on
 * each other.
 *
 * The module that owns a page or an API resource names a point and
 * renders whatever is contributed to it; other modules contribute in
 * their manifest:
 *
 *     // Finance/module.php
 *     'extends' => [
 *         'crm.client.panels' => [['cell' => 'Modules\Finance\Cells\Invoices::forClient', 'title' => 'Invoices', 'permission' => 'invoices.view']],
 *         'crm.client.api'    => [['call' => 'Modules\Finance\Api\ClientSummary::forClient', 'key' => 'invoices', 'permission' => 'invoices.view']],
 *     ],
 *
 *     // Crm client page                      // Crm API controller
 *     <?= slot('crm.client.panels', ['client_id' => $id]) ?>      $data['related'] = extension_data('crm.client.api', $id);
 *
 * Contributions from switched-off modules, and those whose permission the
 * person lacks, are left out. A contribution that throws is logged and
 * skipped, so one module's fault never breaks another's page.
 */
class Extensions
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleGate $gate,
    ) {
    }

    /**
     * Contributions to a point that apply here and now.
     *
     * @return list<array<string, mixed>>
     */
    public function items(string $point): array
    {
        helper('platform');

        return array_values(array_filter(
            $this->registry->extensions($point),
            fn (array $item): bool => $this->gate->enabled($item['module']) && allowed($item['permission'])
        ));
    }

    /**
     * Render the view cells contributed to a point.
     *
     * @param array<string, mixed> $params passed to every cell
     *
     * @return list<array{title: string, icon: string, key: string, module: string, html: string}>
     */
    public function render(string $point, array $params = []): array
    {
        $out = [];

        foreach ($this->items($point) as $item) {
            if ($item['cell'] === null) {
                continue;
            }

            try {
                $html = view_cell($item['cell'], $params);
            } catch (Throwable $e) {
                log_message('error', 'Extension {cell} on {point} failed: {message}', ['cell' => $item['cell'], 'point' => $point, 'message' => $e->getMessage()]);

                continue;
            }

            if (trim($html) === '') {
                continue;
            }

            $out[] = ['title' => $item['title'], 'icon' => $item['icon'], 'key' => $item['key'], 'module' => $item['module'], 'html' => $html];
        }

        return $out;
    }

    /**
     * Call the data providers contributed to a point.
     *
     * @return array<string, mixed> key => what the provider returned
     */
    public function data(string $point, mixed ...$args): array
    {
        $out = [];

        foreach ($this->items($point) as $item) {
            if ($item['call'] === null) {
                continue;
            }

            [$class, $method] = array_pad(explode('::', $item['call'], 2), 2, '__invoke');

            try {
                $out[$item['key']] = (new $class())->{$method}(...$args);
            } catch (Throwable $e) {
                log_message('error', 'Extension {call} on {point} failed: {message}', ['call' => $item['call'], 'point' => $point, 'message' => $e->getMessage()]);
            }
        }

        return $out;
    }
}
