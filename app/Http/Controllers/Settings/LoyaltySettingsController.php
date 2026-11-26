<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Sales\Money;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\LoyaltySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loyalty club settings (phase 04, spec section 10 / phase 04).
 *
 * - View + update: owner/manager (`loyalty.manage` gate on the routes).
 * - The rule is intentionally simple: every `amount_step` of a completed
 *   order's total grants `points_per_step` points.
 * - Enabling/disabling only affects FUTURE completions; the ledger is never
 *   rewritten retroactively.
 */
class LoyaltySettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function edit(): Response
    {
        $tenant = $this->context->tenant();
        $settings = $this->loyalty->settingsFor($tenant);

        return Inertia::render('settings/Loyalty', [
            'settings' => [
                'points_enabled' => $settings->points_enabled,
                'amount_step' => $settings->amount_step,
                'points_per_step' => $settings->points_per_step,
            ],
            'currency' => $tenant->default_currency,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Persian/Arabic digits fold to ASCII before validation (ADR-025).
        $request->merge([
            'amount_step' => Money::normalize($request->input('amount_step')),
        ]);

        $rules = [
            'points_enabled' => ['required', 'boolean'],
            // Amount rule per ADR-025: regex, never numeric+max (length ≠ value).
            'amount_step' => ['required', 'regex:/^\d+$/'],
            'points_per_step' => ['required', 'integer', 'min:1', 'max:100000'],
        ];

        // A step of zero would divide by zero — reject it only when enabling.
        if ($request->boolean('points_enabled')) {
            $rules['amount_step'][] = 'not_in:0';
        }

        $validated = $request->validate($rules);

        $tenant = $this->context->tenant();
        $settings = $this->loyalty->settingsFor($tenant);

        $changes = [
            'points_enabled' => $request->boolean('points_enabled'),
            'amount_step' => $validated['amount_step'],
            'points_per_step' => (int) $validated['points_per_step'],
        ];

        // Which fields actually changed? (Audit stores keys only — spec 8.6.)
        $dirty = array_keys(array_filter(
            $changes,
            fn ($value, string $key): bool => $settings->{$key} !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        /** @var LoyaltySetting $settings */
        $settings->fill($changes)->save();

        if ($dirty !== []) {
            $this->audit->log(
                action: 'loyalty.settings.updated',
                tenant: $tenant,
                actor: $request->user(),
                entityType: 'tenant',
                entityId: $tenant->id,
                metadata: ['changed' => $dirty],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('loyalty.updated')]);

        return back();
    }
}
