<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ephraitech\Platform\Commands\Concerns\ReadsOptions;
use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Module\Manifest;
use Ephraitech\Platform\Module\ModuleLocator;
use Ephraitech\Platform\Module\ModuleRegistry;

/**
 * Creates a working module: manifest, permissions, sidebar entry, a
 * tenant-scoped table with its migration and model, web pages to list,
 * add, change and delete records, and (with --api) a JSON API for them.
 *
 *     php spark make:module Crm --entity=Client --title="Clients" --group=Sales --icon=bi-people --api
 *
 * The same code runs in a single install and in a multi-tenant one:
 * the model keeps rows per tenant, routes name their guard and
 * permission in one filter, and the module is switched on and off by the
 * module gate like any other.
 */
class MakeModule extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Platform';
    protected $name        = 'make:module';
    protected $description = 'Creates a working module (manifest, table, model, pages, optional API) in modules/.';
    protected $usage       = 'make:module <Name> [options]';
    protected $arguments   = [
        'Name' => 'Module name in StudlyCase, e.g. Crm, Projects, ServiceDesk.',
    ];
    protected $options = [
        '--entity'   => 'What the module keeps, singular StudlyCase, e.g. Client. Default: Item.',
        '--title'    => 'Title shown in the sidebar and module list. Default: from the name.',
        '--group'    => 'Sidebar section. Default: General.',
        '--icon'     => 'Bootstrap Icons class, e.g. bi-people. Default: bi-folder2.',
        '--requires' => 'Modules this one needs, comma-separated, e.g. Crm,Finance.',
        '--api'      => 'Also create a JSON API under {apiPrefix}/<module>.',
        '--optional' => 'Single install: off until switched on under Modules.',
        '--force'    => 'Overwrite files of an existing module folder.',
    ];

    public function run(array $params): int
    {
        $name = (string) ($params[0] ?? CLI::prompt('Module name (StudlyCase)', null, 'required'));

        if (preg_match(ModuleLocator::NAME_PATTERN, $name) !== 1) {
            CLI::error('Use StudlyCase letters and digits only, starting with a capital: Crm, ServiceDesk, Finance.');

            return EXIT_USER_INPUT;
        }

        if (in_array($name, ModuleLocator::BUILT_IN, true)) {
            CLI::error("{$name} is a built-in platform module.");

            return EXIT_USER_INPUT;
        }

        $entity = (string) ($this->option('entity') ?: 'Item');

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $entity) !== 1) {
            CLI::error('--entity must be singular StudlyCase, e.g. Client or WorkOrder.');

            return EXIT_USER_INPUT;
        }

        $icon = (string) ($this->option('icon') ?: 'bi-folder2');

        if (preg_match('/^bi-[a-z0-9-]+$/', $icon) !== 1) {
            CLI::error('Icons are Bootstrap Icons classes such as bi-people or bi-receipt.');

            return EXIT_USER_INPUT;
        }

        $requires = array_values(array_filter(array_map('trim', explode(',', (string) ($this->option('requires') ?: '')))));

        foreach ($requires as $required) {
            if (preg_match(ModuleLocator::NAME_PATTERN, $required) !== 1) {
                CLI::error("'{$required}' in --requires is not a module name.");

                return EXIT_USER_INPUT;
            }
        }

        $path = ROOTPATH . 'modules' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR;

        if (is_dir($path) && ! $this->option('force')) {
            CLI::error("modules/{$name} already exists. Use --force to overwrite its generated files.");

            return EXIT_ERROR;
        }

        $vars = $this->variables($name, $entity, $icon, $requires);
        $api  = (bool) $this->option('api');

        $files = [
            'module.php'                                                       => $this->manifest($vars, $api),
            'Config/Routes.php'                                                => $this->routes($vars, $api),
            "Controllers/{$vars['{Entities}']}Controller.php"                 => $this->webController($vars),
            "Models/{$vars['{Entity}']}Model.php"                             => $this->model($vars),
            "Database/Migrations/{$vars['{migrationFile}']}.php"              => $this->migration($vars),
            'Views/' . $vars['{entities_snake}'] . '/index.php'               => $this->indexView($vars),
            'Views/' . $vars['{entities_snake}'] . '/form.php'                => $this->formView($vars),
            'Libraries/Search.php'                                             => $this->search($vars),
            'Database/Seeds/.gitkeep'                                          => '',
        ];

        if ($api) {
            $files["Controllers/Api/{$vars['{Entities}']}Controller.php"] = $this->apiController($vars);
        }

        foreach ($files as $relative => $contents) {
            $target = $path . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                CLI::error('Could not create ' . dirname($target));

                return EXIT_ERROR;
            }

            if (file_put_contents($target, $contents) === false) {
                CLI::error("Could not write {$target}");

                return EXIT_ERROR;
            }

            CLI::write('  created ' . CLI::color("modules/{$name}/{$relative}", 'green'));
        }

        ModuleRegistry::flush();

        CLI::newLine();
        CLI::write("Module {$name} created. Next:", 'yellow');
        CLI::write('  1. php spark platform:migrate   # create its table');
        CLI::write('  2. php spark auth:sync          # register its permissions and role grants');
        CLI::write("  3. Open /{$vars['{slug}']}" . ($api ? " and GET /{$vars['{apiBase}']}/{$vars['{entities_kebab}']}" : ''));

        if ($this->option('optional')) {
            CLI::write("  It is optional: switch it on under Administration → Modules (or php spark modules:on {$name}).");
        }

        return EXIT_SUCCESS;
    }

    // ------------------------------------------------------------------

    /**
     * @param list<string> $requires
     *
     * @return array<string, string>
     */
    private function variables(string $name, string $entity, string $icon, array $requires): array
    {
        $snake    = static fn (string $s): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $s));
        $plural   = static fn (string $s): string => preg_match('/(s|x|z|ch|sh)$/', $s) === 1 ? $s . 'es' : (preg_match('/[^aeiou]y$/', $s) === 1 ? substr($s, 0, -1) . 'ies' : $s . 's');
        $slug     = str_replace('_', '-', $snake($name));
        $entities = $plural($entity);
        $title    = (string) ($this->option('title') ?: Manifest::humanise($name));
        $prefix   = trim(PlatformConfig::get()->apiPrefix, '/');

        return [
            '{Module}'         => $name,
            '{module}'         => $snake($name),
            '{slug}'           => $slug,
            '{title}'          => $title,
            '{group}'          => (string) ($this->option('group') ?: 'General'),
            '{icon}'           => $icon,
            '{Entity}'         => $entity,
            '{Entities}'       => $entities,
            '{entity}'         => strtolower(Manifest::humanise($entity)),
            '{entities}'       => strtolower(Manifest::humanise($entities)),
            '{entity_snake}'   => $snake($entity),
            '{entities_snake}' => $snake($entities),
            '{entities_kebab}' => str_replace('_', '-', $snake($entities)),
            '{table}'          => $snake($name) . '_' . $snake($entities),
            '{perm}'           => $snake($name),
            '{namespace}'      => 'Modules\\' . $name,
            '{requires}'       => var_export(array_values(array_unique(['Core', ...$requires])), true),
            '{default}'        => $this->option('optional') ? 'false' : 'true',
            '{apiBase}'        => $prefix . '/' . $slug,
            '{migrationFile}'  => date('Y-m-d-His') . '_Create' . $name . $entities . 'Table',
            '{MigrationClass}' => 'Create' . $name . $entities . 'Table',
        ];
    }

    /**
     * @param array<string, string> $v
     */
    private function render(string $template, array $v): string
    {
        return strtr($template, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function manifest(array $v, bool $api): string
    {
        $v['{titleExport}'] = var_export($v['{title}'], true);
        $v['{groupExport}'] = var_export($v['{group}'], true);
        $v['{apiBlock}']    = $api
            ? "\n    'api' => [\n        'base'        => '{$v['{apiBase}']}',\n        'version'     => 'v1',\n        'description' => " . var_export(ucfirst($v['{entities}']) . ': list, read, create, change and delete.', true) . ",\n    ],\n"
            : '';

        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            /*
             * {title}. See Ephraitech\Platform\Module\Manifest for every key.
             */
            return [
                'name'        => '{Module}',
                'title'       => {titleExport},
                'description' => '',
                'version'     => '0.1.0',
                'order'       => 100,
                'requires'    => {requires},
                'default'     => {default},

                'permissions' => [
                    '{perm}.view'   => 'See {entities}',
                    '{perm}.manage' => 'Add, change and delete {entities}',
                ],

                // Starter roles; grants to roles the app does not define are ignored.
                'matrix' => [
                    'owner'  => ['{perm}.view', '{perm}.manage'],
                    'admin'  => ['{perm}.view', '{perm}.manage'],
                    'member' => ['{perm}.view'],
                ],

                'menu' => [
                    ['label' => {titleExport}, 'route' => '{module}.{entities_snake}', 'icon' => '{icon}', 'permission' => '{perm}.view', 'group' => {groupExport}, 'order' => 10],
                ],

                'quick' => [
                    ['label' => '{Entity}', 'route' => '{module}.{entities_snake}.new', 'icon' => '{icon}', 'permission' => '{perm}.manage'],
                ],

                'search' => ['{namespace}\Libraries\Search::find'],

                // Other modules can add panels to the {entity} page through this point:
                //     'extends' => ['{module}.{entity_snake}.panels' => [['cell' => '...', 'title' => '...']]]
            {apiBlock}];

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function routes(array $v, bool $api): string
    {
        $v['{apiRoutes}'] = $api ? $this->render(<<<'PHP'

            // JSON API: bearer tokens; multi-tenant clients send X-Tenant-ID.
            $routes->group('{apiBase}', ['namespace' => '{namespace}\Controllers\Api'], static function (RouteCollection $routes): void {
                $routes->get('{entities_kebab}', '{Entities}Controller::index', ['as' => 'api.{module}.{entities_snake}', 'filter' => 'can:token,{perm}.view']);
                $routes->get('{entities_kebab}/(:num)', '{Entities}Controller::show/$1', ['as' => 'api.{module}.{entities_snake}.show', 'filter' => 'can:token,{perm}.view']);
                $routes->post('{entities_kebab}', '{Entities}Controller::create', ['as' => 'api.{module}.{entities_snake}.create', 'filter' => 'can:token,{perm}.manage']);
                $routes->match(['put', 'patch'], '{entities_kebab}/(:num)', '{Entities}Controller::update/$1', ['as' => 'api.{module}.{entities_snake}.update', 'filter' => 'can:token,{perm}.manage']);
                $routes->delete('{entities_kebab}/(:num)', '{Entities}Controller::delete/$1', ['as' => 'api.{module}.{entities_snake}.delete', 'filter' => 'can:token,{perm}.manage']);
            });

            PHP, $v) : '';

        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            use CodeIgniter\Router\RouteCollection;

            /**
             * Every route names its guard and permission in one filter.
             * The platform filter answers 404 for all of them while the
             * module is switched off.
             *
             * @var RouteCollection $routes
             */
            $routes->group('{slug}', ['namespace' => '{namespace}\Controllers'], static function (RouteCollection $routes): void {
                $routes->get('/', '{Entities}Controller::index', ['as' => '{module}.{entities_snake}', 'filter' => 'can:session,{perm}.view']);
                $routes->get('new', '{Entities}Controller::new', ['as' => '{module}.{entities_snake}.new', 'filter' => 'can:session,{perm}.manage']);
                $routes->post('/', '{Entities}Controller::create', ['as' => '{module}.{entities_snake}.create', 'filter' => 'can:session,{perm}.manage']);
                $routes->get('(:num)/edit', '{Entities}Controller::edit/$1', ['as' => '{module}.{entities_snake}.edit', 'filter' => 'can:session,{perm}.manage']);
                $routes->post('(:num)', '{Entities}Controller::update/$1', ['as' => '{module}.{entities_snake}.update', 'filter' => 'can:session,{perm}.manage']);
                $routes->post('(:num)/delete', '{Entities}Controller::delete/$1', ['as' => '{module}.{entities_snake}.delete', 'filter' => 'can:session,{perm}.manage']);
            });
            {apiRoutes}
            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function model(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {namespace}\Models;

            use CodeIgniter\Model;
            use Ephraitech\Platform\Tenancy\TenantScoped;

            /**
             * {Entities}, kept per tenant ('' in a single install).
             */
            class {Entity}Model extends Model
            {
                use TenantScoped;

                protected $table            = '{table}';
                protected $primaryKey       = 'id';
                protected $returnType       = 'array';
                protected $useAutoIncrement = true;
                protected $useSoftDeletes   = true;
                protected $allowedFields    = ['name', 'notes', 'created_by'];
                protected $useTimestamps    = true;
                protected $dateFormat       = 'datetime';

                protected $validationRules = [
                    'name'  => 'required|max_length[150]',
                    'notes' => 'permit_empty|max_length[2000]',
                ];

                /**
                 * Narrow to rows matching a search term.
                 */
                public function search(?string $term): static
                {
                    $term = trim((string) $term);

                    if ($term !== '') {
                        $this->groupStart()->like('name', $term)->orLike('notes', $term)->groupEnd();
                    }

                    return $this;
                }
            }

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function migration(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {namespace}\Database\Migrations;

            use CodeIgniter\Database\Migration;
            use Ephraitech\Platform\Tenancy\TenantColumn;

            class {MigrationClass} extends Migration
            {
                public function up(): void
                {
                    $this->forge->addField([
                        'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                        ...TenantColumn::field(),
                        'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
                        'notes'      => ['type' => 'TEXT', 'null' => true],
                        'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                        'created_at' => ['type' => 'DATETIME', 'null' => true],
                        'updated_at' => ['type' => 'DATETIME', 'null' => true],
                        'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                    ]);
                    $this->forge->addPrimaryKey('id');
                    $this->forge->addKey(['tenant_id', 'name']);
                    $this->forge->createTable('{table}', true);
                }

                public function down(): void
                {
                    $this->forge->dropTable('{table}', true);
                }
            }

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function webController(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {namespace}\Controllers;

            use CodeIgniter\Exceptions\PageNotFoundException;
            use CodeIgniter\HTTP\RedirectResponse;
            use Ephraitech\Platform\Controllers\BaseModuleController;
            use {namespace}\Models\{Entity}Model;

            class {Entities}Controller extends BaseModuleController
            {
                public function index(): string
                {
                    $model = model({Entity}Model::class, false);
                    $q     = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
                    $rows  = $model->search($q)->orderBy('name', 'asc')->paginate(25);

                    return $this->page('{namespace}\Views\{entities_snake}\index', '{title}', [
                        'rows'  => $rows,
                        'pager' => $model->pager,
                        'q'     => $q,
                    ], [['{title}']]);
                }

                public function new(): string
                {
                    return $this->form(null, []);
                }

                public function create(): string|RedirectResponse
                {
                    $model = model({Entity}Model::class, false);
                    $data  = $this->input();

                    $id = $model->insert($data + ['created_by' => user_id()]);

                    if ($id === false) {
                        return $this->form(null, $data, $model->errors());
                    }

                    audit('{Module}', '{entity_snake}.created', 'Added {entity} ' . $data['name'], '{entity_snake}', $id);

                    return redirect()->route('{module}.{entities_snake}')->with('message', ucfirst('{entity}') . ' added.');
                }

                public function edit(int $id): string
                {
                    return $this->form($this->find($id), []);
                }

                public function update(int $id): string|RedirectResponse
                {
                    $row   = $this->find($id);
                    $model = model({Entity}Model::class, false);
                    $data  = $this->input();

                    if (! $model->update($id, $data)) {
                        return $this->form($row, $data, $model->errors());
                    }

                    $changes = service('auditTrail')->diff($row, $data);

                    if ($changes !== []) {
                        audit('{Module}', '{entity_snake}.updated', 'Changed {entity} ' . $data['name'], '{entity_snake}', $id, $changes);
                    }

                    return redirect()->route('{module}.{entities_snake}')->with('message', $changes === [] ? 'No changes to save.' : ucfirst('{entity}') . ' saved.');
                }

                public function delete(int $id): RedirectResponse
                {
                    $row = $this->find($id);

                    model({Entity}Model::class, false)->delete($id);
                    audit('{Module}', '{entity_snake}.deleted', 'Deleted {entity} ' . $row['name'], '{entity_snake}', $id);

                    return redirect()->route('{module}.{entities_snake}')->with('message', ucfirst('{entity}') . ' deleted.');
                }

                // ------------------------------------------------------------------

                /**
                 * @return array<string, mixed>
                 */
                private function find(int $id): array
                {
                    $row = model({Entity}Model::class, false)->find($id);

                    if (! is_array($row)) {
                        throw PageNotFoundException::forPageNotFound('That {entity} does not exist.');
                    }

                    return $row;
                }

                /**
                 * @return array{name: string, notes: ?string}
                 */
                private function input(): array
                {
                    $notes = trim((string) $this->request->getPost('notes'));

                    return [
                        'name'  => trim((string) $this->request->getPost('name')),
                        'notes' => $notes === '' ? null : $notes,
                    ];
                }

                /**
                 * @param array<string, mixed>|null $row
                 * @param array<string, mixed>      $input
                 * @param array<string, string>     $errors
                 */
                private function form(?array $row, array $input, array $errors = []): string
                {
                    $crumbs = [['{title}', url_to('{module}.{entities_snake}')], [$row === null ? 'New' : (string) $row['name']]];

                    return $this->page('{namespace}\Views\{entities_snake}\form', $row === null ? 'New {entity}' : 'Edit {entity}', [
                        'row'    => $row,
                        'values' => $input + ($row ?? []),
                        'errors' => $errors,
                    ], $crumbs);
                }
            }

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function apiController(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {namespace}\Controllers\Api;

            use CodeIgniter\HTTP\ResponseInterface;
            use Ephraitech\Platform\Controllers\Api\BaseApiController;
            use {namespace}\Models\{Entity}Model;

            /**
             * {Entities} API. Rows are scoped to the tenant chosen by the
             * platform filter (X-Tenant-ID or the token's tenant).
             */
            class {Entities}Controller extends BaseApiController
            {
                public function index(): ResponseInterface
                {
                    $model = model({Entity}Model::class, false)->search((string) $this->request->getGet('q'))->orderBy('name', 'asc');

                    return $this->respondPage($model, fn (array $row): array => $this->present($row));
                }

                public function show(int $id): ResponseInterface
                {
                    $row = model({Entity}Model::class, false)->find($id);

                    if (! is_array($row)) {
                        return $this->failNotFound('No such {entity}.');
                    }

                    // Other modules can add related data: 'extends' => ['{module}.{entity_snake}.api' => [['call' => 'Class::method', 'key' => '...']]]
                    return $this->respondData($this->present($row) + ['related' => extension_data('{module}.{entity_snake}.api', $id)]);
                }

                public function create(): ResponseInterface
                {
                    $model = model({Entity}Model::class, false);
                    $data  = $this->clean($this->input());
                    $id    = $model->insert($data + ['created_by' => user_id()]);

                    if ($id === false) {
                        return $this->failValidation($model->errors());
                    }

                    audit('{Module}', '{entity_snake}.created', 'Added {entity} ' . $data['name'] . ' through the API', '{entity_snake}', $id);

                    return $this->respondCreated($this->present($model->find($id)), url_to('api.{module}.{entities_snake}.show', $id));
                }

                public function update(int $id): ResponseInterface
                {
                    $model = model({Entity}Model::class, false);
                    $row   = $model->find($id);

                    if (! is_array($row)) {
                        return $this->failNotFound('No such {entity}.');
                    }

                    $data = $this->clean($this->input()) + $row;

                    if (! $model->update($id, ['name' => $data['name'], 'notes' => $data['notes']])) {
                        return $this->failValidation($model->errors());
                    }

                    $changes = service('auditTrail')->diff($row, ['name' => $data['name'], 'notes' => $data['notes']]);

                    if ($changes !== []) {
                        audit('{Module}', '{entity_snake}.updated', 'Changed {entity} ' . $data['name'] . ' through the API', '{entity_snake}', $id, $changes);
                    }

                    return $this->respondData($this->present($model->find($id)));
                }

                public function delete(int $id): ResponseInterface
                {
                    $model = model({Entity}Model::class, false);
                    $row   = $model->find($id);

                    if (! is_array($row)) {
                        return $this->failNotFound('No such {entity}.');
                    }

                    $model->delete($id);
                    audit('{Module}', '{entity_snake}.deleted', 'Deleted {entity} ' . $row['name'] . ' through the API', '{entity_snake}', $id);

                    return $this->respondNoContent();
                }

                // ------------------------------------------------------------------

                /**
                 * @param array<string, mixed> $input
                 *
                 * @return array<string, mixed>
                 */
                private function clean(array $input): array
                {
                    $out = [];

                    if (array_key_exists('name', $input)) {
                        $out['name'] = trim((string) $input['name']);
                    }

                    if (array_key_exists('notes', $input)) {
                        $notes        = trim((string) $input['notes']);
                        $out['notes'] = $notes === '' ? null : $notes;
                    }

                    return $out;
                }

                /**
                 * @param array<string, mixed> $row
                 *
                 * @return array<string, mixed>
                 */
                private function present(array $row): array
                {
                    return [
                        'id'         => (int) $row['id'],
                        'name'       => $row['name'],
                        'notes'      => $row['notes'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                    ];
                }
            }

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function indexView(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php
            /**
             * @var CodeIgniter\View\View      $this
             * @var list<array<string, mixed>> $rows
             * @var CodeIgniter\Pager\Pager    $pager
             * @var string                     $q
             */
            ?>
            <?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

            <?= $this->section('actions') ?>
            <?php if (can('{perm}.manage')): ?>
                <a class="btn btn-primary" href="<?= url_to('{module}.{entities_snake}.new') ?>"><i class="bi bi-plus-lg me-1"></i>New {entity}</a>
            <?php endif ?>
            <?= $this->endSection() ?>

            <?= $this->section('content') ?>
            <form class="row g-2 mb-3" method="get" role="search">
                <div class="col-12 col-md-8">
                    <label class="visually-hidden" for="q">Search {entities}</label>
                    <input class="form-control" id="q" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Search {entities}">
                </div>
                <div class="col-12 col-md-4 d-grid"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
            </form>

            <section class="eos-panel">
                <div class="table-responsive">
                    <table class="table eos-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Notes</th>
                                <th scope="col">Added</th>
                                <?php if (can('{perm}.manage')): ?><th scope="col" class="text-end">Actions</th><?php endif ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rows === []): ?>
                                <tr><td colspan="4" class="text-center eos-muted py-4">No {entities}<?= $q !== '' ? ' match' : ' yet' ?>.</td></tr>
                            <?php endif ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc($row['name']) ?></td>
                                    <td class="eos-muted"><?= esc(mb_strimwidth((string) $row['notes'], 0, 120, '…')) ?></td>
                                    <td class="font-tabular"><?= esc(substr((string) $row['created_at'], 0, 10)) ?></td>
                                    <?php if (can('{perm}.manage')): ?>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url_to('{module}.{entities_snake}.edit', $row['id']) ?>">Edit</a>
                                            <form class="d-inline" method="post" action="<?= url_to('{module}.{entities_snake}.delete', $row['id']) ?>" data-eos-confirm="Delete <?= esc($row['name'], 'attr') ?>?" data-eos-confirm-label="Delete">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="mt-3"><?= $pager->links() ?></div>
            <?= $this->endSection() ?>

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function formView(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php
            /**
             * @var CodeIgniter\View\View     $this
             * @var array<string, mixed>|null $row
             * @var array<string, mixed>      $values
             * @var array<string, string>     $errors
             */
            $action = $row === null ? url_to('{module}.{entities_snake}.create') : url_to('{module}.{entities_snake}.update', $row['id']);
            ?>
            <?= $this->extend('Ephraitech\Platform\Views\layouts\app') ?>

            <?= $this->section('content') ?>
            <section class="eos-panel">
                <form class="eos-form" method="post" action="<?= esc($action, 'attr') ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" maxlength="150" required autofocus value="<?= esc((string) ($values['name'] ?? ''), 'attr') ?>"<?= isset($errors['name']) ? ' aria-describedby="name-error" aria-invalid="true"' : '' ?>>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback" id="name-error"><?= esc($errors['name']) ?></div><?php endif ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control<?= isset($errors['notes']) ? ' is-invalid' : '' ?>" id="notes" name="notes" rows="4" maxlength="2000"><?= esc((string) ($values['notes'] ?? '')) ?></textarea>
                        <?php if (isset($errors['notes'])): ?><div class="invalid-feedback"><?= esc($errors['notes']) ?></div><?php endif ?>
                    </div>
                    <?php if ($row !== null): ?>
                        <?= slot('{module}.{entity_snake}.panels', ['id' => (int) $row['id']]) ?>
                    <?php endif ?>
                    <button class="btn btn-primary" type="submit"><?= $row === null ? 'Add {entity}' : 'Save changes' ?></button>
                    <a class="btn btn-link" href="<?= url_to('{module}.{entities_snake}') ?>">Cancel</a>
                </form>
            </section>
            <?= $this->endSection() ?>

            PHP, $v);
    }

    /**
     * @param array<string, string> $v
     */
    private function search(array $v): string
    {
        return $this->render(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {namespace}\Libraries;

            use {namespace}\Models\{Entity}Model;

            /**
             * Global search provider for {entities} (module.php 'search').
             */
            class Search
            {
                /**
                 * @return list<array<string, string>>
                 */
                public function find(string $term, int $limit): array
                {
                    if (! can('{perm}.view')) {
                        return [];
                    }

                    helper('url');

                    $rows = model({Entity}Model::class, false)->search($term)->orderBy('name', 'asc')->findAll($limit);

                    return array_map(static fn (array $row): array => [
                        'id'       => (string) $row['id'],
                        'type'     => '{Entity}',
                        'title'    => (string) $row['name'],
                        'subtitle' => mb_strimwidth((string) $row['notes'], 0, 80, '…'),
                        'url'      => can('{perm}.manage') ? url_to('{module}.{entities_snake}.edit', $row['id']) : url_to('{module}.{entities_snake}') . '?q=' . rawurlencode((string) $row['name']),
                        'icon'     => '{icon}',
                    ], $rows);
                }
            }

            PHP, $v);
    }
}
