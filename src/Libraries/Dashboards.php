<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use Ephraitech\Platform\Config\PlatformConfig;
use Throwable;

/**
 * Dashboards ("boards") and their widgets, both declared by modules.
 *
 *   boards   'boards' => ['sales' => ['title' => 'Sales', 'icon' => 'bi-graph-up', 'roles' => ['sales', 'manager'], 'permission' => null]]
 *   widgets  'widgets' => [['cell' => 'Modules\Crm\Cells\Widgets::pipeline', 'dashboards' => ['sales'], 'permission' => 'crm.view', 'width' => 6]]
 *
 * Who gets which boards:
 *   - everyone: 'home' (My day);
 *   - the boards whose 'roles' include one of the person's roles;
 *   - the default board chosen under Settings → Dashboards;
 *   - boards returned by modules' 'landing' providers (e.g. "the board of
 *     my department");
 *   - holders of the super role: every board.
 * Boards with a 'permission' also need it, and a board's module must be on.
 * Boards follow the job; panels follow permissions: a finance officer who
 * may see one sales panel still does not get the Sales board.
 *
 * Landing: a landing provider's board, else the first board of the
 * person's roles, else the default, else My day, skipping any board that
 * would only show the panels every board has.
 */
final class Dashboards
{
    /**
     * @var array<int, list<string>>
     */
    private static array $rolesCache = [];

    /**
     * Every board of switched-on modules, for select boxes: key => title.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(static fn (array $b): string => $b['title'], self::all());
    }

    /**
     * Boards of switched-on modules, sorted.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $gate = service('moduleGate');

        return array_filter(service('moduleRegistry')->boards(), static fn (array $b): bool => $gate->enabled($b['module']));
    }

    /**
     * Boards the signed-in person may open.
     *
     * @return list<string>
     */
    public static function allowedBoards(): array
    {
        helper('platform');

        $all   = self::all();
        $roles = self::roles();
        $super = (string) PlatformConfig::auth()->superRole;

        if ($super !== '' && in_array($super, $roles, true)) {
            $keys = array_keys($all);
        } else {
            $keys = ['home', self::defaultBoard()];

            foreach ($all as $key => $board) {
                if (array_intersect($board['roles'], $roles) !== []) {
                    $keys[] = $key;
                }
            }

            foreach (self::fromProviders() as $key) {
                $keys[] = $key;
            }
        }

        return array_values(array_filter(
            array_keys($all),
            static fn (string $key): bool => in_array($key, $keys, true) && allowed($all[$key]['permission'])
        ));
    }

    /**
     * Widgets the person may see, grouped by board. Boards they may not
     * open come back empty.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function visibleWidgets(): array
    {
        helper('platform');

        $gate   = service('moduleGate');
        $open   = self::allowedBoards();
        $boards = array_fill_keys(array_keys(self::all()), []);

        foreach (service('moduleRegistry')->widgets() as $widget) {
            if (! $gate->enabled($widget['module']) || ! allowed($widget['permission'])) {
                continue;
            }

            $targets = in_array('*', $widget['dashboards'], true) ? array_keys($boards) : $widget['dashboards'];

            foreach (array_intersect($targets, $open) as $key) {
                $boards[$key][] = $widget;
            }
        }

        return $boards;
    }

    /**
     * Where the person lands.
     *
     * @param array<string, list<array<string, mixed>>> $boards from visibleWidgets()
     */
    public static function landing(array $boards): string
    {
        $allowed    = self::allowedBoards();
        $candidates = [...self::fromProviders(), self::forRoles(), self::defaultBoard(), 'home'];

        foreach ($candidates as $key) {
            if ($key !== null && in_array($key, $allowed, true) && ! self::onlyPersonal($boards, $key)) {
                return $key;
            }
        }

        return 'home';
    }

    /**
     * The first board (in board order) whose roles include one of the person's.
     */
    public static function forRoles(): ?string
    {
        $roles = self::roles();
        $super = (string) PlatformConfig::auth()->superRole;

        foreach (self::all() as $key => $board) {
            if (array_intersect($board['roles'], $roles) !== [] || ($super !== '' && in_array($super, $board['roles'], true) && in_array($super, $roles, true))) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether a board shows nothing beyond the panels every board has
     * (or nothing at all). 'home' always counts as having content.
     *
     * @param array<string, list<array<string, mixed>>> $boards
     */
    public static function onlyPersonal(array $boards, string $key): bool
    {
        if ($key === 'home') {
            return false;
        }

        foreach ($boards[$key] ?? [] as $widget) {
            if (! in_array('*', $widget['dashboards'], true)) {
                return false;
            }
        }

        return true;
    }

    public static function defaultBoard(): string
    {
        helper('platform');

        $default = (string) setting('dashboards.default', PlatformConfig::get()->defaultBoard);

        return isset(self::all()[$default]) ? $default : 'home';
    }

    public static function forget(): void
    {
        self::$rolesCache = [];
    }

    // ------------------------------------------------------------------

    /**
     * Boards named by landing providers of switched-on modules.
     *
     * @return list<string>
     */
    private static function fromProviders(): array
    {
        $gate = service('moduleGate');
        $all  = self::all();
        $out  = [];

        foreach (service('moduleRegistry')->landingProviders() as $provider) {
            if (! $gate->enabled($provider['module'])) {
                continue;
            }

            [$class, $method] = array_pad(explode('::', $provider['call'], 2), 2, '__invoke');

            try {
                $key = (new $class())->{$method}();
            } catch (Throwable $e) {
                log_message('error', 'Landing provider {provider} failed: {message}', ['provider' => $provider['call'], 'message' => $e->getMessage()]);

                continue;
            }

            if (is_string($key) && isset($all[$key])) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function roles(): array
    {
        $id = (int) user_id();

        if ($id <= 0) {
            return [];
        }

        $key = $id . '@' . service('tenancy')->key();

        if (! isset(self::$rolesCache[$key])) {
            try {
                self::$rolesCache[$key] = service('authAuthorizer')->rolesFor($id);
            } catch (Throwable) {
                self::$rolesCache[$key] = [];
            }
        }

        return self::$rolesCache[$key];
    }

    private function __construct()
    {
    }
}
