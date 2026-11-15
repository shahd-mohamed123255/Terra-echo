<?php

namespace Tests\Feature;

use App\Http\Controllers\PlanController;
use App\Models\Crop;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\SoilType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbuQirSimulationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_abu_qir_simulation_integration_lifecycle(): void
    {
        // 1. Setup authenticated user with an active Egyptian farm
        $user = User::factory()->create(['name' => 'Dr. Ahmed Farouk']);
        $soil = SoilType::create(['name' => 'Clay', 'slug' => 'clay']);

        $farm = Farm::create([
            'user_id' => $user->id,
            'status' => 'active',
            'country' => 'Egypt',
            'governorate' => 'Monufia',
            'city' => 'Shebin El Kom',
            'latitude' => 30.5593,
            'longitude' => 31.0084,
            'area' => 10.0,
            'area_unit' => 'feddan',
            'soil_type_id' => $soil->id,
            'target_season' => 'winter',
            'irrigation_source' => 'Nile Canal',
        ]);

        $wheat = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);
        $beans = Crop::create(['name' => 'Beans', 'slug' => 'beans']);
        $clover = Crop::create(['name' => 'Berseem Clover', 'slug' => 'berseem-clover']);
        $barley = Crop::create(['name' => 'Barley', 'slug' => 'barley']);

        foreach (['P1', 'P2', 'P3', 'P4'] as $plotName) {
            Field::create([
                'farm_id' => $farm->id,
                'name' => $plotName,
                'area' => 1.05,
                'status' => 'fallow',
                'crop_id' => null,
            ]);
        }

        // 2. Open Main Application & Navigate to Planning
        $plansResponse = $this->actingAs($user)->get(route('plans', ['farm' => $farm->id]));
        $plansResponse->assertOk();
        $plansResponse->assertSee('Choose a Farming Plan for Your Field');
        $plansResponse->assertSee('Water-Saving Strategy');
        $plansResponse->assertSee('Balanced Production');
        $plansResponse->assertSee('Soil Health & Legume Integration');
        $plansResponse->assertSee('High-Yield Intensive Production');

        // 3. User selects the Water-Saving Plan (plan_b)
        $selectResponse = $this->actingAs($user)->post(route('plans.select'), [
            'farm_id' => $farm->id,
            'plan' => 'water-saving',
        ]);

        $selectResponse->assertRedirect(route('farm.index', ['farm' => $farm->id]));

        // Verify the database state after plan selection
        $this->assertSame('water-saving', $user->fresh()->plan);
        $selectedCropPlan = CropPlan::where('farm_id', $farm->id)->where('status', 'selected')->first();
        $this->assertNotNull($selectedCropPlan);
        $this->assertSame('Water-Saving Strategy', $selectedCropPlan->name);

        // Verify that fields have been assigned crops according to the selected plan
        $assignedFields = Field::where('farm_id', $farm->id)->orderBy('name')->pluck('crop_id', 'name');
        $this->assertSame($wheat->id, $assignedFields['P1']);
        $this->assertSame($barley->id, $assignedFields['P2']);
        $this->assertSame($beans->id, $assignedFields['P3']);
        $this->assertSame($wheat->id, $assignedFields['P4']);

        // 4. Navigate to Abu Qir Simulation page (via /my-farm and /simulation)
        $simPageResponse = $this->actingAs($user)->get(route('farm.index', ['farm' => $farm->id]));
        $simPageResponse->assertOk();

        // Verify authentic Abu Qir HTML components and styling are rendered
        $simPageResponse->assertSee('Farm Simulation');
        $simPageResponse->assertSee('From project history to field scenario');
        $simPageResponse->assertSee('Shebin El Kom Farm');
        $simPageResponse->assertSee('4.2 ha'); // 10 feddan * 0.42 = 4.2 ha
        $simPageResponse->assertSee('Monufia, Egypt');
        $simPageResponse->assertSee('Illustrative plot layout · not a satellite image');
        $simPageResponse->assertSee('id="map"', false);
        $simPageResponse->assertSee('Configure Your Simulation');
        $simPageResponse->assertSee('Active Farming Plan');
        $simPageResponse->assertSee('Crop Library');
        $simPageResponse->assertSee('Abu Qerdan');
        $simPageResponse->assertSee('▷ RUN SIMULATION (Mode A)');
        $simPageResponse->assertSee('⚡ COMPARE ALL PLANS (Mode B)');

        // Verify the JavaScript bridge is properly populated with the selected plan and farm parameters
        $simPageResponse->assertSee('window.terraEchoSelectedPlanCode = "plan_b"', false);
        $simPageResponse->assertSee('Shebin El Kom Farm', false);
        $simPageResponse->assertSee('my-farm\/farms\/'.$farm->id.'\/simulations\/record', false);

        // Also test the dedicated /simulation route
        $aliasResponse = $this->actingAs($user)->get('/simulation?farm='.$farm->id);
        $aliasResponse->assertOk();
        $aliasResponse->assertSee('Farm Simulation');

        // 5. Run simulation & record actual simulation output to the unified backend
        $simResultsPayload = [
            'planName' => 'Water-Saving Strategy',
            'cropDisplay' => 'Wheat',
            'overallRating' => 'Good Water Efficiency',
            'growthConditionLabel' => 'Optimal Canopy Cover',
            'suitabilityScore' => 88,
            'visualStatus' => 'healthy',
            'waterBalance' => [
                'demandM3' => 15200,
                'availableM3' => 16000,
                'balanceM3' => 800,
                'satisfactionPct' => 100,
                'statusText' => 'Satisfied · Buffer of +800 m³',
            ],
            'estimatedYield' => [
                'estimatedT_Ha' => 6.38,
                'totalTons' => 26.8,
                'baselineT_Ha' => 6.63,
                'citation' => 'FAOSTAT National Averages 2015-2024',
            ],
            'fieldExplanation' => 'The water-saving deficit regime satisfied full ETc demands during critical grain filling.',
        ];

        $recordResponse = $this->actingAs($user)->postJson(
            route('farm.simulations.record', ['farm' => $farm->id]),
            [
                'name' => 'Water-Saving Strategy Simulation Run #1',
                'results' => $simResultsPayload,
                'settings' => [
                    'plan_id' => 'water-saving',
                    'season' => 'winter',
                    'area_ha' => 4.2,
                    'soil' => 'clay',
                ],
                'plots' => [
                    ['name' => 'P1', 'crop' => 'wheat'],
                    ['name' => 'P2', 'crop' => 'barley'],
                    ['name' => 'P3', 'crop' => 'faba_bean'],
                    ['name' => 'P4', 'crop' => 'wheat'],
                ],
            ]
        );

        $recordResponse->assertOk();
        $recordResponse->assertJson(['success' => true]);

        // 6. Verify simulation is saved in SQLite database and linked to the active plan
        $savedSim = Simulation::where('farm_id', $farm->id)->where('status', 'completed')->latest()->first();
        $this->assertNotNull($savedSim);
        $this->assertSame($selectedCropPlan->id, (int) $savedSim->selected_plan_id);
        $this->assertSame('Water-Saving Strategy', $savedSim->results['planName']);
        $this->assertSame(88, $savedSim->results['suitabilityScore']);
        $this->assertSame(100, $savedSim->results['waterBalance']['satisfactionPct']);

        // 7. Re-visit simulation page: verify saved simulation is passed in window.terraEchoLatestSimulation
        $revisitResponse = $this->actingAs($user)->get(route('farm.index', ['farm' => $farm->id]));
        $revisitResponse->assertOk();
        $revisitResponse->assertSee('"Water-Saving Strategy"', false);
        $revisitResponse->assertSee('"Optimal Canopy Cover"', false);

        // 8. Verify the simulation results are directly accessible to Abu Qerdan Chatbot
        $chatController = app(\App\Http\Controllers\ChatController::class);
        $reflection = new \ReflectionClass($chatController);
        $buildSummaryMethod = $reflection->getMethod('buildFarmSummary');
        $buildSummaryMethod->setAccessible(true);
        $chatSummary = $buildSummaryMethod->invoke($chatController, $farm->id, $user);

        $this->assertNotNull($chatSummary);
        $this->assertArrayHasKey('latest_simulation', $chatSummary);
        $this->assertSame($savedSim->id, $chatSummary['latest_simulation']['simulation_id']);
        $this->assertSame('completed', $chatSummary['latest_simulation']['status']);
        $this->assertSame('Water-Saving Strategy', $chatSummary['latest_simulation']['simulation_results']['planName']);
    }
}
