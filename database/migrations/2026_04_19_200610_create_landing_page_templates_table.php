<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_templates', function (Blueprint $table) {
            $table->id();
            
            // Core Identity
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Naming & Status
            $table->string('name'); // Internal name (e.g., "Master Diwali V1")
            $table->string('title'); // Public facing title (e.g., "Claim Your Diwali Bonus!") - Editable by Client
            $table->string('status')->default('draft'); // 'draft', 'published', 'archived'
            
            // The Headless CMS JSON Columns
            $table->json('global_theme_tokens')->nullable(); // High-level branding (Colors, Fonts)
            $table->json('seo_meta')->nullable(); // Social sharing data (OG Image, Description)
            $table->json('page_schema'); // The modular array of UI sections and content
            
            // Admin Controls
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_templates');
    }
};