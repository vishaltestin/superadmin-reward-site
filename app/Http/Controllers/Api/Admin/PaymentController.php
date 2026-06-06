<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api as RazorpayApi;

class PaymentController extends Controller
{
    private RazorpayApi $razorpay;

    public function __construct()
    {
        $this->razorpay = new RazorpayApi(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret')
        );
    }

    /**
     * GET /payment/balance
     * Returns wallet balance + company point settings.
     */
    public function balance(Request $request)
    {
        $company = $request->user()->company->load('wallet');

        return response()->json([
            'balance'          => $company->wallet->balance ?? 0,
            'points_name'      => $company->points_name ?? 'Points',
            'point_multiplier' => $company->point_multiplier ?? 1.0,
        ]);
    }

    /**
     * GET /payment/transactions
     * Returns paginated transaction history for the company wallet.
     */
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

    /**
     * POST /payment/razorpay/order
     *
     * Step 1 of payment flow.
     * Creates a Razorpay order and returns details needed by the frontend
     * to open the Razorpay checkout modal.
     */
    public function createOrder(Request $request)
    {
        // Only business heads can add funds
        if ($request->user()->user_type !== 'business_head') {
            return response()->json(['message' => 'Only Business Heads can add funds.'], 403);
        }

        $validated = $request->validate([
            'fiat_amount' => 'required|numeric|min:100|max:500000',
        ]);

        // Razorpay requires amount in paise (1 INR = 100 paise)
        $amountInPaise = (int) ($validated['fiat_amount'] * 100);

        // Create the order on Razorpay's servers
        $order = $this->razorpay->order->create([
            'amount'          => $amountInPaise,
            'currency'        => 'INR',
            'receipt'         => 'rcpt_' . uniqid(),
            'payment_capture' => 1, // Auto-capture payment immediately
        ]);

        return response()->json([
            'order_id'     => $order->id,
            'amount'       => $amountInPaise,
            'currency'     => 'INR',
            'key_id'       => config('services.razorpay.key_id'),
            'company_name' => $request->user()->company->name ?? 'Your Company',
            'user_name'    => $request->user()->name ?? '',
            'user_email'   => $request->user()->email ?? '',
        ]);
    }

    /**
     * POST /payment/razorpay/verify
     *
     * Step 2 of payment flow.
     * Razorpay sends back 3 values after successful payment.
     * We verify the cryptographic signature to confirm the payment is genuine
     * (not tampered with), then credit the wallet.
     */
    /**
     * POST /payment/razorpay/verify
     */
    public function verifyPayment(Request $request)
    {
        if ($request->user()->user_type !== 'business_head') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'fiat_amount'         => 'required|numeric|min:100',
        ]);

        // Verify HMAC-SHA256 signature
        $expectedSignature = hash_hmac(
            'sha256',
            $validated['razorpay_order_id'] . '|' . $validated['razorpay_payment_id'],
            config('services.razorpay.key_secret')
        );

        if (! hash_equals($expectedSignature, $validated['razorpay_signature'])) {
            return response()->json([
                'message' => 'Payment verification failed. Signature mismatch.',
            ], 400);
        }

        $company    = $request->user()->company;
        $wallet     = $company->wallet;
        $multiplier = $company->point_multiplier ?? 1.0;
        $points     = $validated['fiat_amount'] * $multiplier;

        DB::transaction(function () use ($wallet, $points, $validated) {
            $wallet->credit(
                amount: $points,
                description: 'Wallet Top-up via Razorpay | Payment ID: ' . $validated['razorpay_payment_id'],
                reference: null,                           
                expiresAt: null,                           
                fiatPaid: (float) $validated['fiat_amount']
            );
        });

        return response()->json([
            'message' => "Successfully added {$points} points to your wallet.",
            'new_balance' => $wallet->fresh()->balance,
        ]);
    }
}
