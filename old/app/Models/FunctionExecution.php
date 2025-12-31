<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionExecution extends Model
{
    protected $fillable = [
        'function_id',
        'conversation_id',
        'message_id',
        'extracted_params',
        'action_results',
        'status',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'extracted_params' => 'array',
        'action_results' => 'array',
        'executed_at' => 'datetime',
    ];

    public function function(): BelongsTo
    {
        return $this->belongsTo(BotFunction::class, 'function_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}