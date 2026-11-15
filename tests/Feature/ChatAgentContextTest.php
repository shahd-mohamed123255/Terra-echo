<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Crop;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Simulation;
use App\Models\SoilType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatAgentContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent.url' => 'http://127.0.0.1:8001']);
    }

    public function test_start_sends_owned_farm_and_current_plan_context_to_agent(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $farm->update([
            'area' => 4,
            'area_unit' => 'feddan',
            'latitude' => 29.5,
            'longitude' => 31.2,
            'soil_type_id' => SoilType::forceCreate(['name' => 'Clay', 'slug' => 'clay'])->id,
            'irrigation_source' => 'canal',
        ]);
        $farm->soilReport()->create(['ph' => 6.7, 'salinity' => 1.1]);
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);
        Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $crop->id,
            'name' => 'P1',
            'area' => 2,
            'status' => 'planted',
        ]);
        Http::fake([
            'http://127.0.0.1:8001/api/agent/start' => Http::response([
                'success' => true,
                'data' => [
                    'conversation_id' => 'agent-conversation-1',
                    'missing_fields' => [],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('chat.start'), ['farm_id' => $farm->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages', []);

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'agent_conversation_id' => 'agent-conversation-1',
        ]);
        Http::assertSent(function ($request) use ($farm) {
            $summary = $request->data()['summary'] ?? [];

            return $request->url() === 'http://127.0.0.1:8001/api/agent/start'
                && $summary['farm_id'] === (string) $farm->id
                && $summary['farm_area_hectares'] === 1.68
                && $summary['current_crop'] === 'wheat'
                && $summary['soil']['ph'] === 6.7
                && $summary['crops'][0]['name'] === 'P1';
        });
    }

    public function test_chat_receives_latest_saved_simulation_and_plan_for_its_farm(): void
    {
        $user = User::factory()->create();
        $farm = $this->createFarm($user);
        $crop = Crop::create(['name' => 'Wheat', 'slug' => 'wheat']);
        Field::create([
            'farm_id' => $farm->id,
            'crop_id' => $crop->id,
            'name' => 'P1',
            'area' => 1,
            'status' => 'planted',
        ]);
        $plan = CropPlan::create([
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'name' => 'Winter wheat',
            'settings' => ['start_date' => '2026-01-01', 'duration_days' => 120],
            'plots' => [],
            'status' => 'selected',
        ]);
        $runResults = [
            'simulation_id' => 20,
            'farm_id' => $farm->id,
            'selected_plan_id' => $plan->id,
            'status' => 'completed',
            'input_parameters' => [
                'summary' => ['farm_id' => (string) $farm->id],
                'strategy' => ['plots' => [['crop' => 'wheat']]],
                'weather' => ['seasonal_precipitation_mm' => 100],
                'scenario' => [],
            ],
            'sequence' => [['crop' => 'wheat', 'estimated_net_irrigation_demand_m3' => 6100]],
            'estimated_net_irrigation_demand_m3' => 6100,
            'assumptions' => ['verified test assumption'],
            'limitations' => ['test limitation'],
            'tools_used' => ['calculate_water_balance'],
        ];
        Simulation::create([
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'selected_plan_id' => $plan->id,
            'status' => 'completed',
            'settings' => $plan->settings,
            'plots' => [],
            'results' => $runResults,
        ]);
        ChatConversation::create([
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'agent_conversation_id' => 'agent-conversation-20',
        ]);
        Http::fake([
            'http://127.0.0.1:8001/api/agent/chat' => Http::response([
                'success' => true,
                'data' => [
                    'reply' => 'Saved simulation context received.',
                    'tools_used' => ['retrieve_latest_simulation_results'],
                    'tool_results' => [],
                    'missing_fields' => [],
                    'sources' => [],
                    'assumptions' => [],
                    'assistant_mode' => 'deterministic_simulation_explanation',
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('chat.message'), [
                'farm_id' => $farm->id,
                'message' => 'Explain my latest simulation.',
            ])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Saved simulation context received.');

        Http::assertSent(function ($request) use ($farm, $plan) {
            $summary = $request->data()['summary'] ?? [];
            $latest = $summary['latest_simulation'] ?? [];

            return $request->url() === 'http://127.0.0.1:8001/api/agent/chat'
                && $summary['farm_id'] === (string) $farm->id
                && $latest['selected_plan_id'] === $plan->id
                && $latest['simulation_results']['estimated_net_irrigation_demand_m3'] === 6100;
        });
    }

    public function test_a_farm_conversation_is_not_reused_for_another_farm(): void
    {
        $user = User::factory()->create();
        $firstFarm = $this->createFarm($user);
        $secondFarm = $this->createFarm($user);
        ChatConversation::create([
            'user_id' => $user->id,
            'farm_id' => $firstFarm->id,
            'agent_conversation_id' => 'first-farm-conversation',
        ]);
        Http::fake([
            'http://127.0.0.1:8001/api/agent/start' => Http::response([
                'success' => true,
                'data' => [
                    'conversation_id' => 'second-farm-conversation',
                    'reply' => 'New farm conversation',
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('chat.start'), ['farm_id' => $secondFarm->id])
            ->assertOk()
            ->assertJsonPath('existing', false);

        $this->assertDatabaseHas('chat_conversations', [
            'farm_id' => $secondFarm->id,
            'agent_conversation_id' => 'second-farm-conversation',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/api/agent/start');
    }

    public function test_a_user_cannot_start_a_chat_for_another_users_farm(): void
    {
        $farm = $this->createFarm(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->postJson(route('chat.start'), ['farm_id' => $farm->id])
            ->assertUnprocessable();
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
}
