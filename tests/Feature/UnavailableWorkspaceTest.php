<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Ephraitech\Platform\Tests\Support\PlatformTestCase;

/**
 * A suspended workspace, or one whose trial has ended, shows the
 * "workspace unavailable" page (Views/errors/workspace.php) with a way
 * out, never an error, whoever asks.
 */
final class UnavailableWorkspaceTest extends PlatformTestCase
{
    use FeatureTestTrait;

    protected string $tenancy = 'multi';

    protected function setUp(): void
    {
        parent::setUp();

        config('Filters')->globals['before'] = ['platform'];
        $this->platform->tenantSources       = ['domain', 'header', 'session'];

        $tenancy = service('tenancy');
        $tenancy->create('acme', 'Acme Ltd', 'basic');
        $tenancy->update('acme', ['status' => 'suspended']);
    }

    public function testSomeoneNotSignedInIsOfferedToSignIn(): void
    {
        $r = $this->withHeaders(['X-Tenant-ID' => 'acme'])->get('crm');

        $r->assertStatus(403);
        $r->assertSee('Acme Ltd');
        $r->assertSee('Sign in');
        $r->assertDontSee('Sign out');
    }

    public function testASignedInMemberIsOfferedToSignOutOrSwitch(): void
    {
        $jane = $this->createUser('jane@example.com', ['admin'], 'acme');
        service('auth')->session()->login($jane);

        $r = $this->withHeaders(['X-Tenant-ID' => 'acme'])->get('crm');

        $r->assertStatus(403);
        $r->assertSee('Sign out');
        $r->assertSee('Your workspaces');
    }

    public function testAnEndedTrialSaysSo(): void
    {
        service('tenancy')->create('globex', 'Globex', 'pro');
        service('tenancy')->update('globex', ['status' => 'trial', 'trial_ends_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);

        $r = $this->withHeaders(['X-Tenant-ID' => 'globex'])->get('crm');

        $r->assertStatus(403);
        $r->assertSee('The trial of this workspace has ended.');
    }
}
