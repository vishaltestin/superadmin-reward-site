<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_company', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            
            $table->timestamps();
            $table->unique(['company_id', 'category_id']); 
            $table->index('company_id');
$table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_company');
    }
};