<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionAction extends Model
{
    protected $fillable = [
        'function_id',
        'type',
        'provider',
        'config',
        'field_mapping',
        'position',
    ];

    protected $casts = [
        'config' => 'array',
        'field_mapping' => 'array',
        'position' => 'integer',
    ];

    public function function(): BelongsTo
    {
        return $this->belongsTo(BotFunction::class, 'function_id');
    }
}