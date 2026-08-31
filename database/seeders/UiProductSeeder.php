<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductTierPrice;
use App\Models\ProductVariant;
use App\Models\VoucherCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UI TEST CATALOG — seeds every product shape the storefront can render.
 *
 * Run:            php artisan db:seed --class=UiProductSeeder
 * Re-runnable:    yes (updateOrCreate by slug / sku — no duplicates)
 * Images:         generates real color-matched PNGs via GD into
 *                 storage/app/public/qa/ (needs `php artisan storage:link`).
 *                 Without GD the image fields stay NULL, which itself
 *                 exercises the "no image" placeholder path.
 *
 * What it covers, by product (see the table printed after seeding):
 *  - Color (hex swatch) x Size matrix, out-of-stock combo, impossible combo
 *  - Color (named -> fallback initials swatch), variant price bump, NULL mrp
 *  - Size-only single axis; single-variant product; zero-variant product
 *  - Capacity axis, variant-level tier prices + product-level tiers
 *  - Hidden (is_active=false) variant inside a live product
 *  - Weight axis (grocery), Weight x Packaging, 3-axis matrix (Size x Weight x Color)
 *  - Digital vouchers: codes in stock, sold out (all used), denomination variants
 *  - Experiences with Duration x Add-on; single expensive variant (price formatting)
 *  - Company overrides: excluded product, white-label name/price, variant price override
 *  - Inactive product (negative test), no-image product, video_url product,
 *    long name/long description, mrp == selling (no discount badge), Re. 1 item
 */
class UiProductSeeder extends Seeder
{
    private bool $gd;

