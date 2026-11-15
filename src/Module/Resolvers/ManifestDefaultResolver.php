<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module\Resolvers;

/**
 * Single install: a module nobody has switched is on unless its
 * manifest says 'default' => false (an optional extra the client has to
 * ask for).
 */
final class ManifestDefaultResolver implements ModuleResolver
{
    public function decide(string $module, array $manifest, ResolverContext $context): ?bool
    {
        return (bool) $manifest['default'];
    }

    public function label(): string
    {
        return 'default';
    }
}
