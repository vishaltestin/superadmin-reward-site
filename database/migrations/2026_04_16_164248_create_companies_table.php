<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            
            $table->string('name'); 
            $table->string('number_of_employee')->nullable();
            
            $table->string('gst_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->string('industry')->nullable();
            $table->text('address')->nullable();
            
            $table->string('alias')->unique()->nullable();
            $table->string('logo')->nullable();
            $table->string('points_name')->default('Points');
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false); 
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
