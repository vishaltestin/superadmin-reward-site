<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 1. Drop the old string column
            $table->dropColumn('brand');
            
            // 2. Add the proper relationship ID
            $table->foreignId('brand_id')->nullable()->after('sku')->constrained()->nullOnDelete();
            
            // 3. Add Compliance & Storefront columns
            $table->string('warranty_info')->nullable()->after('brand_id');
            $table->decimal('gst_percentage', 5, 2)->default(0.00)->after('selling_price');
            $table->string('video_url')->nullable()->after('gallery_images');
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn(['brand_id', 'warranty_info', 'gst_percentage', 'video_url', 'sort_order']);
            $table->string('brand')->nullable(); // Bring back the old column if we rollback
        });
    }
};