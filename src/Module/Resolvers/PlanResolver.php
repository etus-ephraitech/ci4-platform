<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module\Resolvers;

/**
 * Multi-tenant: a module is on when the tenant's plan includes it
 * (Config\Platform::$plans). A tenant on a plan that no longer exists
 * gets nothing beyond the always-on modules, and the problem is logged.
 */
final class PlanResolver implements ModuleResolver
{
    /**
     * @var array<string, true>
     */
    private static array $warned = [];

    public function decide(string $module, array $manifest, ResolverContext $context): ?bool
    {
        if ($context->tenant === null) {
            return null;
        }

        $plan = (string) ($context->tenant['plan'] ?? '');

        if (! isset($context->config->plans[$plan])) {
            if (! isset(self::$warned[$plan])) {
                self::$warned[$plan] = true;
                log_message('error', 'Platform: tenant {tenant} is on plan "{plan}", which is not in Config\Platform::$plans. Only always-on modules are available to it.', [
                    'tenant' => $context->tenantKey,
                    'plan'   => $plan,
                ]);
            }

            return false;
        }

        $modules = $context->planModules();

        return in_array('*', $modules, true) || in_array($module, $modules, true);
    }

    public function label(): string
    {
        return 'plan';
    }
}
