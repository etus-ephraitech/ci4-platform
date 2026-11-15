<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Ephraitech\Platform\Tests\Support\PlatformTestCase;

/**
 * Multi-tenant API: the tenant comes from X-Tenant-ID or the token, the
 * module gate and membership are enforced, and tokens cannot cross tenants.
 */
final class MultiTenantApiTest extends PlatformTestCase
{
    use FeatureTestTrait;

    protected string $tenancy = 'multi';

    private string $janeKey;

    private string $janeAcmeKey;

    protected function setUp(): void
    {
        parent::setUp();

        config('Filters')->globals['before'] = ['platform'];
        $this->platform->tenantSources       = ['domain', 'header', 'session'];

        $tenancy = service('tenancy');
        $tenancy->create('acme', 'Acme Ltd', 'basic');
        $tenancy->create('globex', 'Globex', 'pro');
        $tenancy->create('initech', 'Initech', 'pro');

        $jane = $this->createUser('jane@example.com', ['admin'], 'acme');
        service('authAuthorizer')->assignRole($jane, 'admin', 'globex');

        $this->janeKey     = service('authTokens')->issueApiKey((int) $jane->id, 'Any tenant')->plaintext;
        $this->janeAcmeKey = service('authTokens')->issueApiKey((int) $jane->id, 'Acme only', null, 'acme')->plaintext;
    }

    private function api(string $key, ?string $tenant = null): static
    {
        $headers = ['Authorization' => 'Bearer ' . $key];

        if ($tenant !== null) {
            $headers['X-Tenant-ID'] = $tenant;
        }

        return $this->withHeaders($headers);
    }

    public function testTheHeaderPicksTheTenant(): void
    {
        $r = $this->api($this->janeKey, 'acme')->get('api/v1/crm/ping');
        $r->assertStatus(200);
        $r->assertJSONExact(['pong' => 'acme']);
        $r = $this->api($this->janeKey, 'globex')->get('api/v1/crm/ping');
        $r->assertStatus(200);
        $r->assertJSONExact(['pong' => 'globex']);
    }

    public function testATokenBoundToATenantImpliesItAndCannotCross(): void
    {
        $r = $this->api($this->janeAcmeKey)->get('api/v1/crm/ping');
        $r->assertStatus(200);
        $r->assertJSONExact(['pong' => 'acme']);
        $r = $this->api($this->janeAcmeKey, 'globex')->get('api/v1/crm/ping');
        $r->assertStatus(403);
        $r->assertJSONFragment(['error' => 'tenant_mismatch']);
    }

    public function testNonMembersAreRefused(): void
    {
        $r = $this->api($this->janeKey, 'initech')->get('api/v1/crm/ping');
        $r->assertStatus(403);
        $r->assertJSONFragment(['error' => 'no_tenant_access']);
    }

    public function testUnknownAndSuspendedTenants(): void
    {
        $r = $this->api($this->janeKey, 'nope')->get('api/v1/crm/ping');
        $r->assertStatus(404);
        $r->assertJSONFragment(['error' => 'not_found']);

        service('tenancy')->update('globex', ['status' => 'suspended']);
        $r = $this->api($this->janeKey, 'globex')->get('api/v1/crm/ping');
        $r->assertStatus(403);
        $r->assertJSONFragment(['error' => 'tenant_unavailable']);
    }

    public function testModulesOffForTheTenantAre404(): void
    {
        service('moduleGate')->setState('Crm', false, 'acme');

        $r = $this->api($this->janeKey, 'acme')->get('api/v1/crm/ping');
        $r->assertStatus(404);
        $r = $this->api($this->janeKey, 'globex')->get('api/v1/crm/ping');
        $r->assertStatus(200);
    }

    public function testContextTellsTheClientWhatIsOn(): void
    {
        $result = $this->api($this->janeKey, 'acme')->get('api/v1/platform/context');
        $result->assertStatus(200);

        $data    = json_decode((string) $result->getJSON(), true)['data'];
        $modules = array_column($data['modules'], 'name');

        $this->assertSame('multi', $data['tenancy']);
        $this->assertSame('acme', $data['tenant']['slug']);
        $this->assertContains('Crm', $modules);
        $this->assertNotContains('Sales', $modules, 'not on the basic plan');
        $this->assertContains('crm.manage', $data['permissions']);
        $this->assertNotContains('sales.view', $data['permissions']);
        $this->assertContains('Clients', array_column($data['menu'], 'label'));
    }
}
