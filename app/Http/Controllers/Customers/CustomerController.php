<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Customers\CustomerNumberGenerator;
use App\Domain\Customers\CustomerStatus;
use App\Domain\Customers\DuplicateDetector;
use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Tag;
use App\Models\User;
use App\Support\PersianText;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer CRM (phase 02, spec section 5.2 / 6.5 / 7).
 *
 * All queries are tenant-scoped through the Customer global scope plus the
 * tenant-scoped route binding; nothing trusts a client-sent tenant id.
 */
class CustomerController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly CustomerNumberGenerator $numbers,
        private readonly DuplicateDetector $duplicates,
        private readonly LoyaltyService $loyalty,
    ) {}

    /** Customer list: server-side pagination, search and filters (spec 7.1). */
    public function index(Request $request): Response
    {
        $tenant = $this->context->tenant();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $tagSlug = (string) $request->query('tag', '');
        $channel = (string) $request->query('channel', '');

        $query = Customer::query()
            ->with(['primaryContact', 'tags'])
            ->withAggregate('defaultAddress', 'city');

        if ($status !== '' && in_array($status, array_column(CustomerStatus::cases(), 'value'), true)) {
            $query->where('customer_status', $status);
        } else {
            // Archived customers stay out of the default view (spec 4.5).
            $query->where('customer_status', '!=', CustomerStatus::Archived->value);
        }

        if ($channel !== '') {
            $query->where('acquisition_channel', $channel);
        }

        if ($tagSlug !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $tagSlug));
        }

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $page = $query
            ->orderByDesc('created_at')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('customers/Index', [
            'customers' => $page->through(fn (Customer $customer): array => $this->listRow($customer)),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'tag' => $tagSlug,
                'channel' => $channel,
            ],
            'tags' => $this->tagOptions(),
            'duplicates' => $request->session()->get('duplicates', []),
            'draft' => $request->session()->get('draft', []),
        ]);
    }

    /** Non-blocking duplicate check for the create/edit form (spec 7.2). */
    public function duplicates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'social_handle' => ['nullable', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'ignore' => ['nullable', 'string', 'max:26'],
        ]);

        return response()->json([
            'matches' => $this->duplicates->matches($validated, $validated['ignore'] ?? null)->all(),
        ]);
    }

    /** Create a customer from the short form (spec 6.5). */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'social_platform' => ['nullable', 'string', Rule::in(Fields::SOCIAL_PLATFORMS)],
            'social_handle' => ['nullable', 'string', 'max:120'],
            'acquisition_channel' => ['nullable', 'string', Rule::in(Fields::ACQUISITION_CHANNELS)],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:2000'],
            'force' => ['nullable', 'boolean'],
        ]);

        $phone = Phone::normalize($validated['phone'] ?? null);
        abort_if(($validated['phone'] ?? '') !== '' && $phone === null, 422, __('validation.phone_iranian'));

        // Duplicate warning is NON-blocking: it requires an explicit confirm.
        $candidate = [
            'phone' => $phone,
            'social_handle' => $validated['social_handle'] ?? null,
            'display_name' => $validated['display_name'],
        ];

        $matches = $this->duplicates->matches($candidate);

        if ($matches->isNotEmpty() && ! $request->boolean('force')) {
            // Deterministic redirect (not back(): no referer in some clients)
            // carrying the matches and the draft so the dialog can re-open.
            return redirect()
                ->route('customers.index')
                ->with('duplicates', $matches->all())
                ->with('draft', $validated);
        }

        $tenant = $this->context->tenant();
        $channel = $validated['acquisition_channel'] ?? 'unknown';

        $customer = DB::transaction(function () use ($validated, $phone, $tenant, $request, $channel): Customer {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->id,
                'customer_number' => $this->numbers->next(),
                'display_name' => $validated['display_name'],
                'normalized_name' => PersianText::normalize($validated['display_name']),
                'acquisition_channel' => $channel,
                'created_by_user_id' => $request->user()?->id,
            ]);

            if ($phone !== null) {
                $customer->contacts()->create([
                    'tenant_id' => $tenant->id,
                    'contact_type' => 'mobile',
                    'display_value' => $phone,
                    'normalized_value' => $phone,
                    'is_primary' => true,
                ]);
            }

            if (($validated['social_handle'] ?? '') !== '') {
                $customer->socialAccounts()->create([
                    'tenant_id' => $tenant->id,
                    'platform' => $validated['social_platform'] ?? 'instagram',
                    'username' => $validated['social_handle'],
                    'normalized_handle' => PersianText::normalizeHandle($validated['social_handle']),
                    'is_primary' => true,
                ]);
            }

            if (($validated['city'] ?? '') !== '') {
                $customer->addresses()->create([
                    'tenant_id' => $tenant->id,
                    'title' => 'main',
                    'province' => $validated['province'] ?? null,
                    'city' => $validated['city'],
                    'is_default' => true,
                ]);
            }

            if (($validated['note'] ?? '') !== '') {
                $customer->notes()->create([
                    'tenant_id' => $tenant->id,
                    'body' => $validated['note'],
                    'visibility' => 'team',
                    'created_by_user_id' => $request->user()?->id,
                ]);
            }

            return $customer;
        });

        $this->audit->log(
            action: 'customer.created',
            tenant: $tenant,
            actor: $request->user(),
            entityType: 'customer',
            entityId: $customer->id,
            metadata: [
                'customer_number' => $customer->customer_number,
                'acquisition_channel' => $channel,
                'duplicate_confirmed' => $matches->isNotEmpty(),
            ],
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('toast', ['type' => 'success', 'message' => __('customers.created')]);
    }

    /** 360-degree customer profile (spec 6.5). */
    public function show(Request $request, Customer $customer): Response
    {
        $customer->load([
            'contacts', 'socialAccounts', 'addresses', 'consents',
            'preferences', 'measurements', 'tags',
        ]);

        $isPrivileged = in_array($this->context->role(), [
            TenantRole::Owner,
            TenantRole::Manager,
        ], true);
        $isSelfAuthor = fn (?string $authorId): bool => $authorId !== null && $authorId === $request->user()?->id;

        $notes = $customer->notes()
            ->with('author:id,name')
            ->get()
            ->filter(fn (CustomerNote $note): bool => $note->visibility === 'team' || $isPrivileged || $isSelfAuthor($note->created_by_user_id))
            ->values();

        $interactions = $customer->interactions()
            ->with('author:id,name')
            ->limit(50)
            ->get();

        // Purchase history tab (phase 03, spec 5.5): latest 10 orders plus
        // the derived counters — never stored by hand (spec 5.7).
        $orders = $customer->orders()->with('items')->limit(10)->get();
        $ordersTotal = $customer->orders()->count();

        // Loyalty tab (phase 04, spec section 10): balance/summary plus the
        // latest ledger rows — all derived from the append-only ledger.
        $loyaltySummary = $this->loyalty->summaryFor($customer);
        $loyaltyHistory = $this->loyalty->historyFor($customer)->map(fn ($transaction): array => [
            'id' => $transaction->id,
            'type' => $transaction->transaction_type->value,
            'points' => $transaction->points,
            'amount_basis' => $transaction->amount_basis,
            'reason' => $transaction->reason,
            'order_number' => $transaction->order?->order_number,
            'actor_name' => $transaction->creator?->name,
            'created_at' => $transaction->created_at?->toISOString(),
        ])->all();

        return Inertia::render('customers/Show', [
            'customer' => $this->profilePayload($customer, $isPrivileged),
            'notes' => $notes->map(fn (CustomerNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'visibility' => $note->visibility,
                'author' => $note->author?->name,
                'created_at' => $note->created_at?->toISOString(),
            ])->all(),
            'interactions' => $interactions->map($this->interactionRow(...))->all(),
            'orders' => $orders->map(function ($order): array {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'total_amount' => $order->total_amount,
                    'currency_code' => $order->currency_code,
                    'items_count' => $order->items->count(),
                    'placed_at' => $order->placed_at?->toISOString(),
                ];
            })->all(),
            'ordersTotal' => $ordersTotal,
            'loyalty' => $loyaltySummary + ['transactions' => $loyaltyHistory],
            'canAdjustLoyalty' => $request->user()?->can('loyalty.manage') ?? false,
            'isPrivileged' => $isPrivileged,
            'tags' => $this->tagOptions(),
            'duplicates' => $request->session()->get('duplicates', []),
        ]);
    }

    /** Update base information (audited with the changed field list). */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'string', Rule::in(Fields::GENDERS)],
            'birth_date' => ['nullable', 'date'],
            'customer_status' => ['required', 'string', Rule::in(array_column(CustomerStatus::cases(), 'value'))],
            'acquisition_channel' => ['required', 'string', Rule::in(Fields::ACQUISITION_CHANNELS)],
            'acquisition_note' => ['nullable', 'string', 'max:255'],
            'preferred_contact_channel' => ['nullable', 'string', 'max:30'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
        ]);

        $changes = [
            'display_name' => $validated['display_name'],
            'normalized_name' => PersianText::normalize($validated['display_name']),
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'customer_status' => $validated['customer_status'],
            'acquisition_channel' => $validated['acquisition_channel'],
            'acquisition_note' => $validated['acquisition_note'] ?? null,
            'preferred_contact_channel' => $validated['preferred_contact_channel'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'fa',
        ];

        // Which fields actually changed? (Enum-cast columns need ->value.)
        $dirty = array_keys(array_filter(
            $changes,
            function ($value, $key) use ($customer): bool {
                $current = $customer->{$key};

                $currentValue = $current instanceof \BackedEnum
                    ? $current->value
                    : (string) $current;

                return $currentValue !== (string) $value;
            },
            ARRAY_FILTER_USE_BOTH,
        ));

        $customer->update($changes);

        if ($dirty !== []) {
            $this->audit->log(
                action: 'customer.updated',
                tenant: $this->context->tenant(),
                actor: $request->user(),
                entityType: 'customer',
                entityId: $customer->id,
                metadata: ['changed' => $dirty], // keys only — never values (spec 8.6)
            );
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.updated')]);
    }

    /** Archive instead of delete (spec 4.5) — owner/manager only. */
    public function archive(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeAbility('customers.archive');

        $customer->update(['customer_status' => CustomerStatus::Archived->value]);

        $this->audit->log(
            action: 'customer.archived',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'customer',
            entityId: $customer->id,
            metadata: ['customer_number' => $customer->customer_number],
        );

        return redirect()
            ->route('customers.index')
            ->with('toast', ['type' => 'success', 'message' => __('customers.archived')]);
    }

    /** Bring an archived customer back as active — owner/manager only. */
    public function restore(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeAbility('customers.archive');

        $customer->update(['customer_status' => CustomerStatus::Active->value]);

        $this->audit->log(
            action: 'customer.restored',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'customer',
            entityId: $customer->id,
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.restored')]);
    }

    // --- helpers -----------------------------------------------------------

    /**
     * Search strategy (spec 7.1): digit-only terms hit normalized phone
     * values; everything else hits normalized names and social handles.
     */
    private function applySearch($query, string $term): void
    {
        $digits = PersianText::digitsOnly($term);

        if ($digits !== '') {
            $query->whereHas(
                'contacts',
                fn ($q) => $q->where('normalized_value', 'like', "%{$digits}%"),
            );

            return;
        }

        $normalized = PersianText::normalize($term);
        $handle = PersianText::normalizeHandle($term);

        $query->where(function ($q) use ($normalized, $handle): void {
            $q->where('normalized_name', 'like', "%{$normalized}%")
                ->orWhereHas(
                    'socialAccounts',
                    fn ($sq) => $sq->where('normalized_handle', 'like', "%{$handle}%"),
                );
        });
    }

    /** Row for the list view — no private data (spec 8.5). */
    private function listRow(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'display_name' => $customer->display_name,
            'phone' => $customer->primaryContact?->display_value,
            'city' => $customer->default_address_city,
            'status' => $customer->customer_status->value,
            'channel' => $customer->acquisition_channel,
            'tags' => $customer->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color_token,
            ])->all(),
            'created_at' => $customer->created_at?->toISOString(),
        ];
    }

    /** Full profile payload; exact addresses are privileged-only (spec 8.5). */
    private function profilePayload(Customer $customer, bool $isPrivileged): array
    {
        return [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'display_name' => $customer->display_name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'gender' => $customer->gender,
            'birth_date' => $customer->birth_date?->toDateString(),
            'status' => $customer->customer_status->value,
            'acquisition_channel' => $customer->acquisition_channel,
            'acquisition_note' => $customer->acquisition_note,
            'preferred_contact_channel' => $customer->preferred_contact_channel,
            'preferred_language' => $customer->preferred_language,
            'created_at' => $customer->created_at?->toISOString(),
            'contacts' => $customer->contacts->map(fn ($c): array => [
                'id' => $c->id,
                'type' => $c->contact_type,
                'label' => $c->label,
                'value' => $c->display_value,
                'is_primary' => $c->is_primary,
                'is_verified' => $c->is_verified,
            ])->all(),
            'social_accounts' => $customer->socialAccounts->map(fn ($s): array => [
                'id' => $s->id,
                'platform' => $s->platform,
                'username' => $s->username,
                'is_primary' => $s->is_primary,
            ])->all(),
            'addresses' => $customer->addresses->map(fn ($a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'city' => $a->city,
                'province' => $a->province,
                // Exact address lines stay with owner/manager.
                'address_line' => $isPrivileged ? $a->address_line : null,
                'postal_code' => $isPrivileged ? $a->postal_code : null,
                'recipient_name' => $a->recipient_name,
                'is_default' => $a->is_default,
            ])->all(),
            'consents' => $customer->consents->map(fn ($c): array => [
                'id' => $c->id,
                'channel' => $c->channel,
                'purpose' => $c->purpose,
                'status' => $c->status,
                'captured_at' => $c->captured_at?->toISOString(),
            ])->all(),
            'preferences' => $customer->preferences->map(fn ($p): array => [
                'id' => $p->id,
                'category' => $p->preference_category,
                'value_code' => $p->value_code,
                'value_label' => $p->value_label,
                'level' => $p->preference_level,
                'source' => $p->source,
            ])->all(),
            'measurements' => $customer->measurements->map(fn ($m): array => [
                'id' => $m->id,
                'code' => $m->measurement_code,
                'value_text' => $m->value_text,
                'value_number' => $m->value_number,
                'unit' => $m->unit,
                'recorded_at' => $m->recorded_at?->toISOString(),
                'source' => $m->source,
            ])->all(),
            'tags' => $customer->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color_token,
            ])->all(),
        ];
    }

    private function interactionRow($i): array
    {
        return [
            'id' => $i->id,
            'type' => $i->interaction_type,
            'channel' => $i->channel,
            'subject' => $i->subject,
            'summary' => $i->summary,
            'outcome' => $i->outcome,
            'occurred_at' => $i->occurred_at?->toISOString(),
            'follow_up_at' => $i->follow_up_at?->toISOString(),
            'follow_up_status' => $i->follow_up_status,
            'author' => $i->author?->name,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tagOptions(): array
    {
        return Tag::query()
            ->where('tenant_id', $this->context->tenant()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color_token,
            ])
            ->all();
    }

    private function authorizeAbility(string $ability): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor !== null && $actor->can($ability), 403);
    }
}
