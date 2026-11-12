<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Crop extends Model
{
    protected $fillable = ['name', 'slug'];

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_crop')
            ->withPivot('type')
            ->withTimestamps();
    }
} 