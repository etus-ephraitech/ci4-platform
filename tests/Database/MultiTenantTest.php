<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Database;

use CodeIgniter\I18n\Time;
use Ephraitech\Platform\Tenancy\TenantRequiredException;
use Ephraitech\Platform\Tests\Support\PlatformTestCase;
use Modules\Crm\Models\ClientModel;
use RuntimeException;

/**
 * SaaS: each tenant gets modules from its plan, with per-tenant add-ons
 * and exclusions; data, numbers and settings are kept per tenant.
 *
 * Plans (PlatformTestCase): basic = Crm, pro = everything.
 */
final class MultiTenantTest extends PlatformTestCase
{
    protected string $tenancy = 'multi';

    private function tenants(): void
    {
        $tenancy = service('tenancy');
        $tenancy->create('acme', 'Acme Ltd', 'basic');
        $tenancy->create('globex', 'Globex', 'pro');
    }

    public function testPlansDecideModulesPerTenant(): void
    {
        $this->tenants();
        $gate = service('moduleGate');

        $this->assertSame(['Core', 'Users', 'Crm'], $gate->enabledModules('acme'));
        $this->assertSame(['Core', 'Users', 'Crm', 'Payroll', 'Sales'], $gate->enabledModules('globex'), "'*' is every installed module, 'default' => false or not");
        $this->assertSame('plan', $gate->decisions('acme')['Sales']['source']);
    }

    public function testCentralHasOnlyCentralModules(): void
    {
        $this->tenants();

        $this->assertSame(['Core', 'Users'], service('moduleGate')->enabledModules(), 'no tenant selected');
        $this->assertSame('no tenant selected', service('moduleGate')->decisions()['Crm']['source']);
    }

    public function testAddOnsAndTrialsOnTopOfThePlan(): void
    {
        $this->tenants();
        $gate = service('moduleGate');

        $also = $gate->setState('Sales', true, 'acme', 1, Time::now()->addDays(14)->toDateTimeString(), '14-day trial');
        $this->assertSame([], $also);
        $this->assertTrue($gate->enabled('Sales', 'acme'));
        $this->assertSame('switched by an admin', $gate->decisions('acme')['Sales']['source']);
        $this->assertFalse($gate->enabled('Sales', 'globex') === false, 'other tenants are unaffected');

        Time::setTestNow(Time::now()->addDays(15));
        $gate->flush();
        $this->assertFalse($gate->enabled('Sales', 'acme'), 'the add-on ended');

        Time::setTestNow();
        $gate->setState('Crm', false, 'globex');
        $this->assertFalse($gate->enabled('Sales', 'globex'), 'excluding Crm takes Sales with it');
    }

    public function testSwitchingNeedsATenant(): void
    {
        $this->expectException(RuntimeException::class);

        service('moduleGate')->setState('Crm', false);
    }

    public function testPermissionGateFollowsTheTenant(): void
    {
        $this->tenants();
        $jane = $this->createUser('jane@example.com', ['admin'], 'acme');
        service('authAuthorizer')->assignRole($jane, 'admin', 'globex');

        $this->assertTrue(service('authAuthorizer')->can($jane, 'crm.view', 'acme'));
        $this->assertFalse(service('authAuthorizer')->can($jane, 'sales.view', 'acme'), 'not on the basic plan');
        $this->assertTrue(service('authAuthorizer')->can($jane, 'sales.view', 'globex'));

        service('tenancy')->enter(service('tenancy')->find('acme'));
        $this->assertFalse(can_user($jane, 'sales.view'), 'the current tenant is used when none is given');
    }

