<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionBehavior extends Model
{
    protected $fillable = [
        'function_id',
        'on_success',
        'on_error',
        'success_message',
        'error_message',
        'prompt_enhancement',
        'accumulate_parameters'
    ];
    
    protected $casts = [
        'accumulate_parameters' => 'boolean',
    ];

    public function function(): BelongsTo
    {
        return $this->belongsTo(BotFunction::class, 'function_id');
    }
}