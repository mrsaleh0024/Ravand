<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Customer preferences (spec 5.2): structured like/dislike rows. */
class CustomerPreferenceController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'preference_category' => ['required', 'string', Rule::in(Fields::PREFERENCE_CATEGORIES)],
            'value_code' => ['required', 'string', 'max:80'],
            'value_label' => ['required', 'string', 'max:120'],
            'preference_level' => ['nullable', 'string', Rule::in(Fields::PREFERENCE_LEVELS)],
            'source' => ['nullable', 'string', Rule::in(Fields::PREFERENCE_SOURCES)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->preferences()->create([
            'tenant_id' => $this->context->tenant()->id,
            'preference_category' => $validated['preference_category'],
            'value_code' => $validated['value_code'],
            'value_label' => $validated['value_label'],
            'preference_level' => $validated['preference_level'] ?? 'like',
            'source' => $validated['source'] ?? 'customer_stated',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.preference_added')]);
    }

    public function destroy(Request $request, Customer $customer, string $preference): RedirectResponse
    {
        $customer->preferences()->findOrFail($preference)->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.preference_removed')]);
    }
}
