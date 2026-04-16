<?php

namespace Database\Seeders;

use App\Models\Vertical;
use Illuminate\Database\Seeder;

class VerticalSeeder extends Seeder
{
    public function run(): void
    {
        $verticals = [
            'Internal Employees',
            'External Client',
            'Channel Partners',
            'Auto - Dealers & Distributors',
            'Real Estate',
        ];

        foreach ($verticals as $vertical) {
            Vertical::firstOrCreate(['name' => $vertical]);
        }
    }
}