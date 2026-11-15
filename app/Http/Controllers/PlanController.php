<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Services\AgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public const PLANS = ['water-saving', 'balanced', 'soil-health', 'high-yield'];

    public function __construct(protected AgentService $agent) {}

    public function plans(Request $request)
    {
        $farmId = $request->query('farm');
        $farm = null;

        if ($farmId) {
            $farm = $request->user()->farms()->whereKey($farmId)->first();
        }

        if (! $farm) {
            $farm = $request->user()->farms()->where('status', 'draft')->latest()->first()
                ?? $request->user()->farms()->where('status', 'active')->latest()->first();
        }

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        $farm->loadMissing(['soilType', 'soilReport', 'crops']);

        $farmAreaFeddan = (float) ($farm->area ?? 5.0);
        $farmAreaHa = $farmAreaFeddan * 0.42;
        $soilName = $farm->soilType?->name ?? 'Clay';
        $targetSeason = strtolower((string) ($farm->target_season ?? 'winter'));
        $isWinter = str_contains($targetSeason, 'winter') || ! str_contains($targetSeason, 'summer');

        // Retrieve available crops from database
        $crops = Crop::all()->keyBy('slug');
        $wheat = $crops->get('wheat') ?? Crop::firstOrCreate(['slug' => 'wheat'], ['name' => 'Wheat', 'category' => 'cereal']);
        $beans = $crops->get('beans') ?? Crop::firstOrCreate(['slug' => 'beans'], ['name' => 'Beans', 'category' => 'legume']);
        $clover = $crops->get('berseem-clover') ?? Crop::firstOrCreate(['slug' => 'berseem-clover'], ['name' => 'Berseem Clover', 'category' => 'forage']);
        $corn = $crops->get('corn') ?? Crop::firstOrCreate(['slug' => 'corn'], ['name' => 'Corn', 'category' => 'cereal']);
        $barley = $crops->get('barley') ?? Crop::firstOrCreate(['slug' => 'barley'], ['name' => 'Barley', 'category' => 'cereal']);

        // Build the 4 plans tailored to the farm
        $planDefinitions = [
            'water-saving' => [
                'id' => 'water-saving',
                'name' => 'Water-Saving Strategy',
                'badge' => '−22% Water',
                'is_recommended' => false,
                'description' => 'Optimized for water scarcity using precision drip scheduling, deficit irrigation during vegetative stages, and high water-use efficiency crops.',
                'irrigation_factor' => 0.78,
                'yield_multiplier' => 0.96,
                'suitability_score' => 86,
                'risk_level' => 'Low water risk · Moderate yield variance',
                'management' => 'Drip fertigation with 20% regulated deficit in vegetative stage; organic mulch cover.',
                'pros' => [
                    'Saves ~20–25% irrigation water, buffering against canal supply quotas',
                    'Raises crop water-use efficiency (kg produce per m³ water)',
                    'Significantly cuts energy and pumping diesel/electric costs',
                ],
                'cons' => [
                    'Requires strict scheduling and drip infrastructure maintenance',
                    'Slightly lower gross harvest tonnage under fully saturated water supply',
                ],
                'plots' => $isWinter
                    ? ['P1' => $wheat, 'P2' => $barley, 'P3' => $beans, 'P4' => $wheat]
                    : ['P1' => $corn, 'P2' => $crops->get('sorghum') ?? $corn, 'P3' => $crops->get('soybeans') ?? $corn, 'P4' => $corn],
            ],
            'balanced' => [
                'id' => 'balanced',
                'name' => 'Balanced Production',
                'badge' => '(Recommended)',
                'is_recommended' => true,
                'description' => 'Standard regional agronomic package for staple crops with balanced split mineral nutrition and proven historical stability.',
                'irrigation_factor' => 1.0,
                'yield_multiplier' => 1.0,
                'suitability_score' => 92,
                'risk_level' => 'Moderate · Well-buffered regional baseline',
                'management' => 'Improved surface/sprinkler irrigation with balanced 3-stage NPK split applications.',
                'pros' => [
                    'Proven regional track record with predictable equipment and labor',
                    'Stable historical yields aligning with Egyptian national averages',
                    'High market demand and familiar post-harvest trading channels',
                ],
                'cons' => [
                    'Higher water consumption than deficit or drip regimes',
                    'Standard soil organic matter depletion if residues are exported',
                ],
                'plots' => $isWinter
                    ? ['P1' => $wheat, 'P2' => $wheat, 'P3' => $beans, 'P4' => $wheat]
                    : ['P1' => $corn, 'P2' => $corn, 'P3' => $crops->get('soybeans') ?? $corn, 'P4' => $corn],
            ],
            'soil-health' => [
                'id' => 'soil-health',
                'name' => 'Soil Health & Legume Integration',
                'badge' => 'Bio-N Fixation',
                'is_recommended' => false,
                'description' => 'Focuses on soil regeneration, integrating biological nitrogen-fixing legumes (faba beans / berseem clover) with compost amendments.',
                'irrigation_factor' => 0.86,
                'yield_multiplier' => 0.98,
                'suitability_score' => 90,
                'risk_level' => 'Low soil risk · Diversified harvest portfolio',
                'management' => '10 t/ha organic compost amendment, Rhizobium bacterial seed inoculation, green cover residue.',
                'pros' => [
                    'Fixes 40–70 kg biological nitrogen/ha, reducing chemical fertilizer costs by ~35%',
                    'Improves soil crumb structure, biological activity, and water retention',
                    'Breaks continuous cereal monoculture disease and insect cycles',
                ],
                'cons' => [
                    'Legume market prices can fluctuate more than government-procured staple grain',
                    'Requires diverse harvesting, handling, and storage logistics',
                ],
                'plots' => $isWinter
                    ? ['P1' => $wheat, 'P2' => $beans, 'P3' => $clover, 'P4' => $wheat]
                    : ['P1' => $corn, 'P2' => $crops->get('soybeans') ?? $corn, 'P3' => $crops->get('soybeans') ?? $corn, 'P4' => $corn],
            ],
            'high-yield' => [
                'id' => 'high-yield',
                'name' => 'High-Yield Intensive Production',
                'badge' => 'Max Harvest Tonnage',
                'is_recommended' => false,
                'description' => 'High-input agronomic regime aimed at maximum harvest tonnage per hectare with 100% ETc satisfaction and precision foliar nutrition.',
                'irrigation_factor' => 1.10,
                'yield_multiplier' => 1.08,
                'suitability_score' => 88,
                'risk_level' => 'High water sensitivity · High input investment',
                'management' => 'High-frequency precision irrigation, complete macro + micronutrient fertigation program.',
                'pros' => [
                    'Maximizes total harvest tonnage and gross crop revenue under optimal conditions',
                    'Unlocks the full genetic yield potential of modern certified seed cultivars',
                    'Uniform crop maturity and premium market grade produce',
                ],
                'cons' => [
                    'Highest water consumption; suffers severe yield penalties under supply interruptions',
                    'Higher upfront working capital for soluble fertilizers and plant protection',
                ],
                'plots' => $isWinter
                    ? ['P1' => $wheat, 'P2' => $wheat, 'P3' => $wheat, 'P4' => $wheat]
                    : ['P1' => $corn, 'P2' => $corn, 'P3' => $corn, 'P4' => $corn],
            ],
        ];

        // Base agronomic values for Egypt (FAOSTAT & FAO-56 standards)
        $baseEtcMm = $isWinter ? 480.0 : 680.0;
        $effectiveRainMm = $isWinter ? 12.0 : 0.0;
        $faoBaseYield = $isWinter ? 6.63 : 7.62; // Wheat: 6.63 t/ha, Corn: 7.62 t/ha (FAOSTAT Egypt 2015-2024)

        $plans = [];
        foreach ($planDefinitions as $key => $p) {
            $adjustedEtc = round($baseEtcMm * $p['irrigation_factor'], 1);
            $netIrrigationMm = max(0, round($adjustedEtc - $effectiveRainMm, 1));
            $totalWaterM3 = (int) round($netIrrigationMm * $farmAreaHa * 10);
            $yieldTHa = round($faoBaseYield * $p['yield_multiplier'], 2);
            $totalHarvestTons = round($yieldTHa * $farmAreaHa, 1);

            $plotNames = collect($p['plots'])->map(fn ($c, $slot) => "$slot: {$c->name}")->implode(', ');

            $plans[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'badge' => $p['badge'],
                'is_recommended' => $p['is_recommended'],
                'description' => $p['description'],
                'suitability_score' => $p['suitability_score'],
                'risk_level' => $p['risk_level'],
                'management' => $p['management'],
                'water_demand_mm' => $netIrrigationMm,
                'water_demand_m3' => $totalWaterM3,
                'yield_t_ha' => $yieldTHa,
                'total_tons' => $totalHarvestTons,
                'crop_allocation' => $plotNames,
                'plots_map' => $p['plots'],
                'pros' => $p['pros'],
                'cons' => $p['cons'],
                'features' => [
                    "Water Demand: {$netIrrigationMm} mm (" . number_format($totalWaterM3) . " m³ for farm)",
                    "Expected Yield: {$yieldTHa} t/ha (~{$totalHarvestTons} tons total)",
                    "Suitability Score: {$p['suitability_score']}/100 in {$soilName} soil",
                    "Plot Allocation: {$plotNames}",
                    "Agronomic Focus: {$p['management']}",
                ],
            ];
        }

        $userSelectedPlan = $request->user()->plan ?? 'balanced';

        return view('pages.plans', [
            'farm' => $farm,
            'plans' => $plans,
            'userSelectedPlan' => $userSelectedPlan,
        ]);
    }

    public function selectPlan(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(self::PLANS)],
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
        ]);

        $farm = null;
        if (! empty($data['farm_id'])) {
            $farm = $request->user()->farms()->whereKey($data['farm_id'])->first();
        }

        if (! $farm) {
            $farm = $request->user()->farms()->where('status', 'draft')->latest()->first()
                ?? $request->user()->farms()->where('status', 'active')->latest()->first();
        }

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        $user = $request->user();
        $planType = $data['plan'];

        DB::transaction(function () use ($user, $farm, $planType): void {
            // 1. Update user selected plan and farm active status
            $user->update(['plan' => $planType]);
            $farm->update(['status' => 'active']);

            // 2. Ensure plots P1..P4 exist
            if (! $farm->fields()->exists() && $farm->area !== null) {
                $areaCents = (int) round((float) $farm->area * 100);
                $plotAreaCents = (int) round($areaCents / 4);
                $plotAreas = [
                    $plotAreaCents,
                    $plotAreaCents,
                    $plotAreaCents,
                    $areaCents - (3 * $plotAreaCents),
                ];

                foreach (['P1', 'P2', 'P3', 'P4'] as $index => $name) {
                    $farm->fields()->create([
                        'name' => $name,
                        'area' => number_format($plotAreas[$index] / 100, 2, '.', ''),
                        'status' => 'fallow',
                        'crop_id' => null,
                    ]);
                }
            }

            // 3. Determine recommended crops for the chosen plan
            $crops = Crop::all()->keyBy('slug');
            $wheat = $crops->get('wheat') ?? Crop::firstOrCreate(['slug' => 'wheat'], ['name' => 'Wheat', 'category' => 'cereal']);
            $beans = $crops->get('beans') ?? Crop::firstOrCreate(['slug' => 'beans'], ['name' => 'Beans', 'category' => 'legume']);
            $clover = $crops->get('berseem-clover') ?? Crop::firstOrCreate(['slug' => 'berseem-clover'], ['name' => 'Berseem Clover', 'category' => 'forage']);
            $barley = $crops->get('barley') ?? Crop::firstOrCreate(['slug' => 'barley'], ['name' => 'Barley', 'category' => 'cereal']);
            $corn = $crops->get('corn') ?? Crop::firstOrCreate(['slug' => 'corn'], ['name' => 'Corn', 'category' => 'cereal']);

            $isWinter = str_contains(strtolower((string) $farm->target_season), 'winter')
                || ! str_contains(strtolower((string) $farm->target_season), 'summer');

            $cropAssignments = match ($planType) {
                'water-saving' => $isWinter
                    ? ['P1' => $wheat->id, 'P2' => $barley->id, 'P3' => $beans->id, 'P4' => $wheat->id]
                    : ['P1' => $corn->id, 'P2' => $corn->id, 'P3' => ($crops->get('soybeans')?->id ?? $corn->id), 'P4' => $corn->id],
                'soil-health' => $isWinter
                    ? ['P1' => $wheat->id, 'P2' => $beans->id, 'P3' => $clover->id, 'P4' => $wheat->id]
                    : ['P1' => $corn->id, 'P2' => ($crops->get('soybeans')?->id ?? $corn->id), 'P3' => ($crops->get('soybeans')?->id ?? $corn->id), 'P4' => $corn->id],
                'high-yield' => $isWinter
                    ? ['P1' => $wheat->id, 'P2' => $wheat->id, 'P3' => $wheat->id, 'P4' => $wheat->id]
                    : ['P1' => $corn->id, 'P2' => $corn->id, 'P3' => $corn->id, 'P4' => $corn->id],
                default => $isWinter // balanced
                    ? ['P1' => $wheat->id, 'P2' => $wheat->id, 'P3' => $beans->id, 'P4' => $wheat->id]
                    : ['P1' => $corn->id, 'P2' => $corn->id, 'P3' => ($crops->get('soybeans')?->id ?? $corn->id), 'P4' => $corn->id],
            };

            // 4. Assign recommended crops to farm fields
            $fields = $farm->fields()->orderBy('name')->get();
            foreach ($fields as $field) {
                $cropId = $cropAssignments[$field->name] ?? $wheat->id;
                $field->update([
                    'crop_id' => $cropId,
                    'status' => 'planted',
                ]);
            }

            // 5. Pre-fill scenario settings
            $planName = match ($planType) {
                'water-saving' => 'Water-Saving Strategy',
                'soil-health' => 'Soil Health & Legume Integration',
                'high-yield' => 'High-Yield Intensive Production',
                default => 'Balanced Production Plan',
            };

            $suitability = match ($planType) {
                'water-saving' => 86,
                'soil-health' => 90,
                'high-yield' => 88,
                default => 92,
            };
            $waterDemandMm = match ($planType) {
                'water-saving' => round(360 + ($isWinter ? 0 : 150), 1),
                'soil-health' => round(400 + ($isWinter ? 0 : 160), 1),
                'high-yield' => round(515 + ($isWinter ? 0 : 180), 1),
                default => round(465 + ($isWinter ? 0 : 170), 1),
            };
            $farmAreaHa = (float) ($farm->area ?? 4.0) * 0.42;
            $waterVolumeM3 = (int) round($waterDemandMm * $farmAreaHa * 10);
            $estYieldTons = match ($planType) {
                'water-saving' => round(6.36 * $farmAreaHa, 1),
                'soil-health' => round(6.50 * $farmAreaHa, 1),
                'high-yield' => round(7.16 * $farmAreaHa, 1),
                default => round(6.63 * $farmAreaHa, 1),
            };

            $settings = [
                'plan_type' => $planType,
                'metrics' => [
                    'suitability_score' => $suitability,
                    'water_demand_mm' => $waterDemandMm,
                    'water_demand_m3' => $waterVolumeM3,
                    'total_yield_tons' => $estYieldTons,
                ],
                'planting_season' => $isWinter ? 'winter' : 'summer',
                'start_date' => now()->addDays(7)->format('Y-m-d'),
                'duration_days' => 150,
                'water_scenario' => match ($planType) {
                    'water-saving' => 'scarce',
                    'high-yield' => 'abundant',
                    default => 'typical',
                },
                'climate_scenario' => 'baseline',
                'seasonal_et0_mm' => $isWinter ? 480.0 : 680.0,
                'seasonal_precipitation_mm' => $isWinter ? 15.0 : 0.0,
                'seasonal_irrigation_available_mm' => match ($planType) {
                    'water-saving' => 380.0,
                    'high-yield' => 600.0,
                    default => 480.0,
                },
                'rainfall_change_pct' => 0.0,
                'temperature_change_c' => 0.0,
                'water_availability_change_pct' => 0.0,
            ];

            $plotsData = $fields->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'crop_id' => $f->crop_id,
                'crop_name' => $f->crop?->name ?? 'Wheat',
                'area' => $f->area,
            ])->values()->all();

            // 6. Save or update selected CropPlan
            CropPlan::query()
                ->where('user_id', $user->id)
                ->where('farm_id', $farm->id)
                ->where('status', 'selected')
                ->update(['status' => 'superseded']);

            $cropPlan = CropPlan::create([
                'user_id' => $user->id,
                'farm_id' => $farm->id,
                'name' => $planName,
                'settings' => $settings,
                'plots' => $plotsData,
                'status' => 'selected',
            ]);

            // 7. Mark prior simulations stale and create pending simulation
            Simulation::query()
                ->where('user_id', $user->id)
                ->where('farm_id', $farm->id)
                ->whereIn('status', ['running', 'completed'])
                ->update([
                    'status' => 'stale',
                    'error' => 'Plan selection updated; run simulation for new plan results.',
                    'finished_at' => now(),
                ]);

            Simulation::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'farm_id' => $farm->id,
                    'status' => 'pending',
                ],
                [
                    'selected_plan_id' => $cropPlan->id,
                    'name' => $planName,
                    'settings' => $settings,
                    'plots' => $plotsData,
                    'results' => null,
                    'error' => null,
                    'stage' => null,
                    'started_at' => null,
                    'finished_at' => null,
                ]
            );
        });

        return redirect()->route('farm.index', ['farm' => $farm->id])
            ->with('status', 'Plan selected successfully! Your field plots and simulation scenario have been configured.');
    }
}
