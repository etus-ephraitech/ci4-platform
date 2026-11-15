<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\FeatureTestTrait;
use Ephraitech\Platform\Tests\Support\PlatformTestCase;

/**
 * Single install: the platform filter answers 404 for routes of
 * switched-off modules, before any auth filter runs.
 */
final class PlatformFilterTest extends PlatformTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        config('Filters')->globals['before'] = ['platform'];
    }

    public function testModuleRoutesReachTheirAuthFilterWhileOn(): void
    {
        $result = $this->get('crm');

        $result->assertRedirect();
        $this->assertStringContainsString('login', (string) $result->getRedirectUrl());
    }

    public function testModuleRoutesAre404WhileOff(): void
    {
        service('moduleGate')->setState('Crm', false);

        $this->expectException(PageNotFoundException::class);
        $this->get('crm');
    }

    public function testApiRoutesOfSwitchedOffModulesAnswerJson404(): void
    {
        service('moduleGate')->setState('Crm', false);

        $result = $this->get('api/v1/crm/ping');

        $result->assertStatus(404);
        $result->assertJSONFragment(['error' => 'not_found']);
    }

    public function testApiWithATokenWhileOn(): void
    {
        $user  = $this->createUser('jane@example.com', ['user']);
        $token = service('authTokens')->issueApiKey((int) $user->id, 'Test');

        $r = $this->withHeaders(['Authorization' => 'Bearer ' . $token->plaintext])
            ->get('api/v1/crm/ping');
        $r->assertStatus(200);
        $r->assertJSONExact(['pong' => '']);
    }
}
