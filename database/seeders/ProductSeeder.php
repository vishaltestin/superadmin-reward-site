<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductTierPrice;
use App\Models\VoucherCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------
        // 1. CREATE CORE DEPENDENCIES (Brands & Categories)
        // ---------------------------------------------------
        $brandApple = Brand::firstOrCreate(['slug' => 'apple'], ['name' => 'Apple', 'is_active' => true]);
        $brandAmazon = Brand::firstOrCreate(['slug' => 'amazon'], ['name' => 'Amazon', 'is_active' => true]);
        $brandTaj = Brand::firstOrCreate(['slug' => 'taj-hotels'], ['name' => 'Taj Hotels', 'is_active' => true]);

        $catElectronics = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics', 'is_active' => true]);
        $catVouchers = Category::firstOrCreate(['slug' => 'gift-cards'], ['name' => 'Gift Cards', 'is_active' => true]);
        $catTravel = Category::firstOrCreate(['slug' => 'experiences'], ['name' => 'Experiences', 'is_active' => true]);

        // ---------------------------------------------------
        // 2. CREATE A PHYSICAL PRODUCT (With Variants)
        // ---------------------------------------------------
        $airpods = Product::create([
            'category_id' => $catElectronics->id,
            'brand_id' => $brandApple->id,
            'type' => 'physical',
            'name' => 'Apple AirPods Pro (2nd Gen)',
            'slug' => Str::slug('Apple AirPods Pro 2nd Gen'),
            'sku' => 'APP-PRO-02',
            'warranty_info' => '1 Year Apple Limited Warranty',
            'mrp' => 24900.00,
            'selling_price' => 22500.00,
            'gst_percentage' => 18.00,
            'short_description' => 'Rich, high-quality audio and voice with active noise cancellation.',
            'key_features' => ['Active Noise Cancellation', 'Spatial Audio', 'MagSafe Charging Case'],
            'specifications' => ['Color' => 'White', 'Connectivity' => 'Bluetooth 5.3'],
            'tags' => ['bestseller', 'audio', 'premium'],
            'sort_order' => 100,
            'type_data' => [
                'weight_grams' => 250,
                'dimensions' => '10x10x5 cm'
            ],
            'is_active' => true,
        ]);

        // Add Variants to the Physical Product
        ProductVariant::create([
            'product_id' => $airpods->id,
            'name' => 'AirPods Pro - Standard',
            'sku' => 'APP-PRO-02-STD',
            'mrp' => 24900.00,
            'selling_price' => 22500.00,
            'stock_quantity' => 50,
            'attributes' => ['Edition' => 'Standard', 'Case' => 'MagSafe'],
        ]);

        // Add Bulk Tier Pricing (B2B Discounts)
        ProductTierPrice::create(['product_id' => $airpods->id, 'min_quantity' => 10, 'selling_price' => 21000.00]);
        ProductTierPrice::create(['product_id' => $airpods->id, 'min_quantity' => 50, 'selling_price' => 19500.00]);

        // ---------------------------------------------------
        // 3. CREATE A DIGITAL VOUCHER (With Code Vault)
        // ---------------------------------------------------
        $amazonCard = Product::create([
            'category_id' => $catVouchers->id,
            'brand_id' => $brandAmazon->id,
            'type' => 'digital',
            'name' => '₹500 Amazon Gift Card',
            'slug' => Str::slug('500 Amazon Gift Card'),
            'sku' => 'AMZ-GC-500',
            'mrp' => 500.00,
            'selling_price' => 500.00,
            'gst_percentage' => 0.00, // Vouchers usually don't have GST applied at purchase
            'short_description' => 'Give the gift of endless choices with an Amazon Gift Card.',
            'terms_and_conditions' => 'Valid for 1 year from the date of issue. Cannot be reloaded.',
            'tags' => ['gift', 'digital', 'instant'],
            'sort_order' => 90,
            'type_data' => [
                'redemptionLink' => 'https://www.amazon.in/vouchers',
                'storeName' => 'Amazon India',
                'backgroundColor' => '#FF9900',
                'validUntil' => now()->addYear()->format('Y-m-d'),
            ],
            'is_active' => true,
        ]);

        // Stock the "Code Vault" with 5 unique codes ready to be bought
        for ($i = 1; $i <= 5; $i++) {
            VoucherCode::create([
                'product_id' => $amazonCard->id,
                'code' => 'AMZN-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'is_used' => false,
            ]);
        }

        // ---------------------------------------------------
        // 4. CREATE AN EXPERIENCE (Travel)
        // ---------------------------------------------------
        $tajStay = Product::create([
            'category_id' => $catTravel->id,
            'brand_id' => $brandTaj->id,
            'type' => 'experience',
            'name' => 'Luxury Weekend at Taj Mahal Palace',
            'slug' => Str::slug('Luxury Weekend Taj Mahal Palace'),
            'sku' => 'EXP-TAJ-MUM',
            'mrp' => 45000.00,
            'selling_price' => 38000.00,
            'gst_percentage' => 18.00,
            'short_description' => 'A breathtaking 2-night stay at the iconic Taj Mahal Palace, Mumbai.',
            'key_features' => ['Sea View Room', 'Complimentary Breakfast', 'Spa Access'],
            'tags' => ['luxury', 'weekend', 'couple'],
            'sort_order' => 80,
            'type_data' => [
                'destination' => 'Mumbai, Maharashtra',
                'duration' => '2 Nights, 3 Days',
                'groupSize' => '2 Adults',
                'departureDate' => now()->addDays(30)->format('Y-m-d'),
                'includes_excludes' => 'Includes: Breakfast, WiFi. Excludes: Flights, Airport Transfers.',
            ],
            'is_active' => true,
        ]);

        // Add Variants (Room Types for the Travel Experience)
        ProductVariant::create([
            'product_id' => $tajStay->id,
            'name' => 'Luxury Sea View',
            'sku' => 'EXP-TAJ-MUM-SEA',
            'mrp' => 45000.00,
            'selling_price' => 38000.00,
            'attributes' => ['Room Type' => 'Sea View', 'Meals' => 'Breakfast Only'],
        ]);

        ProductVariant::create([
            'product_id' => $tajStay->id,
            'name' => 'Taj Club Suite (Premium)',
            'sku' => 'EXP-TAJ-MUM-CLUB',
            'mrp' => 65000.00,
            'selling_price' => 55000.00,
            'attributes' => ['Room Type' => 'Club Suite', 'Meals' => 'All Inclusive + Butler'],
        ]);
    }
}