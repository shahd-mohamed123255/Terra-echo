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

        $availablePlans = app(PlanController::class)->generatePlansForFarm($farm);
        $userPlanId = $request->user()->plan ?? 'balanced';
        $selectedPlanCode = match ($userPlanId) {
            'water-saving' => 'plan_b',
            'soil-health' => 'plan_c',
            'high-yield' => 'plan_d',
            default => 'plan_a',
        };

        $cropSlugMap = [
            'wheat' => 'wheat',
            'beans' => 'faba_bean',
            'faba-bean' => 'faba_bean',
            'faba_bean' => 'faba_bean',
            'berseem-clover' => 'berseem_clover',
            'berseem_clover' => 'berseem_clover',
            'clover' => 'berseem_clover',
            'barley' => 'barley',
            'corn' => 'maize',
            'maize' => 'maize',
            'cotton' => 'cotton',
            'rice' => 'rice',
            'potato' => 'potato',
            'potatoes' => 'potato',
            'sugar-beet' => 'sugar_beet',
            'sugar_beet' => 'sugar_beet',
            'soybeans' => 'soybeans',
            'sorghum' => 'sorghum',
        ];

        $plotsMap = [];
        foreach ($plots as $plot) {
            $rawSlug = $plot->crop?->slug ?? ($plot->crop?->name ? strtolower(str_replace(' ', '_', $plot->crop->name)) : null);
            $mappedCrop = $rawSlug ? ($cropSlugMap[$rawSlug] ?? $rawSlug) : 'wheat';
            $plotsMap[$plot->name] = $mappedCrop;
        }

        $farmData = [
            'id' => $farm->id,
            'name' => $farm->city ? $farm->city . ' Farm' : ($farm->name ?? 'Illustrative Farm'),
            'location' => collect([$farm->city, $farm->governorate, $farm->country])->filter()->implode(', ') ?: 'Egypt',
            'governorate' => $farm->governorate,
            'city' => $farm->city,
            'country' => $farm->country,
            'latitude' => $farm->latitude ? (float) $farm->latitude : 30.56,
            'longitude' => $farm->longitude ? (float) $farm->longitude : 31.01,
            'coordinates' => $farm->latitude && $farm->longitude
                ? round((float) $farm->latitude, 2) . '° N, ' . round((float) $farm->longitude, 2) . '° E'
                : '30.56° N · Monufia data zone',
            'area' => (float) ($farm->area ?? 5.0),
            'area_unit' => $farm->area_unit ?? 'feddan',
            'area_hectares' => round((float) ($farm->area ?? 5.0) * 0.42, 2),
            'soil_type' => strtolower((string) ($farm->soilType?->name ?? 'clay')),
            'target_season' => strtolower((string) ($farm->target_season ?? 'winter')),
            'priority' => $farm->priority ?? 'balanced',
            'plots' => $plotsMap,
        ];

        return view('farm.my-farm', [
            'farms' => $farms,
            'farm' => $farm,
            'farmData' => $farmData,
            'crops' => Crop::orderBy('id')->get(),
            'plots' => $plots,
            'availablePlans' => $availablePlans,
            'selectedPlanCode' => $selectedPlanCode,
            'userPlanId' => $userPlanId,
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
