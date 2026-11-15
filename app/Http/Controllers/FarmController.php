<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class FarmController extends Controller
{
    private const PLANS = ['Starter', 'Professional', 'Enterprise'];

    private function draftFarm(Request $request)
    {
        return $request->user()
            ->farms()
            ->where('status', 'draft')
            ->latest()
            ->first();
    }

    public function explore(Request $request)
    {
        $farm = $request->user()
            ->farms()
            ->where('status', 'draft')
            ->latest()
            ->first();

        return view('pages.explore-farm', [
            'locations' => config('locations'),
            'centers'   => config('governorate_centers'),
            'farm'      => $farm,
        ]);
    }

    public function storeLocation(Request $request)
    {
        $locations = config('locations');

        $data = $request->validate([
            'country'     => ['required', Rule::in(array_keys($locations))],
            'governorate' => ['required', 'string'],
            'city'        => ['required', 'string'],
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required'  => 'Please pin your farm on the map.',
            'longitude.required' => 'Please pin your farm on the map.',
        ]);

        $governorates = $locations[$data['country']];

        if (! array_key_exists($data['governorate'], $governorates)) {
            return back()->withErrors(['governorate' => 'Invalid governorate.'])->withInput();
        }

        if (! in_array($data['city'], $governorates[$data['governorate']], true)) {
            return back()->withErrors(['city' => 'Invalid city for the selected governorate.'])->withInput();
        }

        if (! $this->insideGovernorate($data['governorate'], (float) $data['latitude'], (float) $data['longitude'])) {
            return back()
                ->withErrors(['latitude' => 'The pin is outside the selected governorate. Move it or change the governorate.'])
                ->withInput();
        }

        $farm = $request->user()
            ->farms()
            ->where('status', 'draft')
            ->latest()
            ->first();

        if ($farm) {
            $farm->update($data);
        } else {
            $request->user()->farms()->create($data + ['status' => 'draft']);
        }

                return redirect()->route('farm.details');
    }

    public function plans(Request $request)
    {
        if (! $this->draftFarm($request)) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        return view('pages.plans', ['plans' => self::PLANS]);
    }

    public function selectPlan(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(self::PLANS)],
        ]);

        $farm = $this->draftFarm($request);

        if (! $farm) {
            return redirect()->route('farm.explore')
                ->withErrors(['location' => 'Please save your farm location first.']);
        }

        $user = $request->user();

        DB::transaction(function () use ($user, $farm, $data) {
            $user->update(['plan' => $data['plan']]);
            $farm->update(['status' => 'active']);
        });

        return redirect()->route('farm.index', ['farm' => $farm->id]);
    }

    // فحص تقريبي: المسافة بين الـ pin ومركز المحافظة (haversine) لازم تكون داخل الـ radius
    private function insideGovernorate(string $governorate, float $lat, float $lng): bool
    {
        $center = config('governorate_centers')[$governorate] ?? null;

        if (! $center) {
            return false;
        }

        $earthRadius = 6371; // km
        $dLat = deg2rad($lat - $center['lat']);
        $dLng = deg2rad($lng - $center['lng']);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($center['lat'])) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        $distance = 2 * $earthRadius * asin(sqrt($a));

        return $distance <= $center['radius'];
    }
}