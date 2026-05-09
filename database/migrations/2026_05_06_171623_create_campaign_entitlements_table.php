<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_entitlements', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            
            // Financials
            $table->decimal('reward_value', 15, 2);
            
            // The Delivery Mechanisms
            $table->string('claim_token')->unique()->nullable(); // Cryptographic URL string
            $table->string('claim_code')->unique()->nullable();  // Human-readable code
            
            // Claim Status & Tracking
            $table->boolean('is_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            
            // Lifecycle Rules per user
            $table->timestamp('expires_at')->nullable(); 
            $table->timestamp('reminded_at')->nullable(); // Prevents spamming reminders
            
            $table->timestamps();
            
            // High-performance indexing for URL clicks and Checkout queries
            $table->index(['claim_token', 'is_claimed']);
            $table->index(['claim_code', 'is_claimed']);
            $table->index(['expires_at', 'is_claimed']); // Crucial for the nightly Clawback Job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_entitlements');
    }
};