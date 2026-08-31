<?php
namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\RazorpayPaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private RazorpayPaymentService $razorpay;

    public function __construct()
    {
        $this->razorpay = app(RazorpayPaymentService::class);
    }

    public function balance(Request $request)
    {
        $company = $request->user()->company->load('wallet');

        return response()->json([
            'balance'          => $company->wallet->balance ?? 0,
            'points_name'      => $company->points_name ?? 'Points',
            'point_multiplier' => $company->point_multiplier ?? 1.0,
        ]);
    }

    public function transactions(Request $request)
    {
        $company = $request->user()->company;

        if (! $company->wallet) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $transactions = $company->wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($transactions);
    }

    public function createOrder(Request $request)
    {
        if ($request->user()->user_type !== 'business_head') {
            return response()->json(['message' => 'Only Business Heads can add funds.'], 403);
        }

        $validated = $request->validate([
            'fiat_amount' => 'required|numeric|min:100|max:500000',
        ]);

        $company = $request->user()->company;

        // Guarantees a wallet row exists before we attach the payment to it.
        $wallet = $company->wallet()->firstOrCreate([], ['balance' => 0.00]);

        $amountInPaise = (int) round(((float) $validated['fiat_amount']) * 100);

        $payment = $this->razorpay->createOrderFor(
            $wallet,
            $amountInPaise,
            'rcpt_' . uniqid(),
            ['kind' => 'topup', 'company_id' => $company->id, 'created_by' => $request->user()->id]
        );

        return response()->json([
            'order_id'     => $payment->provider_order_id,
            'amount'       => (int) $payment->amount_paise,
            'currency'     => $payment->currency,
            'key_id'       => config('services.razorpay.key_id'),
            'company_name' => $company->name ?? 'Your Company',
            'user_name'    => $request->user()->name ?? '',
            'user_email'   => $request->user()->email ?? '',
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $user = $request->user();

        if ($user->user_type !== 'business_head') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        try {
            [$payment] = $this->razorpay->verifyAndFetch(
                $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature']
            );

            $wallet = $payment->payable;

            if (($payment->meta['kind'] ?? null) !== 'topup'
                || ! $wallet instanceof Wallet
                || $wallet->id !== $user->company->wallet?->id) {
                return response()->json(['message' => 'This payment does not belong to your company wallet.'], 403);
            }

            $rupees = round($payment->amount_paise / 100, 2);

            $this->razorpay->fulfilOnce($payment, function () use ($wallet, $payment, $validated) {
                \App\Services\WalletTopUpService::fulfil(
                    $wallet,
                    $payment,
                    $validated['razorpay_payment_id']
                );
            });

            $points = $rupees * ((float) ($user->company->point_multiplier ?? 1.0));

            return response()->json([
                'message' => "Successfully added {$points} points to your wallet.",
                'new_balance' => $wallet->fresh()->balance,
            ]);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }
}
