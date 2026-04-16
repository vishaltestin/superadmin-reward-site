<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Electronics' => [
                'Smartphones', 'Audio & Accessories', 'Laptops', 'Televisions'
            ],
            'Merchandise' => [
                'Bags', 'Stationery', 'Executive Kits', 'Apparel', 'Work Essentials', 'Bottles', 'Mugs'
            ],
            'Home Appliances' => [
                'Kitchen Appliances'
            ],
            'Home & Living' => [],
            'Travel' => [],
            'Gadgets' => [],
            'Automotive' => [],
            'Footwear' => [],
            'Fashion Accessories' => [],
            'Charity' => [],
        ];

        $sortOrder = 1;

        foreach ($catalog as $parentName => $children) {
            $parent = Category::firstOrCreate([
                'name' => $parentName,
            ], [
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);

            // Create Children and attach to Parent
            foreach ($children as $childName) {
                Category::firstOrCreate([
                    'name' => $childName,
                ], [
                    'parent_id' => $parent->id,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]);
            }
        }
    }
}