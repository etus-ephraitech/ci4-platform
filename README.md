# ephraitech/ci4-platform

A modular-monolith platform for CodeIgniter 4.7. Build a product as
**modules** that can be switched on and off, and run it either as **one
install per client** or as a **multi-tenant SaaS**, with the same code.

- **Modules** declare everything they add in one `module.php`: permissions,
  role grants, menu entries, settings, document numbers, dashboards, widgets,
  search, extension points and API.
- **One gate**: `service('moduleGate')->enabled('Crm')` decides whether a
  module is on for this request or tenant. Menus, routes, permissions
  (`can()`), settings, dashboards, search, extensions and the API context all
  ask it.
- **Tenancy** (multi-tenant installs): tenants by subdomain, own domain,
  `X-Tenant-ID` header, token or a workspace picker; plans with add-ons and
  trials; `TenantScoped` models; per-tenant settings, numbering and audit.
- **Extensible from pages and the API**: modules contribute to each other
  through extension points, and expose JSON APIs on a common base.
- **Ready screens**: sign-in pages, dashboard, settings, people and roles
  (role editor on ci4-auth), modules, workspaces, audit log, global search,
  responsive tables.
- **Tooling**: `make:module` (working CRUD + API), `platform:migrate`,
  `modules:list/on/off/reset`, `tenants:*`, `deploy:check`, and a cPanel
  deploy kit.

Built on [ephraitech/ci4-auth](https://github.com/etus-ephraitech/ci4-auth)
(≥ 0.12, for its permission gate) and
[ephraitech/ci4-forms](https://github.com/etus-ephraitech/ci4-forms).

## Start a new project

```bash
composer create-project ephraitech/saas-starter my-app
cd my-app
# set app.baseURL and database.* in .env, then:
php spark platform:migrate
php spark auth:sync
php spark auth:user:create --email you@example.com --generate --role superadmin --verified
php spark make:module Crm --entity Client --title Clients --group Sales --icon bi-people --api
php spark platform:migrate && php spark auth:sync
php spark serve
```

To add the platform to an existing app instead, see
[docs/GUIDE.md § Installing into an existing app](docs/GUIDE.md#installing-into-an-existing-app).

## The two levels of "on"

| | **Installed** | **Enabled** |
|---|---|---|
| Decided | at boot, from files and `.env` | per request / tenant, by the module gate |
| By | module folders and packages, minus `.disabled` files, `platform.modulesOff`, `platform.modulesOnly` | single install: an admin's switch, else the manifest's `default`; multi-tenant: the tenant's add-ons and exclusions, else its plan; always-on modules; a module whose `requires` are off is off |
| Controls | namespace, routes, commands, events, migrations, the permission catalogue | menus, quick actions, search, settings, dashboards and widgets, extensions, route access (404), `can()` |

A module that is not installed does not exist for the app. A module that is
installed but switched off for a tenant keeps its tables (every tenant shares
the schema) and its permissions (stable ids), but every tenant without it sees
404s and fails `can()` for its permissions — the super role included.

## Learn more

- [docs/GUIDE.md](docs/GUIDE.md): modules and manifests, the gate, single
  and multi-tenant installs, tenant-scoped data, extension points, the API,
  settings, numbering, audit, dashboards, commands, deploying, and moving an
  existing Ephraitech OS app onto the package.
- [CHANGELOG.md](CHANGELOG.md)

## Requirements

PHP 8.2+, CodeIgniter 4.7, MySQL/MariaDB (SQLite works for development and
tests), ext-intl, ext-mbstring.

## Licence

MIT © Ephraitech Unified Solutions
