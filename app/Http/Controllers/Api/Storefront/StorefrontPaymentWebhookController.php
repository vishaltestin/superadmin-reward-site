<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontPaymentWebhookController extends Controller
{
    /**
     * POST /api/storefront/payment/webhook
     * Excluded from CSRF and Auth middleware.
     */
    public function handleWebhook(Request $request)
    {
        // 1. CRITICAL SECURITY: Verify Gateway Signature
        // $isValid = GatewaySDK::verifySignature($request->header('X-Gateway-Signature'), $request->getContent());
        $isValid = true; // Placeholder for actual gateway validation logic

        if (!$isValid) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        // 2. Extract Event Data
        $gatewayOrderId = $request->input('gateway_order_id'); 
        $paymentStatus = $request->input('status'); // e.g., 'captured' or 'success'

        $order = Order::where('payment_gateway_reference', $gatewayOrderId)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 444);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Order already processed.']);
        }

        DB::beginTransaction();
        try {
            if ($paymentStatus === 'success') {
                // Payment Succeeded -> Complete Order
                $order->update(['status' => 'paid']);
                
                // (Trigger asynchronous inventory deduction or digital voucher assignment here)
            } else {
                // Payment Failed / Cancelled -> Fail Order & Refund Locked Points!
                $order->update(['status' => 'failed']);
                
                if ($order->points_used > 0) {
                    $order->user->wallet->credit(
                        amount: $order->points_used,
                        description: "Refund for failed payment transaction: {$order->order_number}"
                    );
                }
            }

            DB::commit();
            return response()->json(['message' => 'Webhook processed successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }
    }
}