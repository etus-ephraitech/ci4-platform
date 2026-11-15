<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands\Concerns;

use CodeIgniter\CLI\CLI;

/**
 * Options given as "--name value" or "--name=value".
 *
 * CodeIgniter's own parser only understands the first form and reads
 * "--entity=Client" as an option named "entity=Client".
 */
trait ReadsOptions
{
    protected function option(string $name): string|bool|null
    {
        $value = CLI::getOption($name);

        if ($value !== null) {
            return $value === '' ? true : $value;
        }

        foreach (CLI::getOptions() as $key => $given) {
            if (is_string($key) && str_starts_with($key, $name . '=')) {
                return substr($key, strlen($name) + 1);
            }
        }

        return array_key_exists($name, CLI::getOptions()) ? true : null;
    }
}
