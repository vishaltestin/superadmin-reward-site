<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_product_tier_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->integer('min_quantity');
            $table->decimal('selling_price', 15, 2);

            $table->timestamps();
            $table->unique(
                ['company_id', 'product_id', 'product_variant_id', 'min_quantity'],
                'company_tier_prices_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_product_tier_prices');
    }
};
