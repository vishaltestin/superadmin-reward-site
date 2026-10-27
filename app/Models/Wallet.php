<?php
namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = ['balance', 'is_active'];

    // This connects to either a User or a Company.
    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Add funds and create a tracked credit lot. */
    public function credit(
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ? \Carbon\Carbon $expiresAt = null,
        ?float $fiatPaid = null
    ) : Transaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new Exception('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($amount, $description, $reference, $expiresAt, $fiatPaid) {
            $transaction = $this->transactions()->create([
                'type'                         => 'credit',
                'amount'                       => $amount,
                'remaining_amount'             => $amount,
                'fiat_paid'                    => $fiatPaid,
                'expires_at'                   => $expiresAt,
                'description'                  => $description,
                'reference_type'               => $reference ? get_class($reference) : null,
                'reference_id'                 => $reference?->id,
                'allocation_tracking_complete' => true,
            ]);

            $this->increment('balance', $amount);

            return $transaction;
        });
    }

    /**
     * Deduct funds using FIFO (oldest-expiring point lots first) and persist
     * exactly which credit transactions funded this debit.
     */
    public function debit(
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?float $fiatPaid = null
    ): Transaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new Exception('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($amount, $description, $reference, $fiatPaid) {
            // Lock the wallet so concurrent debits cannot spend the same balance.
            $wallet = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ((float) $wallet->balance + 0.001 < $amount) {
                throw new Exception('Insufficient funds in wallet.');
            }

            $transaction = $wallet->transactions()->create([
                'type'                         => 'debit',
                'amount'                       => $amount,
                'fiat_paid'                    => $fiatPaid,
                'remaining_amount'             => 0,
                'description'                  => $description,
                'reference_type'               => $reference ? get_class($reference) : null,
                'reference_id'                 => $reference?->id,
                'allocation_tracking_complete' => false,
            ]);

            $amountToConsume = $amount;

            $credits = $wallet->transactions()
                ->where('type', 'credit')
                ->where('remaining_amount', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByRaw('expires_at IS NULL ASC, expires_at ASC')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($credits as $credit) {
                if ($amountToConsume <= 0) {
                    break;
                }

                $available = round((float) $credit->remaining_amount, 2);
                $allocated = round(min($available, $amountToConsume), 2);

                if ($allocated <= 0) {
                    continue;
                }

                $credit->decrement('remaining_amount', $allocated);
                WalletTransactionAllocation::create([
                    'debit_transaction_id'  => $transaction->id,
                    'credit_transaction_id' => $credit->id,
                    'amount'                => $allocated,
                ]);

                $amountToConsume = round($amountToConsume - $allocated, 2);
            }

            // Legacy balances may not have a corresponding credit lot. The debit
            // still proceeds as before, but reports will not claim that unknown
            // portion was attributed to a specific campaign grant.
            $transaction->update([
                'allocation_tracking_complete' => $amountToConsume <= 0.001,
            ]);

            $wallet->decrement('balance', $amount);

            return $transaction->fresh();
        });
    }

    /**
     * Refund a debit by recreating its original credit lots. This preserves the
     * source campaign reference so a later successful order can still be
     * attributed to the same grant. Order-level idempotency is enforced by
     * OrderPointsRefundService.
     */
    public function refundDebit(Transaction $debit, string $description): void
    {
        DB::transaction(function () use ($debit, $description) {
            $wallet      = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $lockedDebit = Transaction::whereKey($debit->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedDebit->wallet_id !== (int) $wallet->id || $lockedDebit->type !== 'debit') {
                throw new Exception('The transaction is not a debit from this wallet.');
            }

            $allocations = WalletTransactionAllocation::query()
                ->where('debit_transaction_id', $lockedDebit->id)
                ->with('creditTransaction')
                ->orderBy('id')
                ->get();

            $refundedFromLots = 0.0;
            $debitAmount      = round((float) $lockedDebit->amount, 2);

            foreach ($allocations as $allocation) {
                $sourceCredit = $allocation->creditTransaction;
                if (! $sourceCredit) {
                    continue;
                }

                $refundAmount        = round((float) $allocation->amount, 2);
                $remainingRefundable = round($debitAmount - $refundedFromLots, 2);
                $refundAmount        = min($refundAmount, $remainingRefundable);

                if ($refundAmount <= 0) {
                    break;
                }

                $wallet->transactions()->create([
                    'type'                         => 'credit',
                    'amount'                       => $refundAmount,
                    'remaining_amount'             => $refundAmount,
                    'fiat_paid'                    => 0,
                    // Keep the existing refund policy: returned order points do not expire.
                    'expires_at'                   => null,
                    'description'                  => $description,
                    'reference_type'               => $sourceCredit->reference_type,
                    'reference_id'                 => $sourceCredit->reference_id,
                    'reversal_of_transaction_id'   => $lockedDebit->id,
                    'allocation_tracking_complete' => (bool) $sourceCredit->allocation_tracking_complete,
                ]);

                $refundedFromLots = round($refundedFromLots + $refundAmount, 2);
            }

            $unattributedRemainder = round($debitAmount - $refundedFromLots, 2);
            if ($unattributedRemainder > 0) {
                $wallet->transactions()->create([
                    'type'                         => 'credit',
                    'amount'                       => $unattributedRemainder,
                    'remaining_amount'             => $unattributedRemainder,
                    'fiat_paid'                    => 0,
                    'expires_at'                   => null,
                    'description'                  => $description . ' (unattributed legacy balance)',
                    'reference_type'               => null,
                    'reference_id'                 => null,
                    'reversal_of_transaction_id'   => $lockedDebit->id,
                    'allocation_tracking_complete' => false,
                ]);
            }

            $wallet->increment('balance', $debitAmount);
        });
    }
}
