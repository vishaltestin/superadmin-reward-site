<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WebhookEvent;
use App\Services\OrderFulfilmentService;
use App\Services\RazorpayPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RazorpayWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $secret = config('services.razorpay.webhook_secret');

        if (! $secret) {
            Log::warning('Razorpay webhook received but RAZORPAY_WEBHOOK_SECRET is not configured.');
            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        // CRITICAL: verify the signature over the RAW body (never the parsed array).
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $expected  = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $event  = (string) $request->input('event', '');
        $entity = $request->input('payload.payment.entity');

        if (! is_array($entity) || empty($entity['id'])) {
            return response()->json(['message' => 'Ignored: no payment entity.']);
        }

        // ── Event-level deduplication (fast path) ─────────────────────────
        $eventKey = 'razorpay:' . $event . ':' . $entity['id'];

        if (WebhookEvent::query()->where('event_key', $eventKey)->exists()) {
            return response()->json(['message' => 'Duplicate event acknowledged.']);
        }

        $unexpected = null;

        try {
            match ($event) {
                'payment.captured' => $this->handleCaptured($entity),
                'payment.failed'   => $this->handleFailed($entity),
                default            => null,
            };
        } catch (Throwable $e) {
            // Unexpected crash: do NOT record the event, so Razorpay's retry
            // gets another chance. Payment-level idempotency makes the
            // reprocessing safe.
            $unexpected = $e;
            Log::error('Razorpay webhook crashed while handling event.', [
                'event_key' => $eventKey,
                'error'     => $e->getMessage(),
            ]);
        }

        if ($unexpected === null) {
            // Record AFTER successful (or deliberately terminal) handling.
            // A concurrent duplicate insert is a harmless no-op (unique key).
            try {
                WebhookEvent::firstOrCreate(
                    ['event_key' => $eventKey],
                    [
                        'provider'     => 'razorpay',
                        'event'        => $event,
                        'payload'      => $request->all(),
                        'processed_at' => now(),
                    ]
                );
            } catch (Throwable $e) {
                Log::warning('Failed to record webhook event: ' . $e->getMessage());
            }
        }

        if ($unexpected !== null) {
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['message' => 'Webhook processed.']);
    }

    private function handleCaptured(array $entity): void
    {
        $service = app(RazorpayPaymentService::class);

        $payment = Payment::where('provider', 'razorpay')
            ->where('provider_order_id', $entity['order_id'] ?? '')
            ->first();

        if (! $payment) {
            Log::info('Razorpay webhook: unknown gateway order ignored.', ['order_id' => $entity['order_id'] ?? null]);
            return;
        }

        if ($payment->status === Payment::STATUS_PAID) {
            return; // client verify already fulfilled — idempotent no-op
        }

        if (in_array($payment->status, [Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED, Payment::STATUS_FAILED])) {
            return;
        }

        // Bind the gateway payment id (needed for refunds) before fulfilment.
        $payment->provider_payment_id = $entity['id'];
        $payment->save();

        // Defence-in-depth: the signed payload must match the amount we created the order for.
        if ((int) ($entity['amount'] ?? 0) !== (int) $payment->amount_paise) {
            Log::critical('Razorpay webhook amount mismatch — refunding.', [
                'payment_id'     => $payment->id,
                'expected_paise' => $payment->amount_paise,
                'captured_paise' => $entity['amount'] ?? null,
            ]);
            $service->refundCaptured($payment, 'webhook_amount_mismatch', (int) ($entity['amount'] ?? 0));
            return;
        }

        try {
            $service->fulfilOnce($payment, function () use ($payment) {
                $order = $payment->payable;
                $kind  = $payment->meta['kind'] ?? null;

                if ($order instanceof Order && $kind === 'claim') {
                    OrderFulfilmentService::fulfilClaimOrder(
                        $order,
                        (int) ($payment->meta['entitlement_id'] ?? 0),
                        round($payment->amount_paise / 100, 2)
                    );
                    $order->update(['payment_gateway_reference' => $payment->provider_payment_id]);
                } elseif ($order instanceof Order && $kind === 'cart') {
                    OrderFulfilmentService::fulfilCartOrder($order, $payment->provider_payment_id);
                } elseif ($kind === 'topup' && $payment->payable) {
                    // Wallet top-up: the verify endpoint normally credits this;
                    // webhook is the backup for closed-tab scenarios.
                    $payment->payable->credit(
                        amount: $payment->amount_paise / 100,
                        description: 'Wallet Top-up via Razorpay (webhook) | Payment ID: ' . $payment->provider_payment_id,
                        fiatPaid: $payment->amount_paise / 100
                    );
                } else {
                    Log::info('Razorpay webhook: unrecognised payment kind.', ['payment_id' => $payment->id]);
                }
            });
        } catch (\App\Exceptions\PaymentException $e) {
            // Captured but unfulfillable (stock gone, reward already claimed, …) → refund + cancel.
            Log::error('Webhook fulfilment failed — refunding: ' . $e->getMessage(), ['payment_id' => $payment->id]);
            $service->refundCaptured($payment, 'webhook_fulfilment_failed: ' . $e->getMessage());

            $order = $payment->fresh()->payable;
            if ($order instanceof Order && $order->status === 'pending') {
                if ($order->points_used > 0) {
                    $order->user->wallet->credit(
                        amount: $order->points_used,
                        description: "Points refunded — order {$order->order_number} could not be fulfilled"
                    );
                }
                $order->update(['status' => 'cancelled']);
            }
        }
    }

    private function handleFailed(array $entity): void
    {
        $service = app(RazorpayPaymentService::class);

        $payment = Payment::where('provider', 'razorpay')
            ->where('provider_order_id', $entity['order_id'] ?? '')
            ->first();

        if (! $payment || $payment->status !== Payment::STATUS_CREATED) {
            return;
        }

        $payment->provider_payment_id = $entity['id'];
        $service->markFailed($payment, (string) ($entity['error_description'] ?? 'payment failed'));

        $order = $payment->payable;

        if ($order instanceof Order && $order->status === 'pending') {
            if ($order->points_used > 0) {
                $order->user->wallet->credit(
                    amount: $order->points_used,
                    description: "Points refunded — payment failed for order {$order->order_number}"
                );
            }
            $order->update(['status' => 'failed']);
        }
    }
}
