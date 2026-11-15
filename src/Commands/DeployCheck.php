<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use Config\Database;
use Ephraitech\Auth\Config\ConfigResolver;
use Ephraitech\Platform\Auth\ModulePermissionGate;
use Ephraitech\Platform\Config\AuthPermissions;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Models\TenantModel;
use Throwable;

/**
 * Checks a server before (and after) going live:
 *
 *     php spark deploy:check            # everything
 *     php spark deploy:check --wiring   # only the code wiring (safe on a laptop, in CI)
 *
 * FAIL means the system will not work properly or is unsafe; fix those
 * first. WARN means something people will notice. Exit code 1 when
 * anything fails, so a deploy script can stop.
 *
 * Besides the server (PHP, .env, HTTPS, database, folders, mail) it checks
 * the wiring that went wrong in earlier builds:
 *   - app/Config/Auth.php is the config ci4-auth actually uses;
 *   - the platform's permission gate is set, so switched-off modules' permissions fail can();
 *   - the 'platform' global filter is listed in app/Config/Filters.php;
 *   - every module route names a guard (auth: or can:);
 *   - composer.json requires codeigniter4/framework and composer.lock is committed;
 *   - the platform's assets are published and current;
 *   - multi-tenant: plans, owner role and tenant addresses make sense.
 */
