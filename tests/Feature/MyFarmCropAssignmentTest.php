<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\SoilType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyFarmCropAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_crop_can_be_assigned_to_and_removed_from_an_owned_plot(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $field = $this->createField($farm);
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);

        $this->actingAs($user)
            ->postJson(route('farm.plots.crop.assign', $field), ['crop_id' => $crop->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('plot.name', 'P1')
            ->assertJsonPath('plot.status', 'planted')
            ->assertJsonPath('plot.crop.name', 'Wheat');

        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'crop_id' => $crop->id,
            'status' => 'planted',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('farm.plots.crop.remove', $field))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('plot.status', 'fallow')
            ->assertJsonPath('plot.crop', null);

        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'crop_id' => null,
            'status' => 'fallow',
        ]);
    }

    public function test_a_user_cannot_change_a_plot_on_another_users_farm(): void
    {
        $owner = User::factory()->create();
        $farm = $this->createFarm($owner);
        $field = $this->createField($farm);
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('farm.plots.crop.assign', $field), ['crop_id' => $crop->id])
            ->assertForbidden();

        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'crop_id' => null,
            'status' => 'fallow',
        ]);
    }

    public function test_a_crop_must_exist_and_the_farm_must_be_active(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $field = $this->createField($farm);

        $this->actingAs($user)
            ->postJson(route('farm.plots.crop.assign', $field), ['crop_id' => 99999])
            ->assertUnprocessable();

        $farm->update(['status' => 'draft']);

        $this->deleteJson(route('farm.plots.crop.remove', $field))
            ->assertNotFound();

        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'crop_id' => null,
            'status' => 'fallow',
        ]);
    }

    public function test_the_active_farm_page_displays_the_satellite_plot_map(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $farm->update([
            'area' => 4,
            'latitude' => 29.9876543,
            'longitude' => 31.1234567,
        ]);
        Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);

        $response = $this->actingAs($user)
            ->get(route('farm.index'));

        $field = $farm->fields()->where('name', 'P1')->firstOrFail();

        $response
            ->assertOk()
            ->assertSee('Your farm, from above')
            ->assertSee('29.9876543, 31.1234567')
            ->assertSee('Agricultural Plots')
            ->assertSee('id="farm-boundary-toggle"', false)
            ->assertSee('id="plot-details-card"', false)
            ->assertSee('id="selected-crops-total"', false)
            ->assertSee('Scenario name (optional)')
            ->assertSee('id="scenario-readiness"', false)
            ->assertSee('id="scenario-end-date"', false)
            ->assertSee('id="farm-map"', false)
            ->assertSee('Satellite View')
            ->assertSee('Approximate plot layout, not a surveyed boundary')
            ->assertSee('P1')
            ->assertSee('P4')
            ->assertSee('Wheat')
            ->assertSee(str_replace('/', '\\/', route('farm.plots.crop.assign', ['fieldId' => $field->id])), false);
    }

    public function test_the_active_farm_page_explains_when_location_is_missing(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $farm->update(['area' => 4]);

        $this->actingAs($user)
            ->get(route('farm.index'))
            ->assertOk()
            ->assertSee('Your farm map will appear here once its location has been saved.')
            ->assertDontSee('id="farm-map"', false);
    }

    public function test_the_active_farm_page_shows_selected_crops_and_saved_condition_data(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $soilType = SoilType::forceCreate([
            'name' => 'Clay',
            'slug' => 'clay',
        ]);
        $farm->update([
            'area' => 4,
            'soil_type_id' => $soilType->id,
            'irrigation_source' => 'Well',
        ]);
        $farm->soilReport()->create([
            'ph' => 6.5,
            'salinity' => 1.2,
            'nitrogen' => 30,
            'phosphorus' => 12,
            'potassium' => 20,
        ]);
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);
        Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $crop->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'planted',
        ]);

        $this->actingAs($user)
            ->get(route('farm.index'))
            ->assertOk()
            ->assertSee('Selected Crops')
            ->assertSee('P1 · Wheat')
            ->assertSee('aria-label="Remove Wheat from P1"', false)
            ->assertSee('Soil type')
            ->assertSee('Clay')
            ->assertSee('Irrigation source')
            ->assertSee('Well')
            ->assertSee('Climate observations')
            ->assertSee('Farm agent ID')
            ->assertSee('Awaiting data');
    }

    public function test_completed_plot_insights_are_available_to_the_plot_details_card(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $farm->update(['area' => 4, 'latitude' => 29.5, 'longitude' => 31.2]);
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);
        $plot = Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $crop->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'planted',
        ]);
        Simulation::create([
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'status' => 'completed',
            'settings' => [],
            'plots' => [],
            'results' => [
                'plots' => [[
                    'name' => $plot->name,
                    'suitability_pct' => null,
                    'seasonal_water_demand_m3' => 42,
                    'comment' => null,
                ]],
            ],
        ]);
        Simulation::create([
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'status' => 'pending',
            'settings' => [],
            'plots' => [],
        ]);

        $this->actingAs($user)
            ->get(route('farm.index'))
            ->assertOk()
            ->assertSee('"simulationPlots":[{"name":"P1","suitability_pct":null,"seasonal_water_demand_m3":42,"comment":null}]', false);
    }

    private function createFarm(User $user, string $status = 'active'): Farm
    {
        return Farm::create([
            'user_id' => $user->id,
            'status' => $status,
            'country' => 'Egypt',
            'governorate' => 'Giza',
            'city' => 'Giza',
        ]);
    }

    private function createField(Farm $farm): Field
    {
        return Field::create([
            'farm_id' => $farm->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'fallow',
        ]);
    }
}
