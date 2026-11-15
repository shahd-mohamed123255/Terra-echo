<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Farm extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'country',
        'governorate',
        'city',
        'latitude',
        'longitude',
        'area',
        'area_unit',
        'soil_type_id',
        'irrigation_source',
        'priority',
        'target_season',
        'planning_horizon',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function soilType(): BelongsTo
    {
        return $this->belongsTo(SoilType::class);
    }

    public function soilReport(): HasOne
    {
        return $this->hasOne(SoilReport::class);
    }

    public function crops(): BelongsToMany
    {
        return $this->belongsToMany(Crop::class, 'farm_crop')
            ->withPivot('type')
            ->withTimestamps();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }
}