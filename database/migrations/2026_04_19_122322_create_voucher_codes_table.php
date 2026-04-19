<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_codes', function (Blueprint $table) {
            $table->id();
            
            // Link to the digital product
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // The actual secret code
            $table->string('code')->unique();
            $table->string('pin')->nullable(); // Some vouchers require a PIN
            
            // Tracking Usage
            $table->boolean('is_used')->default(false);
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            
            // Optional expiry for the code itself
            $table->date('expires_at')->nullable(); 

            $table->timestamps();
            $table->softDeletes();
            
            // Index for fast checkout queries
            $table->index(['product_id', 'is_used']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_codes');
    }
};