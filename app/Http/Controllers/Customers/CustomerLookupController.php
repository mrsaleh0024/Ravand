<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Tenancy\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight JSON customer picker for the manual order form (phase 03).
 *
 * Follows the phase-02 privacy rules (ADR-024): exact address lines are
 * only returned to owner/manager; operators get the address titles and
 * cities and type the address fresh while the customer dictates it.
 */
class CustomerLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $isPrivileged = in_array($this->context->role(), [TenantRole::Owner, TenantRole::Manager], true);

        $query = Customer::query()
            ->where('customer_status', '!=', 'archived')
            ->with(['contacts' => fn ($q) => $q->orderByDesc('is_primary'), 'addresses'])
            ->limit(8);

        if ($term !== '') {
            $digits = PersianText::digitsOnly($term);

            if ($digits !== '') {
                $query->whereHas(
                    'contacts',
                    fn ($q) => $q->where('normalized_value', 'like', "%{$digits}%"),
                );
            } else {
                $normalized = PersianText::normalize($term);
                $query->where('normalized_name', 'like', "%{$normalized}%");
            }
        }

        return response()->json([
            'matches' => $query->orderByDesc('created_at')->get()
                ->map(fn (Customer $customer): array => $this->row($customer, $isPrivileged))
                ->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Customer $customer, bool $isPrivileged): array
    {
        $phone = $customer->contacts->first(fn ($c): bool => $c->contact_type === 'mobile')
            ?? $customer->contacts->first();

        return [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'display_name' => $customer->display_name,
            'phone' => $phone?->display_value,
            'city' => $customer->addresses->first(fn ($a): bool => $a->is_default)?->city
                ?? $customer->addresses->first()?->city,
            'addresses' => $customer->addresses->map(fn ($a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'city' => $a->city,
                'province' => $a->province,
                'address_line' => $isPrivileged ? $a->address_line : null,
                'postal_code' => $isPrivileged ? $a->postal_code : null,
                'recipient_name' => $a->recipient_name,
                'is_default' => $a->is_default,
            ])->all(),
        ];
    }
}
