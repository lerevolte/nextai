<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BotFunction extends Model
{
    protected $fillable = [
        'bot_id',
        'name',
        'display_name',
        'description',
        'is_active',
        'trigger_type',
        'trigger_keywords',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trigger_keywords' => 'array',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(FunctionParameter::class, 'function_id')->orderBy('position');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(FunctionAction::class, 'function_id')->orderBy('position');
    }

    public function behavior(): HasOne
    {
        return $this->hasOne(FunctionBehavior::class, 'function_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FunctionExecution::class, 'function_id');
    }

    public function shouldTrigger(string $message): bool
    {
        if ($this->trigger_type === 'auto') {
            return true;
        }
        
        if ($this->trigger_type === 'keyword' && $this->trigger_keywords) {
            foreach ($this->trigger_keywords as $keyword) {
                if (stripos($message, $keyword) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
}