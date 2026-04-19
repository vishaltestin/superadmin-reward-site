<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            
            // The Event trigger (e.g., New Joinee, Order Placed)
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            
            // Null = Your Master Template. ID = The Company's Custom Variation.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->string('name'); // e.g., "Master Welcome" or "Ford Sales Welcome"
            $table->string('subject'); 
            
            // The Drag & Drop State and the Final HTML
            $table->longText('html_body')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};