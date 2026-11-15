<?php

namespace Tests\Feature;

use App\Jobs\RunSimulation;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\User;
use App\Services\AgentService;
use App\Services\SimulationAgentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimulationRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent.url' => 'http://127.0.0.1:8001']);
    }

    public function test_a_run_is_queued_and_agent_results_are_saved(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $farm->update([
            'area' => 4,
            'area_unit' => 'hectare',
            'latitude' => 29.5,
            'longitude' => 31.2,
            'irrigation_source' => 'Well',
        ]);
        $plot = $this->createPlantedPlot($farm);
        $plot->update(['area' => 0.75]);
        $simulation = $this->createSimulation($user, $farm, $plot);
        Http::fake(fn () => Http::response($this->successfulAgentResponse([
            'sequence' => [[
                'plot_id' => $plot->id,
                'plot_name' => 'P1',
                'crop' => 'wheat',
                'area_hectares' => 0.75,
                'seasonal_etc_mm' => 525,
                'estimated_net_irrigation_demand_mm' => null,
                'estimated_net_irrigation_demand_m3' => null,
                'suitability' => ['status' => 'screening_suitable'],
            ]],
            'estimated_net_irrigation_demand_mm' => null,
            'estimated_net_irrigation_demand_m3' => null,
            'assumptions' => ['Generic ET0 assumption'],
            'limitations' => ['Screening only'],
            'data_sources' => ['Abu Qerdan crop catalog'],
            'missing_information' => ['seasonal_precipitation_mm'],
            'tools_used' => ['calculate_water_balance'],
            'tool_results' => ['calculate_water_balance' => ['estimated_net_irrigation_demand_mm' => null]],
        ])));

        $this->actingAs($user)
            ->postJson(route('farm.simulations.run', $simulation))
            ->assertAccepted()
            ->assertJsonPath('simulation.status', 'running')
            ->assertJsonPath('simulation.stage', 'validating');

        $this->assertDatabaseCount('jobs', 1);
        $this->runJob($simulation);

        $simulation->refresh();
        $this->assertSame('completed', $simulation->status);
        $this->assertSame('saving', $simulation->stage);
        $this->assertNotNull($simulation->finished_at);
        $this->assertSame(
            'wheat',
            $simulation->results['sequence'][0]['crop']
        );
        $this->assertNull($simulation->results['sequence'][0]['estimated_net_irrigation_demand_mm']);
        $this->assertSame($simulation->id, $simulation->results['simulation_id']);
        $this->assertSame($simulation->user_id, $simulation->results['user_id']);
        $this->assertSame('Generic ET0 assumption', $simulation->results['assumptions'][0]);
        $request = Http::recorded()->first()[0]->data();
        $this->assertSame([
            'summary' => [
                'farm_id' => (string) $farm->id,
                'farm_name' => 'Giza Farm',
                'location' => [
                    'latitude' => 29.5,
                    'longitude' => 31.2,
                    'country' => 'Egypt',
                    'governorate' => 'Giza',
                    'city' => 'Giza',
                ],
                'farm_area_hectares' => 0.75,
                'soil' => [],
                'current_crop' => 'wheat',
                'crops' => [[
                    'plot_id' => $plot->id,
                    'name' => 'P1',
                    'crop' => 'wheat',
                    'area_hectares' => 0.75,
                ]],
                'crop_history' => [],
                'planting_date' => '2026-01-15',
                'analysis_period' => ['start_date' => '2026-01-15', 'end_date' => '2026-05-15'],
                'irrigation' => ['source' => 'Well'],
                'planning_context' => [
                    'priority' => null,
                    'target_season' => null,
                    'planning_horizon' => null,
                    'selected_season' => 'spring',
                    'water_scenario_label' => 'typical',
                    'climate_scenario_label' => 'baseline',
                ],
            ],
            'strategy' => [
                'strategy_id' => (string) $simulation->id,
                'title' => 'Saved farm plan',
                'duration_days' => 120,
                'plots' => [[
                    'plot_id' => $plot->id,
                    'name' => 'P1',
                    'crop' => 'wheat',
                    'area_hectares' => 0.75,
                ]],
            ],
            'weather' => [],
            'scenario' => [
                'rainfall_change_pct' => 0.0,
                'temperature_change_c' => 0.0,
                'water_availability_change_pct' => 0.0,
            ],
        ], $request);
    }

    public function test_a_success_shaped_but_invalid_agent_response_fails_the_simulation(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response(['success' => true, 'data' => ['status' => 'failed']]));

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Invalid agent response', $simulation->fresh()->error);
    }

    public function test_structured_agent_results_and_metadata_are_preserved(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response($this->successfulAgentResponse([
            'sequence' => [['plot_id' => null, 'crop' => 'wheat', 'seasonal_etc_mm' => 100]],
            'assumptions' => ['assumption'],
            'limitations' => ['limitation'],
            'tools_used' => ['calculate_water_balance', 'evaluate_crop_suitability'],
            'tool_results' => ['water_balance' => ['deficit' => null]],
            'data_sources' => ['Abu Qerdan crop catalog'],
            'missing_information' => ['rainfall'],
        ])));

        $this->runJob($simulation);

        $this->assertSame('wheat', $simulation->fresh()->results['sequence'][0]['crop']);
        $this->assertSame(['assumption'], $simulation->fresh()->results['assumptions']);
        $this->assertSame(['limitation'], $simulation->fresh()->results['limitations']);
        $this->assertSame(['calculate_water_balance', 'evaluate_crop_suitability'], $simulation->fresh()->results['tools_used']);
        $this->assertSame(['rainfall'], $simulation->fresh()->results['missing_information']);
        $this->assertSame('wheat', $simulation->fresh()->results['input_parameters']['strategy']['plots'][0]['crop']);
    }

    public function test_simulation_requests_are_sent_to_the_agent_api_route(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response($this->successfulAgentResponse([
            'sequence' => [['crop' => 'wheat']],
        ])));

        $this->runJob($simulation);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/api/agent/simulate';
        });
    }

    public function test_an_agent_http_failure_marks_the_simulation_failed(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response(['message' => 'unavailable'], 503));

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Agent returned HTTP 503', $simulation->fresh()->error);
    }

    public function test_an_agent_timeout_marks_the_simulation_failed(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(function () {
            throw new ConnectionException('Operation timed out');
        });

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Agent request timed out', $simulation->fresh()->error);
    }

    public function test_an_invalid_agent_response_marks_the_simulation_failed(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response('not json', 200));

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Invalid agent response', $simulation->fresh()->error);
    }

    public function test_an_agent_response_missing_required_result_fields_is_invalid(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response(['success' => true, 'data' => ['sequence' => []]]));

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Invalid agent response', $simulation->fresh()->error);
    }

    public function test_a_missing_agent_url_marks_the_simulation_failed(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        config(['services.agent.url' => '']);
        Http::fake();

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Agent not connected', $simulation->fresh()->error);
        Http::assertNothingSent();
    }

    public function test_an_unavailable_agent_host_marks_the_simulation_failed(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => throw new ConnectionException('Connection refused.'));

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Agent not connected', $simulation->fresh()->error);
    }

    public function test_another_users_simulation_is_forbidden_and_a_missing_simulation_is_not_found(): void
    {
        $owner = User::factory()->create();
        $farm = $this->createFarm($owner);
        $plot = $this->createPlantedPlot($farm);
        $simulation = $this->createSimulation($owner, $farm, $plot);

        $this->actingAs(User::factory()->create())
            ->postJson(route('farm.simulations.run', $simulation))
            ->assertForbidden();
        $this->getJson(route('farm.simulations.show', $simulation))
            ->assertForbidden();
        $this->postJson(route('farm.simulations.cancel', $simulation))
            ->assertForbidden();

        $this->getJson(route('farm.simulations.show', ['simulation' => 99999]))
            ->assertNotFound();
    }

    public function test_a_running_simulation_cannot_be_started_twice(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $simulation = $this->createSimulation($user, $farm, $plot, 'running');

        $this->actingAs($user)
            ->postJson(route('farm.simulations.run', $simulation))
            ->assertStatus(409);

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_cancelling_a_run_prevents_a_queued_job_from_overwriting_it(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $simulation = $this->createSimulation($user, $farm, $plot, 'running');
        Http::fake();

        $this->actingAs($user)
            ->postJson(route('farm.simulations.cancel', $simulation))
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->runJob($simulation);

        $this->assertSame('cancelled', $simulation->fresh()->status);
        $this->assertNotNull($simulation->fresh()->finished_at);
        Http::assertNothingSent();
    }

    public function test_a_cancellation_during_the_agent_request_is_not_overwritten_on_save(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(function () use ($simulation) {
            Simulation::query()
                ->whereKey($simulation->id)
                ->update(['status' => 'cancelled']);

            return Http::response([
                'simulation_id' => $simulation->id,
                'results' => ['confidence' => 1],
            ]);
        });

        $this->runJob($simulation);

        $this->assertSame('cancelled', $simulation->fresh()->status);
        $this->assertNull($simulation->fresh()->results);
    }

    public function test_the_poll_endpoint_returns_simulation_state(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $simulation = $this->createSimulation($user, $farm, $plot, 'completed');
        $simulation->update([
            'stage' => 'saving',
            'results' => ['confidence' => ['score' => 0.8]],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('farm.simulations.show', $simulation))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('stage', 'saving')
            ->assertJsonPath('results.confidence.score', 0.8)
            ->assertJsonStructure(['status', 'stage', 'error', 'results', 'started_at', 'finished_at']);
    }

    private function runJob(Simulation $simulation): void
    {
        (new RunSimulation((int) $simulation->id))->handle(
            app(AgentService::class),
            app(SimulationAgentMapper::class)
        );
    }

    private function successfulAgentResponse(array $data = []): array
    {
        return [
            'success' => true,
            'data' => array_merge([
                'sequence' => [],
                'assumptions' => [],
                'limitations' => [],
                'data_sources' => [],
                'missing_information' => [],
                'tools_used' => [],
                'tool_results' => [],
            ], $data),
        ];
    }

    private function runningSimulation(): array
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $simulation = $this->createSimulation($user, $farm, $plot, 'running');

        return [$user, $simulation];
    }

    private function createFarm(User $user): Farm
    {
        return Farm::create([
            'user_id' => $user->id,
            'status' => 'active',
            'country' => 'Egypt',
            'governorate' => 'Giza',
            'city' => 'Giza',
        ]);
    }

    private function createPlantedPlot(Farm $farm): Field
    {
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);

        return Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $crop->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'planted',
        ]);
    }

    private function createSimulation(
        User $user,
        Farm $farm,
        Field $plot,
        string $status = 'pending'
    ): Simulation {
        return Simulation::create([
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'status' => $status,
            'stage' => $status === 'running' ? 'validating' : null,
            'settings' => [
                'planting_season' => 'spring',
                'start_date' => '2026-01-15',
                'duration_days' => 120,
                'water_scenario' => 'typical',
                'climate_scenario' => 'baseline',
            ],
            'plots' => [[
                'id' => $plot->id,
                'name' => $plot->name,
                'crop_id' => $plot->crop_id,
                'crop_name' => $plot->crop->name,
                'area' => $plot->area,
            ]],
        ]);
    }
}
