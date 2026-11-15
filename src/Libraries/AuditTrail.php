<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Libraries;

use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use Ephraitech\Platform\Models\AuditLogModel;
use Ephraitech\Platform\Tenancy\Tenancy;
use Throwable;

/**
 * Records who did what, to which record, and what changed, in the
 * current tenant's audit log.
 *
 *     audit('Crm', 'created', 'Created client Babu Couture', 'client', $id);
 *     audit('Finance', 'updated', 'Changed invoice INV-2026-0004', 'invoice', $id, $changes);
 *
 * Recording never throws: a failed audit write is logged and the person's
 * action carries on.
 */
class AuditTrail
{
    /**
     * Field names whose values are never written to the audit log.
     */
    private const REDACTED = ['password', 'password_hash', 'secret', 'token', 'api_key', 'pin', 'otp'];

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Tenancy $tenancy,
    ) {
    }

    /**
     * @param array<string, mixed> $changes field => ['from' => .., 'to' => ..]
     */
    public function record(
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        array $changes = [],
    ): void {
        try {
            model(AuditLogModel::class, false)->insert([
                'tenant_id'    => $this->tenancy->key(),
                'user_id'      => $this->actorId(),
                'module'       => mb_substr($module, 0, 50),
                'action'       => mb_substr($action, 0, 50),
                'subject_type' => $subjectType === null ? null : mb_substr($subjectType, 0, 80),
                'subject_id'   => $subjectId === null ? null : mb_substr((string) $subjectId, 0, 64),
                'description'  => mb_substr($description, 0, 255),
                'changes'      => $changes === [] ? null : json_encode($this->redact($changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address'   => $this->request instanceof CLIRequest ? null : $this->request->getIPAddress(),
                'user_agent'   => $this->request instanceof IncomingRequest
                    ? mb_substr((string) $this->request->getUserAgent(), 0, 255)
                    : 'cli',
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Audit trail write failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Field-level differences between two versions of a record.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $ignore
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function diff(array $before, array $after, array $ignore = ['updated_at', 'created_at']): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            if (in_array($field, $ignore, true)) {
                continue;
            }

            $old = $before[$field] ?? null;

            if (! $this->same($old, $value)) {
                $changes[$field] = ['from' => $old, 'to' => $value];
            }
        }

        return $changes;
    }

    // ------------------------------------------------------------------

    /**
     * Equal for audit purposes: '' and NULL match, and numbers compare by
     * value so "1500000.00" from the database equals "1500000" from a form.
     */
    private function same(mixed $old, mixed $new): bool
    {
        $old = $old === '' ? null : $old;
        $new = $new === '' ? null : $new;

        if ($old === null || $new === null) {
            return $old === $new;
        }

        return $this->normalise((string) $old) === $this->normalise((string) $new);
    }

    /**
     * "1500000.00" => "1500000"; leaves phone numbers like "0772..." untouched.
     */
    private function normalise(string $value): string
    {
        if (is_numeric($value) && str_contains($value, '.')) {
            return rtrim(rtrim($value, '0'), '.');
        }

        return $value;
    }

    private function actorId(): ?int
    {
        try {
            return auth()->id();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function redact(array $changes): array
    {
        foreach ($changes as $field => $change) {
            if (in_array(strtolower((string) $field), self::REDACTED, true)) {
                $changes[$field] = ['from' => '[hidden]', 'to' => '[hidden]'];
            }
        }

        return $changes;
    }
}
