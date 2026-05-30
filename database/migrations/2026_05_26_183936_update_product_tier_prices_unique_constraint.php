<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_tier_prices', function (Blueprint $table) {
            // 1. Unbind the foreign key from the index first
            $table->dropForeign(['product_id']);

            // 2. Drop the old unique index safely
            $table->dropUnique('product_tier_prices_product_id_min_quantity_unique');

            // 3. Re-add the foreign key (MySQL will automatically create a fresh, plain index for it)
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->cascadeOnDelete();

            // 4. Create your new variant-aware composite unique constraint
            $table->unique(['product_id', 'product_variant_id', 'min_quantity'], 'tier_prices_variant_quantity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_tier_prices', function (Blueprint $table) {
            // Reverse the exact steps if rolled back
            $table->dropUnique('tier_prices_variant_quantity_unique');
            $table->dropForeign(['product_id']);
            
            $table->unique(['product_id', 'min_quantity']);
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->cascadeOnDelete();
        });
    }
};