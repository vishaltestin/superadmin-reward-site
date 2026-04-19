<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_product', function (Blueprint $table) {
            $table->id();
            
            // The Core Link
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // --- The Exception Engine Fields ---
            
            // 1. Exclusions (If true, hide this product from this company completely)
            $table->boolean('is_excluded')->default(false); 
            
            // 2. White-labeling / Branding Overrides
            $table->string('override_name')->nullable();
            $table->string('override_image')->nullable();
            
            // 3. Pricing Overrides (Company subsidizes or marks up the price)
            $table->decimal('override_mrp', 15, 2)->nullable();
            $table->decimal('override_selling_price', 15, 2)->nullable();
            
            $table->timestamps();
            
            // A company can only have one set of rules per product
            $table->unique(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_product');
    }
};