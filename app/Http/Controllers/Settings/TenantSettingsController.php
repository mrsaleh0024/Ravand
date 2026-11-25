<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TenantSettingsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant (store) settings: name, currency, timezone, locale, calendar.
 *
 * - View: owner + manager (`tenant.settings.view`).
 * - Update: owner only (`tenant.settings.update`).
 * - Changing the tenant currency affects only future records; every future
 *   financial row snapshots its own currency_code (ADR-006).
 */
class TenantSettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): Response
    {
        $tenant = $this->context->tenant();

        return Inertia::render('settings/Tenant', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'default_currency' => $tenant->default_currency,
                'timezone' => $tenant->timezone,
                'locale' => $tenant->locale,
                'calendar' => $tenant->calendar,
            ],
            'options' => [
                'currencies' => config('ravand.allowed_currencies'),
                'timezones' => config('ravand.allowed_timezones'),
                'locales' => config('ravand.allowed_locales'),
                'calendars' => config('ravand.allowed_calendars'),
            ],
            'canUpdate' => $this->context->role()?->canUpdateSettings() ?? false,
        ]);
    }

    public function update(TenantSettingsUpdateRequest $request): RedirectResponse
    {
        $tenant = $this->context->tenant();

        $validated = $request->validated();

        $changedKeys = collect($validated)
            ->filter(fn ($value, string $key): bool => $tenant->{$key} !== $value)
            ->keys()
            ->values()
            ->all();

        $tenant->update($validated);

        // Audit stores only the changed keys, never raw before/after content.
        $this->audit->log(
            action: 'tenant.settings.updated',
            tenant: $tenant,
            actor: $request->user(),
            entityType: 'tenant',
            entityId: $tenant->id,
            metadata: ['changed' => $changedKeys],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant settings updated.')]);

        return back();
    }
}
