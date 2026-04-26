<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_variables', function (Blueprint $table) {
            $table->id();
            
            // Null event_id means this is a GLOBAL variable (available to all templates)
            // If it has an event_id, it only shows up for templates attached to that specific event
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->string('name');  // The human-readable label (e.g., "First Name")
            $table->string('value'); // The actual tag (e.g., "{{ first_name }}")
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_variables');
    }
};
