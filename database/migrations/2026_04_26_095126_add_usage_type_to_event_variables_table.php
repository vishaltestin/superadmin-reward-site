<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_variables', function (Blueprint $table) {
            $table->enum('usage_type', ['email', 'landing_page', 'both'])->default('both')->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('event_variables', function (Blueprint $table) {
            $table->dropColumn('usage_type');
        });
    }
};