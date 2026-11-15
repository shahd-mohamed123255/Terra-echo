<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_scenario_is_saved_with_settings_and_a_crop_snapshot(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);

        $response = $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $this->validScenario([$plot->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('simulation.status', 'pending')
            ->assertJsonPath('simulation.name', null)
            ->assertJsonPath('simulation.settings.planting_season', array_key_first(config('simulation.planting_seasons')))
            ->assertJsonPath('simulation.plots.0.id', $plot->id)
            ->assertJsonPath('simulation.plots.0.crop_name', 'Wheat');

        $planId = $response->json('simulation.selected_plan_id');
        $this->assertDatabaseHas('simulations', [
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'selected_plan_id' => $planId,
        ]);
        $this->assertDatabaseHas('crop_plans', [
            'id' => $planId,
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status' => 'selected',
        ]);
    }

    public function test_a_scenario_name_is_saved_and_limited_to_sixty_characters(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $scenario = $this->validScenario([$plot->id]);
        $scenario['name'] = str_repeat('S', 60);

        $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $scenario)
            ->assertOk()
            ->assertJsonPath('simulation.name', $scenario['name']);

        $scenario['name'] = str_repeat('S', 61);
        $this->postJson($this->storeUrl($farm), $scenario)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_another_users_farm_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $farm = $this->createFarm($owner);

        $this->actingAs(User::factory()->create())
            ->postJson($this->storeUrl($farm), $this->validScenario([]))
            ->assertForbidden();
    }

    public function test_a_missing_farm_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('farm.simulations.store', ['farm' => 99999]), $this->validScenario([]))
            ->assertNotFound();
    }

    public function test_a_selected_plot_must_have_an_assigned_crop(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlot($farm);

        $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $this->validScenario([$plot->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plot_ids.0']);
    }

    public function test_a_selected_plot_must_belong_to_the_submitted_farm(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $otherFarm = $this->createFarm($user);
        $foreignPlot = $this->createPlantedPlot($otherFarm);

        $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $this->validScenario([$foreignPlot->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plot_ids.0']);
    }

    public function test_invalid_scenarios_and_duration_are_rejected(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $scenario = $this->validScenario([$plot->id]);
        $scenario['planting_season'] = 'unknown';
        $scenario['duration_days'] = config('simulation.duration_max') + 1;

        $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $scenario)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planting_season', 'duration_days']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $farm = $this->createFarm(User::factory()->create());

        $this->post($this->storeUrl($farm), $this->validScenario([]))
            ->assertRedirect(route('login'));
    }

    public function test_a_second_save_updates_the_existing_pending_scenario(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);
        $scenario = $this->validScenario([$plot->id]);

        $firstResponse = $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $scenario)
            ->assertOk();
        $scenario['duration_days'] = config('simulation.duration_min') + 1;

        $secondResponse = $this->postJson($this->storeUrl($farm), $scenario)
            ->assertOk()
            ->assertJsonPath('simulation.settings.duration_days', $scenario['duration_days']);

        $this->assertSame(
            $firstResponse->json('simulation.id'),
            $secondResponse->json('simulation.id')
        );
        $this->assertSame(
            $firstResponse->json('simulation.selected_plan_id'),
            $secondResponse->json('simulation.selected_plan_id')
        );
        $this->assertSame(1, Simulation::query()->where('farm_id', $farm->id)->count());
    }

    public function test_changing_a_saved_plan_marks_previous_results_stale_without_deleting_them(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $plot = $this->createPlantedPlot($farm);

        $first = $this->actingAs($user)
            ->postJson($this->storeUrl($farm), $this->validScenario([$plot->id]))
            ->assertOk()
            ->json('simulation');
        $oldResults = ['estimated_net_irrigation_demand_m3' => 1234];
        Simulation::query()->whereKey($first['id'])->update([
            'status' => 'completed',
            'results' => $oldResults,
        ]);
        $nextScenario = $this->validScenario([$plot->id]);
        $nextScenario['duration_days']++;

        $second = $this->postJson($this->storeUrl($farm), $nextScenario)
            ->assertOk()
            ->json('simulation');

        $this->assertNotSame($first['selected_plan_id'], $second['selected_plan_id']);
        $this->assertDatabaseHas('simulations', [
            'id' => $first['id'],
            'status' => 'stale',
        ]);
        $this->assertSame($oldResults, Simulation::query()->findOrFail($first['id'])->results);
        $this->assertDatabaseHas('crop_plans', [
            'id' => $first['selected_plan_id'],
            'status' => 'superseded',
        ]);
    }

    private function validScenario(array $plotIds): array
    {
        return [
            'plot_ids' => $plotIds,
            'planting_season' => array_key_first(config('simulation.planting_seasons')),
            'start_date' => '2026-01-15',
            'duration_days' => config('simulation.duration_min'),
            'water_scenario' => array_key_first(config('simulation.water_scenarios')),
            'climate_scenario' => array_key_first(config('simulation.climate_scenarios')),
        ];
    }

    private function storeUrl(Farm $farm): string
    {
        return route('farm.simulations.store', ['farm' => $farm->id]);
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

    private function createPlot(Farm $farm): Field
    {
        return Field::create([
            'farm_id' => $farm->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'fallow',
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
}
