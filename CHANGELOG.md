# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-15

First release, extracted from Ephraitech OS's Core and Users modules.

### Added
- Modules: `module.php` manifests (validated), discovery from built-ins,
  `modules/` and Composer packages (`extra.ephraitech-platform.modules`),
  install-level selection (`.disabled`, `platform.modulesOff`,
  `platform.modulesOnly`), `ModuleAutoloader`, `ModuleRegistry` with
  dependency order and dependents.
- The module gate (`service('moduleGate')`, `module_enabled()`): one answer
  per request/tenant for menus, routes, permissions, settings, dashboards,
  search, extensions and the API; pluggable resolvers (admin switches,
  manifest defaults, plans, per-tenant add-ons with expiry); requirement
  closure; caching.
- ci4-auth bridge: manifest permissions and grants via
  `Config/AuthPermissions` discovery; `ModulePermissionGate` for ci4-auth's
  permission gate.
- Tenancy: `platform_tenants`, `service('tenancy')` (`current`, `key`,
  `enter`, `runAs`, `create`, `update`, `tenantsFor`, `urlFor`),
  `TenantResolver` (domain, header, token, session), `TenantHosts`,
  `TenantScoped` models, `TenantColumn` for migrations, plans, statuses and
  trials, workspace picker, the platform owner's Workspaces screens.
- The `platform` global filter and the `module:` filter.
- Extension points (`extends`, `slot()`, `slot_items()`, `extension_data()`).
- API base controller, platform API (`context`, `modules`, `search`).
- Tenant-aware settings (tenant and global scope), sequences (MySQL,
  PostgreSQL, SQLite) and audit trail; global search; manifest-driven
  dashboards with landing providers.
- Users module on ci4-auth: people, roles (on `RoleManager`), workspace
  membership rules.
- Layouts, branded sign-in pages, responsive tables, `DataTable`.
- Commands: `make:module`, `platform:migrate`, `platform:publish`,
  `modules:list`, `modules:on`, `modules:off`, `modules:reset`,
  `tenants:create`, `tenants:update`, `tenants:list`, `deploy:check`.
- cPanel deploy kit.
