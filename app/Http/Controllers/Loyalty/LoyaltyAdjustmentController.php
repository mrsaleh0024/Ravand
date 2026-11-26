<?php

namespace App\Http\Controllers\Loyalty;

use App\Domain\Loyalty\LoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual loyalty adjustment for one customer (phase 04, spec section 10).
 *
 * - Owner/manager only (`loyalty.manage` gate on the route).
 * - A non-zero points delta and a REASON are both mandatory; the reason is
 *   stored on the ledger row and echoed into the audit trail.
 */
class LoyaltyAdjustmentController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyalty,
    ) {}

    public function __invoke(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->loyalty->adjust(
            $customer,
            (int) $validated['points'],
            $validated['reason'],
            $request->user(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('loyalty.adjusted')]);
    }
}
