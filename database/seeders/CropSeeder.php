<?php

namespace Database\Seeders;

use App\Models\Crop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CropSeeder extends Seeder
{
    public function run(): void
    {
        $crops = ['Wheat', 'Corn', 'Berseem Clover', 'Rice', 'Cotton'];

        foreach ($crops as $name) {
            Crop::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}