class DeployCheck extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'deploy:check';
    protected $description = 'Checks PHP, .env, database, platform wiring, routes, tenancy and folders before going live.';
    protected $usage       = 'deploy:check [--wiring]';
    protected $options     = ['--wiring' => 'Only check code wiring (config, filters, routes, composer, assets); skip server and database checks.'];

    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private array $rows = [];

    private int $fails = 0;

    private int $warns = 0;

    public function run(array $params): int
    {
        helper('platform');

        $wiringOnly = (bool) $this->option('wiring');

        if (! $wiringOnly) {
            $this->php();
            $this->environment();
        }

        $this->authConfig();
        $this->filters();
        $this->modules();
        $this->routes();
        $this->composer();
        $this->assets();
        $this->tenancyConfig();

        if (! $wiringOnly && $this->database()) {
            $this->migrations();
            $this->roles();
            $this->owner();
            $this->tenants();
        }

        if (! $wiringOnly) {
            $this->folders();
            $this->mail();
        }

        CLI::table(array_map(static fn (array $r): array => [
            match ($r[0]) {
                'ok'    => CLI::color('OK', 'green'),
                'warn'  => CLI::color('WARN', 'yellow'),
                default => CLI::color('FAIL', 'red'),
            },
            $r[1],
            $r[2],
        ], $this->rows), ['', 'Check', 'Result']);

        if ($this->fails > 0) {
            CLI::error($this->fails . ' check(s) failed. Fix them before people start using the system.');

            return EXIT_ERROR;
        }

        CLI::write($this->warns > 0 ? 'Ready, with ' . $this->warns . ' thing(s) to finish.' : 'Ready.', $this->warns > 0 ? 'yellow' : 'green');

        return EXIT_SUCCESS;
    }

    // ------------------------------------------------------------------
    // Server
    // ------------------------------------------------------------------

    private function php(): void
    {
        $ok = version_compare(PHP_VERSION, '8.2.0', '>=');
        $this->add($ok ? 'ok' : 'fail', 'PHP version', PHP_VERSION . ($ok ? '' : ' (8.2 or newer needed)'));

        $wanted  = PlatformConfig::get()->requiredExtensions;
        $missing = array_values(array_filter($wanted, static fn (string $e): bool => ! extension_loaded($e)));
        $this->add($missing === [] ? 'ok' : 'fail', 'PHP extensions', $missing === [] ? implode(', ', $wanted) : 'Missing: ' . implode(', ', $missing) . ' (cPanel → Select PHP Version → Extensions)');

        $limit = $this->bytes((string) ini_get('memory_limit'));
        $this->add($limit === -1 || $limit >= 128 * 1024 ** 2 ? 'ok' : 'warn', 'PHP memory_limit', (string) ini_get('memory_limit') . ($limit !== -1 && $limit < 128 * 1024 ** 2 ? ' (128M or more recommended)' : ''));
    }

    private function environment(): void
    {
        $this->add(ENVIRONMENT === 'production' ? 'ok' : 'fail', 'CI_ENVIRONMENT', ENVIRONMENT . (ENVIRONMENT === 'production' ? '' : ': set CI_ENVIRONMENT = production in .env (other modes show errors to users)'));

        $app  = config('App');
        $base = (string) $app->baseURL;
        $this->add(str_starts_with($base, 'https://') ? 'ok' : 'fail', 'app.baseURL', $base . (str_starts_with($base, 'https://') ? '' : ': must be the https:// address of the system'));
        $this->add($app->forceGlobalSecureRequests ? 'ok' : 'warn', 'Force HTTPS', $app->forceGlobalSecureRequests ? 'On' : 'Off: set app.forceGlobalSecureRequests = true');
        $this->add($app->indexPage === '' ? 'ok' : 'warn', 'Clean addresses', $app->indexPage === '' ? 'On' : 'Links contain "' . $app->indexPage . '": set app.indexPage = \'\' in .env');

        $key = (string) config('Encryption')->key;
        $this->add($key !== '' ? 'ok' : 'fail', 'Encryption key', $key !== '' ? 'Set. Keep a copy somewhere safe' : 'Missing: run php spark key:generate (once)');

        $cookie = config('Cookie');
        $this->add($cookie->secure ? 'ok' : 'warn', 'Secure cookies', $cookie->secure ? 'On' : 'Off: set cookie.secure = true');
    }

    private function folders(): void
    {
        foreach (PlatformConfig::get()->writableFolders as $dir) {
            $path = WRITEPATH . $dir;
            $ok   = is_dir($path) && is_writable($path);
            $this->add($ok ? 'ok' : 'fail', 'writable/' . $dir, $ok ? 'Writable' : 'Not writable: mkdir -p ' . $path . ' && chmod 755 ' . $path);
        }

        $env = ROOTPATH . '.env';

        if (is_file($env)) {
            $mode = fileperms($env) & 0o777;
            $this->add(($mode & 0o007) === 0 ? 'ok' : 'warn', '.env permissions', sprintf('%o', $mode) . (($mode & 0o007) === 0 ? '' : ': run chmod 600 .env so other accounts cannot read it'));
        } else {
            $this->add('fail', '.env', 'Missing: copy .env.production.example to .env and fill it in');
        }
    }

    private function mail(): void
    {
        $email = config('Email');
        $ok    = $email->protocol !== 'smtp' || trim((string) $email->SMTPHost) !== '';
        $this->add($ok ? 'ok' : 'warn', 'Email', $ok ? ($email->protocol . ($email->protocol === 'smtp' ? ' via ' . $email->SMTPHost : '')) : 'No SMTP host: password reset links will not be emailed. Set email.* in .env');
    }

    // ------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------

    private function authConfig(): void
    {
        $reason = ConfigResolver::ignoredAppClassReason();

        if ($reason !== null) {
            $this->add('fail', 'Auth config', $reason);
        } else {
            $this->add(ConfigResolver::resolvedClass() === 'Config\Auth' ? 'ok' : 'fail', 'Auth config', ConfigResolver::resolvedClass() === 'Config\Auth'
                ? 'app/Config/Auth.php is used'
                : 'ci4-auth uses its own defaults: create app/Config/Auth.php extending Ephraitech\Auth\Config\Auth');
        }

        $platformReason = PlatformConfig::ignoredAppClassReason();
        $this->add($platformReason === null ? 'ok' : 'fail', 'Platform config', $platformReason ?? (PlatformConfig::resolvedClass() === PlatformConfig::APP_CLASS ? 'app/Config/Platform.php is used' : 'Package defaults (no app/Config/Platform.php)'));

        try {
            $auth = PlatformConfig::auth();
        } catch (Throwable $e) {
            $this->add('fail', 'Auth config', 'Invalid: ' . $e->getMessage());

            return;
        }

        $this->add($auth->permissionGate === ModulePermissionGate::class ? 'ok' : 'fail', 'Permission gate', $auth->permissionGate === ModulePermissionGate::class
            ? 'Switched-off modules fail can()'
            : 'Not set: add public ?string $permissionGate = \\' . ModulePermissionGate::class . '::class; to app/Config/Auth.php');

        $layout = (string) $auth->viewLayout;
        $this->add(str_starts_with($layout, 'Ephraitech\Platform\\') ? 'ok' : 'warn', 'Auth views', str_starts_with($layout, 'Ephraitech\Platform\\')
            ? 'Platform layout and pages'
            : "Layout is {$layout}: set \$viewLayout and \$views in app/Config/Auth.php to the platform's (Ephraitech\\Platform\\Views\\...) for branded sign-in pages");

        $this->add($auth->discoverModulePermissions ? 'ok' : 'fail', 'Module permissions', $auth->discoverModulePermissions
            ? count((new \Ephraitech\Platform\Module\ModuleRegistry())->permissions()) . ' from manifests'
            : 'Discovery is off ($discoverModulePermissions = false): module permissions never reach ci4-auth');

        $profile = array_diff(['first_name', 'last_name', 'job_title'], $auth->userAllowedFields);
        $this->add($profile === [] ? 'ok' : 'fail', 'Profile fields', $profile === []
            ? 'Names and job titles can be saved'
            : 'Missing from $userAllowedFields in app/Config/Auth.php: ' . implode(', ', $profile) . ' (the Staff screens save them)');

        if (AuthPermissions::$droppedGrants !== []) {
            $this->add('warn', 'Role grants', 'Modules grant to roles Config\Auth does not define: ' . implode(', ', array_keys(AuthPermissions::$droppedGrants)) . '. Add the roles, or grant those permissions in $matrix yourself');
        }
    }

    private function filters(): void
    {
        $filters = config('Filters');
        $before  = $filters->globals['before'] ?? [];
        $listed  = in_array('platform', $before, true) || array_key_exists('platform', $before);

        $this->add($listed ? 'ok' : 'fail', 'Platform filter', $listed
            ? 'Global before filter'
            : "Missing: put 'platform' first in \$globals['before'] in app/Config/Filters.php (tenants and the module gate depend on it)");

        $alias = ($filters->aliases['platform'] ?? null) === \Ephraitech\Platform\Filters\PlatformFilter::class;
        $this->add($alias ? 'ok' : 'fail', 'Filter aliases', $alias ? 'platform, module' : "Alias 'platform' points elsewhere: remove it from app/Config/Filters.php");
    }

    private function modules(): void
    {
        $registry = service('moduleRegistry');

        try {
            $modules = $registry->all();
            $registry->permissions();
            $registry->settings();
            $registry->sequences();
            $registry->boards();
            $registry->dependencyOrder();
        } catch (Throwable $e) {
            $this->add('fail', 'Modules', $e->getMessage());

            return;
        }

        $this->add('ok', 'Modules', count($modules) . ' installed: ' . implode(', ', array_keys($modules)));

        $unmet = $registry->unmetRequirements();
        $this->add($unmet === [] ? 'ok' : 'fail', 'Module requirements', $unmet === [] ? 'All met' : implode('; ', $unmet));

        $autoload = service('autoloader');
        $missing  = [];

        foreach ($modules as $name => $module) {
            if ($autoload->getNamespace($module['namespace']) === []) {
                $missing[] = $name;
            }
        }

        $this->add($missing === [] ? 'ok' : 'fail', 'Module namespaces', $missing === []
            ? 'Registered'
            : 'Not registered: ' . implode(', ', $missing) . '. app/Config/Autoload.php must add ModuleAutoloader::namespaces() to $psr4');
    }

    /**
     * Every route handled by a module controller must name a guard, so a
     * page can never be public by accident.
     */
    private function routes(): void
    {
        $collection = service('routes');
        $collection->loadRoutes();

        $gate    = service('moduleGate');
        $open    = [];
        $checked = 0;

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            foreach ($collection->getRoutes($verb) as $from => $handler) {
                if (! is_string($handler) || $gate->moduleForClass(ltrim(explode('::', $handler)[0], '\\')) === null) {
                    continue;
                }

                $checked++;
                $filters = $collection->getFiltersForRoute((string) $from, $verb);
                $guarded = false;

                foreach ($filters as $filter) {
                    if (str_starts_with($filter, 'auth:') || $filter === 'auth' || str_starts_with($filter, 'can:') || str_starts_with($filter, 'permission:')) {
                        $guarded = true;
                    }
                }

                if (! $guarded) {
                    $open[] = $verb . ' /' . ltrim((string) $from, '/');
                }
            }
        }

        try {
            $login = $collection->reverseRoute('auth.login');
        } catch (Throwable) {
            $login = false;
        }

        $this->add($login !== false ? 'ok' : 'fail', 'Sign-in pages', $login !== false
            ? 'At /' . ltrim((string) $login, '/')
            : "Missing: add service('auth')->routes(\$routes, except: ['register']); to app/Config/Routes.php");

        $this->add($open === [] ? 'ok' : 'fail', 'Route guards', $open === []
            ? "{$checked} module routes, each with auth: or can:"
            : 'No guard on: ' . implode(', ', array_slice($open, 0, 8)) . (count($open) > 8 ? ' and ' . (count($open) - 8) . ' more' : '') . ". Add 'filter' => 'can:session,<permission>' (or can:token, for API routes)");
    }

    private function composer(): void
    {
        $json = is_file(ROOTPATH . 'composer.json') ? json_decode((string) file_get_contents(ROOTPATH . 'composer.json'), true) : null;

        if (! is_array($json)) {
            $this->add('fail', 'composer.json', 'Missing or invalid');

            return;
        }

        $require = $json['require'] ?? [];
        $this->add(isset($require['codeigniter4/framework']) ? 'ok' : 'fail', 'CodeIgniter', isset($require['codeigniter4/framework'])
            ? 'codeigniter4/framework ' . $require['codeigniter4/framework']
            : 'composer.json must require codeigniter4/framework (codeigniter4/appstarter is a project template, not a library)');

        $lock = ROOTPATH . 'composer.lock';

        if (! is_file($lock)) {
            $this->add('fail', 'composer.lock', 'Missing: run composer update once and commit composer.lock, so the server installs exactly what you tested');

            return;
        }

        if (is_dir(ROOTPATH . '.git')) {
            $tracked = $this->git('ls-files --error-unmatch composer.lock');
            $this->add($tracked !== null ? 'ok' : 'fail', 'composer.lock', $tracked !== null ? 'Committed' : 'Not committed: git add composer.lock (check .gitignore does not ignore it)');
        } else {
            $this->add('ok', 'composer.lock', 'Present');
        }

        $installed = ROOTPATH . 'vendor/composer/installed.json';

        if (is_file($installed) && filemtime($lock) > filemtime($installed) + 60) {
            $this->add('warn', 'vendor/', 'composer.lock is newer than the installed packages: run composer install --no-dev');
        }
    }

    private function assets(): void
    {
        $config = PlatformConfig::get();
        $source = dirname(__DIR__, 2) . '/resources/assets/css/platform.css';
        $target = FCPATH . trim($config->assetPath, '/') . '/css/platform.css';

        if (! is_file($target)) {
            $this->add('fail', 'Platform assets', 'Not published: run php spark platform:publish');

            return;
        }

        $current = is_file($source) && md5_file($source) === md5_file($target);
        $this->add($current ? 'ok' : 'warn', 'Platform assets', $current ? 'Published and current' : 'Out of date: run php spark platform:publish');
    }

    private function tenancyConfig(): void
    {
        $tenancy = service('tenancy');

        if (! $tenancy->isMulti()) {
            $this->add('ok', 'Tenancy', 'Single install');

            return;
        }

        $config  = PlatformConfig::get();
        $auth    = PlatformConfig::auth();
        $modules = array_keys(service('moduleRegistry')->all());
        $notes   = [];
        $state   = 'ok';

        if (! isset($config->plans[$config->defaultPlan])) {
            $state   = 'fail';
            $notes[] = "default plan '{$config->defaultPlan}' is not in \$plans";
        }

        foreach ($config->plans as $key => $plan) {
            $unknown = array_diff($plan['modules'] ?? [], [...$modules, '*']);

            if ($unknown !== []) {
                $state   = $state === 'fail' ? 'fail' : 'warn';
                $notes[] = "plan '{$key}' lists modules that are not installed: " . implode(', ', $unknown);
            }
        }

        if (! isset($auth->roles[$config->tenantOwnerRole])) {
            $state   = 'fail';
            $notes[] = "\$tenantOwnerRole '{$config->tenantOwnerRole}' is not a role in Config\\Auth";
        }

        if (in_array('domain', $config->tenantSources, true) && $config->baseDomain === '' && ! $config->customDomains) {
            $state   = $state === 'fail' ? 'fail' : 'warn';
            $notes[] = "'domain' is a tenant source but there is no baseDomain and own domains are off";
        }

        if (! in_array('session', $config->tenantSources, true) && $config->baseDomain === '') {
            $state   = $state === 'fail' ? 'fail' : 'warn';
            $notes[] = 'with no baseDomain and no session source, people on the shared domain cannot pick a workspace';
        }

        $this->add($state, 'Tenancy', 'Multi-tenant (' . implode(', ', $config->tenantSources) . ')' . ($notes === [] ? '' : ': ' . implode('; ', $notes)));
    }

    // ------------------------------------------------------------------
    // Database
    // ------------------------------------------------------------------

    private function database(): bool
    {
        try {
            $db = Database::connect();
            $db->initialize();
            $version = $db->getVersion();
        } catch (Throwable $e) {
            $this->add('fail', 'Database', 'Cannot connect: ' . $e->getMessage());

            return false;
        }

        $this->add('ok', 'Database', $db->getDatabase() . ' on ' . $db->getPlatform() . ' ' . $version);

        return true;
    }

    private function migrations(): void
    {
        try {
            $runner = service('migrations');
            $runner->setNamespace(null);

            $done = [];

            foreach ($runner->getHistory() as $row) {
                $done[$runner->getObjectUid($row)] = true;
            }

            $pending = [];

            foreach ($runner->findMigrations() as $migration) {
                if (! isset($done[$migration->uid])) {
                    $pending[] = $migration->namespace . ' ' . $migration->version;
                }
            }
        } catch (Throwable $e) {
            $this->add('fail', 'Migrations', $e->getMessage());

            return;
        }

        $this->add($pending === [] ? 'ok' : 'fail', 'Migrations', $pending === [] ? 'All run' : count($pending) . ' pending: run php spark platform:migrate');
    }

    private function roles(): void
    {
        $auth = PlatformConfig::auth();

        try {
            $roles = array_column(db_connect($auth->DBGroup)->table($auth->table('roles'))->select('name')->get()->getResultArray(), 'name');
            $perms = array_column(db_connect($auth->DBGroup)->table($auth->table('permissions'))->select('name')->get()->getResultArray(), 'name');
        } catch (Throwable $e) {
            $this->add('fail', 'Roles', $e->getMessage());

            return;
        }

        $missingRoles = array_values(array_diff(array_keys($auth->roles), $roles));
        $missingPerms = array_values(array_diff(array_keys($auth->permissions), $perms));

        $this->add($missingRoles === [] && $missingPerms === [] ? 'ok' : 'fail', 'Roles and permissions', $missingRoles === [] && $missingPerms === []
            ? count($roles) . ' roles, ' . count($perms) . ' permissions'
            : 'Not synced (' . implode(', ', array_slice([...$missingRoles, ...$missingPerms], 0, 6)) . '): run php spark auth:sync');
    }

    private function owner(): void
    {
        $auth = PlatformConfig::auth();

        if ($auth->superRole === null) {
            $this->add('warn', 'Owner account', 'No super role defined');

            return;
        }

        try {
            $ids = service('authAuthorizer')->usersWithRole($auth->superRole, '');
        } catch (Throwable $e) {
            $this->add('fail', 'Owner account', $e->getMessage());

            return;
        }

        $this->add($ids !== [] ? 'ok' : 'fail', 'Owner account', $ids !== []
            ? count($ids) . " account(s) with the {$auth->superRole} role"
            : "None: php spark auth:user:create --email you@example.com --generate --role {$auth->superRole} --verified");
    }

    private function tenants(): void
    {
        if (! service('tenancy')->isMulti()) {
            return;
        }

        try {
            $rows = model(TenantModel::class, false)->findAll();
        } catch (Throwable $e) {
            $this->add('fail', 'Workspaces', $e->getMessage());

            return;
        }

        $plans   = PlatformConfig::get()->plans;
        $unknown = array_values(array_filter($rows, static fn (array $t): bool => ! isset($plans[$t['plan']])));
        $usable  = count(array_filter($rows, static fn (array $t): bool => TenantModel::isUsable($t)));

        $this->add($unknown === [] ? 'ok' : 'warn', 'Workspaces', count($rows) . " ({$usable} usable)" . ($unknown === [] ? '' : '; on plans that no longer exist: ' . implode(', ', array_column($unknown, 'slug'))));
    }

    // ------------------------------------------------------------------

    private function add(string $state, string $check, string $result): void
    {
        $this->rows[] = [$state, $check, $result];

        if ($state === 'fail') {
            $this->fails++;
        } elseif ($state === 'warn') {
            $this->warns++;
        }
    }

    private function git(string $args): ?string
    {
        $out  = [];
        $code = 1;
        @exec('git -C ' . escapeshellarg(ROOTPATH) . ' ' . $args . ' 2>/dev/null', $out, $code);

        return $code === 0 ? implode("\n", $out) : null;
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $n = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $n * 1024 ** 3,
            'm'     => $n * 1024 ** 2,
            'k'     => $n * 1024,
            default => $n,
        };
    }
}
