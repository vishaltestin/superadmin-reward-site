<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // Strict Tenancy & Ownership
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            
            // The unique receipt number (e.g., ORD-2026-X9B2)
            $table->string('order_number')->unique();
            
            // The Financial Split
            $table->decimal('total_amount', 15, 2); // The total cost of the cart
            $table->decimal('gst_total', 15, 2)->default(0.00); // Total tax collected
            $table->integer('points_used')->default(0); // How many points they burned
            $table->decimal('fiat_paid', 15, 2)->default(0.00); // How much they paid via Credit Card
            
            // Gateway Tracking
            $table->string('payment_gateway_reference')->nullable(); // e.g., Razorpay/Stripe ID
            
            // Order Lifecycle
            $table->enum('status', [
                'pending',    // User clicked checkout, waiting for payment gateway
                'paid',       // Payment success, waiting for processing
                'processing', // Warehouse is packing it / generating codes
                'shipped',    // On the way
                'completed',  // Delivered / Code claimed
                'cancelled',  // User or admin cancelled
                'failed'      // Payment failed
            ])->default('pending');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};