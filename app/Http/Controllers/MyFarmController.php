<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\CropPlan;
use App\Models\Field;
use App\Models\Simulation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MyFarmController extends Controller
{
    public function index(Request $request)
    {
        $farms = $request->user()->farms()->where('status', 'active')->latest()->get();

        // لو اليوزر اختار مزرعة من الـ dropdown ناخدها، وإلا أحدث واحدة
        $farm = $farms->firstWhere('id', $request->query('farm')) ?? $farms->first();

        if (! $farm) {
            return redirect()->route('farm.explore');
        }

        DB::transaction(function () use ($farm): void {
            $farm->newQuery()
                ->whereKey($farm->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($farm->fields()->exists() || $farm->area === null) {
                return;
            }

            $areaCents = (int) round((float) $farm->area * 100);
            $plotAreaCents = (int) round($areaCents / 4);
            $plotAreas = [
                $plotAreaCents,
                $plotAreaCents,
                $plotAreaCents,
                $areaCents - (3 * $plotAreaCents),
            ];

            foreach (['P1', 'P2', 'P3', 'P4'] as $index => $name) {
                $farm->fields()->create([
                    'name' => $name,
                    'area' => number_format($plotAreas[$index] / 100, 2, '.', ''),
                    'status' => 'fallow',
                    'crop_id' => null,
                ]);
            }
        });

        $farm->load(['soilType', 'soilReport', 'crops']);
        $plots = $farm->fields()->with('crop')->orderBy('name')->get();
        $scenario = Simulation::query()
            ->where('user_id', $request->user()->id)
            ->where('farm_id', $farm->id)
            ->latest()
            ->first();
        $latestCompletedSimulation = Simulation::query()
            ->where('user_id', $request->user()->id)
            ->where('farm_id', $farm->id)
            ->where('status', 'completed')
            ->latest()
            ->first();
        $selectedCropPlan = CropPlan::query()
            ->where('farm_id', $farm->id)
            ->where('status', 'selected')
            ->latest()
            ->first();

        return view('farm.my-farm', [
            'farms' => $farms,
            'farm' => $farm,
            'crops' => Crop::orderBy('id')->get(),
            'plots' => $plots,
            'simulationOptions' => config('simulation'),
            'scenarioSettings' => $scenario?->settings ?? [],
            'latestSimulation' => $scenario,
            'latestCompletedSimulation' => $latestCompletedSimulation,
            'selectedCropPlan' => $selectedCropPlan,
        ]);
    }

    public function assignCrop(Request $request, int $fieldId)
    {
        $field = Field::with(['farm', 'crop'])->findOrFail($fieldId);

        abort_unless($field->farm->user_id === auth()->id(), 403, 'You do not own this plot.');
        abort_unless($field->farm->status === 'active', 404, 'Plot not found.');

        $validator = Validator::make($request->all(), [
            'crop_id' => ['required', 'integer', 'exists:crops,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The selected crop is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $field->update([
            'crop_id' => $validator->validated()['crop_id'],
            'status' => 'planted',
        ]);
        $this->markFarmSimulationsStale($field->farm_id);

        return response()->json([
            'success' => true,
            'plot' => $this->plotPayload($field->load('crop')),
        ]);
    }

    public function removeCrop(int $fieldId)
    {
        $field = Field::with(['farm', 'crop'])->findOrFail($fieldId);

        abort_unless($field->farm->user_id === auth()->id(), 403, 'You do not own this plot.');
        abort_unless($field->farm->status === 'active', 404, 'Plot not found.');

        $field->update([
            'crop_id' => null,
            'status' => 'fallow',
        ]);
        $this->markFarmSimulationsStale($field->farm_id);

        return response()->json([
            'success' => true,
            'plot' => $this->plotPayload($field->load('crop')),
        ]);
    }

    private function plotPayload(Field $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'status' => $field->status,
            'crop' => $field->crop
                ? ['id' => $field->crop->id, 'name' => $field->crop->name]
                : null,
        ];
    }

    private function markFarmSimulationsStale(int $farmId): void
    {
        CropPlan::query()
            ->where('farm_id', $farmId)
            ->where('status', 'selected')
            ->update(['status' => 'stale']);

        Simulation::query()
            ->where('farm_id', $farmId)
            ->whereIn('status', ['running', 'completed'])
            ->update([
                'status' => 'stale',
                'error' => 'Farm crop assignments changed; save and run a new plan for current results.',
                'finished_at' => now(),
            ]);
    }
}
