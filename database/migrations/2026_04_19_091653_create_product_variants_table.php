<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            
            // Link to the main product
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // Variant Identity
            $table->string('name'); // e.g., "Red - Medium"
            $table->string('sku')->unique();
            
            // Fiat Pricing for this specific variant
            $table->decimal('mrp', 15, 2)->nullable(); 
            $table->decimal('selling_price', 15, 2)->default(0.00); 
            
            // The Flexible Attributes Engine
            // e.g., {"Color": "Red", "Size": "M", "Weight": "200g"}
            $table->json('attributes')->nullable(); 
            
            // Optional: Stock tracking for physical items
            $table->integer('stock_quantity')->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};