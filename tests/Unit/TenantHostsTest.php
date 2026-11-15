<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Platform\Config\Platform;
use Ephraitech\Platform\Tenancy\TenantHosts;

final class TenantHostsTest extends CIUnitTestCase
{
    public function testNothingInASingleInstall(): void
    {
        config(Auth::class)->tenancy = 'single';

        $this->assertSame([], TenantHosts::forRequest('acme.app.test'));
    }

    public function testWorkspaceSubdomainsAndOwnDomains(): void
    {
        config(Auth::class)->tenancy = 'multi';
        $platform                    = config(Platform::class);
        $platform->baseDomain        = 'app.test';
        $platform->customDomains     = false;

        $this->assertSame(['acme.app.test'], TenantHosts::forRequest('Acme.App.Test:443'));
        $this->assertSame([], TenantHosts::forRequest('a.b.app.test'), 'one label only');
        $this->assertSame([], TenantHosts::forRequest('app.test'), 'the central host is already allowed');
        $this->assertSame([], TenantHosts::forRequest('erp.acme.co.ug'), 'own domains are off');
        $this->assertSame([], TenantHosts::forRequest('bad host<>'));

        $platform->customDomains = true;
        $this->assertSame(['erp.acme.co.ug'], TenantHosts::forRequest('erp.acme.co.ug'), 'checked against workspaces later by the platform filter');

        $platform->tenantSources = ['header', 'session'];
        $this->assertSame([], TenantHosts::forRequest('acme.app.test'), 'not when the domain source is off');
    }
}
