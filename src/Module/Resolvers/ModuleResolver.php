<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module\Resolvers;

/**
 * Decides whether an installed module is switched on.
 *
 * Resolvers are listed in Config\Platform::$resolvers and asked in order
 * for each module; the first that returns true or false decides. Return
 * null to let the next resolver decide.
 *
 * Always-on modules never reach a resolver, and a module whose
 * requirements are off is switched off afterwards whatever was decided.
 */
interface ModuleResolver
{
    /**
     * @param array<string, mixed> $manifest the module's normalised manifest
     */
    public function decide(string $module, array $manifest, ResolverContext $context): ?bool;

    /**
     * A few words shown as the reason in modules:list and the admin screens,
     * e.g. "plan", "switched on by an admin".
     */
    public function label(): string;
}
