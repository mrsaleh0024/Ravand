<?php

namespace App\Domain\Customers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSocialAccount;
use App\Support\PersianText;
use Illuminate\Support\Collection;

/**
 * Non-blocking duplicate detection (spec 7.2).
 *
 * Sharing a phone inside a family is legitimate, so matches are surfaced
 * as a WARNING before/while creating a customer — never as a validation
 * error. Match signals, ordered by strength:
 *   1. identical canonical phone number
 *   2. identical normalized social handle
 *   3. identical normalized display name
 */
final class DuplicateDetector
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array{
     *     phone?: string|null,
     *     social_handle?: string|null,
     *     display_name?: string|null,
     * }  $candidate
     * @return Collection<int, array<string, mixed>> matching customers with reasons
     */
    public function matches(array $candidate, ?string $ignoreCustomerId = null): Collection
    {
        $tenantId = $this->context->tenant()->id;
        $reasons = [];

        $query = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_status', '!=', CustomerStatus::Archived->value)
            ->with('primaryContact');

        if ($ignoreCustomerId !== null) {
            $query->whereKeyNot($ignoreCustomerId);
        }

        $phoneDigits = PersianText::digitsOnly($candidate['phone'] ?? null);
        $handle = PersianText::normalizeHandle($candidate['social_handle'] ?? null);
        $name = PersianText::normalize($candidate['display_name'] ?? null);

        $customerIds = collect();

        if ($phoneDigits !== '') {
            $ids = CustomerContact::query()
                ->where('tenant_id', $tenantId)
                ->where('normalized_value', 'like', "%{$phoneDigits}%")
                ->pluck('customer_id');
            $customerIds = $customerIds->merge($ids);
        }

        if ($handle !== '') {
            $ids = CustomerSocialAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('normalized_handle', $handle)
                ->pluck('customer_id');
            $customerIds = $customerIds->merge($ids);
        }

        if ($customerIds->isNotEmpty()) {
            $query->whereKey($customerIds->unique()->values()->all());
        } elseif ($name !== '') {
            // Name-only match: weaker signal, exact normalized equality only.
            $query->where('normalized_name', $name);
        } else {
            return collect();
        }

        return $query->limit(10)->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'customer_number' => $customer->customer_number,
                'display_name' => $customer->display_name,
                'phone' => $customer->primaryContact?->display_value,
                'status' => $customer->customer_status->value,
            ]);
    }
}
