<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Money;
use App\Domain\Sales\OrderService;
use App\Domain\Sales\SalesException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Payments attached to an order (phase 03, spec 5.5).
 *
 * Recording a payment is open to every role (operators take the money);
 * refunding/voiding is an owner/manager decision (gate payments.manage).
 */
class OrderPaymentController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        // Persian digits / separators → plain integer string.
        $request->merge([
            'amount' => Money::normalize($request->input('amount')),
        ]);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+$/', 'not_in:0', 'max:20'],
            'method' => ['required', 'string', 'in:cash,card_to_card,pos,online,bank_transfer,other'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $this->orders->recordPayment($order, $request->user(), [
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'reference_code' => $validated['reference_code'] ?? null,
                'note' => $validated['note'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
            ]);
        } catch (SalesException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', 'پرداخت ثبت شد.');
    }

    public function refund(Request $request, Order $order, Payment $payment): RedirectResponse
    {
        return $this->close($request, $order, $payment, 'refund');
    }

    public function void(Request $request, Order $order, Payment $payment): RedirectResponse
    {
        return $this->close($request, $order, $payment, 'void');
    }

    private function close(Request $request, Order $order, Payment $payment, string $action): RedirectResponse
    {
        // The payment must belong to the bound order within this tenant.
        abort_unless($payment->order_id === $order->id, 404);

        try {
            if ($action === 'refund') {
                $this->orders->refundPayment($payment, $request->user());
            } else {
                $this->orders->voidPayment($payment, $request->user());
            }
        } catch (SalesException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', $action === 'refund' ? 'پرداخت برگشت داده شد.' : 'پرداخت ابطال شد.');
    }
}
