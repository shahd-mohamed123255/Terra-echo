<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Crop extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'water_mm',
        'water_m3_per_ha',
        'season',
        'season_months',
        'duration_min_days',
        'duration_max_days',
        'preferred_soil',
        'image',
    ];

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_crop')
            ->withPivot('type')
            ->withTimestamps();
    }
} 