<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-company category overrides on the category_company pivot:
     * - is_active  (nullable bool): null = inherit the global categories.is_active;
     *              true  = company opts IN to a platform-deactivated category;
     *              false = company forces the category off.
     * - sort_order (nullable int):  null = inherit the platform's categories.sort_order;
     *              set  = HR's own ordering (written for ALL assigned categories at once,
     *              so the COALESCE ordering never mixes the two schemes).
     */
    public function up(): void
    {
        Schema::table('category_company', function (Blueprint $table) {
            $table->boolean('is_active')->nullable()->after('category_id');
            $table->unsignedInteger('sort_order')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('category_company', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'sort_order']);
        });
    }
};