<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VoucherCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure we have a Company and a User to attach these orders to
        $company = Company::firstOrCreate(
            ['name' => 'Acme Corp'],
            ['is_active' => true] // Adjust if your Company model requires other fields
        );

        $user = User::firstOrCreate(
            ['email' => 'john.doe@acmecorp.com'],
            [
                'name' => 'John Doe',
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
            ]
        );

        // Fetch the products we seeded earlier
        $airpods = Product::where('sku', 'APP-PRO-02')->first();
        $amazonCard = Product::where('sku', 'AMZ-GC-500')->first();
        $tajStay = Product::where('sku', 'EXP-TAJ-MUM')->first();

        if (!$airpods || !$amazonCard || !$tajStay) {
            $this->command->warn('Products not found! Please run `php artisan db:seed --class=ProductSeeder` first.');
            return;
        }

        // ---------------------------------------------------
        // ORDER 1: Physical Item (AirPods) - Partially paid with points
        // ---------------------------------------------------
        $variantAirpods = ProductVariant::where('product_id', $airpods->id)->first();
        $unitPrice1 = $variantAirpods ? $variantAirpods->selling_price : $airpods->selling_price;
        $gstAmount1 = $unitPrice1 * ($airpods->gst_percentage / 100);
        $total1 = $unitPrice1 + $gstAmount1;

        $order1 = Order::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'total_amount' => $total1,
            'gst_total' => $gstAmount1,
            'points_used' => 10000, // User burned 10,000 points
            'fiat_paid' => $total1 - 10000, // Paid the rest on Credit Card
            'payment_gateway_reference' => 'pay_' . Str::random(14),
            'status' => 'processing', // Warehouse is packing it
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $airpods->id,
            'product_variant_id' => $variantAirpods?->id,
            'product_name' => $airpods->name,
            'quantity' => 1,
            'unit_price' => $unitPrice1,
            'unit_gst_percentage' => $airpods->gst_percentage,
            'total_price' => $unitPrice1,
            'delivery_status' => 'pending',
        ]);

        // ---------------------------------------------------
        // ORDER 2: Digital Voucher - Fully paid with points & Code Claimed
        // ---------------------------------------------------
        $order2 = Order::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'total_amount' => $amazonCard->selling_price,
            'gst_total' => 0, // Vouchers usually don't have GST upfront
            'points_used' => $amazonCard->selling_price, // 100% covered by points
            'fiat_paid' => 0,
            'status' => 'completed', // Digital items complete instantly
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $amazonCard->id,
            'product_name' => $amazonCard->name,
            'quantity' => 1,
            'unit_price' => $amazonCard->selling_price,
            'unit_gst_percentage' => 0,
            'total_price' => $amazonCard->selling_price,
            'delivery_status' => 'delivered', // Sent via email
        ]);

        // SIMULATE THE VAULT: Find an unused code and assign it to John
        $availableCode = VoucherCode::where('product_id', $amazonCard->id)
            ->where('is_used', false)
            ->first();

        if ($availableCode) {
            $availableCode->update([
                'is_used' => true,
                'issued_to_user_id' => $user->id,
                'issued_at' => now(),
            ]);
        }

        // ---------------------------------------------------
        // ORDER 3: Travel Experience - Fully paid with Cash (Fiat)
        // ---------------------------------------------------
        $variantTaj = ProductVariant::where('product_id', $tajStay->id)->first();
        $unitPrice3 = $variantTaj ? $variantTaj->selling_price : $tajStay->selling_price;
        $gstAmount3 = $unitPrice3 * ($tajStay->gst_percentage / 100);
        $total3 = $unitPrice3 + $gstAmount3;

        $order3 = Order::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'total_amount' => $total3,
            'gst_total' => $gstAmount3,
            'points_used' => 0, // Used 0 points, purely cash transaction
            'fiat_paid' => $total3,
            'payment_gateway_reference' => 'pay_' . Str::random(14),
            'status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $tajStay->id,
            'product_variant_id' => $variantTaj?->id,
            'product_name' => $tajStay->name,
            'quantity' => 1,
            'unit_price' => $unitPrice3,
            'unit_gst_percentage' => $tajStay->gst_percentage,
            'total_price' => $unitPrice3,
            'delivery_status' => 'pending', // Waiting for hotel confirmation
        ]);
    }
}