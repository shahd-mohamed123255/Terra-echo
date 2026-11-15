<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('farm.my-farm', [
            'farms' => $farms,
            'farm'  => $farm,
            'crops' => Crop::orderBy('id')->get(),
            'plots' => $plots,
        ]);
    }
}