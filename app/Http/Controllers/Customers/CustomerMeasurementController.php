<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Customer measurements (spec 5.2): size history as separate rows. */
class CustomerMeasurementController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'measurement_code' => ['required', 'string', 'max:60'],
            'value_text' => ['nullable', 'string', 'max:120'],
            'value_number' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', Rule::in(Fields::MEASUREMENT_SOURCES)],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->measurements()->create([
            'tenant_id' => $this->context->tenant()->id,
            'measurement_code' => $validated['measurement_code'],
            'value_text' => $validated['value_text'] ?? null,
            'value_number' => $validated['value_number'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'source' => $validated['source'] ?? 'staff_measured',
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.measurement_added')]);
    }

    public function destroy(Request $request, Customer $customer, string $measurement): RedirectResponse
    {
        $customer->measurements()->findOrFail($measurement)->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.measurement_removed')]);
    }
}
