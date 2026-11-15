<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Database;

use Ephraitech\Platform\Config\AuthPermissions;
use Ephraitech\Platform\Libraries\Navigation;
use Ephraitech\Platform\Tests\Support\PlatformTestCase;
use RuntimeException;

/**
 * One install per client: modules are on by default (or off with
 * 'default' => false) and switched by an admin.
 */
final class SingleInstallGateTest extends PlatformTestCase
{
    public function testDefaultsAndAlwaysOn(): void
    {
        $gate = service('moduleGate');

        $this->assertSame(['Core', 'Users', 'Crm', 'Sales'], $gate->enabledModules());
        $this->assertFalse($gate->enabled('Payroll'), "'default' => false keeps it off until switched on");
        $this->assertFalse($gate->enabled('Ghost'), 'not installed');
        $this->assertSame('default', $gate->decisions()['Payroll']['source']);
    }

    public function testSwitchingOffAlsoSwitchesOffWhatNeedsIt(): void
    {
        $gate = service('moduleGate');
        $also = $gate->setState('Crm', false, null, 1);

        $this->assertSame(['Sales'], $also);
        $this->assertFalse($gate->enabled('Crm'));
        $this->assertFalse($gate->enabled('Sales'));
        $this->assertSame('needs Crm', $gate->decisions()['Sales']['source']);

        $gate->setState('Crm', null);
        $this->assertTrue($gate->enabled('Sales'), 'clearing the switch returns to the default');

        $gate->setState('Payroll', true);
        $this->assertTrue($gate->enabled('Payroll'));
    }

    public function testAlwaysOnModulesCannotBeSwitched(): void
    {
        $this->expectException(RuntimeException::class);

        service('moduleGate')->setState('Users', false);
    }

    public function testPermissionsOfSwitchedOffModulesFailEvenForTheSuperRole(): void
    {
        $root  = $this->createUser('root@example.com', ['superadmin']);
        $admin = $this->createUser('admin@example.com', ['admin']);

        $this->assertTrue(can_user($root, 'crm.view'));
        $this->assertTrue(can_user($admin, 'crm.manage'), 'module grants reach Config\Auth through the manifests');
        $this->assertFalse(can_user($admin, 'payroll.run'), 'Payroll is off by default');

        service('moduleGate')->setState('Crm', false);
        service('authAuthorizer')->flush();

        $this->assertFalse(can_user($root, 'crm.view'));
        $this->assertFalse(can_user($admin, 'sales.view'), 'Sales went off with Crm');
        $this->assertTrue(can_user($admin, 'users.view'), 'platform permissions are unaffected');
        $this->assertNotContains('crm.view', service('authAuthorizer')->permissionsFor($root));
    }

    public function testGrantsToUndefinedRolesAreDroppedNotFatal(): void
    {
        $this->assertArrayHasKey('nobody', AuthPermissions::$droppedGrants);
        $this->assertArrayNotHasKey('nobody', $this->authConfig->matrix);
    }

    public function testMenuFollowsTheGateAndPermissions(): void
    {
        $admin = $this->createUser('admin@example.com', ['admin']);
        service('auth')->session()->login($admin);

        $labels = static fn (): array => array_merge(...array_map(static fn (array $items): array => array_column($items, 'label'), array_values(Navigation::menu())));

        $this->assertContains('Clients', $labels());

        service('moduleGate')->setState('Crm', false);
        service('authAuthorizer')->flush();

        $this->assertNotContains('Clients', $labels());
    }

    public function testSequencesAndSettingsInASingleInstall(): void
    {
        helper('platform');

        $this->assertSame('CL-001', next_number('client'));
        $this->assertSame('CL-002', next_number('client'));
        $this->assertSame('CL-003', service('sequences')->peek('client'));

        $this->assertSame('Hello', setting('crm.greeting'));
        service('platformSettings')->save('crm', ['greeting' => 'Karibu'], 1);
        $this->assertSame('Karibu', setting('crm.greeting'));
        $this->assertArrayHasKey('branding', service('platformSettings')->editableGroups(), 'global groups are plain groups in a single install');
    }

    public function testExtensionDataSkipsSwitchedOffModules(): void
    {
        $admin = $this->createUser('admin@example.com', ['admin']);
        service('auth')->session()->login($admin);
        model(\Modules\Crm\Models\ClientModel::class, false)->insert(['name' => 'Babu Couture']);

        $this->assertSame(['count' => 1], extension_data('crm.client.api'));

        service('moduleGate')->setState('Crm', false);
        $this->assertSame([], extension_data('crm.client.api'));
    }
}
