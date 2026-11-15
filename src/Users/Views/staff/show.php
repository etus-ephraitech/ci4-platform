<?php
/**
 * @var CodeIgniter\View\View                     $this
 * @var Ephraitech\Auth\Entities\User             $user
 * @var list<string>                              $roles
 * @var array<string, array{title: string, description: string}> $roleCatalog
 * @var bool                                      $isSuper
 * @var bool                                      $actorIsSuper
 * @var bool                                      $isSelf
 * @var array<string, array<string, string>>      $grouped
 * @var list<string>                              $effective
 * @var list<string>                              $direct
 * @var array<string, string>                     $statusLabels
 * @var int                                       $apiTokens
 * @var list<array<string, mixed>>                $activity
 * @var ?string                                   $temporaryPassword
 * @var list<string>                              $globalRoles      platform-wide roles (multi-tenant, in a workspace)
 * @var bool                                      $canManageAccount the account itself may be changed from here
 * @var bool                                      $inWorkspace
 */
$name   = display_name($user);
$tones  = ['active' => 'success', 'pending' => 'gold', 'suspended' => 'danger', 'inactive' => 'muted'];
$status = (string) $user->status;

$superRole   = Ephraitech\Platform\Config\PlatformConfig::auth()->superRole;
$canManage   = can('roles.manage');
$effectiveSet = array_flip($effective);
$directSet    = array_flip($direct);
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('actions') ?>
<?php if (can('users.update') && $canManageAccount): ?>
    <a class="btn btn-outline-secondary" href="<?= url_to('users.staff.edit', $user->id) ?>"><i class="bi bi-pencil me-1"></i>Edit details</a>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($temporaryPassword): ?>
    <div class="alert alert-warning" role="status">
        <p class="mb-2 fw-semibold">Temporary password for <?= esc($name) ?>, shown only once:</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <code class="eos-secret" id="temp-password"><?= esc($temporaryPassword) ?></code>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="#temp-password"><i class="bi bi-clipboard me-1"></i>Copy</button>
        </div>
        <p class="small mb-0 mt-2">Give it to them in person or by phone, not over email. They should change it from their account menu after signing in.</p>
    </div>
<?php endif ?>

