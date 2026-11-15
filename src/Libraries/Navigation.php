<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use Ephraitech\Platform\Config\PlatformConfig;
use Throwable;

/**
 * Sidebar entries and "New" actions the signed-in person can use: from
 * switched-on modules, allowed by their permissions, with a route that
 * exists. Shared by the web layout and the API context endpoint.
 */
final class Navigation
{
    /**
     * Sidebar entries grouped by section, sections in Config\Platform order.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function menu(): array
    {
        helper(['platform', 'url']);

        $gate   = service('moduleGate');
        $groups = [];

        foreach (service('moduleRegistry')->menu() as $item) {
            if (! $gate->enabled($item['module']) || ! allowed($item['permission'])) {
                continue;
            }

            try {
                $item['url'] = url_to($item['route']);
            } catch (Throwable) {
                continue;
            }

            $groups[$item['group']][] = $item;
        }

        foreach ($groups as &$items) {
            usort($items, static fn (array $a, array $b): int => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        }
        unset($items);

        return self::sortGroups($groups);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function quickActions(): array
    {
        helper(['platform', 'url']);

        $gate    = service('moduleGate');
        $actions = [];

        foreach (service('moduleRegistry')->quickActions() as $action) {
            if (! $gate->enabled($action['module']) || ! allowed($action['permission'])) {
                continue;
            }

            try {
                $action['url'] = url_to($action['route']);
            } catch (Throwable) {
                continue;
            }

            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $groups
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function sortGroups(array $groups): array
    {
        $config = PlatformConfig::get();
        $first  = array_flip($config->menuGroupsFirst);
        $last   = array_flip($config->menuGroupsLast);

        uksort($groups, static function (string $a, string $b) use ($first, $last): int {
            $rank = static fn (string $g): array => isset($first[$g]) ? [0, $first[$g], ''] : (isset($last[$g]) ? [2, $last[$g], ''] : [1, 0, $g]);

            return $rank($a) <=> $rank($b);
        });

        return $groups;
    }
}
