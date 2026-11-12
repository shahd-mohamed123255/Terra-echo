<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoilReport extends Model
{
    protected $fillable = [
        'farm_id',
        'salinity',
        'ph',
        'nitrogen',
        'phosphorus',
        'potassium',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
} 