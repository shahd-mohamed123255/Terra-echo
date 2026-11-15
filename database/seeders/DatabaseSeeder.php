<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\SoilType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Soil Types and Crops
        $this->call([
            SoilTypeSeeder::class,
            CropSeeder::class,
        ]);

        // 2. Create or retrieve demo test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Demo Farmer',
                'password' => Hash::make('password'),
            ]
        );

        $clay = SoilType::where('slug', 'clay')->first() ?? SoilType::first();

        // 3. Create or retrieve demo active farm
        $farm = Farm::firstOrCreate(
            [
                'user_id' => $user->id,
                'city' => 'Giza',
            ],
            [
                'country' => 'Egypt',
                'governorate' => 'Giza',
                'latitude' => 29.98,
                'longitude' => 31.20,
                'area' => 5.0,
                'area_unit' => 'feddan',
                'soil_type_id' => $clay?->id,
                'irrigation_source' => 'Nile Canal',
                'priority' => 'balanced',
                'target_season' => 'winter',
                'planning_horizon' => '1_year',
                'status' => 'active',
            ]
        );

        // Ensure farm is active
        $farm->update(['status' => 'active']);

        // Soil report
        $farm->soilReport()->firstOrCreate(
            ['farm_id' => $farm->id],
            [
                'ph' => 7.2,
                'salinity' => 1.5,
                'nitrogen' => 26.0,
                'phosphorus' => 14.0,
                'potassium' => 170.0,
            ]
        );

        // 4. Create 4 field plots if missing
        $wheat = Crop::where('slug', 'wheat')->first() ?? Crop::first();
        $beans = Crop::where('slug', 'faba_beans')->first() ?? $wheat;
        $clover = Crop::where('slug', 'clover')->first() ?? $wheat;

        $plotAssignments = [
            'P1' => ['crop_id' => $wheat->id, 'area' => 1.25],
            'P2' => ['crop_id' => $wheat->id, 'area' => 1.25],
            'P3' => ['crop_id' => $beans->id, 'area' => 1.25],
            'P4' => ['crop_id' => $wheat->id, 'area' => 1.25],
        ];

        foreach ($plotAssignments as $name => $spec) {
            Field::updateOrCreate(
                ['farm_id' => $farm->id, 'name' => $name],
                [
                    'area' => $spec['area'],
                    'crop_id' => $spec['crop_id'],
                    'status' => 'planted',
                ]
            );
        }

        $fields = $farm->fields()->orderBy('name')->get();
        $plotsData = $fields->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'crop_id' => $f->crop_id,
            'crop_name' => $f->crop?->name ?? 'Wheat',
            'area' => $f->area,
        ])->values()->all();

        // 5. Create selected CropPlan
        $planSettings = [
            'plan_type' => 'balanced',
            'metrics' => [
                'suitability_score' => 92,
                'water_demand_mm' => 468.0,
                'water_demand_m3' => 9828,
                'total_yield_tons' => 13.9,
            ],
            'planting_season' => 'winter',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'duration_days' => 150,
            'water_scenario' => 'typical',
            'climate_scenario' => 'baseline',
            'seasonal_et0_mm' => 480.0,
            'seasonal_precipitation_mm' => 15.0,
            'seasonal_irrigation_available_mm' => 480.0,
            'rainfall_change_pct' => 0.0,
            'temperature_change_c' => 0.0,
            'water_availability_change_pct' => 0.0,
        ];

        $cropPlan = CropPlan::updateOrCreate(
            [
                'user_id' => $user->id,
                'farm_id' => $farm->id,
                'status' => 'selected',
            ],
            [
                'name' => 'Balanced Production Plan',
                'settings' => $planSettings,
                'plots' => $plotsData,
            ]
        );

        // 6. Create baseline completed simulation
        $baselineResults = [
            'simulation_id' => 1,
            'farm_id' => $farm->id,
            'selected_plan_id' => $cropPlan->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'sequence' => [
                [
                    'plot_id' => $fields->get(0)?->id,
                    'plot_name' => 'P1',
                    'crop' => 'wheat',
                    'season_days' => 150,
                    'kc_mid' => 1.15,
                    'seasonal_etc_mm' => 552.0,
                    'estimated_net_irrigation_demand_mm' => 540.0,
                    'estimated_net_irrigation_demand_m3' => 2835.0,
                    'suitability' => ['status' => 'screening_suitable', 'score' => 92],
                ],
                [
                    'plot_id' => $fields->get(1)?->id,
                    'plot_name' => 'P2',
                    'crop' => 'wheat',
                    'season_days' => 150,
                    'kc_mid' => 1.15,
                    'seasonal_etc_mm' => 552.0,
                    'estimated_net_irrigation_demand_mm' => 540.0,
                    'estimated_net_irrigation_demand_m3' => 2835.0,
                    'suitability' => ['status' => 'screening_suitable', 'score' => 92],
                ],
                [
                    'plot_id' => $fields->get(2)?->id,
                    'plot_name' => 'P3',
                    'crop' => 'faba_beans',
                    'season_days' => 135,
                    'kc_mid' => 1.10,
                    'seasonal_etc_mm' => 445.0,
                    'estimated_net_irrigation_demand_mm' => 433.0,
                    'estimated_net_irrigation_demand_m3' => 2273.0,
                    'suitability' => ['status' => 'screening_suitable', 'score' => 90],
                ],
                [
                    'plot_id' => $fields->get(3)?->id,
                    'plot_name' => 'P4',
                    'crop' => 'wheat',
                    'season_days' => 150,
                    'kc_mid' => 1.15,
                    'seasonal_etc_mm' => 552.0,
                    'estimated_net_irrigation_demand_mm' => 540.0,
                    'estimated_net_irrigation_demand_m3' => 2835.0,
                    'suitability' => ['status' => 'screening_suitable', 'score' => 92],
                ],
            ],
            'estimated_net_irrigation_demand_mm' => 513.2,
            'estimated_net_irrigation_demand_m3' => 10778.0,
            'assumptions' => [
                'Reference ET0 calculated following FAO-56 Penman-Monteith method.',
                'Effective rainfall calculated at 80% of seasonal precipitation.',
                'Balanced crop rotation maintains soil fertility and controls weed cycles.',
            ],
            'limitations' => [
                'Screening-level calculation based on verified regional averages.',
            ],
            'tools_used' => ['calculate_water_balance', 'evaluate_crop_suitability'],
            'data_sources' => [
                'FAOSTAT Egyptian national crop yields',
                'Terra Echo 8-zones agro-climatic dataset',
            ],
            'created_at' => now()->toIso8601String(),
        ];

        Simulation::updateOrCreate(
            [
                'user_id' => $user->id,
                'farm_id' => $farm->id,
            ],
            [
                'name' => 'Balanced Production Baseline',
                'selected_plan_id' => $cropPlan->id,
                'status' => 'completed',
                'stage' => 'saving',
                'settings' => $planSettings,
                'plots' => $plotsData,
                'results' => $baselineResults,
                'started_at' => now()->subMinutes(5),
                'finished_at' => now(),
            ]
        );
    }
}
