<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewardee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('vertical_id')->constrained('verticals')->cascadeOnDelete();
            
            // The JSON column for dynamic fields (property_type, testDrive, etc.)
            $table->json('vertical_data')->nullable();
            
            $table->timestamps();
            
            // Prevent duplicate profiles for the same vertical in the same company
            $table->unique(['user_id', 'company_id', 'vertical_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewardee_profiles');
    }
};