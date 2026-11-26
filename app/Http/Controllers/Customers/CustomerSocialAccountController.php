<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Customer social accounts (spec 5.2). */
class CustomerSocialAccountController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', Rule::in(Fields::SOCIAL_PLATFORMS)],
            'username' => ['required', 'string', 'max:120'],
            'profile_url' => ['nullable', 'url', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->boolean('is_primary')) {
            $customer->socialAccounts()->update(['is_primary' => false]);
        }

        $customer->socialAccounts()->create([
            'tenant_id' => $this->context->tenant()->id,
            'platform' => $validated['platform'],
            'username' => $validated['username'],
            'normalized_handle' => PersianText::normalizeHandle($validated['username']),
            'profile_url' => $validated['profile_url'] ?? null,
            'display_name' => $validated['display_name'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.social_added')]);
    }

    public function destroy(Request $request, Customer $customer, string $socialAccount): RedirectResponse
    {
        $customer->socialAccounts()->findOrFail($socialAccount)->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.social_removed')]);
    }
}
