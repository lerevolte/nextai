<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'channel_id',
        'external_id',
        'status',
        'user_name',
        'user_email',
        'user_phone',
        'user_data',
        'messages_count',
        'ai_tokens_used',
        'last_message_at',
        'closed_at',
        'context',
        'metadata',
    ];

    protected $casts = [
        'user_data' => 'array',
        'context' => 'array',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeWaitingOperator($query)
    {
        return $query->where('status', 'waiting_operator');
    }

    /**
     * Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isWaitingOperator(): bool
    {
        return $this->status === 'waiting_operator';
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'active',
            'closed_at' => null,
        ]);
    }

    public function assignToOperator(): void
    {
        $this->update([
            'status' => 'waiting_operator',
        ]);
    }

    public function getUserDisplayName(): string
    {
        return $this->user_name ?? $this->user_email ?? $this->user_phone ?? 'Гость #' . $this->id;
    }

    public function getLastMessage()
    {
        return $this->messages()->latest()->first();
    }

    public function getDuration(): string
    {
        if ($this->closed_at) {
            $duration = $this->closed_at->diff($this->created_at);
        } else {
            $duration = now()->diff($this->created_at);
        }

        if ($duration->d > 0) {
            return $duration->d . ' д. ' . $duration->h . ' ч.';
        } elseif ($duration->h > 0) {
            return $duration->h . ' ч. ' . $duration->i . ' мин.';
        } else {
            return $duration->i . ' мин.';
        }
    }

    public function getAverageResponseTime(): float
    {
        $avgTime = $this->messages()
            ->where('role', 'assistant')
            ->whereNotNull('response_time')
            ->avg('response_time');

        return round($avgTime ?? 0, 2);
    }
}