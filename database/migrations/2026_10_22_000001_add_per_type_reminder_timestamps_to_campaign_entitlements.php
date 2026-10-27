<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_entitlements', function (Blueprint $table) {
            $table->timestamp('initial_reminded_at')->nullable()->after('reminded_at');
            $table->timestamp('expiry_reminded_at')->nullable()->after('initial_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_entitlements', function (Blueprint $table) {
            $table->dropColumn(['initial_reminded_at', 'expiry_reminded_at']);
        });
    }
};
