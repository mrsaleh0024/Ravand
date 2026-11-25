<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit trail view for the current tenant (owner + manager).
 * Server-side pagination; timestamps are exported as ISO 8601 UTC and the
 * UI renders them in the Persian calendar through the central formatter.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        $tenant = $this->context->tenant();

        $paginator = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Inertia 3 serializes paginators as plain arrays — meta and links
        // are handed over explicitly for the table UI.
        $entries = [
            'data' => collect($paginator->items())
                ->map(fn (AuditLog $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'entity_type' => $entry->entity_type,
                    'entity_id' => $entry->entity_id,
                    'occurred_at' => $entry->occurred_at?->toISOString(),
                    'ip_address' => $entry->ip_address,
                    'metadata' => $entry->metadata,
                    'actor' => $entry->actor?->only(['id', 'name']),
                ])
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => $paginator->linkCollection()->toArray(),
        ];

        return Inertia::render('settings/AuditLog', [
            'entries' => $entries,
        ]);
    }
}