    public function testTenantScopedModelsKeepRowsApart(): void
    {
        $this->tenants();
        $tenancy = service('tenancy');
        $clients = model(ClientModel::class, false);

        $tenancy->runAs('acme', static function () use ($clients): void {
            $clients->insert(['name' => 'Acme client 1']);
            $clients->insert(['name' => 'Acme client 2']);
        });

        $globexId = $tenancy->runAs('globex', static fn (): int => (int) $clients->insert(['name' => 'Globex client']));

        $this->assertSame(2, $tenancy->runAs('acme', static fn (): int => $clients->countAllResults()));
        $this->assertSame(['Globex client'], $tenancy->runAs('globex', static fn (): array => array_column($clients->findAll(), 'name')));
        $this->assertNull($tenancy->runAs('acme', static fn () => $clients->find($globexId)), 'another tenant\'s row is not found by id');

        $tenancy->runAs('acme', static fn () => $clients->update($globexId, ['name' => 'Hijacked']));
        $tenancy->runAs('acme', static fn () => $clients->delete($globexId));
        $this->assertSame('Globex client', $tenancy->runAs('globex', static fn () => $clients->find($globexId)['name']), 'updates and deletes do not cross tenants');

        $this->assertSame(3, $clients->withoutTenantScope()->countAllResults(), 'across tenants on purpose');
    }

    public function testScopedModelsRefuseToRunWithNoTenant(): void
    {
        $this->expectException(TenantRequiredException::class);

        model(ClientModel::class, false)->findAll();
    }

    public function testSequencesAndSettingsPerTenant(): void
    {
        $this->tenants();
        $tenancy = service('tenancy');
        helper('platform');

        $this->assertSame('CL-001', $tenancy->runAs('acme', static fn (): string => next_number('client')));
        $this->assertSame('CL-002', $tenancy->runAs('acme', static fn (): string => next_number('client')));
        $this->assertSame('CL-001', $tenancy->runAs('globex', static fn (): string => next_number('client')), 'each tenant counts from 1');

        $settings = service('platformSettings');
        $tenancy->runAs('acme', static fn () => $settings->save('crm', ['greeting' => 'Oli otya'], 1));
        $this->assertSame('Oli otya', $tenancy->runAs('acme', static fn () => setting('crm.greeting')));
        $this->assertSame('Hello', $tenancy->runAs('globex', static fn () => setting('crm.greeting')));

        $settings->save('branding', ['footer' => 'Platform-wide'], 1);
        $this->assertSame('Platform-wide', $tenancy->runAs('globex', static fn () => setting('branding.footer')), 'global groups are shared');

        $this->assertArrayNotHasKey('crm', $settings->editableGroups(), 'tenant groups are not edited centrally');
        $this->assertArrayHasKey('branding', $settings->editableGroups());
        $this->assertArrayNotHasKey('branding', $tenancy->runAs('globex', static fn () => $settings->editableGroups()), 'global groups are not edited inside a tenant');

        $this->expectException(\InvalidArgumentException::class);
        $settings->save('crm', ['greeting' => 'x'], 1);
    }

    public function testCreatingATenantWithAnOwner(): void
    {
        $owner  = $this->createUser('owner@acme.co.ug', []);
        $tenant = service('tenancy')->create('acme', 'Acme Ltd', null, (int) $owner->id);

        $this->assertSame('basic', $tenant['plan'], 'the default plan');
        $this->assertSame(['owner'], service('authAuthorizer')->rolesFor($owner, 'acme'));
        $this->assertTrue(service('tenancy')->belongs((int) $owner->id, 'acme'));
        $this->assertSame(['acme'], array_column(service('tenancy')->tenantsFor((int) $owner->id), 'slug'));

        $this->expectException(RuntimeException::class);
        service('tenancy')->create('acme', 'Again');
    }

    public function testReservedAndInvalidAddressesAreRefused(): void
    {
        foreach (['api', 'www', '-acme', 'Acme', 'a_b'] as $slug) {
            try {
                service('tenancy')->create($slug, 'X');
                $this->fail("'{$slug}' was accepted");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSuspendedAndExpiredTenants(): void
    {
        $this->tenants();
        $tenancy = service('tenancy');

        $tenancy->update('acme', ['status' => 'suspended']);
        $this->assertFalse(\Ephraitech\Platform\Models\TenantModel::isUsable($tenancy->find('acme')));

        $tenancy->update('globex', ['status' => 'trial', 'trial_ends_at' => Time::yesterday()->toDateTimeString()]);
        $this->assertFalse(\Ephraitech\Platform\Models\TenantModel::isUsable($tenancy->find('globex')));
        $this->assertSame('The trial of this workspace has ended.', \Ephraitech\Platform\Models\TenantModel::blockedReason($tenancy->find('globex')));
    }
}
