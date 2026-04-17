<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertical_id')->constrained('verticals')->cascadeOnDelete();    
            $table->foreignId('parent_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};