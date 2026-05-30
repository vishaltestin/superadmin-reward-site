<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_product_variant', function (Blueprint $table) {
            $table->id();
            
            // Core Relationships
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            
            // Atomic Overrides for this specific tenant's variant configuration
            $table->string('override_image')->nullable();
            $table->decimal('override_mrp', 15, 2)->nullable();
            $table->decimal('override_selling_price', 15, 2)->nullable();
            
            $table->timestamps();

            // Guardrail against redundant entries per company/variant configuration
            $table->unique(['company_id', 'product_variant_id'], 'company_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_product_variant');
    }
};