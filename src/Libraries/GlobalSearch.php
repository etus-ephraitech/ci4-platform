<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use Ephraitech\Platform\Module\ModuleGate;
use Ephraitech\Platform\Module\ModuleRegistry;
use Throwable;

/**
 * Runs the search providers of switched-on modules (module.php 'search')
 * and merges their results. A failing provider is logged and skipped, so
 * one module's bug never breaks search for the rest.
 *
 * A provider is "Class::method", called with the term and a per-provider
 * limit, returning rows of type, title, subtitle, url and icon that the
 * signed-in person may see (and, for the API, an 'id').
 */
class GlobalSearch
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleGate $gate,
    ) {
    }

    /**
     * @return list<array{type: string, title: string, subtitle: string, url: string, icon: string, module: string, id: ?string}>
     */
    public function find(string $term, int $perProvider = 6): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $results = [];

        foreach ($this->registry->searchProviders() as $provider) {
            if (! $this->gate->enabled($provider['module'])) {
                continue;
            }

            [$class, $method] = array_pad(explode('::', $provider['call'], 2), 2, 'find');

            try {
                $found = (new $class())->{$method}($term, $perProvider);
            } catch (Throwable $e) {
                log_message('error', 'Search provider {provider} failed: {message}', ['provider' => $provider['call'], 'message' => $e->getMessage()]);

                continue;
            }

            foreach ((array) $found as $row) {
                $results[] = [
                    'type'     => (string) ($row['type'] ?? ''),
                    'title'    => (string) ($row['title'] ?? ''),
                    'subtitle' => (string) ($row['subtitle'] ?? ''),
                    'url'      => (string) ($row['url'] ?? '#'),
                    'icon'     => (string) ($row['icon'] ?? 'bi-search'),
                    'module'   => $provider['module'],
                    'id'       => isset($row['id']) ? (string) $row['id'] : null,
                ];
            }
        }

        return $results;
    }
}
