<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simulation extends Model
{
    protected $fillable = [
        'user_id',
        'farm_id',
        'selected_plan_id',
        'chat_conversation_id',
        'name',
        'status',
        'settings',
        'plots',
        'results',
        'source',
        'completed_at',
        'stage',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'plots' => 'array',
        'results' => 'array',
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedPlan(): BelongsTo
    {
        return $this->belongsTo(CropPlan::class, 'selected_plan_id');
    }

    public function chatConversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }
}
