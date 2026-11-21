# ci4-platform guide

1. [How an app is laid out](#how-an-app-is-laid-out)
2. [Modules and module.php](#modules-and-modulephp)
3. [The module gate](#the-module-gate)
4. [Single install or multi-tenant](#single-install-or-multi-tenant)
5. [Tenants](#tenants)
6. [Tenant-scoped data](#tenant-scoped-data)
7. [Routes and permissions](#routes-and-permissions)
8. [Extension points](#extension-points)
9. [The API](#the-api)
10. [Settings, numbering, audit, search](#settings-numbering-audit-search)
11. [Dashboards](#dashboards)
12. [People and roles](#people-and-roles)
13. [Layouts, branding and tables](#layouts-branding-and-tables)
14. [Commands](#commands)
15. [Deploying](#deploying)
16. [Installing into an existing app](#installing-into-an-existing-app)
17. [Moving Ephraitech OS onto the package](#moving-ephraitech-os-onto-the-package)

---

## How an app is laid out

```
app/Config/Auth.php        roles, tenancy ('single'|'multi'), $permissionGate
app/Config/Platform.php    name, brand, menu sections, plans, tenant sources
app/Config/Autoload.php    registers module namespaces (ModuleAutoloader)
app/Config/Filters.php     'platform' first in $globals['before']
app/Config/App.php         accepts workspace hosts (TenantHosts)
modules/<Name>/            your modules (namespace Modules\<Name>)
vendor/ephraitech/ci4-platform/src/          Core  (namespace Ephraitech\Platform)
vendor/ephraitech/ci4-platform/src/Users/    Users (namespace Ephraitech\Platform\Users)
```

Core and Users are built-in modules: always installed, always on.

## Modules and module.php

A module is a folder with a `module.php` returning an array. Every key is
optional; unknown keys are an error, so typos are caught at once.

```php
return [
    'title'       => 'Clients',
    'description' => 'Clients and contacts.',
    'version'     => '1.2.0',
    'order'       => 20,               // sidebar and list order
    'requires'    => ['Core'],         // switched off without these
    'default'     => true,             // single install: on until switched off
    'always_on'   => false,            // true: can never be switched off
    'central'     => false,            // multi-tenant: also on with no tenant selected

    'permissions' => ['crm.view' => 'See clients', 'crm.manage' => 'Change clients'],
    'matrix'      => ['owner' => ['crm.*'], 'member' => ['crm.view']],

    'menu'  => [['label' => 'Clients', 'route' => 'crm.clients', 'icon' => 'bi-people', 'permission' => 'crm.view', 'group' => 'Sales', 'order' => 10]],
    'quick' => [['label' => 'Client', 'route' => 'crm.clients.new', 'icon' => 'bi-person-plus', 'permission' => 'crm.manage']],

    'settings' => [
        'crm' => [
            'title'  => 'Clients',
            'scope'  => 'tenant',                  // or 'global' (one value for the whole platform)
            'fields' => ['code_prefix' => ['label' => 'Client code prefix', 'type' => 'text', 'default' => 'CL', 'rules' => 'required|max_length[5]']],
        ],
    ],
    'sequences' => ['client' => ['prefix' => 'CL', 'padding' => 4, 'reset' => 'never']],

    'boards'  => ['sales' => ['title' => 'Sales', 'icon' => 'bi-graph-up', 'roles' => ['sales', 'owner']]],
    'widgets' => [['cell' => 'Modules\Crm\Cells\Widgets::newClients', 'dashboards' => ['sales'], 'permission' => 'crm.view', 'width' => 6]],
    'landing' => [],                                  // Class::method returning a board key for the signed-in person
    'search'  => ['Modules\Crm\Libraries\Search::find'],

    'extends' => [/* see Extension points */],
    'api'     => ['base' => 'api/v1/crm', 'version' => 'v1', 'description' => 'Clients'],
];
```

Where modules come from:

| Source | Location | Namespace |
|---|---|---|
| built-in | inside this package | `Ephraitech\Platform`, `Ephraitech\Platform\Users` |
| app | `modules/<Name>/module.php` (more folders: `platform.modulePaths` in `.env`) | `Modules\<Name>` |
| Composer package | declared in the package's `composer.json` | the package's own |

A module shipped as a Composer package declares itself:

```json
"extra": {
    "ephraitech-platform": {
        "modules": { "Billing": { "path": "src", "namespace": "Acme\\Billing" } }
    }
}
```

**Choosing what is installed** (read at boot, so only in `.env`):

```ini
platform.modulesOff  = Payroll, Fleet     ; never installed here
platform.modulesOnly = Crm, Sales         ; only these (plus Core and Users)
```

or put an empty `.disabled` file in a module's folder.

## The module gate

```php
service('moduleGate')->enabled('Crm');            // this request / tenant
service('moduleGate')->enabled('Crm', 'acme');    // a given tenant
service('moduleGate')->enabledModules();          // ['Core', 'Users', 'Crm', ...]
service('moduleGate')->decisions('acme');         // ['Crm' => ['on' => true, 'source' => 'plan'], ...]
module_enabled('Crm');                             // helper
```

How a module's state is decided, for one tenant ('' in a single install):

1. not installed → never on;
2. `always_on` → on;
3. multi-tenant with no tenant selected → on only if `central`;
4. otherwise the resolvers in `Config\Platform::$resolvers`, first answer wins:
   - single: an admin's switch (`StoredStateResolver`), else the manifest's `default`;
   - multi: the tenant's add-on/exclusion, optionally until a date (`StoredStateResolver`), else its plan (`PlanResolver`);
5. a module whose `requires` are not all on is switched off.

Switching:

```php
service('moduleGate')->setState('Payroll', true);                          // single install
service('moduleGate')->setState('Sales', true, 'acme', $userId, '2026-12-31 23:59:59', '30-day trial');
service('moduleGate')->setState('Sales', null, 'acme');                    // back to the plan
```

`setState()` returns the other modules that changed as a result (a module
switched off takes those that require it along) and fires the event
`platform.module.changed`.

**Your own resolver** — e.g. from a billing system or a licence file —
implements `Ephraitech\Platform\Module\Resolvers\ModuleResolver`:

```php
final class SubscriptionResolver implements ModuleResolver
{
    public function decide(string $module, array $manifest, ResolverContext $context): ?bool
    {
        return Billing::hasActiveAddOn($context->tenantKey, $module);   // null = let the next resolver decide
    }

    public function label(): string
    {
        return 'subscription';
    }
}
```

```php
// app/Config/Platform.php
public array $resolvers = [
    'single' => [StoredStateResolver::class, ManifestDefaultResolver::class],
    'multi'  => [StoredStateResolver::class, SubscriptionResolver::class, PlanResolver::class],
];
```

Decisions are cached per tenant for `$gateCacheTtl` seconds; changes made
through the platform clear the cache at once. A resolver that reads another
system should call `service('moduleGate')->flush($tenant)` when that system
changes.

## Single install or multi-tenant

`Config\Auth::$tenancy` decides, for ci4-auth and the platform together
(also `auth.tenancy = multi` in `.env`).

| | single | multi |
|---|---|---|
| Tenant | none; `tenant_key()` is `''` | each request has a tenant, or none (central) |
| Modules | installed, then switched by an admin | plan + per-tenant add-ons and exclusions |
| Roles | assigned once | assigned per workspace; platform-wide roles (e.g. superadmin) apply everywhere |
| Settings | one set | per workspace; `scope => 'global'` groups shared, edited centrally |
| Numbering, audit | one series | per workspace |
| Admin screens | Modules: switches | Workspaces: tenants, plans, add-ons, trials |

The same module code runs in both, as long as it uses `TenantScoped` models
and the platform helpers.

## Tenants

Tenants (workspaces) live in `platform_tenants`. Their **slug** is the tenant
id everywhere: ci4-auth role assignments and tokens, every `tenant_id`
column, the subdomain.

```bash
php spark tenants:create acme --name "Acme Ltd" --plan standard --owner jane@acme.co.ug --trial-days 14
php spark tenants:update acme --plan pro --status active
php spark tenants:list
```

or Administration → Workspaces (platform owner, no workspace selected).

**How a request finds its tenant** — `Config\Platform::$tenantSources`, in order:

| Source | How | Notes |
|---|---|---|
| `domain` | `<slug>.<baseDomain>`, or the tenant's own `domain` | needs `$baseDomain`, a wildcard DNS record and certificate, `cookie.domain = .<baseDomain>`, and the `TenantHosts` line in `app/Config/App.php`. Unknown hosts get 404. |
| `header` | `X-Tenant-ID: acme` (`Config\Auth::$tenantHeader`) | for API clients. A token bound to a tenant implies it; a header naming another tenant gets 403. |
| `session` | the workspace picked at `/workspaces` | one shared domain. People in exactly one workspace are taken into it. |

Then the platform filter:

- refuses unknown tenants (404), suspended, cancelled or expired-trial ones (a page saying so / 403 JSON);
- refuses signed-in people with no role or permission in the tenant (403);
- enters the tenant: ci4-auth's `TenantContext`, `TenantScoped` models, settings, numbering and audit all follow.

**Working inside a tenant from code** (commands, queues, cron):

```php
service('tenancy')->runAs('acme', static fn () => model(InvoiceModel::class)->overdue());

foreach (model(TenantModel::class)->where('status', 'active')->findAll() as $tenant) {
    service('tenancy')->runAs($tenant, static fn () => service(Reminders::class)->send());
}
```

Helpers: `tenant()` (the row), `tenant('name')`, `tenant_key()` (slug or `''`).

Events: `platform.tenant.created`, `platform.tenant.updated`,
`platform.tenant.entered`, `platform.module.changed` — subscribe in a
module's `Config/Events.php`, e.g. to seed a new workspace or notify billing.

## Tenant-scoped data

Every table that holds a tenant's data gets a `tenant_id` column and its model
uses `TenantScoped`:

```php
// migration
$this->forge->addField([
    'id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
    ...TenantColumn::field(),
    'name' => ['type' => 'VARCHAR', 'constraint' => 150],
]);
$this->forge->addKey('tenant_id');
$this->forge->addUniqueKey(['tenant_id', 'code']);     // unique per tenant
```

```php
class ClientModel extends Model
{
    use TenantScoped;
    protected $table = 'crm_clients';
}
```

Every `find`, `first`, `findAll`, `paginate`, `countAllResults`, `insert`,
`update` and `delete` through the model is limited to the current tenant, new
rows are stamped, and `tenant_id` cannot be changed by an update. In a single
install the value is always `''`, so nothing changes — and an install can
become multi-tenant later by filling the column in.

- With no tenant selected (multi-tenant), a scoped model throws
  `TenantRequiredException` instead of reading every tenant's rows. Override
  `tenantRequired()` to return `false` for models that also keep central rows.
- `withoutTenantScope()` lifts the scope for the next query (platform reports).
- **Not scoped:** `$model->builder()`, `db_connect()->table()`, raw SQL and
  `DataTable` builders. Add `->where('tenant_id', tenant_key())` yourself.

## Routes and permissions

Put the guard and the permission in one filter:

```php
$routes->get('clients', 'ClientsController::index', ['filter' => 'can:session,crm.view']);   // pages
$routes->get('api/v1/crm/clients', 'Api\ClientsController::index', ['filter' => 'can:token,crm.view']);  // API
```

`can:` signs the person in with that guard first, so a route can never lose
its authentication through an edit to a separate filter. `deploy:check` fails
for any module route without `auth:` or `can:`.

You never add a module check to routes: the global `platform` filter maps the
matched controller to its module and answers 404 while the module is off. For
routes outside modules that depend on one, use `module:Crm` (or `module:Crm,Sales`).

Permissions declared in manifests are fed to ci4-auth by
`Ephraitech\Platform\Config\AuthPermissions` (no edits to `Config\Auth`), and
`ModulePermissionGate` (set as `Config\Auth::$permissionGate`) refuses the
permissions of switched-off modules in `can()`, `can_user()`, the `can:`
filter and `permissionsFor()`. Run `php spark auth:sync` after changing manifests.

## Extension points

A module that owns a page or an API resource names a point; other modules
contribute to it in their manifest, without the owner knowing they exist.

```php
// Finance/module.php
'extends' => [
    'crm.client.panels' => [
        ['cell' => 'Modules\Finance\Cells\Invoices::forClient', 'title' => 'Invoices', 'icon' => 'bi-receipt', 'permission' => 'invoices.view', 'order' => 10],
    ],
    'crm.client.api' => [
        ['call' => 'Modules\Finance\Api\ClientSummary::forClient', 'key' => 'invoices', 'permission' => 'invoices.view'],
    ],
],
```

```php
// Crm client page
<?= slot('crm.client.panels', ['client_id' => $client['id']]) ?>

// or as tabs
<?php foreach (slot_items('crm.client.panels', ['client_id' => $client['id']]) as $tab): ?> ... <?php endforeach ?>

// Crm API controller
return $this->respondData($client + ['related' => extension_data('crm.client.api', $client['id'])]);
```

Contributions from switched-off modules and those the person may not see are
left out. A contribution that throws is logged and skipped.

The layouts have two points of their own, for notices on every page:

| Point | Where |
|---|---|
| `layout.banners` | Signed-in pages, above the flash messages (maintenance, someone working in the workspace on support) |
| `layout.auth.banners` | Sign-in and password pages, above the form (nobody is signed in) |

A banner cell decides for itself whom it is for, and returns `''` otherwise.

## The API

API controllers extend `Ephraitech\Platform\Controllers\Api\BaseApiController`:

```php
class ClientsController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->respondPage(model(ClientModel::class)->orderBy('name'), fn (array $c): array => [...]);
    }
}
```

- success: `{"data": ..., "meta": {"page", "per_page", "total", "pages"}}`
- failure: `{"status": 422, "error": "validation_failed", "message": "...", "details": {...}}` — the same shape as ci4-auth's errors
- helpers: `respondData`, `respondCreated`, `respondNoContent`, `respondPage` (`?page=`, `?per_page=` up to `$apiMaxPerPage`), `fail`, `failValidation`, `failNotFound`, `failForbidden`, `input()` (JSON or form body)

Clients sign in with ci4-auth (`POST /api/v1/auth/login` in the starter),
then send `Authorization: Bearer <token>` and, multi-tenant, `X-Tenant-ID`
(or use a token bound to the tenant). The platform's own endpoints:

| Endpoint | Returns |
|---|---|
| `GET /api/v1/platform/context` | the person, roles, tenancy mode, tenant, product name, modules on (with their `api` block), permissions, menu, quick actions |
| `GET /api/v1/platform/modules` | every installed module, on or off here, and why |
| `GET /api/v1/platform/search?q=` | global search across switched-on modules |

A mobile app or SPA builds its navigation from `context`, so it adapts to
each client's or tenant's modules without a release.

## Settings, numbering, audit, search

```php
setting('company.name');                       // stored value, else the declared default
service('platformSettings')->save('crm', ['code_prefix' => 'CU'], user_id());
next_number('invoice');                        // INV-2026-0001, gap-free, per tenant
audit('Crm', 'client.updated', 'Changed client Acme', 'client', $id, service('auditTrail')->diff($before, $after));
```

- Settings groups appear under Settings for modules that are on. Field types:
  text, email, tel, url, number, date, textarea, select (`options` as an array
  or `"Class::method"`). `scope => 'global'` groups are edited by the platform
  owner with no workspace selected.
- Sequences use MySQL `LAST_INSERT_ID()` (no table lock); call `next_number()`
  inside the transaction that saves the document.
- The audit log redacts password, token, secret and similar fields.
- Search providers are `Class::method($term, $limit)` returning rows of
  `type, title, subtitle, url, icon` (and `id` for the API); they should check
  their own permission.

## Dashboards

Boards come from manifests (`boards`); Core adds **My day** (`home`) and
**Overview**. People get My day, the boards whose `roles` include one of
theirs, the default board (Settings → Dashboards), boards named by `landing`
providers, and — for the super role — every board. Widgets (`widgets`) show
on the boards they list (`'*'` for all), if their module is on and the person
has their permission. A board that would show only panels every board has is
skipped.

## People and roles

Built-in module **Users** (Administration → Staff, Roles), on ci4-auth:

- create accounts with an emailed link or a temporary password, edit details,
  suspend/deactivate, reset passwords, sign people out of apps, grant extra
  permissions, delete;
- edit roles (ci4-auth `RoleManager`): a built-in role edited here is
  app-managed and kept by `auth:sync`; "reset to defaults" hands it back;
  roles can be created and deleted;
- multi-tenant: inside a workspace the list shows its members; adding an
  email that already has an account adds that person (no second account);
  roles are given per workspace; a person who also belongs elsewhere can only
  be removed from the workspace (their account is the platform owner's to
  change). Role definitions are shared and edited centrally.

Keep roles off these screens (client portal accounts, say) with
`Config\Platform::$staffExcludedRoles = ['client']`.

## Layouts, branding and tables

- `Ephraitech\Platform\Views\layouts\app` (sections: `content`, `actions`,
  `styles`, `scripts`) and `layouts\auth` (set as ci4-auth's `$viewLayout`).
- Controllers extend `BaseModuleController` and render with
  `$this->page($view, $title, $data, $breadcrumbs)`. The event `platform.page`
  fires first (throw a `RedirectException` to send some accounts elsewhere).
- Branding: `$appName`, `$tagline`, `$themeColor` (sidebar colour) and
  `$brand` logo paths in `Config\Platform`; without logos the default mark is
  shown with the company (single) or workspace (multi) name.
- Any `table.eos-table` reads well on phones (rows as cards) and tablets
  (sticky first column); add `eos-table-keep` to keep a grid. `DataTable`
  answers DataTables server-side requests.
- Assets are published to `public/vendor/platform` by `php spark platform:publish`
  (the starter runs it after `composer update` and every deploy).

## Commands

| Command | Does |
|---|---|
| `make:module <Name> [--entity --title --group --icon --requires --api --optional --force]` | a working module: manifest, tenant-scoped table, model, list/add/edit/delete pages, search, and with `--api` a JSON API |
| `platform:migrate [-g group]` | ci4-auth, then installed modules in `requires` order, then everything else |
| `platform:publish [--deploy] [--force]` | assets; with `--deploy` the cPanel kit |
| `modules:list [--tenant]` | installed or not, on or off and why, what each needs |
| `modules:on / modules:off / modules:reset <Module> [--tenant --until --note]` | switch a module (single install, or one workspace) |
| `tenants:create / tenants:update / tenants:list` | workspaces |
| `deploy:check [--wiring]` | server, wiring, routes, composer, assets, tenancy, database |

Options take `--name value` or `--name=value`.

## Deploying

`php spark platform:publish --deploy` writes `deploy/cpanel-deploy.sh`,
`.cpanel.yml`, `.env.production.example` and `deploy/DEPLOY.md`. The script
installs from `composer.lock`, backs up the database, runs `platform:migrate`,
publishes assets, runs `auth:sync`, clears caches and ends with `deploy:check`.

`deploy:check` fails (exit 1) when:

- `app/Config/Auth.php` is not the config ci4-auth uses, or `$permissionGate` is not set;
- the `platform` filter is not in `$globals['before']`;
- a module route has no `auth:`/`can:` filter, or the sign-in routes are missing;
- `composer.json` does not require `codeigniter4/framework`, or `composer.lock` is missing or not committed;
- module requirements are missing, namespaces are not registered, or assets are not published;
- (multi-tenant) the default plan or owner role is undefined;
- the database is unreachable, migrations are pending, roles are not synced, or nobody has the super role;
- production basics are wrong (environment, https baseURL, encryption key, writable folders, `.env`).

## Installing into an existing app

```bash
composer require ephraitech/ci4-platform
```

1. `app/Config/Auth.php` extends `Ephraitech\Auth\Config\Auth`, with
   `$permissionGate = \Ephraitech\Platform\Auth\ModulePermissionGate::class`,
   `$userAllowedFields = ['first_name', 'last_name', 'job_title']`, and
   (optional) the platform's `$viewLayout` and `$views`.
2. `app/Config/Autoload.php`: in the constructor, `require_once COMPOSER_PATH;`
   then `$this->psr4 += \Ephraitech\Platform\Module\ModuleAutoloader::namespaces();`
   before `parent::__construct()`; add `'platform'` to `$helpers`.
3. `app/Config/Filters.php`: `'platform'` first in `$globals['before']`; exclude `api/*` from `csrf`.
4. Multi-tenant on own hosts: the `TenantHosts` constructor in `app/Config/App.php`.
5. `app/Config/Routes.php`: `service('auth')->routes($routes, except: ['register']);`
   and, for API clients, `service('auth')->apiRoutes($routes, except: ['register'], prefix: 'api/v1/auth');`.
   Remove your own `/` route: Core serves the dashboard there.
6. Optional: `app/Config/Platform.php` extending `Ephraitech\Platform\Config\Platform`.
7. `php spark platform:migrate && php spark auth:sync && php spark platform:publish && php spark deploy:check --wiring`.

The [saas-starter](https://github.com/etus-ephraitech/saas-starter) has every one of these files.

## Moving Ephraitech OS onto the package

The package keeps Ephraitech OS's conventions (`eos-` CSS classes, the `EOS`
JS object, route names, view sections), so most module code moves as is.

| Ephraitech OS | Platform |
|---|---|
| `modules/Core` | the package (`Ephraitech\Platform\...`); delete the folder |
| `modules/Users` | built-in `Ephraitech\Platform\Users`; delete the folder |
| `Modules\Core\Controllers\BaseModuleController` | `Ephraitech\Platform\Controllers\BaseModuleController` |
| `Modules\Core\Views\layouts\app` | `Ephraitech\Platform\Views\layouts\app` |
| `Modules\Core\Libraries\DataTable` | `Ephraitech\Platform\Libraries\DataTable` |
| `['auth:session', 'can:x']` | `'can:session,x'` |
| `module_overrides.php`, `auth_config_helper.php`, the `Config\Auth` constructor merge | gone (ci4-auth ≥ 0.10.3 resolves config; manifests reach ci4-auth by discovery) |
| `users_role_overrides`, `RoleEditor::editedRoles()` | ci4-auth `RoleManager` (`app_managed`) |
| tables `settings`, `sequences`, `audit_logs` | `platform_settings`, `platform_sequences`, `platform_audit_logs` (with `tenant_id`) — copy rows with `tenant_id = ''` |
| `Dashboards::ALL`, `ROLE_BOARDS`, the department board | `boards` in manifests; the department board as a `landing` provider in People |
| the `client` → Portal redirect | a `platform.page` listener in Portal's `Config/Events.php`, plus `$staffExcludedRoles = ['client']` |
| Signatures, StaffDirectory, DocumentLines, line items | stay in Ephraitech OS (business features), e.g. in a `Documents` module |
