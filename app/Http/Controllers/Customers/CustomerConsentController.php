<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Communication consents (spec 5.2 / 8.5). Recording a phone number never
 * implies marketing permission — statuses are explicit.
 */
class CustomerConsentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::in(Fields::CONSENT_CHANNELS)],
            'purpose' => ['required', 'string', Rule::in(Fields::CONSENT_PURPOSES)],
            'status' => ['required', 'string', Rule::in(Fields::CONSENT_STATUSES)],
            'capture_method' => ['nullable', 'string', Rule::in(Fields::CONSENT_CAPTURE_METHODS)],
            'evidence_note' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->consents()->create([
            'tenant_id' => $this->context->tenant()->id,
            'channel' => $validated['channel'],
            'purpose' => $validated['purpose'],
            'status' => $validated['status'],
            'capture_method' => $validated['capture_method'] ?? 'manual',
            'evidence_note' => $validated['evidence_note'] ?? null,
            'captured_at' => now(),
            'captured_by_user_id' => $request->user()?->id,
        ]);

        $this->audit->log(
            action: 'customer.consent_recorded',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'customer',
            entityId: $customer->id,
            metadata: [
                'channel' => $validated['channel'],
                'purpose' => $validated['purpose'],
                'status' => $validated['status'],
            ],
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.consent_added')]);
    }
}
