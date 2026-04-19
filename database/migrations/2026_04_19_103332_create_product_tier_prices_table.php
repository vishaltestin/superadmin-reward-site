<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tier_prices', function (Blueprint $table) {
            $table->id();
            
            // Link to the parent product
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // The minimum amount the user has to buy to unlock this price
            $table->integer('min_quantity');
            
            // The discounted fiat price per item at this tier
            $table->decimal('selling_price', 15, 2);
            
            $table->timestamps();
            
            // A product shouldn't have two different prices for the exact same minimum quantity
            $table->unique(['product_id', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tier_prices');
    }
};