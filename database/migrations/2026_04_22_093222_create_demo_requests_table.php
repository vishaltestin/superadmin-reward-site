<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('company_name');
            $table->string('mobile')->nullable();
            
            $table->text('message')->nullable();
            

            $table->enum('status', ['new', 'contacted', 'demo_scheduled', 'closed'])->default('new');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};