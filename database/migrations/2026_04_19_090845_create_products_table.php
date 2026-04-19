<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // The PRIMARY Category (Used for SEO, Breadcrumbs, Main URL)
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            
            // Core Identity
            $table->string('type')->default('physical'); // physical, digital, experience
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique()->nullable();
            $table->string('brand')->nullable();
            
            // Fiat Pricing Engine (Replaces Points)
            $table->decimal('mrp', 15, 2)->nullable(); // The crossed-out retail price
            $table->decimal('selling_price', 15, 2)->default(0.00); // The actual cost
            
            // Rich Content
            $table->text('short_description')->nullable();
            $table->longText('long_description')->nullable();
            $table->json('key_features')->nullable(); // JSON array for bullet points
            $table->longText('terms_and_conditions')->nullable(); 
            
            // Media Engine
            $table->string('main_image')->nullable();
            $table->json('gallery_images')->nullable(); // JSON array for extra images
            
            // Specifications Engine (Static key-value pairs like {"Material": "Cotton"})
            $table->json('specifications')->nullable(); 
            
            // SEO Engine
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};