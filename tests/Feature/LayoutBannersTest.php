<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Feature;

use Config\Services;
use Ephraitech\Platform\Tests\Support\PlatformTestCase;

/**
 * Modules put notices on every page through 'layout.banners' (signed-in
 * pages) and 'layout.auth.banners' (sign-in pages), above the content.
 */
final class LayoutBannersTest extends PlatformTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The page layout links to the core and account routes. A fresh
        // collection: the test app's own "/" would keep core.dashboard out.
        Services::resetSingle('routes');
        $routes = service('routes');
        require __DIR__ . '/../../src/Config/Routes.php';
        service('auth')->routes($routes, except: ['register']);
    }

    public function testSignedInPagesShowBannersAboveTheContent(): void
    {
        service('auth')->session()->login($this->createUser('jane@example.com', ['admin']));

        $html = view('Modules\Crm\Views\app_page');

        $this->assertStringContainsString('<div class="crm-banner">Clients banner</div>', $html);
        $this->assertLessThan(strpos($html, 'page body'), strpos($html, 'crm-banner'));
        $this->assertStringNotContainsString('crm-auth-banner', $html);
    }

    public function testSignInPagesShowTheirOwnBanners(): void
    {
        $html = view('Modules\Crm\Views\auth_page');

        $this->assertStringContainsString('<div class="crm-auth-banner">Sign-in banner</div>', $html);
        $this->assertLessThan(strpos($html, 'sign-in form'), strpos($html, 'crm-auth-banner'));
        $this->assertStringNotContainsString('crm-banner"', $html);
    }

    public function testNoBannersWhenTheModuleIsOff(): void
    {
        service('auth')->session()->login($this->createUser('jane@example.com', ['admin']));
        service('moduleGate')->setState('Crm', false);

        $this->assertStringNotContainsString('crm-banner', view('Modules\Crm\Views\app_page'));
    }
}
