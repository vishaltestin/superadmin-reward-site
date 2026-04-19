<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This records the individual line items in the cart.
// Crucial Rule: We must copy the unit_price from the Product table to the Order Item table. If the Apple AirPods price goes up to ₹25,000 next month, we don't want this old receipt to

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            // Links
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            
            // If they bought a specific Size/Color, track it!
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            
            // The Immutable Financial Snapshot
            $table->string('product_name'); // Save the name in case the original product is deleted
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2); // Price at the exact time of purchase
            $table->decimal('unit_gst_percentage', 5, 2)->default(0.00);
            $table->decimal('total_price', 15, 2); // quantity * unit_price
            
            // Delivery Status per item (e.g., Physical items ship, Digital is instant)
            $table->string('delivery_status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};