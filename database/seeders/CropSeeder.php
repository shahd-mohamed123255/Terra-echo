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
            [
                'name' => 'Wheat',
                'slug' => 'wheat',
                'category' => 'cereal',
                'water_mm' => 500,
                'water_m3_per_ha' => 5000,
                'season' => 'winter',
                'season_months' => 'Nov–Apr',
                'duration_min_days' => 140,
                'duration_max_days' => 160,
                'preferred_soil' => 'Clay, Loam',
            ],
            [
                'name' => 'Corn',
                'slug' => 'corn',
                'category' => 'cereal',
                'water_mm' => 650,
                'water_m3_per_ha' => 6500,
                'season' => 'summer',
                'season_months' => 'May–Sep',
                'duration_min_days' => 110,
                'duration_max_days' => 130,
                'preferred_soil' => 'Loam, Clay',
            ],
            [
                'name' => 'Beans',
                'slug' => 'beans',
                'category' => 'legume',
                'water_mm' => 400,
                'water_m3_per_ha' => 4000,
                'season' => 'winter',
                'season_months' => 'Oct–Mar',
                'duration_min_days' => 100,
                'duration_max_days' => 120,
                'preferred_soil' => 'Loam, Clay',
            ],
            [
                'name' => 'Berseem Clover',
                'slug' => 'berseem-clover',
                'category' => 'forage',
                'water_mm' => 520,
                'water_m3_per_ha' => 5200,
                'season' => 'winter',
                'season_months' => 'Oct–May',
                'duration_min_days' => 130,
                'duration_max_days' => 150,
                'preferred_soil' => 'Clay, Loam',
            ],
            [
                'name' => 'Rice',
                'slug' => 'rice',
                'category' => 'cereal',
                'water_mm' => 1200,
                'water_m3_per_ha' => 12000,
                'season' => 'summer',
                'season_months' => 'May–Oct',
                'duration_min_days' => 120,
                'duration_max_days' => 140,
                'preferred_soil' => 'Clay',
            ],
            [
                'name' => 'Cotton',
                'slug' => 'cotton',
                'category' => 'fiber',
                'water_mm' => 900,
                'water_m3_per_ha' => 9000,
                'season' => 'summer',
                'season_months' => 'Mar–Oct',
                'duration_min_days' => 150,
                'duration_max_days' => 180,
                'preferred_soil' => 'Clay, Loam',
            ],
            [
                'name' => 'Alfalfa',
                'slug' => 'alfalfa',
                'category' => 'forage',
                'water_mm' => 950,
                'water_m3_per_ha' => 9500,
                'season' => 'winter',
                'season_months' => 'Year-round',
                'duration_min_days' => 180,
                'duration_max_days' => 365,
                'preferred_soil' => 'Loam, Clay',
            ],
            [
                'name' => 'Barley',
                'slug' => 'barley',
                'category' => 'cereal',
                'water_mm' => 350,
                'water_m3_per_ha' => 3500,
                'season' => 'winter',
                'season_months' => 'Nov–Apr',
                'duration_min_days' => 120,
                'duration_max_days' => 140,
                'preferred_soil' => 'Sandy, Loam',
            ],
            [
                'name' => 'Potato',
                'slug' => 'potato',
                'category' => 'tuber',
                'water_mm' => 550,
                'water_m3_per_ha' => 5500,
                'season' => 'winter',
                'season_months' => 'Oct–Feb',
                'duration_min_days' => 110,
                'duration_max_days' => 130,
                'preferred_soil' => 'Loam, Sandy',
            ],
            [
                'name' => 'Sugar Beet',
                'slug' => 'sugar-beet',
                'category' => 'root',
                'water_mm' => 680,
                'water_m3_per_ha' => 6800,
                'season' => 'winter',
                'season_months' => 'Oct–May',
                'duration_min_days' => 170,
                'duration_max_days' => 190,
                'preferred_soil' => 'Loam, Clay',
            ],
            [
                'name' => 'Soybeans',
                'slug' => 'soybeans',
                'category' => 'legume',
                'water_mm' => 550,
                'water_m3_per_ha' => 5500,
                'season' => 'summer',
                'season_months' => 'May–Sep',
                'duration_min_days' => 110,
                'duration_max_days' => 130,
                'preferred_soil' => 'Loam, Clay',
            ],
            [
                'name' => 'Sorghum',
                'slug' => 'sorghum',
                'category' => 'cereal',
                'water_mm' => 450,
                'water_m3_per_ha' => 4500,
                'season' => 'summer',
                'season_months' => 'May–Sep',
                'duration_min_days' => 100,
                'duration_max_days' => 120,
                'preferred_soil' => 'Clay, Loam, Sandy',
            ],
        ];

        foreach ($crops as $data) {
            Crop::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
