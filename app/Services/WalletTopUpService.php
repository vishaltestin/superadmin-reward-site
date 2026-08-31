<?php
namespace App\Services;

use App\Models\Payment;
use App\Models\Wallet;

class WalletTopUpService
{
    /**
     * Credit a wallet for a captured top-up payment.
     *
     * SINGLE implementation shared by the client-side verify endpoint and the
     * Razorpay webhook, so the point_multiplier is applied identically in
     * every path. Must only ever be called inside fulfilOnce() (idempotency).
     */
    public static function fulfil(Wallet $wallet, Payment $payment, string $providerPaymentId, ?string $source = null): void
    {
        $company = $wallet->walletable; // top-up wallets belong to companies

        $rupees = round($payment->amount_paise / 100, 2);
        $points = $rupees * (float) ($company->point_multiplier ?? 1.0);

        $wallet->credit(
            amount: $points,
            description: 'Wallet Top-up via Razorpay' . ($source ? " ({$source})" : '') . ' | Payment ID: ' . $providerPaymentId,
            reference: null,
            expiresAt: null,
            fiatPaid: $rupees
        );
    }
}
