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
        $simulation = $this->createSimulation($user, $farm, $plot);
        $plot->update(['area' => 0.75]);
        Http::fake(fn () => Http::response([
            'simulation_id' => $simulation->id,
            'status' => 'completed',
            'error' => null,
            'summary' => null,
            'overall_suitability_pct' => null,
            'plots' => [
                ['name' => 'P1', 'crop' => 'unknown-crop', 'suitability_pct' => null, 'seasonal_water_demand_m3' => null, 'comment' => null],
            ],
            'soil' => ['type' => null, 'moisture_pct' => null, 'health_score' => null, 'ph' => null, 'compatibility' => null],
            'water' => ['available_m3' => null, 'required_m3' => null, 'balance_m3' => null, 'source' => null, 'monthly' => null],
            'climate' => ['temperature_c' => null, 'rainfall_mm' => null, 'humidity_pct' => null, 'risk_level' => null, 'risk_note' => null],
            'scenarios' => null,
            'recommendations' => null,
            'rotation' => null,
            'confidence' => null,
            'data_sources' => [],
            'generated_at' => null,
        ]));

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
            'unknown-crop',
            $simulation->results['plots'][0]['crop']
        );
        $this->assertNull($simulation->results['plots'][0]['suitability_pct']);
        $request = Http::recorded()->first()[0]->data();
        $this->assertSame([
            'simulation_id' => $simulation->id,
            'farm' => [
                'name' => 'Giza Farm',
                'latitude' => 29.5,
                'longitude' => 31.2,
                'governorate' => 'Giza',
                'total_area_feddan' => 4 * (10000 / 4200),
                'soil_type' => null,
                'irrigation_source' => 'Well',
            ],
            'plots' => [
                ['name' => 'P1', 'area_feddan' => 0.75 * (10000 / 4200), 'crop' => 'wheat'],
            ],
            'available_crops' => ['wheat'],
            'config' => $simulation->settings,
        ], $request);
    }

    public function test_an_agent_failed_status_uses_its_error(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response([
            'simulation_id' => $simulation->id,
            'status' => 'failed',
            'error' => 'Agent could not calculate this scenario',
        ]));

        $this->runJob($simulation);

        $this->assertSame('failed', $simulation->fresh()->status);
        $this->assertSame('Agent could not calculate this scenario', $simulation->fresh()->error);
        $this->assertSame('failed', $simulation->fresh()->results['status']);
        $this->assertSame('Agent could not calculate this scenario', $simulation->fresh()->results['error']);
    }

    public function test_known_crop_slugs_are_mapped_to_crop_names_in_agent_results(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response([
            'simulation_id' => $simulation->id,
            'status' => 'completed',
            'plots' => [['name' => 'P1', 'crop' => 'wheat']],
            'soil' => ['compatibility' => [['crop' => 'wheat', 'score_pct' => 80]]],
            'rotation' => [['plot' => 'P1', 'now' => 'wheat', 'next' => null]],
        ]));

        $this->runJob($simulation);

        $this->assertSame('Wheat', $simulation->fresh()->results['plots'][0]['crop']);
        $this->assertSame('Wheat', $simulation->fresh()->results['soil']['compatibility'][0]['crop']);
        $this->assertSame('Wheat', $simulation->fresh()->results['rotation'][0]['now']);
        $this->assertNull($simulation->fresh()->results['rotation'][0]['next']);
        $this->assertNull($simulation->fresh()->results['summary']);
        $this->assertNull($simulation->fresh()->results['water']);
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

    public function test_a_mismatched_agent_simulation_id_is_invalid(): void
    {
        [$user, $simulation] = $this->runningSimulation();
        Http::fake(fn () => Http::response([
            'simulation_id' => $simulation->id + 1,
            'status' => 'completed',
        ]));

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
