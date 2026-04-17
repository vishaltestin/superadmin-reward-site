<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Core roles for your application architecture
            $table->enum('user_type', ['super_admin', 'business_head', 'sub_admin', 'rewardee'])->default('rewardee');
            
            // Splitting names based on your Zod schemas
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('mobile')->nullable();
            
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'user_type', 'first_name', 'last_name', 'mobile', 'is_active']);
        });
    }
};