<?php

namespace App\Http\Controllers;

use App\Jobs\RunSimulation;
use App\Models\ChatConversation;
use App\Models\CropPlan;
use App\Models\Farm;
use App\Models\Simulation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SimulationController extends Controller
{
    public function run(Request $request, Simulation $simulation)
    {
        $this->ensureOwned($simulation, $request);

        if ($simulation->status === 'running') {
            return response()->json([
                'success' => false,
                'message' => 'This simulation is already running.',
            ], 409);
        }

        if (! in_array($simulation->status, ['pending', 'failed', 'completed', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This simulation cannot be run in its current state.',
            ], 409);
        }

        $plotIds = collect($simulation->plots ?? [])
            ->pluck('id')
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->unique()
            ->values();
        $plots = $simulation->farm
            ->fields()
            ->whereIn('id', $plotIds)
            ->get(['id', 'crop_id']);

        if ($plotIds->isEmpty()
            || $plots->count() !== $plotIds->count()
            || $plots->contains(fn ($plot) => $plot->crop_id === null)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more saved plots are no longer available with crops.',
                'errors' => ['plots' => ['Select saved plots that still belong to this farm and have assigned crops.']],
            ], 422);
        }

        $updated = Simulation::query()
            ->whereKey($simulation->id)
            ->whereIn('status', ['pending', 'failed', 'completed', 'cancelled'])
            ->update([
                'results' => null,
                'error' => null,
                'status' => 'running',
                'stage' => 'validating',
                'started_at' => now(),
                'finished_at' => null,
            ]);
        if ($updated !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'This simulation is already running.',
            ], 409);
        }
        $simulation->refresh();

        dispatch((new RunSimulation((int) $simulation->id))->onConnection('database'));

        return response()->json([
            'success' => true,
            'simulation' => [
                'id' => $simulation->id,
                'status' => $simulation->status,
                'stage' => $simulation->stage,
            ],
        ], 202);
    }

    public function show(Request $request, Simulation $simulation)
    {
        $this->ensureOwned($simulation, $request);

        return response()->json([
            'status' => $simulation->status,
            'stage' => $simulation->stage,
            'error' => $simulation->error,
            'results' => $simulation->results,
            'started_at' => $simulation->started_at,
            'finished_at' => $simulation->finished_at,
        ]);
    }

    public function cancel(Request $request, Simulation $simulation)
    {
        $this->ensureOwned($simulation, $request);

        if ($simulation->status === 'cancelled') {
            return response()->json(['success' => true, 'status' => 'cancelled']);
        }

        if ($simulation->status !== 'running') {
            return response()->json([
                'success' => false,
                'message' => 'Only a running simulation can be cancelled.',
            ], 409);
        }

        $updated = Simulation::query()
            ->whereKey($simulation->id)
            ->where('status', 'running')
            ->update([
                'status' => 'cancelled',
                'finished_at' => now(),
            ]);

        if ($updated !== 1) {
            $simulation->refresh();
            if ($simulation->status === 'cancelled') {
                return response()->json(['success' => true, 'status' => 'cancelled']);
            }

            return response()->json([
                'success' => false,
                'message' => 'This simulation is no longer running.',
            ], 409);
        }

        return response()->json(['success' => true, 'status' => 'cancelled']);
    }

    public function store(Request $request, Farm $farm)
    {
        abort_unless($farm->status === 'active', 404, 'Farm not found.');
        abort_unless((int) $farm->user_id === (int) $request->user()->id, 403, 'You do not own this farm.');

        $options = config('simulation');
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:60'],
            'plot_ids' => ['required', 'array', 'min:1'],
            'plot_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('fields', 'id')->where('farm_id', $farm->id),
            ],
            'planting_season' => ['required', Rule::in(array_keys($options['planting_seasons']))],
            'start_date' => ['required', 'date'],
            'duration_days' => [
                'required',
                'integer',
                'min:'.$options['duration_min'],
                'max:'.$options['duration_max'],
            ],
            'water_scenario' => ['required', Rule::in(array_keys($options['water_scenarios']))],
            'climate_scenario' => ['required', Rule::in(array_keys($options['climate_scenarios']))],
            'seasonal_et0_mm' => ['nullable', 'numeric', 'min:0'],
            'seasonal_precipitation_mm' => ['nullable', 'numeric', 'min:0'],
            'seasonal_irrigation_available_mm' => ['nullable', 'numeric', 'min:0'],
            'rainfall_change_pct' => ['nullable', 'numeric', 'between:-40,40'],
            'temperature_change_c' => ['nullable', 'numeric', 'between:-4,6'],
            'water_availability_change_pct' => ['nullable', 'numeric', 'between:-50,30'],
        ], [
            'plot_ids.required' => 'Select at least one plot with an assigned crop.',
            'plot_ids.array' => 'Select at least one plot with an assigned crop.',
            'plot_ids.min' => 'Select at least one plot with an assigned crop.',
            'plot_ids.*.exists' => 'Each selected plot must belong to this farm.',
            'planting_season.in' => 'Choose a valid planting season.',
            'duration_days.min' => 'Duration must be at least :min days.',
            'duration_days.max' => 'Duration may not exceed :max days.',
            'water_scenario.in' => 'Choose a valid water availability scenario.',
            'climate_scenario.in' => 'Choose a valid climate scenario.',
        ]);

        $validator->after(function ($validator) use ($request, $farm): void {
            $plotIds = $request->input('plot_ids');

            if (! is_array($plotIds)) {
                return;
            }

            $fields = $farm->fields()
                ->whereIn('id', $plotIds)
                ->get(['id', 'crop_id'])
                ->keyBy('id');

            foreach ($plotIds as $index => $plotId) {
                $field = $fields->get($plotId);

                if ($field && $field->crop_id === null) {
                    $validator->errors()->add(
                        "plot_ids.$index",
                        'Each selected plot must have a crop assigned.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the scenario details.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $fields = $farm->fields()
            ->with('crop')
            ->whereIn('id', $data['plot_ids'])
            ->get();

        $settings = [
            'planting_season' => $data['planting_season'],
            'start_date' => $data['start_date'],
            'duration_days' => (int) $data['duration_days'],
            'water_scenario' => $data['water_scenario'],
            'climate_scenario' => $data['climate_scenario'],
            'seasonal_et0_mm' => isset($data['seasonal_et0_mm']) ? (float) $data['seasonal_et0_mm'] : null,
            'seasonal_precipitation_mm' => isset($data['seasonal_precipitation_mm']) ? (float) $data['seasonal_precipitation_mm'] : null,
            'seasonal_irrigation_available_mm' => isset($data['seasonal_irrigation_available_mm']) ? (float) $data['seasonal_irrigation_available_mm'] : null,
            'rainfall_change_pct' => isset($data['rainfall_change_pct']) ? (float) $data['rainfall_change_pct'] : 0.0,
            'temperature_change_c' => isset($data['temperature_change_c']) ? (float) $data['temperature_change_c'] : 0.0,
            'water_availability_change_pct' => isset($data['water_availability_change_pct']) ? (float) $data['water_availability_change_pct'] : 0.0,
        ];
        $plots = $fields->map(fn ($field) => [
            'id' => $field->id,
            'name' => $field->name,
            'crop_id' => $field->crop->id,
            'crop_name' => $field->crop->name,
            'area' => $field->area,
        ])->values()->all();

        $simulation = DB::transaction(function () use ($request, $farm, $settings, $plots, $data) {
            $userId = (int) $request->user()->id;
            $pending = Simulation::query()
                ->where('user_id', $userId)
                ->where('farm_id', $farm->id)
                ->where('status', 'pending')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            CropPlan::query()
                ->where('user_id', $userId)
                ->where('farm_id', $farm->id)
                ->where('status', 'selected')
                ->update(['status' => 'superseded']);

            $plan = $pending?->selectedPlan;
            if ($plan) {
                $plan->update([
                    'name' => $data['name'] ?? null,
                    'settings' => $settings,
                    'plots' => $plots,
                    'status' => 'selected',
                ]);
            } else {
                $plan = CropPlan::create([
                    'user_id' => $userId,
                    'farm_id' => $farm->id,
                    'name' => $data['name'] ?? null,
                    'settings' => $settings,
                    'plots' => $plots,
                    'status' => 'selected',
                ]);
            }

            $conversationId = ChatConversation::query()
                ->where('user_id', $userId)
                ->where('farm_id', $farm->id)
                ->latest('id')
                ->value('id');

            Simulation::query()
                ->where('user_id', $userId)
                ->where('farm_id', $farm->id)
                ->whereIn('status', ['running', 'completed'])
                ->update([
                    'status' => 'stale',
                    'error' => 'Farm or plan inputs changed; run the selected plan to generate current results.',
                    'finished_at' => now(),
                ]);

            return Simulation::updateOrCreate(
                [
                    'user_id' => $userId,
                    'farm_id' => $farm->id,
                    'status' => 'pending',
                ],
                [
                    'selected_plan_id' => $plan->id,
                    'chat_conversation_id' => $conversationId,
                    'settings' => $settings,
                    'plots' => $plots,
                    'name' => $data['name'] ?? null,
                    'results' => null,
                    'error' => null,
                    'stage' => null,
                    'started_at' => null,
                    'finished_at' => null,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'simulation' => [
                'id' => $simulation->id,
                'status' => $simulation->status,
                'name' => $simulation->name,
                'selected_plan_id' => $simulation->selected_plan_id,
                'settings' => $simulation->settings,
                'plots' => $simulation->plots,
            ],
        ]);
    }

    private function ensureOwned(Simulation $simulation, Request $request): void
    {
        $farm = $simulation->farm;
        abort_if($farm === null, 404, 'Farm not found.');
        abort_unless(
            (int) $farm->user_id === (int) $request->user()->id
                && (int) $simulation->user_id === (int) $request->user()->id,
            403,
            'You do not own this simulation.'
        );
    }
}
