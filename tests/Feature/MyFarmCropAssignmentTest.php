<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Field;
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

    public function test_the_active_farm_page_displays_the_plot_cards(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $farm->update(['area' => 4]);
        Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);

        $response = $this->actingAs($user)
            ->get(route('farm.index'));

        $field = $farm->fields()->where('name', 'P1')->firstOrFail();

        $response
            ->assertOk()
            ->assertSee('Your Plots')
            ->assertSee('P1')
            ->assertSee('P4')
            ->assertSee('Wheat')
            ->assertSee(route('farm.plots.crop.assign', ['fieldId' => $field->id]), false);
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
