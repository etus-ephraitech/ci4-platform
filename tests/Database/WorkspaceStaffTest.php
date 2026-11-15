<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tests\Database;

use Ephraitech\Platform\Tests\Support\PlatformTestCase;
use Ephraitech\Platform\Users\Libraries\StaffService;
use RuntimeException;

/**
 * People inside a workspace: accounts are shared, roles are per workspace,
 * and a workspace admin cannot change an account that also belongs elsewhere.
 */
final class WorkspaceStaffTest extends PlatformTestCase
{
    protected string $tenancy = 'multi';

    protected function setUp(): void
    {
        parent::setUp();

        service('tenancy')->create('acme', 'Acme Ltd', 'pro');
        service('tenancy')->create('globex', 'Globex', 'pro');
    }

    private function inAcme(callable $callback): mixed
    {
        return service('tenancy')->runAs('acme', $callback);
    }

    public function testAddingAnExistingEmailAddsThePersonToTheWorkspace(): void
    {
        $owner = $this->createUser('owner@acme.test', ['owner'], 'acme');
        $mary  = $this->createUser('mary@globex.test', ['admin'], 'globex');

        $result = $this->inAcme(static fn () => (new StaffService())->create(['first_name' => 'Mary', 'last_name' => null, 'job_title' => null], 'mary@globex.test', null, ['user'], 'link', $owner, '127.0.0.1'));

        $this->assertTrue($result['existing']);
        $this->assertSame((int) $mary->id, (int) $result['user']->id, 'no second account');
        $this->assertSame(['user'], service('authAuthorizer')->rolesFor($mary, 'acme'));
        $this->assertSame(['admin'], service('authAuthorizer')->rolesFor($mary, 'globex'));
    }

    public function testNewPeopleCanBeInvitedWithoutAPassword(): void
    {
        $owner  = $this->createUser('owner@acme.test', ['owner'], 'acme');
        $result = $this->inAcme(static fn () => (new StaffService())->create(['first_name' => 'Peter', 'last_name' => 'Okello', 'job_title' => 'Tailor'], 'peter@acme.test', null, ['user'], 'link', $owner, '127.0.0.1'));

        $this->assertFalse($result['existing']);
        $this->assertTrue($result['link_requested']);
        $this->assertSame(['user'], service('authAuthorizer')->rolesFor($result['user'], 'acme'));
        $this->assertSame([], service('authAuthorizer')->rolesFor($result['user'], ''), 'no platform-wide role');
    }

    public function testAccountsSharedWithOtherWorkspacesAreOnlyRemoved(): void
    {
        $owner = $this->createUser('owner@acme.test', ['owner'], 'acme');
        $mary  = $this->createUser('mary@globex.test', ['admin'], 'globex');
        service('authAuthorizer')->assignRole($mary, 'user', 'acme');

        $this->inAcme(function () use ($owner, $mary): void {
            $staff = new StaffService();

            $this->assertFalse($staff->canManageAccount($mary));

            try {
                $staff->setStatus($mary, 'suspended', 'test', $owner);
                $this->fail('a workspace admin suspended an account shared with another workspace');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame(['user'], $staff->removeFromWorkspace($mary, $owner));
            $this->assertFalse($staff->isVisible($mary));
        });

        $this->assertSame(['admin'], service('authAuthorizer')->rolesFor($mary, 'globex'), 'their other workspace is untouched');
        $this->assertTrue($mary->isActive());
    }

    public function testTheSuperRoleIsNotGivenInsideAWorkspace(): void
    {
        $root = $this->createUser('root@platform.test', ['superadmin']);
        $jane = $this->createUser('jane@acme.test', ['user'], 'acme');

        $this->expectException(RuntimeException::class);
        $this->inAcme(static fn () => (new StaffService())->setRoles($jane, ['superadmin'], $root));
    }

    public function testAuditIsKeptPerWorkspace(): void
    {
        helper('platform');

        $this->inAcme(static fn () => audit('Crm', 'created', 'Created a client in Acme', 'client', 1));
        service('tenancy')->runAs('globex', static fn () => audit('Crm', 'created', 'Created a client in Globex', 'client', 1));
        audit('Core', 'tenant.created', 'Central action');

        $model = model(\Ephraitech\Platform\Models\AuditLogModel::class, false);

        $this->assertSame(['Created a client in Acme'], $this->inAcme(static fn () => array_column($model->filtered([])->findAll(), 'description')));
        $this->assertSame(['Central action'], array_column($model->filtered([])->findAll(), 'description'), 'central rows only, with no tenant');
    }
}
