<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use Ephraitech\Platform\Config\PlatformConfig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Copies the platform's public files into the app.
 *
 *     php spark platform:publish            # CSS, JS and default logos → public/vendor/platform
 *     php spark platform:publish --deploy   # also the cPanel deploy kit (deploy/, .cpanel.yml, .env.production.example)
 *
 * Assets are always replaced (they belong to the package; run this after
 * every composer update, the deploy script does). Deploy files are only
 * written when missing, unless --force.
 */
class PlatformPublish extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'platform:publish';
    protected $description = 'Publishes platform assets to public/ (and with --deploy, the cPanel deploy kit).';
    protected $usage       = 'platform:publish [--deploy] [--force]';
    protected $options     = [
        '--deploy' => 'Also write deploy/cpanel-deploy.sh, .cpanel.yml and .env.production.example.',
        '--force'  => 'Overwrite deploy files that already exist.',
    ];

    public function run(array $params): int
    {
        $package = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
        $target  = FCPATH . trim(PlatformConfig::get()->assetPath, '/\\') . DIRECTORY_SEPARATOR;

        $count = $this->copyTree($package . 'assets', $target, true);
        CLI::write("Published {$count} asset file(s) to " . CLI::color(str_replace(ROOTPATH, '', $target), 'green'));

        if ($this->option('deploy')) {
            $force = (bool) $this->option('force');
            $files = [
                'deploy/cpanel-deploy.sh'  => ROOTPATH . 'deploy' . DIRECTORY_SEPARATOR . 'cpanel-deploy.sh',
                'deploy/cpanel.yml'        => ROOTPATH . '.cpanel.yml',
                'deploy/env.production'    => ROOTPATH . '.env.production.example',
                'deploy/DEPLOY.md'         => ROOTPATH . 'deploy' . DIRECTORY_SEPARATOR . 'DEPLOY.md',
            ];

            foreach ($files as $stub => $destination) {
                $relative = str_replace(ROOTPATH, '', $destination);

                if (is_file($destination) && ! $force) {
                    CLI::write("  kept    {$relative} (exists; --force to replace)", 'yellow');

                    continue;
                }

                if (! is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0775, true);
                }

                copy($package . 'stubs' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stub), $destination);

                if (str_ends_with($destination, '.sh')) {
                    chmod($destination, 0755);
                }

                CLI::write('  written ' . CLI::color($relative, 'green'));
            }
        }

        return EXIT_SUCCESS;
    }

    private function copyTree(string $from, string $to, bool $overwrite): int
    {
        $count = 0;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($items as $item) {
            $destination = $to . substr($item->getPathname(), strlen($from) + 1);

            if ($item->isDir()) {
                if (! is_dir($destination)) {
                    mkdir($destination, 0775, true);
                }

                continue;
            }

            if (! $overwrite && is_file($destination)) {
                continue;
            }

            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0775, true);
            }

            copy($item->getPathname(), $destination);
            $count++;
        }

        return $count;
    }
}
