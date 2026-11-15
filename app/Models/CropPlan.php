<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CropPlan extends Model
{
    /** @use HasFactory<\Database\Factories\CropPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'farm_id',
        'name',
        'settings',
        'plots',
        'status',
    ];

    protected $casts = [
        'settings' => 'array',
        'plots' => 'array',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class, 'selected_plan_id');
    }
}