<?php if ($status !== 'active'): ?>
    <div class="alert alert-<?= $status === 'suspended' ? 'danger' : 'warning' ?>" role="status">
        This account is <?= esc(strtolower($statusLabels[$status] ?? $status)) ?><?= $user->status_reason ? ': ' . esc($user->status_reason) : '' ?>. They cannot sign in.
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-12 col-xl-4 d-flex flex-column gap-4">
        <section class="eos-panel" aria-labelledby="who">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="eos-avatar eos-avatar-lg" aria-hidden="true"><?= esc(initials($name)) ?></span>
                <div>
                    <h2 class="h5 mb-0" id="who"><?= esc($name) ?></h2>
                    <?php if ($user->job_title): ?><p class="eos-muted mb-0"><?= esc($user->job_title) ?></p><?php endif ?>
                </div>
            </div>
            <dl class="eos-details">
                <dt>Status</dt><dd><span class="eos-pill eos-pill-<?= $tones[$status] ?? 'muted' ?>"><?= esc($statusLabels[$status] ?? $status) ?></span></dd>
                <dt>Email</dt><dd><?= $user->getEmail() ? '<a href="mailto:' . esc($user->getEmail(), 'attr') . '">' . esc($user->getEmail()) . '</a>' : '—' ?></dd>
                <dt>Phone</dt><dd><?= $user->getPhone() ? '<a href="tel:' . esc($user->getPhone(), 'attr') . '">' . esc($user->getPhone()) . '</a>' : '—' ?></dd>
                <dt>Last sign-in</dt><dd><?= $user->last_login_at ? esc(date('j M Y, H:i', strtotime((string) $user->last_login_at))) : 'Never' ?></dd>
                <dt>Last active</dt><dd><?= $user->last_active_at ? esc(date('j M Y, H:i', strtotime((string) $user->last_active_at))) : '—' ?></dd>
                <dt>Account since</dt><dd><?= esc(date('j M Y', strtotime((string) $user->created_at))) ?></dd>
                <dt>App sessions</dt><dd><?= $apiTokens ?> open</dd>
            </dl>
        </section>

        <?php if ($inWorkspace && ! $isSelf && can('users.update')): ?>
            <section class="eos-panel" aria-labelledby="ws-actions">
                <h2 class="eos-section-title" id="ws-actions">This workspace</h2>
                <?php if (! $canManageAccount): ?>
                    <p class="small eos-muted">This person also works in other workspaces, so their account (password, status) is managed by the platform owner.</p>
                <?php endif ?>
                <form action="<?= url_to('users.staff.remove', $user->id) ?>" method="post" data-eos-confirm="Remove <?= esc($name, 'attr') ?> from this workspace? Their roles here are taken away; their account stays." data-eos-confirm-label="Remove">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-person-dash me-1"></i>Remove from workspace</button>
                </form>
            </section>
        <?php endif ?>

        <?php if (can('users.update') && ! $isSelf && $canManageAccount): ?>
            <section class="eos-panel" aria-labelledby="acct-actions">
                <h2 class="eos-section-title" id="acct-actions">Account</h2>

                <h3 class="eos-subhead mt-0">Password</h3>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if ($user->getEmail() && $status === 'active'): ?>
                        <form action="<?= url_to('users.staff.password', $user->id) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="link">
                            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-envelope me-1"></i>Email reset link</button>
                        </form>
                    <?php endif ?>
                    <form action="<?= url_to('users.staff.password', $user->id) ?>" method="post" data-eos-confirm="Replace <?= esc($name, 'attr') ?>'s password with a new temporary one? Their app sessions will be signed out." data-eos-confirm-label="Set temporary password">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mode" value="generate">
                        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-key me-1"></i>Temporary password</button>
                    </form>
                </div>

                <?php if (can('tokens.manage') && $apiTokens > 0): ?>
                    <form action="<?= url_to('users.staff.signout', $user->id) ?>" method="post" class="mb-3">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Sign out of all apps</button>
                    </form>
                <?php endif ?>

                <h3 class="eos-subhead">Status</h3>
                <?php if ($status === 'active'): ?>
                    <form action="<?= url_to('users.staff.status', $user->id) ?>" method="post">
                        <?= csrf_field() ?>
                        <label class="form-label" for="status-choice">Switch off this account</label>
                        <select class="form-select form-select-sm mb-2" id="status-choice" name="status">
                            <option value="suspended">Suspend (temporary, e.g. investigation, leave)</option>
                            <option value="inactive">Deactivate (left the company)</option>
                        </select>
                        <label class="form-label" for="status-reason">Reason</label>
                        <input class="form-control form-control-sm" id="status-reason" name="reason" maxlength="255" required placeholder="e.g. Contract ended 30 November">
                        <button class="btn btn-sm btn-outline-danger mt-2" type="submit">Switch off account</button>
                    </form>
                <?php else: ?>
                    <form action="<?= url_to('users.staff.status', $user->id) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="active">
                        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Reactivate account</button>
                    </form>
                <?php endif ?>

                <?php if (can('users.delete')): ?>
                    <hr>
                    <form action="<?= url_to('users.staff.delete', $user->id) ?>" method="post" data-eos-confirm="Delete <?= esc($name, 'attr') ?>'s account? They lose access for good. Their name stays on records they created." data-eos-confirm-label="Delete account">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-link text-danger p-0" type="submit">Delete account</button>
                    </form>
                <?php endif ?>
            </section>
        <?php elseif ($isSelf): ?>
            <section class="eos-panel">
                <p class="mb-2">This is your own account.</p>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url_to('auth.password') ?>"><i class="bi bi-key me-1"></i>Change your password</a>
            </section>
        <?php endif ?>
    </div>

    <div class="col-12 col-xl-8 d-flex flex-column gap-4">
        <section class="eos-panel" aria-labelledby="roles-title">
            <header class="eos-panel-head">
                <h2 id="roles-title">Roles</h2>
                <?php if ($canManage): ?><a href="<?= url_to('users.roles') ?>">What each role can do</a><?php endif ?>
            </header>
            <?php if ($canManage): ?>
                <form action="<?= url_to('users.staff.roles', $user->id) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="eos-role-grid">
                        <?php foreach ($roleCatalog as $key => $role): ?>
                            <?php
                            $checked  = in_array($key, $roles, true);
                            $disabled = $key === $superRole && ! $actorIsSuper;
                            ?>
                            <label class="eos-role-option<?= $checked ? ' is-checked' : '' ?><?= $disabled ? ' is-disabled' : '' ?>">
                                <input type="checkbox" name="roles[]" value="<?= esc($key, 'attr') ?>"<?= $checked ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?>>
                                <?php if ($disabled && $checked): ?><input type="hidden" name="roles[]" value="<?= esc($key, 'attr') ?>"><?php endif ?>
                                <span>
                                    <strong><?= esc($role['title']) ?></strong>
                                    <small><?= esc($role['description']) ?></small>
                                </span>
                            </label>
                        <?php endforeach ?>
                    </div>
                    <?php if ($globalRoles !== []): ?>
                        <p class="small eos-muted mt-3 mb-0">Also holds, across the whole platform: <?= esc(implode(', ', array_map(static fn (string $r): string => $roleCatalog[$r]['title'] ?? $r, $globalRoles))) ?>.</p>
                    <?php endif ?>
                    <button class="btn btn-primary btn-sm mt-3" type="submit">Save roles</button>
                    <span class="small eos-muted ms-2">Their app sessions are signed out so new access applies at once.</span>
                </form>
            <?php else: ?>
                <p class="mb-0">
                    <?php foreach ($roles as $role): ?>
                        <span class="eos-pill eos-pill-primary"><?= esc($roleCatalog[$role]['title'] ?? $role) ?></span>
                    <?php endforeach ?>
                </p>
            <?php endif ?>
        </section>

        <section class="eos-panel" id="access" aria-labelledby="access-title">
            <header class="eos-panel-head">
                <h2 id="access-title">What they can do</h2>
                <span class="eos-count"><?= $isSuper ? 'Everything (super role)' : count($effective) . ' permissions' ?></span>
            </header>
            <?php if (! $isSuper && $canManage): ?>
                <p class="small eos-muted">Ticks come from their roles. Use <strong>Grant</strong> for a one-off extra permission without changing their role.</p>
            <?php endif ?>
            <div class="eos-perm-groups">
                <?php foreach ($grouped as $group => $permissions): ?>
                    <?php if ($permissions === []) { continue; } ?>
                    <details<?= $group === array_key_first($grouped) ? ' open' : '' ?>>
                        <summary>
                            <?= esc($group) ?>
                            <span class="eos-count"><?= count(array_intersect_key($permissions, $effectiveSet)) ?>/<?= count($permissions) ?></span>
                        </summary>
                        <ul class="eos-perm-list">
                            <?php foreach ($permissions as $permission => $description): ?>
                                <?php
                                $has      = $isSuper || isset($effectiveSet[$permission]);
                                $isDirect = isset($directSet[$permission]);
                                ?>
                                <li class="<?= $has ? 'is-on' : '' ?>">
                                    <i class="bi <?= $has ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
                                    <span>
                                        <?= esc($description) ?>
                                        <code><?= esc($permission) ?></code>
                                        <?php if ($isDirect): ?><span class="eos-pill eos-pill-gold">Extra</span><?php endif ?>
                                    </span>
                                    <span class="visually-hidden"><?= $has ? 'Allowed' : 'Not allowed' ?></span>
                                    <?php if ($canManage && ! $isSuper && ($isDirect || ! $has)): ?>
                                        <form action="<?= url_to('users.staff.permission', $user->id) ?>" method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="permission" value="<?= esc($permission, 'attr') ?>">
                                            <input type="hidden" name="action" value="<?= $isDirect ? 'revoke' : 'grant' ?>">
                                            <button class="btn btn-link btn-sm p-0" type="submit"><?= $isDirect ? 'Remove' : 'Grant' ?></button>
                                        </form>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </details>
                <?php endforeach ?>
            </div>
        </section>

        <?php if (can('audit.view')): ?>
            <section class="eos-panel" aria-labelledby="their-activity">
                <header class="eos-panel-head">
                    <h2 id="their-activity">Recent activity</h2>
                    <a href="<?= url_to('core.audit') ?>?user_id=<?= (int) $user->id ?>">Full history</a>
                </header>
                <?php if ($activity === []): ?>
                    <p class="eos-muted mb-0">Nothing recorded yet.</p>
                <?php else: ?>
                    <ul class="eos-activity">
                        <?php foreach ($activity as $row): ?>
                            <li>
                                <i class="bi bi-dot" aria-hidden="true"></i>
                                <div>
                                    <p><?= esc($row['description']) ?></p>
                                    <small><?= esc($row['module']) ?>, <?= esc(date('j M Y, H:i', strtotime((string) $row['created_at']))) ?></small>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </section>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var text = document.querySelector(btn.getAttribute('data-copy')).textContent;
        navigator.clipboard.writeText(text).then(function () { EOS.notify('success', 'Copied.'); });
    });
});
document.querySelectorAll('.eos-role-option input[type="checkbox"]').forEach(function (box) {
    box.addEventListener('change', function () { box.closest('.eos-role-option').classList.toggle('is-checked', box.checked); });
});
</script>
<?= $this->endSection() ?>
