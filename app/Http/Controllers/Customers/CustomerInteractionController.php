<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Customer interactions (spec 5.2): seller-typed summaries only. */
class CustomerInteractionController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'interaction_type' => ['required', 'string', Rule::in(Fields::INTERACTION_TYPES)],
            'channel' => ['required', 'string', Rule::in(Fields::INTERACTION_CHANNELS)],
            'subject' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:4000'],
            'outcome' => ['nullable', 'string', Rule::in(Fields::INTERACTION_OUTCOMES)],
            'occurred_at' => ['nullable', 'date'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:occurred_at'],
        ]);

        $customer->interactions()->create([
            'tenant_id' => $this->context->tenant()->id,
            'interaction_type' => $validated['interaction_type'],
            'channel' => $validated['channel'],
            'subject' => $validated['subject'] ?? null,
            'summary' => $validated['summary'],
            'outcome' => $validated['outcome'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? now(),
            'follow_up_at' => $validated['follow_up_at'] ?? null,
            'follow_up_status' => isset($validated['follow_up_at']) ? 'pending' : null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.interaction_added')]);
    }
}
