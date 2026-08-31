<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // What is being paid for: an Order (claim/cart checkout) or a Wallet (top-up)
            $table->morphs('payable');

            $table->string('provider')->default('razorpay');
            $table->string('provider_order_id');               // razorpay order_XXXX
            $table->string('provider_payment_id')->nullable(); // razorpay pay_XXXX

            $table->unsignedBigInteger('amount_paise'); // integer paise — never floats
            $table->string('currency', 3)->default('INR');

            // created | paid | failed | refunded | cancelled
            $table->string('status')->default('created');

            // Flow-specific data, e.g. {"kind": "claim", "entitlement_id": 12}
            $table->json('meta')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_order_id']);
            $table->unique(['provider', 'provider_payment_id']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
