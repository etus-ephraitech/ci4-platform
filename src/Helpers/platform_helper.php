<?php

declare(strict_types=1);

/*
 * Platform helpers. Loaded by BaseModuleController, the layouts and the
 * platform's own classes; load them elsewhere with helper('platform').
 */

use Ephraitech\Platform\Config\PlatformConfig;
use Ephraitech\Platform\Libraries\Navigation;

if (! function_exists('setting')) {
    /**
     * A setting as "group.key", e.g. setting('company.name'), for the
     * current tenant (multi-tenant) or the install (single).
     */
    function setting(string $path, mixed $default = null): mixed
    {
        return service('platformSettings')->get($path, $default);
    }
}

if (! function_exists('audit')) {
    /**
     * Record an entry in the audit log.
     *
     * @param array<string, mixed> $changes
     */
    function audit(
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        array $changes = [],
    ): void {
        service('auditTrail')->record($module, $action, $description, $subjectType, $subjectId, $changes);
    }
}

if (! function_exists('next_number')) {
    /**
     * Allocate the next document number, e.g. next_number('invoice') => INV-2026-0001.
     */
    function next_number(string $sequence): string
    {
        return service('sequences')->next($sequence);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Whether a module is switched on for the current request or tenant.
     */
    function module_enabled(string $module, ?string $tenant = null): bool
    {
        return service('moduleGate')->enabled($module, $tenant);
    }
}

if (! function_exists('tenant')) {
    /**
     * The current tenant row, or a field of it; null in a single install
     * or with no tenant selected.
     */
    function tenant(?string $field = null): mixed
    {
        $tenant = service('tenancy')->current();

        return $field === null ? $tenant : ($tenant[$field] ?? null);
    }
}

if (! function_exists('tenant_key')) {
    /**
     * The value of tenant_id columns for the current request: the tenant's
     * slug, or '' in a single install or with no tenant selected.
     */
    function tenant_key(): string
    {
        return service('tenancy')->key();
    }
}

if (! function_exists('allowed')) {
    /**
     * Permission check for menus, quick actions, panels and extensions.
     * null or '' means everyone signed in; "a|b" means either is enough.
     */
    function allowed(?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        return can($permission);
    }
}

if (! function_exists('slot')) {
    /**
     * Render what other modules contribute to an extension point, one
     * after another. See slot_items() to lay them out yourself (tabs).
     *
     * @param array<string, mixed> $params passed to every contributed cell
     */
    function slot(string $point, array $params = []): string
    {
        return implode("\n", array_column(service('extensions')->render($point, $params), 'html'));
    }
}

if (! function_exists('slot_items')) {
    /**
     * Contributions to an extension point, rendered, with title and icon.
     *
     * @param array<string, mixed> $params
     *
     * @return list<array{title: string, icon: string, key: string, module: string, html: string}>
     */
    function slot_items(string $point, array $params = []): array
    {
        return service('extensions')->render($point, $params);
    }
}

if (! function_exists('extension_data')) {
    /**
     * Data other modules contribute to an extension point (for API
     * responses), keyed by each contribution's 'key'.
     *
     * @return array<string, mixed>
     */
    function extension_data(string $point, mixed ...$args): array
    {
        return service('extensions')->data($point, ...$args);
    }
}

if (! function_exists('platform_menu')) {
    /**
     * Sidebar entries the signed-in person may see, grouped by section.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    function platform_menu(): array
    {
        return Navigation::menu();
    }
}

if (! function_exists('platform_quick_actions')) {
    /**
     * "New" menu entries the signed-in person may use.
     *
     * @return list<array<string, mixed>>
     */
    function platform_quick_actions(): array
    {
        return Navigation::quickActions();
    }
}

if (! function_exists('platform_name')) {
    /**
     * The name to show: the workspace (multi-tenant), else the company
     * name from Settings, else Config\Platform::$appName.
     */
    function platform_name(): string
    {
        $tenant = service('tenancy')->current();

        if ($tenant !== null) {
            return (string) $tenant['name'];
        }

        $company = trim((string) setting('company.name', ''));

        return $company !== '' ? $company : PlatformConfig::get()->appName;
    }
}

if (! function_exists('platform_asset')) {
    /**
     * URL of a file published by platform:publish (CSS, JS, default logos).
     */
    function platform_asset(string $file): string
    {
        $path    = trim(PlatformConfig::get()->assetPath, '/') . '/' . ltrim($file, '/');
        $version = is_file(FCPATH . $path) ? '?v=' . substr(md5((string) filemtime(FCPATH . $path)), 0, 8) : '';

        return base_url($path) . $version;
    }
}

if (! function_exists('brand_asset')) {
    /**
     * URL of a logo: the one set in Config\Platform::$brand, else the
     * platform default. Kinds: logo, logoOnDark, mark, markOnDark.
     */
    function brand_asset(string $kind): string
    {
        $brand    = PlatformConfig::get()->brand;
        $defaults = [
            'logo'       => 'brand/logo.svg',
            'logoOnDark' => 'brand/logo-on-dark.svg',
            'mark'       => 'brand/mark.svg',
            'markOnDark' => 'brand/mark-on-dark.svg',
        ];

        if (! empty($brand[$kind])) {
            return base_url(ltrim((string) $brand[$kind], '/'));
        }

        return platform_asset($defaults[$kind] ?? $defaults['mark']);
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount in the configured currency (or the one given).
     * Currencies without minor units in daily use (UGX, KES, ...) show no decimals.
     */
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency ??= (string) setting('finance.currency', 'UGX');

        return $currency . ' ' . number_format((float) $amount, money_decimals($currency));
    }
}

if (! function_exists('money_decimals')) {
    /**
     * Decimal places shown for a currency.
     */
    function money_decimals(string $currency): int
    {
        return in_array($currency, ['UGX', 'KES', 'TZS', 'RWF', 'BIF', 'JPY', 'KRW'], true) ? 0 : 2;
    }
}

if (! function_exists('qty_format')) {
    /**
     * 2.00 => "2", 1.50 => "1.5", 1250 => "1,250"
     */
    function qty_format(float|int|string $quantity): string
    {
        $formatted = number_format((float) $quantity, 2, '.', ',');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}

if (! function_exists('display_name')) {
    /**
     * A person's name for display: first and last name, else email, else "User #id".
     *
     * @param array<string, mixed>|object|null $user
     */
    function display_name(array|object|null $user): string
    {
        if ($user === null) {
            return 'System';
        }

        $get = static fn (string $field): ?string => is_array($user)
            ? (isset($user[$field]) ? (string) $user[$field] : null)
            : (isset($user->{$field}) ? (string) $user->{$field} : null);

        $name = trim(($get('first_name') ?? '') . ' ' . ($get('last_name') ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (is_object($user) && method_exists($user, 'getEmail') && $user->getEmail() !== null) {
            return (string) $user->getEmail();
        }

        if (is_array($user) && ! empty($user['email'])) {
            return (string) $user['email'];
        }

        $id = $get('id') ?? $get('user_id');

        return $id === null ? 'System' : 'User #' . $id;
    }
}

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($words[0] ?? '?', 0, 1);
        $last  = count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}

if (! function_exists('menu_is_active')) {
    /**
     * Whether a menu URL is the current page or one of its children.
     */
    function menu_is_active(string $url): bool
    {
        $current = '/' . trim(service('request')->getUri()->getPath(), '/');
        $target  = '/' . trim((string) parse_url($url, PHP_URL_PATH), '/');
        $base    = '/' . trim((string) parse_url(base_url(), PHP_URL_PATH), '/');

        if ($base !== '/' && str_starts_with($target, $base)) {
            $target = '/' . trim(substr($target, strlen($base)), '/');
        }

        if ($base !== '/' && str_starts_with($current, $base)) {
            $current = '/' . trim(substr($current, strlen($base)), '/');
        }

        if ($target === '/') {
            return $current === '/';
        }

        return $current === $target || str_starts_with($current, $target . '/');
    }
}
