<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module\Resolvers;

use Ephraitech\Platform\Config\Platform;

/**
 * What a resolver may base its decision on, computed once per tenant.
 */
final class ResolverContext
{
    /**
     * @param string                              $tenantKey '' in a single install or with no tenant
     * @param array<string, mixed>|null           $tenant    the tenant row (multi-tenant only)
     * @param array<string, array<string, mixed>> $states    current switches of this tenant, by module
     */
    public function __construct(
        public readonly string $tenantKey,
        public readonly ?array $tenant,
        public readonly bool $multiTenant,
        public readonly array $states,
        public readonly Platform $config,
    ) {
    }

    /**
     * Modules included in the tenant's plan; ['*'] for all.
     *
     * @return list<string>
     */
    public function planModules(): array
    {
        $plan = (string) ($this->tenant['plan'] ?? '');

        return array_values(array_map('strval', $this->config->plans[$plan]['modules'] ?? []));
    }
}
