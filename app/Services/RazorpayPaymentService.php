<?php
namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api as RazorpayApi;

class RazorpayPaymentService
{
    private ?RazorpayApi $api = null;

    private function api(): RazorpayApi
    {
        if ($this->api === null) {
            $key    = config('services.razorpay.key_id');
            $secret = config('services.razorpay.key_secret');

            if (! $key || ! $secret) {
                throw new PaymentException('Payment gateway is not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.', 503);
            }

            $this->api = new RazorpayApi($key, $secret);
        }

        return $this->api;
    }

    /**
     * Create a gateway order + local Payment row for anything that needs paying.
     */
    public function createOrderFor(Model $payable, int $amountPaise, string $receipt, array $meta = []): Payment
    {
        if ($amountPaise <= 0) {
            throw new PaymentException('Payment amount must be greater than zero.');
        }

        $rzpOrder = $this->api()->order->create([
            'amount'          => $amountPaise,
            'currency'        => 'INR',
            'receipt'         => $receipt,
            'payment_capture' => 1, // auto-capture
            'notes'           => ['receipt' => $receipt],
        ]);

        try {
            return Payment::create([
                'payable_type'      => $payable->getMorphClass(),
                'payable_id'        => $payable->getKey(),
                'provider'          => 'razorpay',
                'provider_order_id' => $rzpOrder->id,
                'amount_paise'      => $amountPaise,
                'currency'          => 'INR',
                'status'            => Payment::STATUS_CREATED,
                'meta'              => $meta,
            ]);
        } catch (\Throwable $e) {
            // The gateway order now exists but we could not persist the local
            // record. It can never be paid through our flows (the client never
            // receives its id), but log it so ops can audit it at the gateway.
            Log::critical('Razorpay order created but local Payment record failed.', [
                'provider_order_id' => $rzpOrder->id,
                'amount_paise'      => $amountPaise,
                'error'             => $e->getMessage(),
            ]);

            throw new PaymentException('Failed to record the payment. Nothing has been charged.', 502);
        }
    }

    /**
     * Verify the checkout signature AND fetch the payment from Razorpay.
     * The fetched entity is the source of truth for status/amount/order binding.
     *
     * @return array{0: Payment, 1: object}
     */
    public function verifyAndFetch(string $rzpOrderId, string $rzpPaymentId, string $signature): array
    {
        $secret = config('services.razorpay.key_secret');
        if (! $secret) {
            throw new PaymentException('Payment gateway is not configured.', 503);
        }

        $payment = Payment::where('provider', 'razorpay')
            ->where('provider_order_id', $rzpOrderId)
            ->first();

        if (! $payment) {
            throw new PaymentException('Unknown payment order.', 404);
        }

        // 1. Signature — proves the checkout response wasn't tampered with.
        $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $secret);
        if (! hash_equals($expected, (string) $signature)) {
            throw new PaymentException('Payment verification failed: signature mismatch.', 400);
        }

        // 2. Gateway fetch — proves the payment is real, captured, and for our amount.
        try {
            $entity = $this->api()->payment->fetch($rzpPaymentId);
        } catch (\Exception $e) {
            Log::error('Razorpay payment fetch failed: ' . $e->getMessage());
            throw new PaymentException('Unable to verify payment with the gateway. Please contact support.', 502);
        }

        if (($entity->order_id ?? null) !== $rzpOrderId) {
            throw new PaymentException('Payment does not belong to this order.', 400);
        }

        if (($entity->status ?? null) !== 'captured') {
            throw new PaymentException('Payment is not captured yet (status: ' . ($entity->status ?? 'unknown') . ').', 400);
        }

        if ((int) $entity->amount !== (int) $payment->amount_paise) {
            throw new PaymentException('Payment amount does not match the order amount.', 400);
        }

        if (($entity->currency ?? 'INR') !== $payment->currency) {
            throw new PaymentException('Payment currency mismatch.', 400);
        }

        // 3. Replay protection: this gateway payment may only bind to this row.
        if ($payment->provider_payment_id && $payment->provider_payment_id !== $rzpPaymentId) {
            throw new PaymentException('This payment does not match the recorded order.', 400);
        }

        return [$payment, $entity];
    }

    /**
     * Idempotent fulfilment — the closure runs AT MOST ONCE per payment.
     * Returns true if fulfilled now, false if already fulfilled (idempotent replay).
     */
    public function fulfilOnce(Payment $payment, Closure $fulfilment): bool
    {
        return DB::transaction(function () use ($payment, $fulfilment) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new PaymentException('Payment record not found.', 404);
            }

            if ($locked->status === Payment::STATUS_PAID) {
                return false; // already fulfilled — safe no-op
            }

            if ($locked->status !== Payment::STATUS_CREATED) {
                throw new PaymentException("Payment cannot be fulfilled (status: {$locked->status}).", 409);
            }

            $fulfilment($locked);

            $locked->fill([
                'status'  => Payment::STATUS_PAID,
                'paid_at' => $locked->paid_at ?? now(),
            ])->save();

            return true;
        });
    }

    /**
     * Refund a captured payment (full refund) — used when fulfilment fails
     * AFTER capture so the user is never left out of pocket.
     */
    public function refundCaptured(Payment $payment, string $reason, ?int $amountPaise = null): Payment
    {
        $payment = $payment->fresh() ?? $payment;

        if (! $payment->provider_payment_id) {
            Log::warning("Refund skipped — no provider payment id on payment {$payment->id}.");
            return $payment;
        }

        $refundAmount = $amountPaise ?? (int) $payment->amount_paise;

        try {
            $this->api()->payment
                ->fetch($payment->provider_payment_id)
                ->refund(['amount' => $refundAmount]);

            $payment->fill([
                'status'      => Payment::STATUS_REFUNDED,
                'refunded_at' => now(),
                'meta'        => array_merge($payment->meta ?? [], ['refund_reason' => $reason]),
            ])->save();
        } catch (\Exception $e) {
            Log::critical("Refund FAILED for payment {$payment->id} ({$payment->provider_payment_id}): " . $e->getMessage());
            $payment->fill([
                'meta' => array_merge($payment->meta ?? [], ['refund_reason' => $reason, 'refund_pending' => true]),
            ])->save();
        }

        return $payment->fresh();
    }

    public function markFailed(Payment $payment, string $reason): void
    {
        $payment->fill([
            'status'    => Payment::STATUS_FAILED,
            'failed_at' => now(),
            'meta'      => array_merge($payment->meta ?? [], ['failure_reason' => $reason]),
        ])->save();
    }

    public function markCancelled(Payment $payment): void
    {
        if ($payment->status === Payment::STATUS_CREATED) {
            $payment->fill(['status' => Payment::STATUS_CANCELLED])->save();
        }
    }
}
