<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            
            // This creates walletable_id and walletable_type automatically!
            $table->morphs('walletable'); 
            
            // Cached balance for fast queries (recalculated from transactions if needed)
            $table->decimal('balance', 15, 2)->default(0.00);
            
            // Optional: To lock a wallet if suspicious activity is detected
            $table->boolean('is_active')->default(true); 
            
            $table->timestamps();
            
            // A user or company should only have one primary wallet
            $table->unique(['walletable_id', 'walletable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};