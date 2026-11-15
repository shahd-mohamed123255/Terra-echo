<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Simulation;
use App\Models\User;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function __construct(protected AgentService $agent) {}

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')
                ->where('user_id', $user->id)
                ->where('status', 'active')],
        ]);

        $farmId = $validated['farm_id'] ?? null;
        $conversationQuery = ChatConversation::where('user_id', $user->id);
        if ($farmId === null) {
            $conversationQuery->whereNull('farm_id');
        } else {
            $conversationQuery->where('farm_id', $farmId);
        }
        $conversation = $conversationQuery->latest('id')->first();

        if ($conversation) {
            $history = $this->agent->history($conversation->agent_conversation_id);

            if ($history['ok']) {
                return response()->json([
                    'success'  => true,
                    'existing' => true,
                    'messages' => $history['data']['messages'] ?? [],
                ]);
            }

            if (($history['code'] ?? '') !== 'conversation_not_found') {
                return $this->fail($history);
            }

            $conversation->delete();
        }

        $summary = $this->buildFarmSummary($farmId, $user) ?? [];
        $result = $this->agent->start($summary);

        if (! $result['ok']) {
            return $this->fail($result);
        }

        $data = $result['data'];

        ChatConversation::create([
            'user_id'               => $user->id,
            'farm_id'               => $farmId,
            'agent_conversation_id' => $data['conversation_id'],
        ]);

        $messages = isset($data['reply']) && is_string($data['reply'])
            ? [['role' => 'assistant', 'content' => $data['reply']]]
            : [];

        return response()->json([
            'success'  => true,
            'existing' => false,
            'messages' => $messages,
            'data'     => $data,
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where('user_id', $user->id)],
        ]);

        $farmId = $validated['farm_id'] ?? null;
        $conversationQuery = ChatConversation::where('user_id', $user->id);
        if ($farmId === null) {
            $conversationQuery->whereNull('farm_id');
        } else {
            $conversationQuery->where('farm_id', $farmId);
        }
        $conversation = $conversationQuery->latest('id')->first();

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'code'    => 'no_conversation',
                'message' => 'ابدأ المحادثة الأول.',
            ], 409);
        }

        $summary = $this->buildFarmSummary($farmId, $user) ?? [];
        $result = $this->agent->chat(
            $conversation->agent_conversation_id,
            trim($validated['message']),
            $summary
        );

        if (! $result['ok']) {
            return $this->fail($result);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    protected function buildFarmSummary(?int $farmId, User $user): ?array
    {
        if ($farmId === null) {
            return null;
        }

        $farm = Farm::query()
            ->with(['soilType', 'soilReport', 'fields.crop', 'crops'])
            ->whereKey($farmId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        if (! $farm) {
            return null;
        }

        $fields = $farm->fields->map(function ($field) use ($farm) {
            return [
                'id' => $field->id,
                'name' => $field->name,
                'crop' => $field->crop?->slug ?: $field->crop?->name,
                'crop_name' => $field->crop?->name,
                'area' => $field->area,
                'area_unit' => $farm->area_unit,
            ];
        })->values()->all();
        $farmAreaHectares = $this->toHectares($farm->area, strtolower((string) $farm->area_unit));
        $cropHistory = $farm->crops
            ->filter(fn ($crop) => $crop->pivot?->type === 'previous')
            ->map(fn ($crop) => $crop->slug ?: $crop->name)
            ->values()
            ->all();
        $latestPlan = CropPlan::query()
            ->where('user_id', $user->id)
            ->where('farm_id', $farm->id)
            ->where('status', 'selected')
            ->latest('id')
            ->first();
        $latestSimulation = Simulation::query()
            ->where('user_id', $user->id)
            ->where('farm_id', $farm->id)
            ->where('status', 'completed')
            ->latest('id')
            ->first();
        $soil = array_filter([
            'type' => $farm->soilType?->name,
            'ph' => $farm->soilReport?->ph,
            'salinity' => $farm->soilReport?->salinity,
            'nitrogen' => $farm->soilReport?->nitrogen,
            'phosphorus' => $farm->soilReport?->phosphorus,
            'potassium' => $farm->soilReport?->potassium,
        ], fn ($value) => $value !== null);
        $summary = [
            'farm_id' => (string) $farm->id,
            'farm_name' => $farm->city ? $farm->city.' Farm' : null,
            'location' => [
                'latitude' => $farm->latitude,
                'longitude' => $farm->longitude,
                'country' => $farm->country,
                'governorate' => $farm->governorate,
                'city' => $farm->city,
            ],
            'farm_area_hectares' => $farmAreaHectares,
            'soil' => $soil,
            'current_crop' => collect($fields)->first(fn ($field) => $field['crop'] !== null)['crop'] ?? null,
            'crops' => $fields,
            'crop_history' => $cropHistory,
            'planting_date' => data_get($latestPlan?->settings, 'start_date'),
            'analysis_period' => array_filter([
                'start_date' => data_get($latestPlan?->settings, 'start_date'),
                'end_date' => $this->endDate($latestPlan?->settings),
            ]),
            'irrigation' => array_filter([
                'source' => $farm->irrigation_source,
                'seasonal_irrigation_available_mm' => data_get($latestPlan?->settings, 'seasonal_irrigation_available_mm'),
            ], fn ($value) => $value !== null),
            'planning_context' => [
                'priority' => $farm->priority,
                'target_season' => $farm->target_season,
                'planning_horizon' => $farm->planning_horizon,
                'selected_plan_id' => $latestPlan?->id,
                'selected_plan' => $latestPlan ? [
                    'name' => $latestPlan->name,
                    'settings' => $latestPlan->settings,
                    'plots' => $latestPlan->plots,
                ] : null,
            ],
        ];

        if ($latestSimulation) {
            $summary['latest_simulation'] = [
                'simulation_id' => $latestSimulation->id,
                'farm_id' => $latestSimulation->farm_id,
                'selected_plan_id' => $latestSimulation->selected_plan_id,
                'status' => $latestSimulation->status,
                'input_parameters' => data_get($latestSimulation->results, 'input_parameters', [
                    'settings' => $latestSimulation->settings,
                    'plots' => $latestSimulation->plots,
                ]),
                'simulation_results' => $latestSimulation->results,
                'created_at' => $latestSimulation->finished_at?->toIso8601String(),
            ];
        }

        return $summary;
    }

    private function toHectares(mixed $area, string $unit): ?float
    {
        if ($area === null) {
            return null;
        }

        $factor = match ($unit) {
            'feddan' => 0.42,
            'hectare', 'hectares', 'ha' => 1.0,
            'acre', 'acres' => 0.40468564224,
            default => null,
        };

        return $factor === null ? null : (float) $area * $factor;
    }

    private function endDate(?array $settings): ?string
    {
        $startDate = $settings['start_date'] ?? null;
        $duration = (int) ($settings['duration_days'] ?? 0);

        return $startDate && $duration > 0
            ? date('Y-m-d', strtotime($startDate.' +'.$duration.' days'))
            : null;
    }

    protected function fail(array $result): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => $result['code'] ?? 'error',
            'message' => $result['message'] ?? 'حصلت مشكلة.',
        ], $result['status'] ?? 500);
    }
}