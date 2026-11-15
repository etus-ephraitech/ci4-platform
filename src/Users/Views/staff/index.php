<?php
/**
 * @var CodeIgniter\View\View $this
 * @var array<string, int>    $counts
 * @var array<string, string> $roles
 */
?>
<?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

<?= $this->section('actions') ?>
<?php if (can('users.create')): ?>
    <a class="btn btn-primary" href="<?= url_to('users.staff.new') ?>"><i class="bi bi-person-plus me-1"></i>Add staff member</a>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<dl class="eos-stats">
    <div><dt>Active</dt><dd><?= (int) ($counts['active'] ?? 0) ?></dd></div>
    <div><dt>Suspended</dt><dd><?= (int) ($counts['suspended'] ?? 0) ?></dd></div>
    <div><dt>Deactivated</dt><dd><?= (int) ($counts['inactive'] ?? 0) ?></dd></div>
</dl>

<section class="eos-panel">
    <div class="eos-filters eos-filters-inline">
        <div>
            <label class="form-label" for="f-status">Status</label>
            <select class="form-select" id="f-status" data-eos-filter="#staff-table">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="inactive">Deactivated</option>
                <option value="">All</option>
            </select>
        </div>
        <div>
            <label class="form-label" for="f-role">Role</label>
            <select class="form-select" id="f-role" data-eos-filter="#staff-table">
                <option value="">Any role</option>
                <?php foreach ($roles as $key => $title): ?>
                    <option value="<?= esc($key, 'attr') ?>"><?= esc($title) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table id="staff-table" class="table eos-table align-middle w-100">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email and phone</th>
                    <th scope="col">Roles</th>
                    <th scope="col">Status</th>
                    <th scope="col">Last sign-in</th>
                </tr>
            </thead>
        </table>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var role = new URLSearchParams(window.location.search).get('role');
    if (role) {
        document.getElementById('f-role').value = role;
        document.getElementById('f-status').value = '';
    }
})();
EOS.table('#staff-table', <?= json_encode(url_to('users.staff.data')) ?>, [
    null, { orderable: false }, { orderable: false }, null, null
], function () {
    return {
        status: document.getElementById('f-status').value,
        role: document.getElementById('f-role').value
    };
});
</script>
<?= $this->endSection() ?>
