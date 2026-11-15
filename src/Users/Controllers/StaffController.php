<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Entities\User;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Auth\Exceptions\RegistrationException;
use CodeIgniter\Database\BaseBuilder;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Controllers\BaseModuleController;
use Ephraitech\Platform\Libraries\DataTable;
use Ephraitech\Platform\Models\AuditLogModel;
use Ephraitech\Platform\Users\Forms\StaffForm;
use Ephraitech\Platform\Users\Libraries\PermissionCatalog;
use Ephraitech\Platform\Users\Libraries\RoleCatalog;
use Ephraitech\Platform\Users\Libraries\StaffService;
use RuntimeException;

/**
 * People and their access. In a single install: everyone. In a
 * multi-tenant install: the members of the current workspace, or (with
 * no workspace selected) every account, for the platform owner.
 */
class StaffController extends BaseModuleController
{
    private const STATUS_LABELS = [
        'active'    => 'Active',
        'pending'   => 'Pending',
        'suspended' => 'Suspended',
        'inactive'  => 'Deactivated',
    ];

    private StaffService $staff;

    /**
     * Roles of the people on the current data page, by user id.
     *
     * @var array<int, list<string>>
     */
    private array $rowRoles = [];

    private Auth $authConfig;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->staff      = new StaffService();
        $this->authConfig = PlatformConfig::auth();
    }

    // ------------------------------------------------------------------
    // List
    // ------------------------------------------------------------------

    public function index(): string
    {
        $users   = $this->authConfig->table('users');
        $counts  = [];
        $builder = $this->db()->table($users . ' u')->select('u.status, COUNT(*) AS n')->where('u.deleted_at', null)->groupBy('u.status');

        foreach ($this->scope($builder)->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $this->page('Ephraitech\Platform\Users\Views\staff\index', 'Staff', [
            'counts'      => $counts,
            'roles'       => $this->roleOptions(),
            'inWorkspace' => $this->staff->inWorkspace(),
        ], [['Staff']]);
    }

    public function data(): ResponseInterface
    {
        $users = $this->authConfig->table('users');
        $ids   = $this->authConfig->table('identities');
        $ur    = $this->authConfig->table('user_roles');
        $roles = $this->authConfig->table('roles');

        $db      = $this->db();
        $ids     = $db->prefixTable($ids);
        $ur      = $db->prefixTable($ur);
        $roles   = $db->prefixTable($roles);
        $builder = $db->table($users . ' u')
            ->select("u.id, u.first_name, u.last_name, u.job_title, u.status, u.last_login_at,
                (SELECT i.identifier FROM {$ids} i WHERE i.user_id = u.id AND i.type = 'email' LIMIT 1) AS email,
                (SELECT i.identifier FROM {$ids} i WHERE i.user_id = u.id AND i.type = 'phone' LIMIT 1) AS phone", false)
            ->where('u.deleted_at', null);

        $this->scope($builder);

        $status = (string) $this->request->getGet('status');
        if (array_key_exists($status, self::STATUS_LABELS)) {
            $builder->where('u.status', $status);
        }

        $role = (string) $this->request->getGet('role');
        if (RoleCatalog::exists($role)) {
            $builder->where("EXISTS (SELECT 1 FROM {$ur} x JOIN {$roles} r ON r.id = x.role_id WHERE x.user_id = u.id AND x.tenant_id = " . $db->escape($this->staff->tenant()) . ' AND r.name = ' . $db->escape($role) . ')', null, false);
        }

        $roleTitles = array_map(static fn (array $r): string => $r['title'], RoleCatalog::all());

        $table = (new DataTable($builder, $this->request))
            ->searchable(['u.first_name', 'u.last_name', 'u.job_title'])
            ->orderable([0 => 'u.first_name', 3 => 'u.status', 4 => 'u.last_login_at'])
            ->defaultOrder('u.first_name', 'asc')
            ->prepare(function (array $rows): void {
                $this->rowRoles = $this->staff->assignedRolesFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
            })
            ->format(fn (array $u): array => [
                '<div class="d-flex align-items-center gap-2"><span class="eos-avatar eos-avatar-sm" aria-hidden="true">' . esc(initials($this->nameOf($u))) . '</span>'
                    . '<div><a href="' . url_to('users.staff.show', $u['id']) . '" class="fw-semibold">' . esc($this->nameOf($u)) . '</a>'
                    . ($u['job_title'] ? '<small class="d-block eos-muted">' . esc($u['job_title']) . '</small>' : '') . '</div></div>',
                esc((string) $u['email']) . ($u['phone'] ? '<small class="d-block eos-muted">' . esc($u['phone']) . '</small>' : ''),
                implode(' ', array_map(
                    static fn (string $r): string => '<span class="eos-pill eos-pill-primary">' . esc($roleTitles[$r] ?? $r) . '</span>',
                    $this->rowRoles[(int) $u['id']] ?? []
                )),
                $this->statusBadge((string) $u['status']),
                $u['last_login_at'] ? esc(date('j M Y, H:i', strtotime((string) $u['last_login_at']))) : '<span class="eos-muted">Never</span>',
            ]);

        return $this->response->setJSON($table->response());
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function new(): string
    {
        return $this->formPage($this->form(true), null);
    }

    public function create(): string|RedirectResponse
    {
        $form = $this->form(true);

        if (! $form->validate()) {
            return $this->formPage($form, null);
        }

        $data = $form->validated();

        try {
            $result = $this->staff->create(
                $this->profileFrom($data),
                (string) $data['email'],
                $data['phone'] ?? null,
                array_values((array) ($data['roles'] ?? [])),
                (string) $data['password_mode'],
                $this->actor(),
                $this->request->getIPAddress()
            );
        } catch (RegistrationException $e) {
            return $this->formPage($form, null, $this->flattenErrors($e->getErrors()));
        } catch (RuntimeException|AuthException $e) {
            return $this->formPage($form, null, $e->getMessage());
        }

        $user = $result['user'];

        if ($result['existing']) {
            audit('Users', 'user.added', 'Added ' . $this->nameOf($user) . ' to the workspace with roles ' . implode(', ', (array) $data['roles']), 'user', $user->id);

            return redirect()->route('users.staff.show', [$user->id])
                ->with('message', $this->nameOf($user) . ' already had an account, so they were added to this workspace. They sign in as before.');
        }

        audit('Users', 'user.created', 'Created an account for ' . $this->nameOf($user) . ' with roles ' . implode(', ', (array) $data['roles']), 'user', $user->id);

        $redirect = redirect()->route('users.staff.show', [$user->id]);

        if ($result['password'] !== null) {
            return $redirect->with('temporary_password', $result['password'])
                ->with('message', 'Account created. Give them the temporary password below; it is shown only once.');
        }

        return $redirect->with('message', 'Account created. A link to choose a password has been emailed to ' . $data['email'] . '. If it does not arrive, set a temporary password instead.');
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    public function show(int $id): string
    {
        $user    = $this->userOr404($id);
        $roles   = $this->staff->assignedRoles($id);
        $catalog = new PermissionCatalog();
        $direct  = $this->staff->directPermissions($id);

        $effective = service('authAuthorizer')->permissionsFor($user, $this->staff->tenant());

        $activity = model(AuditLogModel::class, false)->filtered(['user_id' => (string) $id])->findAll(10);

        return $this->page('Ephraitech\Platform\Users\Views\staff\show', $this->nameOf($user), [
            'user'              => $user,
            'roles'             => $roles,
            'roleCatalog'       => array_intersect_key(RoleCatalog::all(), $this->roleOptions() + array_flip($roles)),
            'globalRoles'       => $this->staff->inWorkspace() ? service('authAuthorizer')->rolesFor($user, '') : [],
            'canManageAccount'  => $this->staff->canManageAccount($user),
            'inWorkspace'       => $this->staff->inWorkspace(),
            'isSuper'           => $this->staff->isSuper($user),
            'actorIsSuper'      => $this->staff->isSuper($this->actor()),
            'isSelf'            => (int) $user->id === (int) user_id(),
            'grouped'           => $catalog->grouped(),
            'effective'         => $effective,
            'direct'            => $direct,
            'statusLabels'      => self::STATUS_LABELS,
            'apiTokens'         => count(service('authTokens')->listForUser($id)),
            'activity'          => $activity,
            'temporaryPassword' => session('temporary_password'),
        ], [['Staff', url_to('users.staff')], [$this->nameOf($user)]]);
    }

    public function edit(int $id): string
    {
        $user = $this->userOr404($id);

        $form = $this->form(false)->fill([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'job_title'  => $user->job_title,
            'email'      => $user->getEmail(),
            'phone'      => $user->getPhone(),
        ]);

        return $this->formPage($form, $user);
    }

    public function update(int $id): string|RedirectResponse
    {
        $user = $this->userOr404($id);
        $form = $this->form(false);

        if (! $form->validate()) {
            return $this->formPage($form, $user);
        }

        $data = $form->validated();

        try {
            $changes = $this->staff->updateDetails($user, $this->profileFrom($data), (string) $data['email'], $data['phone'] ?? null);
        } catch (RuntimeException|AuthException $e) {
            return $this->formPage($form, $user, $e->getMessage());
        }

        if ($changes !== []) {
            audit('Users', 'user.updated', 'Changed account details for ' . $this->nameOf($user), 'user', $id, $changes);
        }

        return redirect()->route('users.staff.show', [$id])->with('message', $changes === [] ? 'No changes to save.' : 'Details saved.');
    }

    public function roles(int $id): RedirectResponse
    {
        $user      = $this->userOr404($id);
        $requested = (array) ($this->request->getPost('roles') ?? []);

        try {
            $change = $this->staff->setRoles($user, array_values(array_map('strval', $requested)), $this->actor());
        } catch (RuntimeException|AuthException $e) {
            return redirect()->route('users.staff.show', [$id])->with('error', $e->getMessage());
        }

        if ($change['from'] === $change['to']) {
            return redirect()->route('users.staff.show', [$id])->with('message', 'Roles unchanged.');
        }

        audit('Users', 'user.roles', 'Changed roles for ' . $this->nameOf($user), 'user', $id, [
            'roles' => ['from' => implode(', ', $change['from']), 'to' => implode(', ', $change['to'])],
        ]);

        return redirect()->route('users.staff.show', [$id])->with('message', 'Roles updated. Their app sessions were signed out so the new access applies straight away.');
    }

    public function status(int $id): RedirectResponse
    {
        $user   = $this->userOr404($id);
        $status = (string) $this->request->getPost('status');
        $reason = trim((string) $this->request->getPost('reason')) ?: null;

        if ($status !== 'active' && $reason === null) {
            return redirect()->route('users.staff.show', [$id])->with('error', 'Give a reason. It is kept on the account and in the audit log.');
        }

        try {
            $this->staff->setStatus($user, $status, $reason !== null ? mb_substr($reason, 0, 255) : null, $this->actor());
        } catch (RuntimeException|AuthException $e) {
            return redirect()->route('users.staff.show', [$id])->with('error', $e->getMessage());
        }

        $label = self::STATUS_LABELS[$status] ?? $status;
        audit('Users', 'user.status', $label . ' the account of ' . $this->nameOf($user) . ($reason ? ': ' . $reason : ''), 'user', $id, [
            'status' => ['from' => $user->status, 'to' => $status],
        ]);

        return redirect()->route('users.staff.show', [$id])->with('message', $status === 'active'
            ? 'Account active. They can sign in.'
            : 'Account ' . strtolower($label) . '. They are signed out and cannot sign in.');
    }

    public function password(int $id): RedirectResponse
    {
        $user = $this->userOr404($id);
        $mode = (string) $this->request->getPost('mode');

        try {
            if ($mode === 'link') {
                $this->staff->sendResetLink($user, $this->request->getIPAddress());
                audit('Users', 'user.password.link', 'Sent a password reset link to ' . $this->nameOf($user), 'user', $id);

                return redirect()->route('users.staff.show', [$id])->with('message', 'Reset link emailed to ' . $user->getEmail() . '. It expires after a short time.');
            }

            $password = $this->staff->setTemporaryPassword($user);
        } catch (RuntimeException|AuthException $e) {
            return redirect()->route('users.staff.show', [$id])->with('error', $e->getMessage());
        }

        audit('Users', 'user.password.reset', 'Set a temporary password for ' . $this->nameOf($user), 'user', $id);

        return redirect()->route('users.staff.show', [$id])
            ->with('temporary_password', $password)
            ->with('message', 'New temporary password set. Give it to them privately; they should change it after signing in.');
    }

    public function signOut(int $id): RedirectResponse
    {
        $user  = $this->userOr404($id);
        $count = $this->staff->signOutEverywhere($user);

        audit('Users', 'user.signout', 'Signed ' . $this->nameOf($user) . ' out of ' . $count . ' app session' . ($count === 1 ? '' : 's'), 'user', $id);

        return redirect()->route('users.staff.show', [$id])->with('message', $count === 0 ? 'No app sessions were open.' : "Signed out of {$count} app session" . ($count === 1 ? '' : 's') . '.');
    }

    public function permission(int $id): RedirectResponse
    {
        $user       = $this->userOr404($id);
        $permission = (string) $this->request->getPost('permission');
        $action     = (string) $this->request->getPost('action');

        try {
            $action === 'revoke'
                ? $this->staff->revokePermission($user, $permission)
                : $this->staff->grantPermission($user, $permission);
        } catch (RuntimeException|AuthException $e) {
            return redirect()->to(url_to('users.staff.show', $id) . '#access')->with('error', $e->getMessage());
        }

        audit('Users', 'user.permission', ($action === 'revoke' ? 'Removed extra permission ' : 'Granted extra permission ') . $permission . ($action === 'revoke' ? ' from ' : ' to ') . $this->nameOf($user), 'user', $id);

        return redirect()->to(url_to('users.staff.show', $id) . '#access')->with('message', $action === 'revoke' ? 'Extra permission removed.' : 'Extra permission granted.');
    }

    /**
     * Multi-tenant: take this person out of the workspace (their account stays).
     */
    public function remove(int $id): RedirectResponse
    {
        $user = $this->userOr404($id);

        try {
            $roles = $this->staff->removeFromWorkspace($user, $this->actor());
        } catch (RuntimeException|AuthException $e) {
            return redirect()->route('users.staff.show', [$id])->with('error', $e->getMessage());
        }

        audit('Users', 'user.removed', 'Removed ' . $this->nameOf($user) . ' from the workspace', 'user', $id, [
            'roles' => ['from' => implode(', ', $roles), 'to' => ''],
        ]);

        return redirect()->route('users.staff')->with('message', $this->nameOf($user) . ' no longer has access to this workspace.');
    }

    public function delete(int $id): RedirectResponse
    {
        $user = $this->userOr404($id);

        try {
            $this->staff->delete($user, $this->actor());
        } catch (RuntimeException|AuthException $e) {
            return redirect()->route('users.staff.show', [$id])->with('error', $e->getMessage());
        }

        audit('Users', 'user.deleted', 'Deleted the account of ' . $this->nameOf($user), 'user', $id);

        return redirect()->route('users.staff')->with('message', 'Account deleted. Their records and history keep their name.');
    }

    // ------------------------------------------------------------------

    private function actor(): User
    {
        $actor = user();

        if (! $actor instanceof User) {
            throw new RuntimeException('Not signed in.');
        }

        return $actor;
    }

    private function userOr404(int $id): User
    {
        $user = $this->staff->find($id);

        if ($user === null || ! $this->staff->isVisible($user)) {
            throw PageNotFoundException::forPageNotFound('That account does not exist, was deleted, or is not a member here.');
        }

        return $user;
    }

    /**
     * @param array<string, mixed>|User $user
     */
    private function nameOf(array|User $user): string
    {
        $first = is_array($user) ? ($user['first_name'] ?? '') : ($user->first_name ?? '');
        $last  = is_array($user) ? ($user['last_name'] ?? '') : ($user->last_name ?? '');
        $name  = trim($first . ' ' . $last);

        if ($name !== '') {
            return $name;
        }

        $email = is_array($user) ? ($user['email'] ?? null) : $user->getEmail();

        return $email ?: 'User #' . (is_array($user) ? $user['id'] : $user->id);
    }

    private function statusBadge(string $status): string
    {
        $tones = ['active' => 'success', 'pending' => 'gold', 'suspended' => 'danger', 'inactive' => 'muted'];

        return '<span class="eos-pill eos-pill-' . ($tones[$status] ?? 'muted') . '">' . esc(self::STATUS_LABELS[$status] ?? ucfirst($status)) . '</span>';
    }

    /**
     * @return array<string, string> role key => title
     */
    private function roleOptions(): array
    {
        return $this->staff->assignableRoles($this->actor());
    }

    /**
     * Limit a users query (aliased "u") to the people shown here: workspace
     * members in a workspace, and never people whose only roles are kept
     * off the staff screens (Config\Platform::$staffExcludedRoles).
     */
    private function scope(BaseBuilder $builder): BaseBuilder
    {
        $db       = $this->db();
        $tenant   = $db->escape($this->staff->tenant());
        $ur       = $db->prefixTable($this->authConfig->table('user_roles'));
        $up       = $db->prefixTable($this->authConfig->table('user_permissions'));
        $roles    = $db->prefixTable($this->authConfig->table('roles'));
        $excluded = PlatformConfig::get()->staffExcludedRoles;

        if ($this->staff->inWorkspace()) {
            $builder->where("(EXISTS (SELECT 1 FROM {$ur} m WHERE m.user_id = u.id AND m.tenant_id = {$tenant}) OR EXISTS (SELECT 1 FROM {$up} m WHERE m.user_id = u.id AND m.tenant_id = {$tenant}))", null, false);
        }

        if ($excluded !== []) {
            $list = implode(', ', array_map(static fn (string $r): string => (string) $db->escape($r), $excluded));
            $builder->where("(NOT EXISTS (SELECT 1 FROM {$ur} e WHERE e.user_id = u.id AND e.tenant_id = {$tenant}) OR EXISTS (SELECT 1 FROM {$ur} e JOIN {$roles} er ON er.id = e.role_id WHERE e.user_id = u.id AND e.tenant_id = {$tenant} AND er.name NOT IN ({$list})))", null, false);
        }

        return $builder;
    }

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return db_connect($this->authConfig->DBGroup);
    }

    private function form(bool $isNew): StaffForm
    {
        return new StaffForm($this->roleOptions(), $isNew, $this->authConfig->isIdentifierEnabled(Auth::IDENTIFIER_PHONE));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{first_name: string, last_name: ?string, job_title: ?string}
     */
    private function profileFrom(array $data): array
    {
        $clean = static fn ($v): ?string => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        return [
            'first_name' => (string) $clean($data['first_name'] ?? ''),
            'last_name'  => $clean($data['last_name'] ?? null),
            'job_title'  => $clean($data['job_title'] ?? null),
        ];
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function flattenErrors(array $errors): string
    {
        $lines = [];

        foreach ($errors as $messages) {
            foreach ((array) $messages as $message) {
                $lines[] = $message;
            }
        }

        return implode(' ', $lines) ?: 'The account could not be created.';
    }

    private function formPage(StaffForm $form, ?User $user, ?string $error = null): string
    {
        $form->action($user === null ? url_to('users.staff.create') : url_to('users.staff.update', $user->id));

        $crumbs = [['Staff', url_to('users.staff')]];
        if ($user !== null) {
            $crumbs[] = [$this->nameOf($user), url_to('users.staff.show', $user->id)];
        }
        $crumbs[] = [$user === null ? 'New' : 'Edit'];

        return $this->page('Ephraitech\Platform\Users\Views\staff\form', $user === null ? 'Add a person' : 'Edit details', [
            'form'  => $form,
            'user'  => $user,
            'error' => $error,
        ], $crumbs);
    }
}
