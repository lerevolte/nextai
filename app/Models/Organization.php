<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'bots_limit',
        'messages_limit',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function canCreateBot(): bool
    {
        return $this->bots()->count() < $this->bots_limit;
    }

    public function getMessagesUsedThisMonth(): int
    {
        return $this->bots()
            ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
            ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
            ->whereMonth('messages.created_at', now()->month)
            ->count();
    }
}