    public function run(): void
    {
        $this->gd = extension_loaded('gd');

        // ------------------------------------------------------------------
        // 0. BRANDS + QA CATEGORIES (flat — storefront scopes by PRIMARY category)
        // ------------------------------------------------------------------
        $brandQa     = Brand::firstOrCreate(['slug' => 'qa-house-brand'], ['name' => 'QA House Brand', 'is_active' => true]);
        $brandSony   = Brand::firstOrCreate(['slug' => 'qa-sonic'], ['name' => 'QA Sonic', 'is_active' => true]);

        $cats = collect([
            'qa-apparel'    => 'QA Apparel',
            'qa-electronics' => 'QA Electronics',
            'qa-grocery'    => 'QA Grocery',
            'qa-vouchers'   => 'QA Gift Cards',
            'qa-experiences' => 'QA Experiences',
            'qa-misc'       => 'QA Edge Cases',
        ])->mapWithKeys(function (string $name, string $slug) {
            return [$slug => Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true, 'sort_order' => 500, 'description' => 'QA UI-test category']
            )];
        });

        // ------------------------------------------------------------------
        // 1. APPAREL
        // ------------------------------------------------------------------

        // 1a. Color (HEX -> true swatches) x Size matrix. 8 of 9 combos exist;
        //     Blue/L intentionally missing (impossible-combo state), Black/M
        //     stock 0 (out-of-stock state), White/S stock 3 (low stock).
        $tshirt = $this->product([
            'category_id'       => $cats['qa-apparel']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'physical',
            'name'              => 'QA Classic T-Shirt (Color x Size Matrix)',
            'slug'              => 'qa-classic-t-shirt',
            'sku'               => 'QA-TSHIRT',
            'warranty_info'     => '6 months stitching warranty',
            'mrp'               => 999.00,
            'selling_price'     => 599.00,
            'gst_percentage'    => 5.00,
            'short_description' => 'Hex color swatches + size pills. One combo impossible (Blue/L), one out of stock (Black/M), one low stock (White/S).',
            'long_description'  => 'Tests the 2-axis variant matrix with true CSS-color swatches. Selecting Blue then L should show an unavailable/no-match state because that variant is deliberately not seeded.',
            'key_features'      => ['100% combed cotton', 'Pre-shrunk', 'Reinforced seams'],
            'specifications'    => ['Material' => 'Cotton', 'Fit' => 'Regular', 'Sleeve' => 'Short'],
            'main_image'        => $this->img('tshirt-main', '#1D4ED8', 'QA T-SHIRT', 'main image'),
            'gallery_images'    => array_filter([
                $this->img('tshirt-g1', '#000000', 'QA T-SHIRT', 'gallery 1'),
                $this->img('tshirt-g2', '#FFFFFF', 'QA T-SHIRT', 'gallery 2'),
                $this->img('tshirt-g3', '#1D4ED8', 'QA T-SHIRT', 'gallery 3'),
            ]),
            'is_active'         => true,
            'sort_order'        => 900,
            'type_data'         => ['weight_grams' => 180, 'dimensions' => '25x20x3 cm'],
        ], ['qa', 'apparel', 'tshirt', 'cotton']);

        $tshirtColors = ['Black' => '#000000', 'White' => '#FFFFFF', 'Blue' => '#1D4ED8'];
        foreach ($tshirtColors as $colorName => $hex) {
            foreach (['S', 'M', 'L'] as $size) {
                if ($colorName === 'Blue' && $size === 'L') {
                    continue; // impossible combo
                }
                $stock = 40;
                if ($colorName === 'Black' && $size === 'M') { $stock = 0; }
                if ($colorName === 'White' && $size === 'S') { $stock = 3; }

                $this->variant($tshirt, [
                    'name'          => "{$colorName} / {$size}",
                    'sku'           => "QA-TSHIRT-{$colorName}-{$size}",
                    'image'         => $this->img("tshirt-" . Str::slug($colorName), $hex, 'QA T-SHIRT', $colorName),
                    'mrp'           => 999.00,
                    'selling_price' => 599.00,
                    'stock_quantity' => $stock,
                    'attributes'    => ['Color' => $hex, 'Size' => $size],
                ]);
            }
        }

        // 1b. Named colors (fallback initials swatch — NOT valid CSS colors),
        //     XXL variant costs more, one variant has NULL mrp (no strikethrough).
        $hoodie = $this->product([
            'category_id'       => $cats['qa-apparel']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'physical',
            'name'              => 'QA Hoodie (Named Colors, Fallback Swatch)',
            'slug'              => 'qa-hoodie-named-colors',
            'sku'               => 'QA-HOODIE',
            'mrp'               => 2499.00,
            'selling_price'     => 1799.00,
            'gst_percentage'    => 12.00,
            'short_description' => 'Named colors render the initials-fallback swatch. XXL is +Rs.300. Midnight Black / XXL has no MRP.',
            'key_features'      => ['Fleece-lined', 'Kangaroo pocket', 'Unisex'],
            'specifications'    => ['Fabric' => 'Cotton Fleece', 'GSM' => '320'],
            'main_image'        => $this->img('hoodie-main', '#111827', 'QA HOODIE', 'main image'),
            'is_active'         => true,
            'sort_order'        => 890,
        ], ['qa', 'apparel', 'hoodie']);

        foreach (['Midnight Black', 'Heather Grey', 'Forest Green'] as $colorName) {
            foreach (['M', 'XXL'] as $size) {
                $this->variant($hoodie, [
                    'name'          => "{$colorName} / {$size}",
                    'sku'           => 'QA-HOODIE-' . Str::slug($colorName) . "-{$size}",
                    'image'         => $this->img('hoodie-' . Str::slug($colorName), '#6B7280', 'QA HOODIE', $colorName),
                    'mrp'           => ($colorName === 'Midnight Black' && $size === 'XXL') ? null : 2499.00,
                    'selling_price' => $size === 'XXL' ? 2099.00 : 1799.00,
                    'stock_quantity' => 25,
                    'attributes'    => ['Color' => $colorName, 'Size' => $size],
                ]);
            }
        }

        // 1c. Size-only single axis; L has NULL mrp (no per-variant strikethrough).
        $socks = $this->product([
            'category_id'       => $cats['qa-apparel']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Socks 3-Pack (Size Only)',
            'slug'              => 'qa-socks-3-pack',
            'sku'               => 'QA-SOCKS',
            'mrp'               => 299.00,
            'selling_price'     => 249.00,
            'short_description' => 'Single axis: three size pills, no swatches.',
            'main_image'        => $this->img('socks-main', '#F59E0B', 'QA SOCKS', 'main image'),
            'is_active'         => true,
            'sort_order'        => 880,
        ], ['qa', 'apparel', 'socks']);

        foreach (['S' => [249.00, 299.00], 'M' => [249.00, 299.00], 'L' => [249.00, null]] as $size => [$price, $mrp]) {
            $this->variant($socks, [
                'name'          => "Size {$size}",
                'sku'           => "QA-SOCKS-{$size}",
                'mrp'           => $mrp,
                'selling_price' => $price,
                'stock_quantity' => 120,
                'attributes'    => ['Size' => $size],
            ]);
        }

        // 1d. Single-variant product (one option group with one option).
        $cap = $this->product([
            'category_id'       => $cats['qa-apparel']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Cap (Single Variant)',
            'slug'              => 'qa-cap-single-variant',
            'sku'               => 'QA-CAP',
            'mrp'               => 899.00,
            'selling_price'     => 699.00,
            'short_description' => 'Exactly one variant — the picker should render without confusion.',
            'main_image'        => $this->img('cap-main', '#0EA5E9', 'QA CAP', 'main image'),
            'is_active'         => true,
            'sort_order'        => 870,
        ], ['qa', 'apparel', 'cap']);
        $this->variant($cap, [
            'name'          => 'Sky Blue',
            'sku'           => 'QA-CAP-SKY',
            'image'         => $this->img('cap-sky', '#0EA5E9', 'QA CAP', 'Sky Blue'),
            'mrp'           => 899.00,
            'selling_price' => 699.00,
            'stock_quantity' => 60,
            'attributes'    => ['Color' => '#0EA5E9'],
        ]);

        // ------------------------------------------------------------------
        // 2. ELECTRONICS
        // ------------------------------------------------------------------

        // 2a. Capacity x Color; prices ascend with capacity; product AND variant tiers.
        $phone = $this->product([
            'category_id'       => $cats['qa-electronics']->id,
            'brand_id'          => $brandSony->id,
            'type'              => 'physical',
            'name'              => 'QA Smartphone (Capacity x Color, Tier Pricing)',
            'slug'              => 'qa-smartphone-capacity-color',
            'sku'               => 'QA-PHONE',
            'warranty_info'     => '1 year manufacturer warranty',
            'mrp'               => 72999.00,
            'selling_price'     => 62999.00,
            'gst_percentage'    => 18.00,
            'short_description' => 'Three storage tiers x two colors. Bulk slabs exist at product level and variant level.',
            'key_features'      => ['AMOLED display', '5000 mAh', 'IP68', '5 years of updates'],
            'specifications'    => [
                'Display' => '6.7" AMOLED', 'Chipset' => 'Octa-core', 'RAM' => '8 GB',
                'Rear Camera' => '50 MP + 12 MP', 'Front Camera' => '32 MP',
                'Battery' => '5000 mAh', 'Charging' => '67 W wired', 'Water Resistance' => 'IP68',
            ],
            'main_image'        => $this->img('phone-main', '#374151', 'QA PHONE', 'Graphite'),
            'gallery_images'    => array_filter([
                $this->img('phone-g1', '#9CA3AF', 'QA PHONE', 'Silver'),
                $this->img('phone-g2', '#374151', 'QA PHONE', 'back'),
                $this->img('phone-g3', '#111827', 'QA PHONE', 'box'),
                $this->img('phone-g4', '#1D4ED8', 'QA PHONE', 'in hand'),
            ]),
            'video_url'         => null,
            'is_active'         => true,
            'sort_order'        => 860,
            'type_data'         => ['weight_grams' => 195, 'dimensions' => '16x7.6x0.8 cm'],
        ], ['qa', 'electronics', 'phone', 'smartphone']);

        $capacities = ['128 GB' => 62999.00, '256 GB' => 71999.00, '512 GB' => 89999.00];
        $phoneColors = ['Graphite' => '#374151', 'Silver' => '#9CA3AF'];
        $graphite128 = null;
        foreach ($capacities as $cap => $price) {
            foreach ($phoneColors as $colorName => $hex) {
                $v = $this->variant($phone, [
                    'name'          => "{$cap} / {$colorName}",
                    'sku'           => 'QA-PHONE-' . str_replace(' ', '', $cap) . '-' . Str::slug($colorName),
                    'image'         => $this->img('phone-' . Str::slug($colorName), $hex, 'QA PHONE', $colorName),
                    'mrp'           => $price + 10000,
                    'selling_price' => $price,
                    'stock_quantity' => 15,
                    'attributes'    => ['Capacity' => $cap, 'Color' => $hex],
                ]);
                if ($cap === '128 GB' && $colorName === 'Graphite') {
                    $graphite128 = $v;
                }
            }
        }
        // Product-level bulk slabs + a slab tied to ONE variant (128 GB Graphite).
        $this->tier($phone, 3, 61999.00);
        $this->tier($phone, 10, 59999.00);
        if ($graphite128) {
            $this->tier($phone, 5, 58999.00, $graphite128);
        }

        // 2b. Color-only; one DISABLED variant that must never appear in the UI.
        $speaker = $this->product([
            'category_id'       => $cats['qa-electronics']->id,
            'brand_id'          => $brandSony->id,
            'name'              => 'QA Bluetooth Speaker (Hidden Variant)',
            'slug'              => 'qa-speaker-hidden-variant',
            'sku'               => 'QA-SPEAKER',
            'mrp'               => 5999.00,
            'selling_price'     => 4499.00,
            'gst_percentage'    => 18.00,
            'short_description' => 'Five swatches seeded, one (Onyx) is is_active=false — only four should render.',
            'main_image'        => $this->img('speaker-main', '#EF4444', 'QA SPEAKER', 'main image'),
            'is_active'         => true,
            'sort_order'        => 850,
        ], ['qa', 'electronics', 'speaker', 'bluetooth']);
        foreach (['Crimson' => '#EF4444', 'Emerald' => '#10B981', 'Azure' => '#3B82F6', 'Amber' => '#F59E0B'] as $c => $hex) {
            $this->variant($speaker, [
                'name'          => $c,
                'sku'           => 'QA-SPEAKER-' . Str::slug($c),
                'image'         => $this->img('speaker-' . Str::slug($c), $hex, 'QA SPEAKER', $c),
                'mrp'           => 5999.00,
                'selling_price' => 4499.00,
                'stock_quantity' => 30,
                'attributes'    => ['Color' => $hex],
            ]);
        }
        $this->variant($speaker, [
            'name'          => 'Onyx (disabled)',
            'sku'           => 'QA-SPEAKER-ONYX',
            'image'         => $this->img('speaker-onyx', '#111827', 'QA SPEAKER', 'Onyx'),
            'mrp'           => 5999.00,
            'selling_price' => 4499.00,
            'stock_quantity' => 30,
            'attributes'    => ['Color' => '#111827'],
            'is_active'     => false,
        ]);

        // 2c. Single variant carrying TWO attributes (Weight + Color).
        $cable = $this->product([
            'category_id'       => $cats['qa-electronics']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA USB-C Cable (Weight Attribute)',
            'slug'              => 'qa-usb-c-cable',
            'sku'               => 'QA-CABLE',
            'mrp'               => 599.00,
            'selling_price'     => 399.00,
            'short_description' => 'One variant, two attribute groups (Weight pill + Color swatch).',
            'main_image'        => $this->img('cable-main', '#111827', 'QA CABLE', 'main image'),
            'gallery_images'    => array_filter([$this->img('cable-g1', '#4B5563', 'QA CABLE', 'gallery 1')]),
            'is_active'         => true,
            'sort_order'        => 840,
        ], ['qa', 'electronics', 'cable']);
        $this->variant($cable, [
            'name'          => '1 m / Slate',
            'sku'           => 'QA-CABLE-1M',
            'mrp'           => 599.00,
            'selling_price' => 399.00,
            'stock_quantity' => 200,
            'attributes'    => ['Weight' => '50 g', 'Color' => '#111827'],
        ]);

        // 2d. NO variants at all + a steep 60% discount badge.
        $this->product([
            'category_id'       => $cats['qa-electronics']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Mystery Box (No Variants, 60% Off)',
            'slug'              => 'qa-mystery-box',
            'sku'               => 'QA-MYSTERY',
            'mrp'               => 1999.00,
            'selling_price'     => 799.00,
            'short_description' => 'has_variants=false path — no picker, straight CTA, big discount %.',
            'key_features'      => ['Surprise assortment', 'Value guaranteed above price'],
            'main_image'        => $this->img('mystery-main', '#7C3AED', 'QA MYSTERY BOX', 'what is inside?'),
            'is_active'         => true,
            'sort_order'        => 830,
        ], ['qa', 'mystery', 'surprise']);

        // 2e. Video tile: single image + video_url (Wave-6 video strip path).
        $this->product([
            'category_id'       => $cats['qa-electronics']->id,
            'brand_id'          => $brandSony->id,
            'name'              => 'QA Video Demo Product (Trailer Tile)',
            'slug'              => 'qa-video-demo',
            'sku'               => 'QA-VIDEO',
            'mrp'               => 1499.00,
            'selling_price'     => 1299.00,
            'short_description' => 'Single main image + video_url: the play tile must appear next to the thumbnail.',
            'key_features'      => ['Watch the trailer from the thumbnail strip'],
            'main_image'        => $this->img('video-main', '#0F172A', 'QA VIDEO DEMO', 'press play'),
            'video_url'         => 'https://www.youtube.com/embed/aqz-KE-bpKQ',
            'is_active'         => true,
            'sort_order'        => 820,
        ], ['qa', 'video', 'demo']);

        // 2f. Long name + long copy + big spec sheet + mrp == selling (no badge).
        $this->product([
            'category_id'       => $cats['qa-electronics']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Long Name Product With An Extremely verbose Title That Wraps Across Multiple Lines On Every Card And Detail Page Without Truncation Issues Whatsoever',
            'slug'              => 'qa-long-name-product',
            'sku'               => 'QA-LONGNAME',
            'mrp'               => 4999.00,
            'selling_price'     => 4999.00, // no discount — no strike-through badge
            'short_description' => 'Long title wrapping, 8-row spec table, 6 bullet features, long description, zero discount.',
            'long_description'  => str_repeat('This paragraph tests long-description layout, line heights and readable measure on the product detail page. ', 8),
            'key_features'      => ['Feature one', 'Feature two', 'Feature three', 'Feature four', 'Feature five', 'Feature six'],
            'specifications'    => [
                'Width' => '60 cm', 'Height' => '90 cm', 'Depth' => '45 cm', 'Weight' => '7.4 kg',
                'Material' => 'Engineered wood', 'Finish' => 'Matte lacquer', 'Assembly' => 'Tool-free', 'Warranty' => '3 years',
            ],
            'main_image'        => $this->img('long-main', '#334155', 'QA LONG NAME', 'very long title'),
            'is_active'         => true,
            'sort_order'        => 810,
        ], ['qa', 'layout', 'long']);

        // ------------------------------------------------------------------
        // 3. GROCERY
        // ------------------------------------------------------------------

        // 3a. Weight-only axis, price scales, low-stock + NULL-mrp cases.
        $almonds = $this->product([
            'category_id'       => $cats['qa-grocery']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Organic Almonds (Weight Axis)',
            'slug'              => 'qa-organic-almonds',
            'sku'               => 'QA-ALMOND',
            'mrp'               => 449.00,
            'selling_price'     => 349.00,
            'gst_percentage'    => 5.00,
            'short_description' => '250 g / 500 g / 1 kg / 2 kg. The 1 kg pack has no MRP; 1 kg is low stock.',
            'main_image'        => $this->img('almond-main', '#D97706', 'QA ALMONDS', 'main image'),
            'is_active'         => true,
            'sort_order'        => 800,
            'type_data'         => ['weight_grams' => 250],
        ], ['qa', 'grocery', 'almonds', 'dryfruit']);
        foreach ([
            '250 g' => [349.00, 449.00, 999], '500 g' => [649.00, 799.00, 500],
            '1 kg'  => [1199.00, null, 3], '2 kg' => [2199.00, 2599.00, 40],
        ] as $w => [$price, $mrp, $stock]) {
            $this->variant($almonds, [
                'name'          => $w,
                'sku'           => 'QA-ALMOND-' . str_replace(' ', '', $w),
                'mrp'           => $mrp,
                'selling_price' => $price,
                'stock_quantity' => $stock,
                'attributes'    => ['Weight' => $w],
            ]);
        }

        // 3b. Weight x Packaging (two non-color axes).
        $oil = $this->product([
            'category_id'       => $cats['qa-grocery']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Cold-Pressed Olive Oil (Weight x Packaging)',
            'slug'              => 'qa-olive-oil',
            'sku'               => 'QA-OIL',
            'mrp'               => 999.00,
            'selling_price'     => 699.00,
            'short_description' => 'Volume pills x packaging pills; glass bottle costs Rs.100 more.',
            'main_image'        => $this->img('oil-main', '#65A30D', 'QA OLIVE OIL', 'main image'),
            'is_active'         => true,
            'sort_order'        => 790,
        ], ['qa', 'grocery', 'oil']);
        foreach (['500 ml' => 699.00, '1 L' => 999.00] as $vol => $base) {
            foreach (['Glass Bottle' => 100, 'Pouch' => 0] as $pack => $surcharge) {
                $this->variant($oil, [
                    'name'          => "{$vol} / {$pack}",
                    'sku'           => 'QA-OIL-' . str_replace(' ', '', $vol) . '-' . Str::slug($pack),
                    'mrp'           => $base + 200,
                    'selling_price' => $base + $surcharge,
                    'stock_quantity' => 80,
                    'attributes'    => ['Weight' => $vol, 'Packaging' => $pack],
                ]);
            }
        }

        // 3c. THREE axes: Size x Weight x Color(Chocolate). 7 of 8 combos;
        //     Large/250 g/Milk not seeded, Small/500 g/Dark exists but stock 0.
        $hamper = $this->product([
            'category_id'       => $cats['qa-grocery']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Chocolate Hamper (Size x Weight x Color)',
            'slug'              => 'qa-chocolate-hamper',
            'sku'               => 'QA-HAMPER',
            'mrp'               => 1299.00,
            'selling_price'     => 899.00,
            'gst_percentage'    => 18.00,
            'short_description' => '3-axis matrix. One impossible combo (Large + 250 g + Milk), one out-of-stock combo (Small + 500 g + Dark).',
            'main_image'        => $this->img('hamper-main', '#4A2C2A', 'QA HAMPER', 'main image'),
            'is_active'         => true,
            'sort_order'        => 780,
        ], ['qa', 'grocery', 'chocolate', 'gift']);
        $hamperColors = ['Milk' => '#D2A679', 'Dark' => '#4A2C2A'];
        foreach (['Small' => 899.00, 'Large' => 1499.00] as $size => $price) {
            foreach (['250 g', '500 g'] as $w) {
                foreach ($hamperColors as $cName => $hex) {
                    if ($size === 'Large' && $w === '250 g' && $cName === 'Milk') {
                        continue; // impossible combo
                    }
                    $this->variant($hamper, [
                        'name'          => "{$size} / {$w} / {$cName}",
                        'sku'           => 'QA-HAMPER-' . Str::slug($size) . '-' . str_replace(' ', '', $w) . '-' . Str::slug($cName),
                        'image'         => $this->img('hamper-' . Str::slug($cName), $hex, 'QA HAMPER', $cName),
                        'mrp'           => $price + 400,
                        'selling_price' => $price,
                        'stock_quantity' => ($size === 'Small' && $w === '500 g' && $cName === 'Dark') ? 0 : 20,
                        'attributes'    => ['Size' => $size, 'Weight' => $w, 'Color' => $hex],
                    ]);
                }
            }
        }

        // ------------------------------------------------------------------
        // 4. DIGITAL VOUCHERS
        // ------------------------------------------------------------------

        // 4a. Healthy vault: 5 unused codes + 1 already used.
        $flipkart = $this->product([
            'category_id'       => $cats['qa-vouchers']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'digital',
            'name'              => 'QA Flipkart Rs.500 Voucher (Codes In Stock)',
            'slug'              => 'qa-flipkart-500-voucher',
            'sku'               => 'QA-FLIP-500',
            'mrp'               => 500.00,
            'selling_price'     => 500.00,
            'gst_percentage'    => 0.00,
            'short_description' => 'Claim flow: 5 unused codes, 1 burned. Claim, then watch the vault shrink.',
            'terms_and_conditions' => 'Valid 12 months. Single use per user.',
            'main_image'        => $this->img('flipkart-main', '#1E40AF', 'QA FLIPKART', 'Rs.500'),
            'is_active'         => true,
            'sort_order'        => 770,
            'type_data'         => [
                'redemptionLink' => 'https://www.flipkart.com/account/giftcard',
                'storeName'      => 'Flipkart',
                'backgroundColor' => '#1E40AF',
                'validUntil'     => now()->addYear()->format('Y-m-d'),
            ],
        ], ['qa', 'voucher', 'flipkart', 'gift']);
        $this->codes($flipkart, 5, 1);

        // 4b. Sold-out voucher: every code is_used=true.
        $soldOut = $this->product([
            'category_id'       => $cats['qa-vouchers']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'digital',
            'name'              => 'QA Sold-Out Voucher (All Codes Used)',
            'slug'              => 'qa-sold-out-voucher',
            'sku'               => 'QA-FLIP-SOLDOUT',
            'mrp'               => 1000.00,
            'selling_price'     => 1000.00,
            'short_description' => 'Vault empty — the claim CTA must reflect unavailability.',
            'main_image'        => $this->img('soldout-main', '#7F1D1D', 'QA SOLD OUT', 'no codes left'),
            'is_active'         => true,
            'sort_order'        => 760,
            'type_data'         => ['redemptionLink' => 'https://example.com/redeem', 'storeName' => 'QA Store'],
        ], ['qa', 'voucher', 'soldout']);
        $this->codes($soldOut, 0, 3);

        // 4c. Digital WITH denomination variants.
        $denom = $this->product([
            'category_id'       => $cats['qa-vouchers']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'digital',
            'name'              => 'QA Universal Gift Card (Denomination Variants)',
            'slug'              => 'qa-universal-gift-card',
            'sku'               => 'QA-UNIVERSAL',
            'mrp'               => 500.00,
            'selling_price'     => 500.00,
            'short_description' => 'Digital product with a variant picker (Rs.250 / 500 / 1000).',
            'main_image'        => $this->img('universal-main', '#0F766E', 'QA GIFT CARD', 'any amount'),
            'is_active'         => true,
            'sort_order'        => 750,
            'type_data'         => ['redemptionLink' => 'https://example.com/redeem', 'storeName' => 'Universal'],
        ], ['qa', 'voucher', 'denomination']);
        foreach ([250 => 250.00, 500 => 475.00, 1000 => 925.00] as $amount => $price) {
            $this->variant($denom, [
                'name'          => "Rs.{$amount}",
                'sku'           => "QA-UNIVERSAL-{$amount}",
                'mrp'           => $amount === 250 ? null : (float) $amount,
                'selling_price' => $price,
                'stock_quantity' => 0, // digital — stock lives in the code vault
                'attributes'    => ['Denomination' => "Rs.{$amount}"],
            ]);
        }
        $this->codes($denom, 6, 0);

        // ------------------------------------------------------------------
        // 5. EXPERIENCES
        // ------------------------------------------------------------------

        // 5a. Duration x Add-on matrix on an experience.
        $spa = $this->product([
            'category_id'       => $cats['qa-experiences']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'experience',
            'name'              => 'QA Spa Day (Duration x Add-on)',
            'slug'              => 'qa-spa-day',
            'sku'               => 'QA-SPA',
            'mrp'               => 2999.00,
            'selling_price'     => 2499.00,
            'gst_percentage'    => 18.00,
            'short_description' => '60/90 minutes x none/aromatherapy. Experience enquiry + checkout paths.',
            'key_features'      => ['Certified therapists', 'Steam access included'],
            'main_image'        => $this->img('spa-main', '#14B8A6', 'QA SPA DAY', 'relax'),
            'is_active'         => true,
            'sort_order'        => 740,
            'type_data'         => [
                'destination' => 'New Delhi',
                'duration'    => '60 or 90 minutes',
                'groupSize'   => '1 person',
                'departureDate' => now()->addDays(21)->format('Y-m-d'),
                'includes_excludes' => 'Includes: steam, towels. Excludes: transport.',
            ],
        ], ['qa', 'experience', 'spa', 'wellness']);
        foreach (['60 min' => 2499.00, '90 min' => 2999.00] as $dur => $base) {
            foreach (['None' => 0, 'Aromatherapy' => 300] as $addon => $extra) {
                $this->variant($spa, [
                    'name'          => "{$dur} / {$addon}",
                    'sku'           => 'QA-SPA-' . str_replace(' ', '', $dur) . '-' . Str::slug($addon),
                    'mrp'           => $base + 500,
                    'selling_price' => $base + $extra,
                    'stock_quantity' => 10,
                    'attributes'    => ['Duration' => $dur, 'Add-on' => $addon],
                ]);
            }
        }

        // 5b. Single very expensive variant — price formatting (₹2,49,999).
        $balloon = $this->product([
            'category_id'       => $cats['qa-experiences']->id,
            'brand_id'          => $brandQa->id,
            'type'              => 'experience',
            'name'              => 'QA Hot Air Balloon Ride (Big Ticket Price)',
            'slug'              => 'qa-balloon-ride',
            'sku'               => 'QA-BALLOON',
            'mrp'               => 299999.00,
            'selling_price'     => 249999.00,
            'short_description' => '₹2,49,999 — Indian-grouping price formatting on cards, PDP and checkout.',
            'main_image'        => $this->img('balloon-main', '#DB2777', 'QA BALLOON', 'up, up and away'),
            'is_active'         => true,
            'sort_order'        => 730,
            'type_data'         => [
                'destination' => 'Jaipur, Rajasthan',
                'duration'    => '1 hour flight',
                'groupSize'   => '2 adults',
                'departureDate' => now()->addDays(45)->format('Y-m-d'),
            ],
        ], ['qa', 'experience', 'balloon', 'luxury']);
        $this->variant($balloon, [
            'name'          => 'Sunrise Flight',
            'sku'           => 'QA-BALLOON-SUNRISE',
            'mrp'           => 299999.00,
            'selling_price' => 249999.00,
            'stock_quantity' => 4,
            'attributes'    => ['Slot' => 'Sunrise'],
        ]);

        // ------------------------------------------------------------------
        // 6. EDGE CASES + COMPANY CURATION
        // ------------------------------------------------------------------

        // 6a. Inactive product — must NEVER appear anywhere.
        $this->product([
            'category_id'       => $cats['qa-misc']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Invisible Product (is_active = false)',
            'slug'              => 'qa-invisible-product',
            'sku'               => 'QA-INVISIBLE',
            'mrp'               => 1999.00,
            'selling_price'     => 1499.00,
            'short_description' => 'Seeded inactive. If you can see this in the storefront, visibility filtering is broken.',
            'main_image'        => $this->img('invisible-main', '#DC2626', 'QA INVISIBLE', 'you cant see me'),
            'is_active'         => false,
            'sort_order'        => 720,
        ], ['qa', 'negative']);

        // 6b. No image, no gallery, no variants — placeholder + bare CTA.
        $this->product([
            'category_id'       => $cats['qa-misc']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA No-Image Product (Placeholder Path)',
            'slug'              => 'qa-no-image-product',
            'sku'               => 'QA-NOIMAGE',
            'mrp'               => 499.00,
            'selling_price'     => 299.00,
            'short_description' => 'main_image and gallery are NULL — the UI must fall back to a placeholder, not crash.',
            'main_image'        => null,
            'gallery_images'    => null,
            'is_active'         => true,
            'sort_order'        => 710,
        ], ['qa', 'placeholder']);

        // 6c. Re. 1 item — minimum checkout value edge.
        $this->product([
            'category_id'       => $cats['qa-misc']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA One Rupee Test Item',
            'slug'              => 'qa-one-rupee-item',
            'sku'               => 'QA-RE1',
            'mrp'               => 1.00,
            'selling_price'     => 1.00,
            'short_description' => 'Total = ₹1.00 — exercises the cheapest possible cart/checkout.',
            'main_image'        => $this->img('rupee-main', '#16A34A', 'RE 1', 'minimum checkout'),
            'is_active'         => true,
            'sort_order'        => 700,
        ], ['qa', 'cheap']);

        // 6d. Excluded for every company via company_product.is_excluded — hidden.
        $excluded = $this->product([
            'category_id'       => $cats['qa-misc']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Excluded Product (Hidden From Companies)',
            'slug'              => 'qa-excluded-product',
            'sku'               => 'QA-EXCLUDED',
            'mrp'               => 1599.00,
            'selling_price'     => 1299.00,
            'short_description' => 'Active product, excluded per-company. If it shows up, is_excluded filtering is broken.',
            'main_image'        => $this->img('excluded-main', '#FB923C', 'QA EXCLUDED', 'not for you'),
            'is_active'         => true,
            'sort_order'        => 690,
        ], ['qa', 'negative']);

        // 6e. White-label overrides for every company (name + price replaced).
        $whiteLabel = $this->product([
            'category_id'       => $cats['qa-misc']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA White-Label Base Product',
            'slug'              => 'qa-white-label-product',
            'sku'               => 'QA-WHITELABEL',
            'mrp'               => 4999.00,
            'selling_price'     => 3999.00,
            'short_description' => 'Catalog price 3999 — companies should see the override price 7999 and the override name instead.',
            'main_image'        => $this->img('whitelabel-main', '#4338CA', 'QA WHITE-LABEL', 'base product'),
            'is_active'         => true,
            'sort_order'        => 680,
        ], ['qa', 'override']);

        // 6f. Variant-level company price override.
        $variantOverride = $this->product([
            'category_id'       => $cats['qa-misc']->id,
            'brand_id'          => $brandQa->id,
            'name'              => 'QA Variant Price Override (Company Override On One Variant)',
            'slug'              => 'qa-variant-price-override',
            'sku'               => 'QA-VAROVERRIDE',
            'mrp'               => 1299.00,
            'selling_price'     => 999.00,
            'short_description' => 'The 1 kg variant is overridden to ₹1,999 for every company; other variants stay at catalog price.',
            'main_image'        => $this->img('varoverride-main', '#0891B2', 'QA VARIANT OVERRIDE', 'one price differs'),
            'is_active'         => true,
            'sort_order'        => 670,
        ], ['qa', 'override']);
        $overrideVariant = null;
        foreach (['250 g' => 999.00, '500 g' => 1599.00, '1 kg' => 2299.00] as $w => $price) {
            $v = $this->variant($variantOverride, [
                'name'          => $w,
                'sku'           => 'QA-VAROVERRIDE-' . str_replace(' ', '', $w),
                'mrp'           => $price + 300,
                'selling_price' => $price,
                'stock_quantity' => 50,
                'attributes'    => ['Weight' => $w],
            ]);
            if ($w === '1 kg') {
                $overrideVariant = $v;
            }
        }

        // ------------------------------------------------------------------
        // 7. TENANT WIRING — without this, NOTHING shows on a storefront.
        //    Attach every QA category to every company (existing pivot rows,
        //    including Wave-6 overrides, are left untouched).
        // ------------------------------------------------------------------
        $categoryIds = $cats->pluck('id');
        $companyCount = 0;

        foreach (Company::all() as $company) {
            $companyCount++;
            foreach ($categoryIds as $catId) {
                $exists = DB::table('category_company')
                    ->where('company_id', $company->id)
                    ->where('category_id', $catId)
                    ->exists();
                if (! $exists) {
                    DB::table('category_company')->insert([
                        'company_id'  => $company->id,
                        'category_id' => $catId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            // 6d — hide the excluded product from THIS company.
            $this->pivotRow('company_product', [
                'company_id' => $company->id, 'product_id' => $excluded->id,
            ], ['is_excluded' => true]);

            // 6e — white-label this product for THIS company.
            $this->pivotRow('company_product', [
                'company_id' => $company->id, 'product_id' => $whiteLabel->id,
            ], [
                'is_excluded' => false,
                'override_name' => 'QA Exclusive Reward (Renamed For Your Company)',
                'override_mrp' => 9999.00,
                'override_selling_price' => 7999.00,
            ]);

            // 6f — variant-level price override for THIS company.
            if ($overrideVariant) {
                $this->pivotRow('company_product_variant', [
                    'company_id' => $company->id, 'product_variant_id' => $overrideVariant->id,
                ], ['override_mrp' => 2599.00, 'override_selling_price' => 1999.00]);
            }
        }

        // ------------------------------------------------------------------
        // 8. REPORT
        // ------------------------------------------------------------------
        $variantCount = ProductVariant::whereIn('product_id', Product::where('slug', 'like', 'qa-%')->pluck('id'))->count();
        $codeCount = VoucherCode::whereIn('product_id', Product::where('slug', 'like', 'qa-%')->pluck('id'))->count();

        if ($this->command) {
            $this->command->info('');
            $this->command->info('QA UI catalog seeded: ' . Product::where('slug', 'like', 'qa-%')->count() . ' products, '
                . $variantCount . ' variants, ' . $codeCount . ' voucher codes, wired to ' . $companyCount . ' company/companies.');
            $this->command->table(
                ['Product (slug)', 'UI case to verify'],
                [
                    ['qa-classic-t-shirt',            'Hex color swatches x size; Blue+L impossible; Black/M out of stock; White/S low stock'],
                    ['qa-hoodie-named-colors',        'Named colors -> fallback initials swatch; XXL +price; NULL mrp variant'],
                    ['qa-socks-3-pack',               'Single axis (size only); one variant without MRP'],
                    ['qa-cap-single-variant',         'Exactly one variant'],
                    ['qa-smartphone-capacity-color',  'Capacity x color; ascending prices; product + variant tier slabs; 4-image gallery; 8-row specs'],
                    ['qa-speaker-hidden-variant',     '5 swatches seeded, only 4 render (1 disabled)'],
                    ['qa-usb-c-cable',                'Single variant with two attribute groups (Weight + Color)'],
                    ['qa-mystery-box',                'No variants; 60% discount badge'],
                    ['qa-video-demo',                 'Single image + video_url -> play tile in thumbnail strip'],
                    ['qa-long-name-product',          'Wrapping title; long copy; 8 specs / 6 features; no discount badge'],
                    ['qa-organic-almonds',            'Weight axis; low stock; NULL mrp'],
                    ['qa-olive-oil',                  'Weight x Packaging (two non-color axes)'],
                    ['qa-chocolate-hamper',           '3-axis matrix (Size x Weight x Color); impossible + out-of-stock combos'],
                    ['qa-flipkart-500-voucher',       'Digital claim flow; 5 codes free, 1 used'],
                    ['qa-sold-out-voucher',           'Voucher vault empty -> sold-out state'],
                    ['qa-universal-gift-card',        'Digital product WITH denomination variants'],
                    ['qa-spa-day',                    'Experience; Duration x Add-on; type_data block'],
                    ['qa-balloon-ride',               '₹2,49,999 price formatting'],
                    ['qa-invisible-product',          'NEGATIVE: is_active=false — must never appear'],
                    ['qa-excluded-product',           'NEGATIVE: company is_excluded — must never appear'],
                    ['qa-white-label-product',        'Renders as "QA Exclusive Reward..." at ₹7,999 via company overrides'],
                    ['qa-variant-price-override',     '1 kg variant shows ₹1,999 for companies, others at catalog price'],
                    ['qa-no-image-product',           'NULL images -> placeholder, no crash'],
                    ['qa-one-rupee-item',             '₹1.00 minimum checkout edge'],
                ]
            );
            if (! $this->gd) {
                $this->command->warn('PHP GD not installed — all images seeded as NULL (placeholder path). Install php-gd and re-run for color swatch images.');
            }
            if (! file_exists(public_path('storage')) && ! is_link(public_path('storage'))) {
                $this->command->warn('public/storage link missing — run: php artisan storage:link (else images 404).');
            }
        }
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function product(array $data, array $tags = []): Product
    {
        $product = Product::updateOrCreate(
            ['slug' => $data['slug']],
            collect($data)->except('slug')->all()
        );

        // `tags` is not mass-assignable on Product; set it explicitly so
        // storefront tag search (orWhereJsonContains) has data to match.
        if (! empty($tags)) {
            $product->tags = array_values($tags);
            $product->save();
        }

        return $product;
    }

    private function variant(Product $product, array $data): ProductVariant
    {
        return ProductVariant::updateOrCreate(
            ['sku' => $data['sku']],
            collect($data)->except('sku')->merge(['product_id' => $product->id])->all()
        );
    }

    private function tier(Product $product, int $minQty, float $price, ?ProductVariant $variant = null): void
    {
        ProductTierPrice::firstOrCreate(
            [
                'product_id'          => $product->id,
                'product_variant_id'  => $variant?->id,
                'min_quantity'        => $minQty,
            ],
            ['selling_price' => $price]
        );
    }

    private function codes(Product $product, int $unused, int $used): void
    {
        $existing = VoucherCode::where('product_id', $product->id)->count();
        $need = ($unused + $used) - $existing;

        for ($i = 0; $i < $need; $i++) {
            VoucherCode::create([
                'product_id' => $product->id,
                'code'       => 'QA-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'pin'        => (string) random_int(1000, 9999),
                'is_used'    => $i < $used,
                'expires_at' => now()->addYear(),
            ]);
        }
    }

    private function pivotRow(string $table, array $key, array $fields): void
    {
        DB::table($table)->updateOrInsert(
            $key,
            array_merge($fields, ['created_at' => now(), 'updated_at' => now()])
        );
    }

    /**
     * Generate a 600x600 color-matched PNG placeholder into
     * storage/app/public/qa/. Returns the storage-relative path
     * ("qa/name.png") the API wraps with asset('storage/...'),
     * or null when GD is unavailable (placeholder path test).
     */
    private function img(string $name, string $hex, string $line1, string $line2 = ''): ?string
    {
        if (! $this->gd) {
            return null;
        }

        try {
            $dir = storage_path('app/public/qa');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $rgb = sscanf(str_replace('#', '', $hex), '%02x%02x%02x');
            $im = imagecreatetruecolor(600, 600);
            $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
            imagefill($im, 0, 0, $bg);

            $white = imagecolorallocate($im, 255, 255, 255);
            $frame = imagecolorallocate($im, 255, 255, 255);
            imagerectangle($im, 10, 10, 589, 589, $frame);

            imagestring($im, 5, 60, 260, mb_substr($line1, 0, 28), $white);
            if ($line2 !== '') {
                imagestring($im, 5, 60, 300, mb_substr($line2, 0, 28), $white);
            }

            $path = "{$dir}/{$name}.png";
            imagepng($im, $path);
            imagedestroy($im);

            return "qa/{$name}.png";
        } catch (\Throwable) {
            return null;
        }
    }
}