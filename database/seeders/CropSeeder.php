<?php

namespace Database\Seeders;

use App\Models\Crop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CropSeeder extends Seeder
{
    public function run(): void
    {
        $crops = [
            'Wheat' => 'cereal',
            'Corn' => 'cereal',
            'Beans' => 'legume',
            'Alfalfa' => 'forage',
            'Rice' => 'cereal',
            'Cotton' => 'fiber',
            'Berseem Clover' => 'forage',
        ];

        foreach ($crops as $name => $category) {
            Crop::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'category' => $category,
                ]
            );
        }
    }
}