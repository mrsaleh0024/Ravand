<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\CustomerFieldAllowlists as Fields;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customer notes (spec 5.2). Plain text only. Private notes are an
 * owner/manager concern; operators may write team notes.
 */
class CustomerNoteController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'visibility' => ['nullable', 'string', Rule::in(Fields::NOTE_VISIBILITIES)],
        ]);

        $requested = $validated['visibility'] ?? 'team';

        // Operators cannot file private notes (they could not even read them).
        $role = $this->context->role();
        $allowed = $role === TenantRole::Owner
            || $role === TenantRole::Manager;

        $customer->notes()->create([
            'tenant_id' => $this->context->tenant()->id,
            'body' => $validated['body'],
            'visibility' => $allowed ? $requested : 'team',
            'created_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.note_added')]);
    }

    public function destroy(Request $request, Customer $customer, string $note): RedirectResponse
    {
        $noteModel = $customer->notes()->findOrFail($note);

        $isAuthor = $noteModel->created_by_user_id === $request->user()?->id;
        $isPrivileged = in_array($this->context->role(), [
            TenantRole::Owner,
            TenantRole::Manager,
        ], true);

        abort_unless($isAuthor || $isPrivileged, 403);

        $noteModel->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.note_removed')]);
    }
}
