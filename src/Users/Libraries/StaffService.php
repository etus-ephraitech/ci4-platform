<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Libraries;

use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Entities\User;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Auth\Exceptions\IdentifierTakenException;
use Ephraitech\Auth\Models\IdentityModel;
use Ephraitech\Auth\Models\UserModel;
use Ephraitech\Platform\Config\PlatformConfig;
use RuntimeException;
use Throwable;

/**
 * People and their access, on top of ephraitech/ci4-auth.
 *
 * Every change goes through the package's own services (registrar,
 * authorizer, password manager, token manager), so behaviour matches the
 * CLI. On top of that it enforces:
 *   - nobody can suspend, deactivate or delete their own account;
 *   - the last active holder of the super role cannot lose it or be switched off;
 *   - only a holder of the super role can give or take it away;
 *   - role changes, suspensions and password resets end the person's API
 *     sessions (token abilities are fixed when a token is issued).
 *
 * Single install: one team; this is everyone.
 *
 * Multi-tenant, inside a workspace: roles and extra permissions are given
 * in that workspace only. Accounts are shared across workspaces (one
 * email, one person), so:
 *   - adding an email that already has an account adds that person to the
 *     workspace instead of creating a second account;
 *   - suspending, deleting, resetting a password or signing someone out
 *     everywhere is allowed only for people who belong to this workspace
 *     alone and hold no platform-wide role; everyone else can be removed
 *     from the workspace.
 *
 * Multi-tenant, central: the platform owner manages every account and the
 * platform-wide roles.
 */
class StaffService
{
    private Auth $config;

    public function __construct()
    {
        $this->config = PlatformConfig::auth();
    }

    /**
     * The workspace roles are given in: the current tenant, or '' (single
     * install, or central in a multi-tenant one).
     */
    public function tenant(): string
    {
        return service('tenancy')->key();
    }

    /**
     * Inside a workspace of a multi-tenant install.
     */
    public function inWorkspace(): bool
    {
        return service('tenancy')->isMulti() && service('tenancy')->has();
    }

    public function find(int $id): ?User
    {
        $user = model(UserModel::class, false)->find($id);

        return $user instanceof User ? $user : null;
    }

    /**
     * Whether a person shows up here: everyone (single, central), or
     * members of the workspace.
     */
    public function isVisible(User $user): bool
    {
        if (! $this->inWorkspace()) {
            return true;
        }

        return $this->assignedRoles((int) $user->id) !== [] || $this->directPermissions((int) $user->id) !== [];
    }

    /**
     * Roles in effect here (workspace roles plus platform-wide ones).
     *
     * @return list<string>
     */
    public function rolesOf(User $user): array
    {
        return service('authAuthorizer')->rolesFor($user, $this->tenant());
    }

    /**
     * Roles given in this workspace only ('' rows in a single install or centrally).
     *
     * @return list<string>
     */
    public function assignedRoles(int $userId): array
    {
        $db    = db_connect($this->config->DBGroup);
        $rows  = $db->table($this->config->table('user_roles') . ' ur')
            ->select('r.name')
            ->join($this->config->table('roles') . ' r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.tenant_id', $this->tenant())
            ->orderBy('r.name', 'asc')
            ->get()
            ->getResultArray();

        return array_column($rows, 'name');
    }

