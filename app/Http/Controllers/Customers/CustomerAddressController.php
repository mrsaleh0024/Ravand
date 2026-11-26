<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Customer addresses (spec 5.2). */
class CustomerAddressController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $customer->addresses()->create([
            ...$validated,
            'tenant_id' => $this->context->tenant()->id,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.address_added')]);
    }

    public function destroy(Request $request, Customer $customer, string $address): RedirectResponse
    {
        $customer->addresses()->findOrFail($address)->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.address_removed')]);
    }
}
