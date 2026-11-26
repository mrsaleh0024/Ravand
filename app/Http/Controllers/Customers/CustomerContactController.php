<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\PersianText;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customer contact rows (spec 5.2). Multiple numbers per customer are the
 * norm; shared numbers across customers never raise a unique error.
 */
class CustomerContactController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'contact_type' => ['required', 'string', Rule::in(Fields::CONTACT_TYPES)],
            'label' => ['nullable', 'string', 'max:60'],
            'value' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $normalized = match ($validated['contact_type']) {
            'mobile' => Phone::normalize($validated['value']),
            'landline' => PersianText::digitsOnly($validated['value']),
            default => PersianText::normalize($validated['value']),
        };

        if ($validated['contact_type'] === 'mobile' && $normalized === null) {
            return back()->withErrors(['value' => __('validation.phone_iranian')]);
        }

        if ($request->boolean('is_primary')) {
            $customer->contacts()->update(['is_primary' => false]);
        }

        $customer->contacts()->create([
            'tenant_id' => $this->context->tenant()->id,
            'contact_type' => $validated['contact_type'],
            'label' => $validated['label'] ?? null,
            'display_value' => $validated['value'],
            'normalized_value' => $normalized ?? $validated['value'],
            'country_code' => $validated['country_code'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.contact_added')]);
    }

    public function destroy(Request $request, Customer $customer, string $contact): RedirectResponse
    {
        $customer->contacts()->findOrFail($contact)->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.contact_removed')]);
    }
}
