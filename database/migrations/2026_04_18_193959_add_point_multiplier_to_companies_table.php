<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // 5 total digits, 2 after the decimal. Default is 1.00 (1:1 ratio)
            // Placing it right after the points_name column for neatness
            $table->decimal('point_multiplier', 5, 2)->default(1.00)->after('points_name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('point_multiplier');
        });
    }
};