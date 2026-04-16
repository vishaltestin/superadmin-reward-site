<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_vertical', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vertical_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['company_id', 'vertical_id']);
            $table->index('company_id');
$table->index('vertical_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_vertical');
    }
};