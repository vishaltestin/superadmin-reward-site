<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
            // Tracks how much of this specific credit hasn't been spent yet
            $table->decimal('remaining_amount', 15, 2)->default(0.00); 
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'remaining_amount']);
        });
    }
};