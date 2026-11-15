<?php

namespace App\Http\Controllers;

use App\Models\SoilType;
use App\Models\Crop;
use Illuminate\Http\Request;

class FarmWizardController extends Controller
{
    // نفس طريقة FarmController: آخر farm بحالة draft لليوزر
    private function draftFarm(Request $request)
    {
        return $request->user()
            ->farms()
            ->where('status', 'draft')
            ->latest()
            ->first();
    }

    public function details(Request $request)
    {
        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        return view('farm.details', [
            'farm'      => $farm,
            'soilTypes' => SoilType::orderBy('id')->get(),
            'report'    => $farm->soilReport,
        ]);
    }

    public function saveDetails(Request $request)
    {
        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        $data = $request->validate([
            'area'              => ['required', 'numeric', 'min:0.01'],
            'area_unit'         => ['required', 'in:feddan'],
            'soil_type_id'      => ['required', 'exists:soil_types,id'],
            'has_soil_analysis' => ['required', 'boolean'],
            'salinity'          => ['nullable', 'required_if:has_soil_analysis,1', 'numeric', 'min:0'],
            'ph'                => ['nullable', 'required_if:has_soil_analysis,1', 'numeric', 'between:0,14'],
            'nitrogen'          => ['nullable', 'numeric', 'min:0'],
            'phosphorus'        => ['nullable', 'numeric', 'min:0'],
            'potassium'         => ['nullable', 'numeric', 'min:0'],
        ]);

        $farm->update([
            'area'         => $data['area'],
            'area_unit'    => $data['area_unit'],
            'soil_type_id' => $data['soil_type_id'],
        ]);

        if ($data['has_soil_analysis']) {
            $farm->soilReport()->updateOrCreate(
                ['farm_id' => $farm->id],
                [
                    'salinity'   => $data['salinity'],
                    'ph'         => $data['ph'],
                    'nitrogen'   => $data['nitrogen'] ?? null,
                    'phosphorus' => $data['phosphorus'] ?? null,
                    'potassium'  => $data['potassium'] ?? null,
                ]
            );
        } else {
            $farm->soilReport()->delete();
        }

        return redirect()->route('farm.water');
    }

    private const SEASONS = [
        'Winter 2026/2027',
        'Summer 2027',
        'Winter 2027/2028',
        'Summer 2028',
    ];

    public function water(Request $request)
    {
        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        return view('farm.water', [
            'farm'          => $farm,
            'crops'         => Crop::orderBy('id')->get(),
            'previousIds'   => $farm->crops()->wherePivot('type', 'previous')->pluck('crops.id')->all(),
            'consideringIds'=> $farm->crops()->wherePivot('type', 'considering')->pluck('crops.id')->all(),
            'seasons'       => self::SEASONS,
        ]);
    }

    public function saveWater(Request $request)
    {
        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        $data = $request->validate([
            'irrigation_source'       => ['required', 'in:canal,groundwater,rainfed,mixed'],
            'previous_crops'          => ['required', 'array', 'min:1'],
            'previous_crops.*'        => ['exists:crops,id'],
            'considering_crops'       => ['required', 'array', 'min:1'],
            'considering_crops.*'     => ['exists:crops,id'],
            'priority'                => ['required', 'in:balanced,water_efficiency,soil_health,local_cost'],
            'target_season'           => ['required', 'in:' . implode(',', self::SEASONS)],
            'planning_horizon_choice' => ['required', 'in:1,2,3,custom'],
            'custom_horizon'          => ['nullable', 'required_if:planning_horizon_choice,custom', 'integer', 'between:1,12'],
        ]);

        $horizon = $data['planning_horizon_choice'] === 'custom'
            ? (int) $data['custom_horizon']
            : (int) $data['planning_horizon_choice'];

        $farm->update([
            'irrigation_source' => $data['irrigation_source'],
            'priority'          => $data['priority'],
            'target_season'     => $data['target_season'],
            'planning_horizon'  => $horizon,
        ]);

        $farm->crops()->detach();

        foreach (array_unique($data['previous_crops']) as $id) {
            $farm->crops()->attach($id, ['type' => 'previous']);
        }
        foreach (array_unique($data['considering_crops']) as $id) {
            $farm->crops()->attach($id, ['type' => 'considering']);
        }

        return redirect()->route('farm.summary');
    }

    public function summary(Request $request)
    {
        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        // لو خطوة ناقصة نرجّعها للخطوة دي
        if ($farm->area === null || ! $farm->soil_type_id) {
            return redirect()->route('farm.details')
                ->withErrors(['details' => 'Please complete the farm details first.']);
        }

        if (! $farm->irrigation_source || ! $farm->priority || ! $farm->target_season || ! $farm->planning_horizon) {
            return redirect()->route('farm.water')
                ->withErrors(['water' => 'Please complete the water and crops step first.']);
        }

        $farm->load(['soilType', 'soilReport', 'crops']);

        return view('farm.summary', [
            'farm'        => $farm,
            'previous'    => $farm->crops->where('pivot.type', 'previous')->pluck('name'),
            'considering' => $farm->crops->where('pivot.type', 'considering')->pluck('name'),
        ]);
    }



    public function confirm(Request $request)
    {
        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        $farm->update(['status' => 'active']);

        return redirect()->route('plans', ['farm' => $farm->id]);
    }


}