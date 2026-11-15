<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module\Resolvers;

/**
 * Switches people set: the admin's on/off in a single install, or a
 * tenant's add-ons and exclusions (optionally until a date) on top of its
 * plan. Stored in platform_module_states; expired switches are ignored.
 */
final class StoredStateResolver implements ModuleResolver
{
    public function decide(string $module, array $manifest, ResolverContext $context): ?bool
    {
        if (! isset($context->states[$module])) {
            return null;
        }

        return (bool) $context->states[$module]['enabled'];
    }

    public function label(): string
    {
        return 'switched by an admin';
    }
}
