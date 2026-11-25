<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * Central append-only audit trail writer (spec section 8.6).
 *
 * Rules:
 * - Never stores passwords, tokens, or before/after values of sensitive fields.
 * - Metadata must stay small and non-sensitive (e.g. list of changed keys).
 * - Rows are append-only: AuditLog blocks updates/deletes at the model layer.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?Tenant $tenant,
        ?User $actor,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
    ): AuditLog {
        /** @var AuditLog $entry */
        $entry = AuditLog::query()->create([
            'tenant_id' => $tenant?->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'occurred_at' => now(),
            'ip_address' => Request::ip(),
            'metadata' => $metadata,
        ]);

        return $entry;
    }
}
