<?php

namespace Tests\Feature;

use App\Jobs\RunSimulation;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\SoilType;
use App\Models\User;
use App\Services\AgentService;
use App\Services\SimulationAgentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentLiveSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_farm_plan_simulation_and_agent_explanation_work_end_to_end(): void
    {
        if (trim((string) config('services.agent.url')) === '') {
            $this->markTestSkipped('Set AGENT_BASE_URL and AGENT_API_TOKEN to run the live Agent integration test.');
        }

        $user = User::factory()->create();
        $soilType = SoilType::forceCreate(['name' => 'Clay', 'slug' => 'clay']);
        $farm = Farm::create([
            'user_id' => $user->id,
            'status' => 'active',
            'country' => 'Egypt',
            'governorate' => 'Giza',
            'city' => 'Giza',
            'latitude' => 29.5,
            'longitude' => 31.2,
            'area' => 5,
            'area_unit' => 'feddan',
            'soil_type_id' => $soilType->id,
            'irrigation_source' => 'canal',
        ]);
        $farm->soilReport()->create(['ph' => 6.7]);
        $wheat = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);
        $corn = Crop::create(['name' => 'Corn', 'slug' => 'corn']);
        $wheatField = Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $wheat->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'planted',
        ]);
        $cornField = Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $corn->id,
            'name' => 'P2',
            'area' => 1,
            'status' => 'planted',
        ]);

        $this->actingAs($user)
            ->postJson(route('chat.start'), ['farm_id' => $farm->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $scenario = [
            'name' => 'Live integration winter plan',
            'plot_ids' => [$wheatField->id, $cornField->id],
            'planting_season' => 'winter',
            'start_date' => '2026-11-01',
            'duration_days' => 120,
            'water_scenario' => 'typical',
            'climate_scenario' => 'baseline',
            'seasonal_et0_mm' => 600,
            'seasonal_precipitation_mm' => 100,
            'seasonal_irrigation_available_mm' => 500,
            'rainfall_change_pct' => 0,
            'temperature_change_c' => 0,
            'water_availability_change_pct' => 0,
        ];
        $saved = $this->postJson(route('farm.simulations.store', $farm), $scenario)
            ->assertOk()
            ->json('simulation');
        $firstRun = Simulation::query()->findOrFail($saved['id']);
        $this->postJson(route('farm.simulations.run', $firstRun))
            ->assertAccepted()
            ->assertJsonPath('simulation.status', 'running');
        $this->runJob($firstRun);
        $firstRun->refresh();

        $this->assertSame('completed', $firstRun->status);
        $this->assertSame($saved['selected_plan_id'], $firstRun->results['selected_plan_id']);
        $this->assertSame('wheat', $firstRun->results['sequence'][0]['crop']);
        $this->assertSame('corn', $firstRun->results['sequence'][1]['crop']);
        $this->assertGreaterThan(0, $firstRun->results['estimated_net_irrigation_demand_m3']);
        $this->assertSame(
            'calculate_water_balance',
            $firstRun->results['tools_used'][0]
        );
        $this->assertNotEmpty($firstRun->results['input_parameters']);
        $this->assertNotEmpty($firstRun->results['assumptions']);

        $this->postJson(route('chat.message'), [
            'farm_id' => $farm->id,
            'message' => 'Explain my latest simulation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.assistant_mode', 'deterministic_simulation_explanation')
            ->assertJsonPath('data.tool_results.latest_simulation.simulation_id', $firstRun->id);

        $scenario['duration_days'] = 130;
        $scenario['water_availability_change_pct'] = -20;
        $savedSecond = $this->postJson(route('farm.simulations.store', $farm), $scenario)
            ->assertOk()
            ->json('simulation');
        $this->assertSame('stale', $firstRun->fresh()->status);
        $secondRun = Simulation::query()->findOrFail($savedSecond['id']);
        $this->postJson(route('farm.simulations.run', $secondRun))
            ->assertAccepted()
            ->assertJsonPath('simulation.status', 'running');
        $this->runJob($secondRun);
        $secondRun->refresh();
        $this->assertSame('completed', $secondRun->status);
        $this->assertSame(-20, $secondRun->results['scenario']['water_availability_change_pct']);

        $latestExplanation = $this->postJson(route('chat.message'), [
            'farm_id' => $farm->id,
            'message' => 'Explain my latest simulation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.tool_results.latest_simulation.simulation_id', $secondRun->id);
        $this->assertStringContainsString(
            'simulation #'.$secondRun->id,
            $latestExplanation->json('data.reply')
        );
    }

    private function runJob(Simulation $simulation): void
    {
        (new RunSimulation((int) $simulation->id))->handle(
            app(AgentService::class),
            app(SimulationAgentMapper::class)
        );
    }
}
