<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            // Nullable so records submitted before this migration remain valid.
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('number_of_employee', 30)->nullable();
            $table->string('how_did_you_hear_about_us')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropColumn([
                'department',
                'designation',
                'number_of_employee',
                'how_did_you_hear_about_us',
            ]);
        });
    }
};