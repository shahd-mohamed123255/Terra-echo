<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use Illuminate\Http\Request;

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

        $farm->load(['soilType', 'soilReport', 'crops']);

        return view('farm.my-farm', [
            'farms' => $farms,
            'farm'  => $farm,
            'crops' => Crop::orderBy('id')->get(),
        ]);
    }
}