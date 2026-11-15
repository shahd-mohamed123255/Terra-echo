<?php

namespace Tests\Feature;

use App\Jobs\RunSimulation;
use App\Models\Crop;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\SoilType;
use App\Models\User;
use App\Services\AgentService;
use App\Services\SimulationAgentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndUserWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.local_fallback' => true]);

        // Seed basic soil types and crops
        SoilType::firstOrCreate(['slug' => 'clay'], ['name' => 'Clay']);
        SoilType::firstOrCreate(['slug' => 'sandy'], ['name' => 'Sandy']);
        SoilType::firstOrCreate(['slug' => 'loam'], ['name' => 'Loam']);

        Crop::firstOrCreate(['slug' => 'wheat'], [
            'name' => 'Wheat',
            'category' => 'grains',
            'season' => 'winter',
            'water_mm' => 500,
            'duration_min_days' => 120,
            'duration_max_days' => 160,
            'preferred_soil' => 'Clay, Loam',
        ]);

        Crop::firstOrCreate(['slug' => 'clover'], [
            'name' => 'Clover',
            'category' => 'forage',
            'season' => 'winter',
            'water_mm' => 450,
            'duration_min_days' => 90,
            'duration_max_days' => 150,
            'preferred_soil' => 'Clay, Loam',
        ]);

        Crop::firstOrCreate(['slug' => 'faba_beans'], [
            'name' => 'Faba Beans',
            'category' => 'legumes',
            'season' => 'winter',
            'water_mm' => 380,
            'duration_min_days' => 120,
            'duration_max_days' => 150,
            'preferred_soil' => 'Clay, Loam',
        ]);

        Crop::firstOrCreate(['slug' => 'barley'], [
            'name' => 'Barley',
            'category' => 'grains',
            'season' => 'winter',
            'water_mm' => 350,
            'duration_min_days' => 100,
            'duration_max_days' => 130,
            'preferred_soil' => 'Sandy, Loam',
        ]);
    }

    public function test_complete_farm_planning_and_simulation_workflow(): void
    {
        $user = User::factory()->create();
        $soilType = SoilType::where('slug', 'clay')->first();

        // Step 1: Create active farm with realistic Monufia coordinates
        $farm = Farm::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'country' => 'Egypt',
            'governorate' => 'Monufia',
            'city' => 'Shebin El Kom',
            'latitude' => 30.55,
            'longitude' => 31.01,
            'area' => 4.0,
            'area_unit' => 'feddan',
            'soil_type_id' => $soilType->id,
            'irrigation_source' => 'Nile Canal',
            'priority' => 'water',
            'target_season' => 'winter',
            'planning_horizon' => '1_year',
        ]);

        $farm->soilReport()->create([
            'ph' => 7.4,
            'salinity' => 1.8,
            'nitrogen' => 28.0,
            'phosphorus' => 14.0,
            'potassium' => 180.0,
        ]);

        // Step 2: User confirms draft farm -> gets activated and redirected to /plans
        $response = $this->actingAs($user)
            ->post(route('farm.confirm'));

        $response->assertRedirect(route('plans', ['farm' => $farm->id]));
        $this->assertSame('active', $farm->fresh()->status);

        // Step 3: Visit Plans page -> AI plans generated
        $plansResponse = $this->actingAs($user)
            ->get(route('plans', ['farm' => $farm->id]));

        $plansResponse->assertOk();
        $plansResponse->assertSee('Water-Saving Strategy');
        $plansResponse->assertSee('Balanced Production');
        $plansResponse->assertSee('Soil Health &amp; Legume Integration', false);
        $plansResponse->assertSee('High-Yield Intensive Production');

        // Step 4: User selects 'balanced' plan
        $selectResponse = $this->actingAs($user)
            ->post(route('plans.select'), [
                'farm_id' => $farm->id,
                'plan' => 'balanced',
                'season' => 'winter',
            ]);

        $selectResponse->assertRedirect(route('farm.index', ['farm' => $farm->id]));

        // Verify CropPlan record created
        $cropPlan = CropPlan::where('farm_id', $farm->id)->where('status', 'selected')->first();
        $this->assertNotNull($cropPlan);
        $this->assertSame('balanced', $cropPlan->archetype);
        $this->assertGreaterThan(50, data_get($cropPlan->metrics, 'suitability_score'));

        // Verify fields P1..P4 are populated with crops
        $fields = $farm->fresh()->fields()->with('crop')->orderBy('name')->get();
        $this->assertCount(4, $fields);
        foreach ($fields as $field) {
            $this->assertNotNull($field->crop, "Field {$field->name} should have an assigned crop.");
            $this->assertSame('planted', $field->status);
        }

        // Verify pending simulation created
        $simulation = Simulation::where('farm_id', $farm->id)->latest()->first();
        $this->assertNotNull($simulation);
        $this->assertSame($cropPlan->id, (int) $simulation->selected_plan_id);
        $this->assertSame('balanced', $cropPlan->archetype);
        $this->assertCount(4, $simulation->plots);

        // Step 5: Visit /my-farm -> displays Active Farming Plan banner and 4 plots
        $myFarmResponse = $this->actingAs($user)
            ->get(route('farm.index', ['farm' => $farm->id]));

        $myFarmResponse->assertOk();
        $myFarmResponse->assertSee('Active Farming Plan');
        $myFarmResponse->assertSee($cropPlan->name);
        $myFarmResponse->assertSee('P1');
        $myFarmResponse->assertSee('P2');
        $myFarmResponse->assertSee('P3');
        $myFarmResponse->assertSee('P4');

        // Step 6: User clicks "Run Simulation"
        $runResponse = $this->actingAs($user)
            ->postJson(route('farm.simulations.run', ['simulation' => $simulation->id]));

        $runResponse->assertAccepted();
        $this->assertSame('running', $simulation->fresh()->status);

        // Run the simulation job
        (new RunSimulation((int) $simulation->id))->handle(
            app(AgentService::class),
            app(SimulationAgentMapper::class)
        );

        $simulation->refresh();
        $this->assertSame('completed', $simulation->status);
        $this->assertSame('saving', $simulation->stage);
        $this->assertNotNull($simulation->results);
        $this->assertIsArray($simulation->results['sequence']);
        $this->assertNotEmpty($simulation->results['sequence']);

        // Check calculated agronomic parameters
        $firstResult = $simulation->results['sequence'][0];
        $this->assertArrayHasKey('seasonal_etc_mm', $firstResult);
        $this->assertGreaterThan(0, $firstResult['seasonal_etc_mm']);
        $this->assertContains('calculate_water_balance', $simulation->results['tools_used']);

        // Step 7: Verify simulation insights API returns completed results
        $showResponse = $this->actingAs($user)
            ->getJson(route('farm.simulations.show', ['simulation' => $simulation->id]));

        $showResponse->assertOk();
        $showResponse->assertJsonPath('status', 'completed');
        $this->assertNotNull($showResponse->json('results.sequence'));

        // Step 8: User opens Abu Qerdan Chatbot and queries agricultural guidance
        $chatStart = $this->actingAs($user)
            ->postJson(route('chat.start'), ['farm_id' => $farm->id]);

        $chatStart->assertOk();
        $chatStart->assertJsonPath('success', true);

        $chatMessage = $this->actingAs($user)
            ->postJson(route('chat.message'), [
                'farm_id' => $farm->id,
                'message' => 'What is the irrigation requirement for wheat in winter?',
            ]);

        $chatMessage->assertOk();
        $chatMessage->assertJsonPath('success', true);
        $this->assertNotEmpty($chatMessage->json('data.reply'));
    }
}
