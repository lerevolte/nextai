<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionParameter extends Model
{
    protected $fillable = [
        'function_id',
        'code',
        'name',
        'type',
        'description',
        'is_required',
        'validation_rules',
        'extraction_hints',
        'position',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
        'extraction_hints' => 'array',
        'position' => 'integer',
    ];

    public function function(): BelongsTo
    {
        return $this->belongsTo(BotFunction::class, 'function_id');
    }
}