<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            
            // Core Relationships
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vertical_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_event_name')->nullable();
            // Campaign Identity
            $table->string('name');
            $table->text('description')->nullable();
            
            // Branching Logic Enablers
            $table->enum('distribution_type', ['online', 'bulk']);
            $table->enum('reward_type', ['points', 'code', 'link'])->nullable();
            
            // The Escrow / Financial Lock
            $table->decimal('budget_locked', 15, 2)->default(0.00); 
            $table->integer('total_recipients')->default(0); // Tracks scale for frontend UI without heavy counting
            
            // Dynamic Configuration (Catalogs, Templates, Matrices)
            $table->json('config_json')->nullable();
            
            // Lifecycle
            $table->enum('status', ['draft', 'processing', 'scheduled', 'active', 'completed', 'cancelled', 'inquiry_pending'])->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // High-level campaign expiry
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};