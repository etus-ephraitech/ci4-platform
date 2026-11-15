<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Tenancy;

/**
 * What TenantResolver found: a tenant, nothing, or an error to show.
 */
final class TenantResolution
{
    /**
     * @param array<string, mixed>|null $tenant
     */
    private function __construct(
        public readonly ?array $tenant,
        public readonly ?string $source,
        public readonly ?string $error,
        public readonly ?string $message,
    ) {
    }

    /**
     * @param array<string, mixed> $tenant
     */
    public static function found(array $tenant, string $source): self
    {
        return new self($tenant, $source, null, null);
    }

    public static function none(): self
    {
        return new self(null, null, null, null);
    }

    public static function error(string $code, string $message, string $source): self
    {
        return new self(null, $source, $code, $message);
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
