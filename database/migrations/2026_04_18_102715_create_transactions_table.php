<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            
            // Is money coming IN or going OUT?
            $table->enum('type', ['credit', 'debit'])->index();
            
            $table->decimal('amount', 15, 2);
            
            // What caused this transaction? (e.g., 'App\Models\Campaign', 'App\Models\Order', 'Manual_Admin_Add')
            $table->nullableMorphs('reference'); 
            
            $table->string('description')->nullable(); // e.g., "Birthday Campaign Reward"
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};