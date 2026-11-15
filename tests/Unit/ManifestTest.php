<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Ephraitech\Platform\Module\Manifest;
use Ephraitech\Platform\Module\ModuleException;

final class ManifestTest extends CIUnitTestCase
{
    private const MODULE = ['name' => 'Crm', 'path' => '/x/', 'namespace' => 'Modules\Crm', 'source' => 'app', 'manifest' => '/x/module.php'];

    public function testDefaultsAreFilledIn(): void
    {
        $m = Manifest::normalise([], self::MODULE);

        $this->assertSame('Crm', $m['title']);
        $this->assertTrue($m['default']);
        $this->assertFalse($m['always_on']);
        $this->assertFalse($m['central']);
        $this->assertSame([], $m['requires']);
        $this->assertSame('Modules\Crm', $m['namespace']);
    }

    public function testBuiltInsAreAlwaysOn(): void
    {
        $m = Manifest::normalise([], ['name' => 'Users'] + self::MODULE);

        $this->assertTrue($m['always_on']);
    }

    public function testUnknownKeysAreRejected(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageMatches('/unknown manifest key\(s\) premissions/');

        Manifest::normalise(['premissions' => []], self::MODULE);
    }

    public function testNameMustMatchFolder(): void
    {
        $this->expectException(ModuleException::class);

        Manifest::normalise(['name' => 'Sales'], self::MODULE);
    }

    public function testPermissionNamesAreChecked(): void
    {
        $this->expectException(ModuleException::class);

        Manifest::normalise(['permissions' => ['CRM View' => 'x']], self::MODULE);
    }

    public function testEntriesGetTheirModuleAndDefaults(): void
    {
        $m = Manifest::normalise([
            'menu'    => [['label' => 'Clients', 'route' => 'crm.index']],
            'widgets' => [['cell' => 'X::y']],
            'boards'  => ['sales' => ['title' => 'Sales', 'roles' => ['sales']]],
        ], self::MODULE);

        $this->assertSame('Crm', $m['menu'][0]['module']);
        $this->assertSame('General', $m['menu'][0]['group']);
        $this->assertSame(['home'], $m['widgets'][0]['dashboards']);
        $this->assertSame(['sales'], $m['boards']['sales']['roles']);
    }

    public function testExtensionsNeedACellOrACallButNotBoth(): void
    {
        $m = Manifest::normalise(['extends' => ['crm.client.panels' => [['cell' => 'A::b', 'title' => 'Invoices']]]], self::MODULE);
        $this->assertSame('A::b', $m['extends']['crm.client.panels'][0]['cell']);
        $this->assertNull($m['extends']['crm.client.panels'][0]['call']);

        $this->expectException(ModuleException::class);
        Manifest::normalise(['extends' => ['crm.client.panels' => [['cell' => 'A::b', 'call' => 'C::d']]]], self::MODULE);
    }

    public function testSettingsScopeIsChecked(): void
    {
        $m = Manifest::normalise(['settings' => ['crm' => ['fields' => ['greeting' => []]]]], self::MODULE);
        $this->assertSame('tenant', $m['settings']['crm']['scope']);
        $this->assertSame('settings.manage', $m['settings']['crm']['permission']);

        $this->expectException(ModuleException::class);
        Manifest::normalise(['settings' => ['crm' => ['scope' => 'everyone']]], self::MODULE);
    }
}