    /**
     * Roles given in this workspace, for many people at once: user id => names.
     *
     * @param list<int> $userIds
     *
     * @return array<int, list<string>>
     */
    public function assignedRolesFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = db_connect($this->config->DBGroup)->table($this->config->table('user_roles') . ' ur')
            ->select('ur.user_id, r.name')
            ->join($this->config->table('roles') . ' r', 'r.id = ur.role_id')
            ->whereIn('ur.user_id', $userIds)
            ->where('ur.tenant_id', $this->tenant())
            ->orderBy('r.name', 'asc')
            ->get()
            ->getResultArray();

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['user_id']][] = (string) $row['name'];
        }

        return $out;
    }

    public function isSuper(User $user): bool
    {
        return $this->config->superRole !== null
            && in_array($this->config->superRole, service('authAuthorizer')->rolesFor($user, ''), true);
    }

    /**
     * Whether the account itself (status, password, deletion, every
     * session) may be changed from here. Always in a single install and
     * centrally; in a workspace only for people who belong to it alone.
     */
    public function canManageAccount(User $user): bool
    {
        if (! $this->inWorkspace()) {
            return true;
        }

        $db     = db_connect($this->config->DBGroup);
        $here   = $this->tenant();
        $others = $db->table($this->config->table('user_roles'))
            ->where('user_id', (int) $user->id)
            ->where('tenant_id !=', $here)
            ->countAllResults()
            + $db->table($this->config->table('user_permissions'))
                ->where('user_id', (int) $user->id)
                ->where('tenant_id !=', $here)
                ->countAllResults();

        return $others === 0;
    }

    /**
     * Permissions granted to this person directly here, on top of their roles.
     *
     * @return list<string>
     */
    public function directPermissions(int $userId): array
    {
        $rows = db_connect($this->config->DBGroup)->table($this->config->table('user_permissions') . ' up')
            ->select('p.name')
            ->join($this->config->table('permissions') . ' p', 'p.id = up.permission_id')
            ->where('up.user_id', $userId)
            ->where('up.tenant_id', $this->tenant())
            ->orderBy('p.name', 'asc')
            ->get()
            ->getResultArray();

        return array_column($rows, 'name');
    }

    /**
     * Create an account, or (multi-tenant, in a workspace) add an existing
     * account with this email to the workspace.
     *
     * @param array{first_name: string, last_name: ?string, job_title: ?string} $profile
     * @param list<string>                                                      $roles
     * @param string                                                            $passwordMode 'link' (email a set-password link) or 'generate'
     *
     * @return array{user: User, password: ?string, link_requested: bool, existing: bool}
     */
    public function create(array $profile, string $email, ?string $phone, array $roles, string $passwordMode, User $actor, ?string $ip): array
    {
        $roles = $this->checkRoleChange([], $roles, $actor);

        if ($this->inWorkspace()) {
            $existing = model(UserModel::class, false)->findByIdentifier(Auth::IDENTIFIER_EMAIL, $email);

            if ($existing instanceof User) {
                if ($this->isVisible($existing)) {
                    throw new RuntimeException('That person is already a member of this workspace.');
                }

                service('authAuthorizer')->syncRoles($existing, $roles, $this->tenant());

                return ['user' => $existing, 'password' => null, 'link_requested' => false, 'existing' => true];
            }
        }

        $identifiers = [Auth::IDENTIFIER_EMAIL => $email];

        if ($phone !== null && $phone !== '' && $this->config->isIdentifierEnabled(Auth::IDENTIFIER_PHONE)) {
            $identifiers[Auth::IDENTIFIER_PHONE] = $phone;
        }

        $password = $passwordMode === 'generate' ? $this->generatePassword($identifiers) : null;

        // Accounts made by an administrator are trusted: identifiers count as
        // verified so the person is not blocked at sign-in by a verification step.
        $user = service('authRegistrar')->register($identifiers, $password, $profile, [
            'roles'            => [],
            'verified'         => array_keys($identifiers),
            'status'           => 'active',
            'enforcePolicy'    => true,
            // No password yet: they choose one from the emailed link.
            'passwordOptional' => $password === null,
        ]);

        service('authAuthorizer')->syncRoles($user, $roles, $this->tenant());

        $linkRequested = false;

        if ($password === null) {
            $this->requestResetLink($email, $ip);
            $linkRequested = true;
        }

        return ['user' => $user, 'password' => $password, 'link_requested' => $linkRequested, 'existing' => false];
    }

    /**
     * Change name, job title, email and phone.
     *
     * @param array{first_name: string, last_name: ?string, job_title: ?string} $profile
     *
     * @return array<string, array{from: mixed, to: mixed}> changes for the audit log
     */
    public function updateDetails(User $user, array $profile, string $email, ?string $phone): array
    {
        $identities = model(IdentityModel::class, false);
        $changes    = [];
        $ownAccount = $this->canManageAccount($user);

        foreach ($profile as $field => $value) {
            if ((string) ($user->{$field} ?? '') !== (string) $value) {
                $changes[$field] = ['from' => $user->{$field} ?? null, 'to' => $value];
            }
        }

        $emailChanged = mb_strtolower((string) $user->getEmail()) !== mb_strtolower($email);
        $phoneChanged = $this->config->isIdentifierEnabled(Auth::IDENTIFIER_PHONE) && (string) $user->getPhone() !== (string) ($phone ?? '');

        if (! $ownAccount && ($emailChanged || $phoneChanged)) {
            throw new RuntimeException('This person also belongs to other workspaces, so only they (or the platform owner) can change how they sign in.');
        }

        $db = db_connect($this->config->DBGroup);
        $db->transBegin();

        try {
            if ($changes !== []) {
                model(UserModel::class, false)->update($user->id, $profile);
            }

            if ($emailChanged) {
                $identities->setIdentifier((int) $user->id, Auth::IDENTIFIER_EMAIL, $email, true);
                $changes['email'] = ['from' => $user->getEmail(), 'to' => $email];
            }

            if ($this->config->isIdentifierEnabled(Auth::IDENTIFIER_PHONE)) {
                $current = $user->getPhone();

                if ($phone !== null && $phone !== '') {
                    $identity = $identities->setIdentifier((int) $user->id, Auth::IDENTIFIER_PHONE, $phone, true);

                    if ((string) $current !== (string) $identity->identifier) {
                        $changes['phone'] = ['from' => $current, 'to' => $identity->identifier];
                    }
                } elseif ($current !== null && ! $this->config->isIdentifierRequired(Auth::IDENTIFIER_PHONE)) {
                    $identities->removeIdentifier((int) $user->id, Auth::IDENTIFIER_PHONE);
                    $changes['phone'] = ['from' => $current, 'to' => null];
                }
            }

            $db->transCommit();
        } catch (IdentifierTakenException $e) {
            $db->transRollback();

            throw new RuntimeException('That ' . (str_contains($e->getMessage(), 'phone') ? 'phone number' : 'email address') . ' already belongs to another account.');
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        return $changes;
    }

    /**
     * Replace a person's roles here (in this workspace, or install-wide).
     *
     * @param list<string> $roles
     *
     * @return array{from: list<string>, to: list<string>}
     */
    public function setRoles(User $user, array $roles, User $actor): array
    {
        $current = $this->assignedRoles((int) $user->id);
        $roles   = $this->checkRoleChange($current, $roles, $actor);

        $super = $this->config->superRole;

        if ($super !== null && in_array($super, $current, true) && ! in_array($super, $roles, true) && $this->activeSuperCount() <= 1) {
            throw new RuntimeException('This is the only active holder of the super role. Give it to someone else first.');
        }

        sort($current);
        sort($roles);

        if ($current !== $roles) {
            service('authAuthorizer')->syncRoles($user, $roles, $this->tenant());
            service('authTokens')->revokeAllForUser((int) $user->id);
        }

        return ['from' => $current, 'to' => $roles];
    }

    /**
     * Multi-tenant: take away every role and extra permission this person
     * has in the workspace. Their account and other workspaces are untouched.
     *
     * @return list<string> the roles they had here
     */
    public function removeFromWorkspace(User $user, User $actor): array
    {
        if (! $this->inWorkspace()) {
            throw new RuntimeException('Only members of a workspace can be removed from it.');
        }

        if ((int) $user->id === (int) $actor->id) {
            throw new RuntimeException('You cannot remove yourself from the workspace.');
        }

        $owner = service('tenancy')->current()['owner_user_id'] ?? null;

        if ($owner !== null && (int) $owner === (int) $user->id) {
            throw new RuntimeException('This person owns the workspace. Ask the platform owner to change the owner first.');
        }

        $roles = $this->assignedRoles((int) $user->id);
        $authz = service('authAuthorizer');

        $authz->syncRoles($user, [], $this->tenant());

        foreach ($this->directPermissions((int) $user->id) as $permission) {
            $authz->revokePermission($user, $permission, $this->tenant());
        }

        service('authTokens')->revokeAllForUser((int) $user->id);

        return $roles;
    }

    /**
     * Activate, suspend or deactivate an account.
     */
    public function setStatus(User $user, string $status, ?string $reason, User $actor): void
    {
        $this->assertAccountManageable($user);

        if (! in_array($status, Auth::USER_STATUSES, true)) {
            throw new RuntimeException('Unknown status.');
        }

        if ($status !== 'active') {
            if ((int) $user->id === (int) $actor->id) {
                throw new RuntimeException('You cannot switch off your own account.');
            }

            if ($this->isSuper($user) && $user->isActive() && $this->activeSuperCount() <= 1) {
                throw new RuntimeException('This is the only active holder of the super role and cannot be switched off.');
            }
        }

        model(UserModel::class, false)->setStatus((int) $user->id, $status, $reason);

        if ($status !== 'active') {
            service('authTokens')->revokeAllForUser((int) $user->id);
        }
    }

    /**
     * Email the person a link to choose a new password.
     */
    public function sendResetLink(User $user, ?string $ip): void
    {
        $this->assertAccountManageable($user);

        $email = $user->getEmail();

        if ($email === null) {
            throw new RuntimeException('This account has no email address. Set a temporary password instead.');
        }

        if (! $user->isActive()) {
            throw new RuntimeException('Activate the account before sending a reset link.');
        }

        $this->requestResetLink($email, $ip);
    }

    /**
     * Replace the password with a generated one, shown once to the administrator.
     */
    public function setTemporaryPassword(User $user): string
    {
        $this->assertAccountManageable($user);

        $identifiers = array_filter([
            Auth::IDENTIFIER_EMAIL => $user->getEmail(),
            Auth::IDENTIFIER_PHONE => $user->getPhone(),
        ]);

        $password = $this->generatePassword($identifiers);

        service('authPasswords')->setPassword((int) $user->id, $password);
        service('authTokens')->revokeAllForUser((int) $user->id);

        return $password;
    }

    /**
     * End all API sessions and keys for a person.
     *
     * @return int how many were revoked
     */
    public function signOutEverywhere(User $user): int
    {
        $this->assertAccountManageable($user);

        return count(service('authTokens')->revokeAllForUser((int) $user->id));
    }

    public function grantPermission(User $user, string $permission): void
    {
        $this->assertPermission($permission);
        service('authAuthorizer')->grantPermission($user, $permission, $this->tenant());
    }

    public function revokePermission(User $user, string $permission): void
    {
        $this->assertPermission($permission);
        service('authAuthorizer')->revokePermission($user, $permission, $this->tenant());
    }

    /**
     * Soft-delete an account. Its history (audit log, documents) keeps the id.
     */
    public function delete(User $user, User $actor): void
    {
        $this->assertAccountManageable($user);

        if ((int) $user->id === (int) $actor->id) {
            throw new RuntimeException('You cannot delete your own account.');
        }

        if ($this->isSuper($user) && $this->activeSuperCount() <= 1) {
            throw new RuntimeException('This is the only active holder of the super role and cannot be deleted.');
        }

        service('authTokens')->revokeAllForUser((int) $user->id);
        model(UserModel::class, false)->setStatus((int) $user->id, 'inactive', 'Account deleted');
        model(UserModel::class, false)->delete($user->id);
    }

    /**
     * Roles that can be given here by the signed-in person: every role but
     * the super role (unless they hold it, outside a workspace) and the roles
     * Config\Platform keeps off staff screens.
     *
     * @return array<string, string> role => title
     */
    public function assignableRoles(User $actor): array
    {
        $excluded = PlatformConfig::get()->staffExcludedRoles;
        $super    = $this->config->superRole;
        $options  = [];

        foreach (RoleCatalog::all() as $name => $role) {
            if (in_array($name, $excluded, true)) {
                continue;
            }

            if ($name === $super && ($this->inWorkspace() || ! $this->isSuper($actor))) {
                continue;
            }

            $options[$name] = $role['title'];
        }

        return $options;
    }

    // ------------------------------------------------------------------

    /**
     * Validate a requested role list and return it cleaned.
     *
     * @param list<string> $current
     * @param list<string> $requested
     *
     * @return list<string>
     */
    private function checkRoleChange(array $current, array $requested, User $actor): array
    {
        $requested = array_values(array_unique(array_filter(array_map('strval', $requested))));
        $unknown   = array_diff($requested, array_keys(RoleCatalog::all()));

        if ($unknown !== []) {
            throw new RuntimeException('Unknown role: ' . implode(', ', $unknown) . '.');
        }

        $excluded = array_intersect($requested, PlatformConfig::get()->staffExcludedRoles);

        if ($excluded !== []) {
            throw new RuntimeException('The ' . implode(', ', $excluded) . ' role is not given from this screen.');
        }

        if ($requested === []) {
            throw new RuntimeException('Give the person at least one role.');
        }

        $super = $this->config->superRole;

        if ($super !== null) {
            $adding   = in_array($super, $requested, true) && ! in_array($super, $current, true);
            $removing = in_array($super, $current, true) && ! in_array($super, $requested, true);

            if (($adding || $removing) && $this->inWorkspace()) {
                throw new RuntimeException('The super role applies to the whole platform and is not given inside a workspace.');
            }

            if (($adding || $removing) && ! $this->isSuper($actor)) {
                throw new RuntimeException('Only a holder of the super role can give or take it away.');
            }
        }

        return $requested;
    }

    private function assertAccountManageable(User $user): void
    {
        if (! $this->canManageAccount($user)) {
            throw new RuntimeException('This person also belongs to other workspaces. You can remove them from this workspace; their account is managed by the platform owner.');
        }
    }

    private function activeSuperCount(): int
    {
        if ($this->config->superRole === null) {
            return PHP_INT_MAX;
        }

        $ids = service('authAuthorizer')->usersWithRole($this->config->superRole, '');

        if ($ids === []) {
            return 0;
        }

        return model(UserModel::class, false)->whereIn('id', $ids)->where('status', 'active')->countAllResults();
    }

    private function assertPermission(string $permission): void
    {
        if (! array_key_exists($permission, $this->config->permissions)) {
            throw new RuntimeException('Unknown permission.');
        }

        if (! service('moduleGate')->allowsPermission($permission)) {
            throw new RuntimeException('That permission belongs to a module that is switched off here.');
        }
    }

    private function requestResetLink(string $email, ?string $ip): void
    {
        helper('url');

        service('authPasswordReset')->request(
            $email,
            $ip,
            static fn (string $selector, string $code): string => url_to('auth.reset') . '?' . http_build_query(['s' => $selector, 'c' => $code])
        );
    }

    /**
     * A readable password that satisfies the configured password policy,
     * e.g. "Kq7T-4Vxm-9aHd-pR2e47".
     *
     * @param array<string, string|null> $identifiers type => value
     */
    private function generatePassword(array $identifiers): string
    {
        $policy   = service('authPasswords')->policy();
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $chunks = [];

            for ($c = 0; $c < 4; $c++) {
                $chunk = '';

                for ($i = 0; $i < 4; $i++) {
                    $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }

                $chunks[] = $chunk;
            }

            $password = implode('-', $chunks) . random_int(10, 99);

            if ($policy->errors($password, $identifiers) === []) {
                return $password;
            }
        }

        throw new AuthException('Could not generate a password that meets the password policy. Check the password settings in Config\Auth.');
    }
}
