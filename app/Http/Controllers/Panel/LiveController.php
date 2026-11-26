<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PasswordResetRequest;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Bale\BaleBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live dashboard data (polling every 10 seconds — ADR-023).
 *
 * Returns a small JSON document: audit events, login statistics, access
 * overview and — for the owner only — technical system health. Aggregations
 * are cached for a few seconds so 10s polling stays cheap even under load.
 */
class LiveController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = request()->user();
        assert($user instanceof User);

        $isOwner = $this->context->role()?->value === 'owner';
        $tenant = $this->context->tenant();

        $payload = Cache::remember(
            $this->cacheKey($tenant->id, $isOwner),
            5,
            fn (): array => $this->build($tenant->id, $isOwner),
        );

        // Polling is deliberately NOT audited — every 10s request must stay
        // cheap and must not flood the append-only audit trail.

        return response()->json($payload)->header('Cache-Control', 'no-store');
    }

    private function cacheKey(string $tenantId, bool $isOwner): string
    {
        return "panel:live:{$tenantId}:".($isOwner ? 'owner' : 'manager');
    }

    /** @return array<string, mixed> */
    private function build(string $tenantId, bool $isOwner): array
    {
        $events = AuditLog::query()
            ->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id')
                    ->where('action', 'like', 'auth.%');
            })
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'occurred_at' => $entry->occurred_at?->toISOString(),
                'ip_address' => $entry->ip_address,
                'actor' => $entry->actor?->name,
            ]);

        $logins14d = collect(range(13, 0))->map(function (int $daysAgo): array {
            $day = now()->startOfDay()->subDays($daysAgo);

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('m/d'),
                'logins' => AuditLog::query()
                    ->where('action', 'auth.login')
                    ->whereBetween('occurred_at', [$day, $day->copy()->endOfDay()])
                    ->count(),
                'failed' => AuditLog::query()
                    ->where('action', 'auth.login_failed')
                    ->whereBetween('occurred_at', [$day, $day->copy()->endOfDay()])
                    ->count(),
            ];
        });

        $members = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->select('role', 'status', DB::raw('count(*) as total'))
            ->groupBy('role', 'status')
            ->get();

        $activeMembers = $members->where('status', 'active')->sum('total');
        $byRole = [
            'owner' => (int) ($members->where('role', 'owner')->where('status', 'active')->sum('total')),
            'manager' => (int) ($members->where('role', 'manager')->where('status', 'active')->sum('total')),
            'operator' => (int) ($members->where('role', 'operator')->where('status', 'active')->sum('total')),
        ];

        $pendingResets = PasswordResetRequest::query()
            ->where('status', 'pending')
            ->count();

        $payload = [
            'generated_at' => now()->toISOString(),
            'events' => $events,
            'chart' => [
                'series' => $logins14d->values(),
            ],
            'stats' => [
                'logins_today' => AuditLog::query()
                    ->where('action', 'auth.login')
                    ->where('occurred_at', '>=', now()->startOfDay())
                    ->count(),
                'failed_today' => AuditLog::query()
                    ->where('action', 'auth.login_failed')
                    ->where('occurred_at', '>=', now()->startOfDay())
                    ->count(),
                'active_members' => (int) $activeMembers,
                'by_role' => $byRole,
                'pending_reset_requests' => $pendingResets,
            ],
        ];

        if ($isOwner) {
            $payload['system'] = $this->systemHealth();
        }

        return $payload;
    }

    /** Technical health block — owner only (senior manager = technical lead). */
    private function systemHealth(): array
    {
        $logTail = [];

        try {
            $logPath = storage_path('logs/laravel.log');

            if (is_file($logPath)) {
                $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $logTail = array_slice(is_array($lines) ? $lines : [], -30);
            }
        } catch (\Throwable) {
            $logTail = [];
        }

        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'db_driver' => DB::connection()->getDriverName(),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
            'bale_code_bot' => BaleBot::code()->configured(),
            'bale_admin_bot' => BaleBot::admin()->configured(),
            'owner_2fa' => (bool) config('ravand.bale.owner_2fa'),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'cache_ok' => Cache::add('panel:healthcheck', true, 30),
            'log_tail' => $logTail,
        ];
    }
}
