<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerCondition extends Model
{
    protected $fillable = [
        'trigger_id',
        'type',
        'field',
        'operator',
        'value',
        'logic_operator',
        'position',
    ];

    protected $casts = [
        'value' => 'json',
        'position' => 'integer',
    ];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(FunctionTrigger::class, 'trigger_id');
    }
}