<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'type',
        'comment',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Scopes
     */
    public function scopePositive($query)
    {
        return $query->where('type', 'positive');
    }

    public function scopeNegative($query)
    {
        return $query->where('type', 'negative');
    }

    /**
     * Methods
     */
    public function isPositive(): bool
    {
        return $this->type === 'positive';
    }

    public function isNegative(): bool
    {
        return $this->type === 'negative';
    }

    public function getTypeIcon(): string
    {
        return $this->isPositive() ? '👍' : '👎';
    }

    public function getTypeColor(): string
    {
        return $this->isPositive() ? 'green' : 'red';
    }
}