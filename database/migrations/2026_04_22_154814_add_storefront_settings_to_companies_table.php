<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('points_name');
            
            $table->longText('terms_text')->nullable()->after('social_links');
            $table->longText('privacy_text')->nullable()->after('terms_text');

            $table->json('hidden_category_ids')->nullable()->after('privacy_text');
            $table->json('hidden_product_ids')->nullable()->after('hidden_category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'social_links',
                'terms_text',
                'privacy_text',
                'hidden_category_ids',
                'hidden_product_ids',
            ]);
        });
    }
};