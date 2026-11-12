<?php

namespace Database\Seeders;

use App\Models\SoilType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SoilTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Loamy' => 'A balanced mix of sand, silt and clay. Holds water and nutrients well.',
            'Sandy' => 'Gritty soil that drains quickly and holds fewer nutrients.',
            'Clay'  => 'Sticky soil that holds water and nutrients but drains slowly.',
        ];

        foreach ($types as $name => $description) {
            SoilType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description]
            );
        }
    }
}