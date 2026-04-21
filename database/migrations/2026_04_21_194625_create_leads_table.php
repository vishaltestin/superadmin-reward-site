<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            // React Form Data
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('mobile');
            $table->string('company_name');
            $table->string('number_of_employee');
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            
            // Pipeline Status (Crucial for the holding pen)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};