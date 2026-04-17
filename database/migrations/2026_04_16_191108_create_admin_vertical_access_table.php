<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_vertical_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vertical_id')->constrained('verticals')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'vertical_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_vertical_access');
    }
};