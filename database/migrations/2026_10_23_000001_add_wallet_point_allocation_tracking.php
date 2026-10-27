<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Existing ledger rows cannot be reconstructed reliably; new Wallet
            // writes explicitly mark whether their source allocation is complete.
            $table->boolean('allocation_tracking_complete')->default(false);
            $table->foreignId('reversal_of_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();
        });

        Schema::create('wallet_transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_transaction_id')
                ->constrained('transactions')
                ->restrictOnDelete();
            $table->foreignId('credit_transaction_id')
                ->constrained('transactions')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['debit_transaction_id', 'credit_transaction_id'], 'wallet_allocations_debit_credit_unique');
            $table->index('credit_transaction_id', 'wallet_allocations_credit_index');
        });

        Schema::create('wallet_order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('debit_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_order_refunds');
        Schema::dropIfExists('wallet_transaction_allocations');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_transaction_id']);
            $table->dropColumn([
                'allocation_tracking_complete',
                'reversal_of_transaction_id',
            ]);
        });
    }
};
