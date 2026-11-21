<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Platform\Config\Platform;

/**
 * Where a workspace lives: its own domain, its subdomain (keeping the
 * port of app.baseURL, as in development), or the workspace picker.
 */
final class TenantUrlTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(Auth::class)->tenancy = 'multi';
        $platform                    = config(Platform::class);
        $platform->baseDomain        = 'app.test';
        $platform->customDomains     = true;
        $platform->tenantSources     = ['domain', 'header', 'session'];
    }

    public function testSubdomainKeepsTheScheme(): void
    {
        config('App')->baseURL = 'https://app.test/';

        $this->assertSame('https://acme.app.test/patients', service('tenancy', false)->urlFor(['slug' => 'acme', 'domain' => null], '/patients'));
    }

    public function testSubdomainKeepsADevelopmentPort(): void
    {
        config('App')->baseURL = 'http://app.test:8080/';

        $this->assertSame('http://acme.app.test:8080/', service('tenancy', false)->urlFor(['slug' => 'acme', 'domain' => null]));
    }

    public function testCentralUrlIsOnThePlatformsHost(): void
    {
        config('App')->baseURL   = 'http://app.test:8080/';
        config('App')->indexPage = '';
        service('routes')->add('workspaces', '\\Ephraitech\\Platform\\Controllers\\WorkspacesController::index', ['as' => 'test.workspaces']);

        $this->assertSame('http://app.test:8080/workspaces', service('tenancy', false)->centralUrl('test.workspaces'));
    }

    public function testOwnDomainWins(): void
    {
        config('App')->baseURL = 'https://app.test/';

        $this->assertSame('https://erp.acme.co.ug/', service('tenancy', false)->urlFor(['slug' => 'acme', 'domain' => 'erp.acme.co.ug']));
    }
}
