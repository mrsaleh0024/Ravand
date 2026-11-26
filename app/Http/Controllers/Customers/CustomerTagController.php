<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attaching/detaching tags on a customer (spec 5.2). Tag vocabulary is
 * shared per tenant and managed by owner/manager.
 */
class CustomerTagController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function attach(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'tag_id' => ['required', 'string', 'max:26'],
        ]);

        $tenant = $this->context->tenant();

        $tag = Tag::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['tag_id']);

        // Direct insert (not BelongsToMany::attach): the pivot table carries
        // created_at ONLY (spec 5.2), and Eloquent's attach() force-enables
        // model timestamps whenever a timestamp column is passed — adding an
        // updated_at the table does not have.
        $already = DB::table('customer_tag')
            ->where('customer_id', $customer->id)
            ->where('tag_id', $tag->id)
            ->exists();

        if (! $already) {
            DB::table('customer_tag')->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'tag_id' => $tag->id,
                'created_by_user_id' => $request->user()?->id,
                'created_at' => now(),
            ]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.tag_attached')]);
    }

    public function detach(Request $request, Customer $customer, Tag $tag): RedirectResponse
    {
        abort_unless($tag->tenant_id === $this->context->tenant()->id, 404);

        $customer->tags()->detach($tag->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.tag_detached')]);
    }
}
