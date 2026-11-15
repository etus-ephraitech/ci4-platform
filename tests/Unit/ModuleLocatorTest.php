<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Ephraitech\Platform\Module\ModuleAutoloader;
use Ephraitech\Platform\Module\ModuleLocator;
use Ephraitech\Platform\Module\ModuleRegistry;

final class ModuleLocatorTest extends CIUnitTestCase
{
    private const ROOT = __DIR__ . '/../_support/';

    protected function setUp(): void
    {
        parent::setUp();
        ModuleLocator::flush();
    }

    private function names(array $env): array
    {
        return array_column((new ModuleLocator(self::ROOT, $env, self::ROOT . 'none/autoload.php'))->installed(), 'name');
    }

    public function testFindsBuiltInAndAppModules(): void
    {
        $this->assertSame(['Core', 'Crm', 'Payroll', 'Sales', 'Users'], $this->names([]));
    }

    public function testDisabledFileKeepsAModuleOut(): void
    {
        $all = (new ModuleLocator(self::ROOT, [], self::ROOT . 'none/autoload.php'))->all();
        $ghost = array_values(array_filter($all, static fn (array $m): bool => $m['name'] === 'Ghost'))[0];

        $this->assertFalse($ghost['installed']);
        $this->assertStringContainsString('.disabled', (string) $ghost['reason']);
    }

    public function testModulesOffAndOnlyFromTheEnvironment(): void
    {
        $this->assertSame(['Core', 'Crm', 'Users'], $this->names(['platform.modulesOff' => 'Sales, Payroll']));
        $this->assertSame(['Core', 'Payroll', 'Users'], $this->names(['platform.modulesOnly' => 'Payroll']));
        $this->assertContains('Users', $this->names(['platform.modulesOff' => 'Users']), 'built-in modules cannot be left out');
    }

    public function testNamespacesForTheAutoloader(): void
    {
        $namespaces = ModuleAutoloader::namespaces(new ModuleLocator(self::ROOT, ['platform.modulesOff' => 'Payroll'], self::ROOT . 'none/autoload.php'));

        $this->assertArrayHasKey('Modules\Crm', $namespaces);
        $this->assertArrayHasKey('Ephraitech\Platform\Users', $namespaces);
        $this->assertArrayNotHasKey('Modules\Payroll', $namespaces);
        $this->assertArrayNotHasKey('Modules\Ghost', $namespaces);
    }

    public function testRegistryOrdersByDependencies(): void
    {
        ModuleRegistry::flush();
        $registry = new ModuleRegistry(new ModuleLocator(self::ROOT, [], self::ROOT . 'none/autoload.php'));
        $order    = $registry->dependencyOrder();

        $this->assertLessThan(array_search('Sales', $order, true), array_search('Crm', $order, true));
        $this->assertSame('Core', $order[0]);
        $this->assertSame(['Sales'], $registry->dependentsOf('Crm'));
        $this->assertSame('Crm', $registry->moduleForPermission('crm.view'));
        $this->assertSame('Crm', $registry->moduleForClass('Modules\Crm\Controllers\Clients'));
        $this->assertSame('Users', $registry->moduleForClass('\Ephraitech\Platform\Users\Controllers\StaffController'));
        $this->assertSame('Core', $registry->moduleForClass('Ephraitech\Platform\Controllers\SettingsController'));
        $this->assertNull($registry->moduleForClass('App\Controllers\Home'));
    }

    public function testRegistryReportsUnmetRequirements(): void
    {
        ModuleRegistry::flush();
        $registry = new ModuleRegistry(new ModuleLocator(self::ROOT, ['platform.modulesOff' => 'Crm'], self::ROOT . 'none/autoload.php'));

        $this->assertSame(['Sales needs Crm'], $registry->unmetRequirements());
    }
}
