<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            
            // Admin Reference
            $table->string('internal_name');
            
            // The Targeting Engine
            $table->enum('target_type', ['global', 'industry', 'specific_companies'])->default('global');
            $table->json('target_data')->nullable(); 

            // The Format Engine
            $table->enum('format', ['hero_banner', 'featured_product'])->default('hero_banner');
            $table->json('format_data'); 
            
            // Scheduling & Status
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};