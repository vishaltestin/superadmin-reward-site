<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 1. Immutable Shipping Snapshot (Where does the box go?)
            $table->string('shipping_name')->nullable()->after('status');
            $table->string('shipping_mobile')->nullable()->after('shipping_name');
            $table->string('shipping_address_line_1')->nullable()->after('shipping_mobile');
            $table->string('shipping_address_line_2')->nullable()->after('shipping_address_line_1');
            $table->string('shipping_city')->nullable()->after('shipping_address_line_2');
            $table->string('shipping_state')->nullable()->after('shipping_city');
            $table->string('shipping_pincode')->nullable()->after('shipping_state');
            
            // 2. Immutable Billing Snapshot (Who is paying for the tax invoice?)
            // We use JSON here to prevent adding 8 more separate columns for billing
            $table->json('billing_address_snapshot')->nullable()->after('shipping_pincode');
            
            // 3. Logistics Tracking
            $table->string('logistics_provider')->nullable()->after('billing_address_snapshot'); // e.g., BlueDart
            $table->string('tracking_number')->nullable()->after('logistics_provider');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_name', 'shipping_mobile', 'shipping_address_line_1', 
                'shipping_address_line_2', 'shipping_city', 'shipping_state', 
                'shipping_pincode', 'billing_address_snapshot', 'logistics_provider', 'tracking_number'
            ]);
        });
    }
};