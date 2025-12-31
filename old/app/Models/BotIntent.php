<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotIntent extends Model
{
    protected $fillable = [
        'bot_id',
        'name',
        'display_name',
        'training_phrases',
        'entities',
        'confidence_threshold',
        'is_active',
    ];

    protected $casts = [
        'training_phrases' => 'array',
        'entities' => 'array',
        'confidence_threshold' => 'float',
        'is_active' => 'boolean',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}