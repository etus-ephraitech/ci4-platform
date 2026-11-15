<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Support;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Ephraitech\Auth\Authorization\RbacSynchronizer;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Entities\User;
use Ephraitech\Platform\Auth\ModulePermissionGate;
use Ephraitech\Platform\Config\AuthPermissions;
use Ephraitech\Platform\Config\Platform;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Module\ModuleAutoloader;
use Ephraitech\Platform\Module\ModuleLocator;
use Ephraitech\Platform\Module\ModuleRegistry;

/**
 * Freshly migrated database (ci4-auth, platform and fixture modules in
 * tests/_support/modules), RBAC synced, and the fixture modules installed.
 *
 * Fixtures: Crm (crm.*), Sales (needs Crm), Payroll ('default' => false),
 * Ghost (has a .disabled file, never installed).
 *
 * Override $tenancy ('single' | 'multi') and $env (platform.modules*)
 * per test class.
 */
abstract class PlatformTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected const PASSWORD = 'Correct-Horse-42';

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected string $tenancy = 'single';

    /**
     * @var array<string, string>
     */
    protected array $env = [];

    protected Auth $authConfig;

    protected Platform $platform;

    protected ModuleLocator $locator;

    /**
     * Runs after the test application (and its autoloader) is created and
     * before the database is migrated.
     *
     * @var list<string>
     */
    protected $setUpMethods = ['resetFactories', 'mockCache', 'mockEmail', 'mockSession', 'installFixtureModules'];

    protected function setUp(): void
    {
        $this->resetServices();
        ModuleRegistry::flush();
        AuthPermissions::$droppedGrants = [];

        parent::setUp();

        $this->authConfig                 = PlatformConfig::auth();
        $this->authConfig->tenancy        = $this->tenancy;
        $this->authConfig->permissionGate = ModulePermissionGate::class;
        $this->authConfig->hashOptions    = ['cost' => 4];
        $this->authConfig->userAllowedFields = ['first_name', 'last_name', 'job_title'];
        $this->authConfig->roles['owner'] = ['title' => 'Owner', 'description' => 'Runs a workspace'];

        $this->platform               = PlatformConfig::get();
        $this->platform->gateCacheTtl = 0;
        $this->platform->plans        = [
            'basic' => ['title' => 'Basic', 'modules' => ['Crm']],
            'pro'   => ['title' => 'Pro', 'modules' => ['*']],
        ];
        $this->platform->defaultPlan     = 'basic';
        $this->platform->tenantOwnerRole = 'owner';

        RbacSynchronizer::create($this->authConfig)->sync();
    }

    /**
     * Register the fixture modules' namespaces (as app/Config/Autoload.php
     * does through ModuleAutoloader) and the registry that knows them.
     */
    protected function installFixtureModules(): void
    {
        $this->locator = new ModuleLocator(__DIR__ . DIRECTORY_SEPARATOR, $this->env, __DIR__ . '/no-composer/autoload.php');

        $autoloader = service('autoloader');

        foreach (ModuleAutoloader::namespaces($this->locator) as $namespace => $path) {
            $autoloader->addNamespace($namespace, $path);
        }

        // After a service reset this is a new autoloader instance that is
        // not yet registered with PHP; the app's own is registered at boot.
        $autoloader->register();

        Services::injectMock('moduleRegistry', new ModuleRegistry($this->locator));

        // Routes may already have been collected without the fixture modules.
        Services::resetSingle('locator');
        Services::resetSingle('routes');

        // Known before the migrations, routes and auth services read the config.
        config(Auth::class)->tenancy        = $this->tenancy;
        config(Auth::class)->permissionGate = ModulePermissionGate::class;

        service('routes')->loadRoutes();

        // What app/Config/Routes.php in the starter adds.
        service('auth')->routes(service('routes'), except: ['register']);
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        service('tenancy')->leave();

        parent::tearDown();
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $email = 'jane@example.com', array $roles = ['user'], ?string $tenant = null): User
    {
        $user = service('authRegistrar')->register(['email' => $email], self::PASSWORD, [], ['roles' => []]);

        if ($roles !== []) {
            service('authAuthorizer')->syncRoles($user, $roles, $tenant ?? '');
        }

        return $user;
    }
}
