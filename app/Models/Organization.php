<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'api_key',
        'settings',
        'bots_limit',
        'messages_limit',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'bots_limit' => 'integer',
        'messages_limit' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Автоматически генерируем API ключ при создании
        static::creating(function ($organization) {
            if (empty($organization->api_key)) {
                $organization->api_key = 'org_' . Str::random(32);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function crmIntegrations(): HasMany
    {
        return $this->hasMany(CrmIntegration::class);
    }

    public function abTests(): HasMany
    {
        return $this->hasMany(AbTest::class);
    }

    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }

    public function canCreateBot(): bool
    {
        return $this->bots()->count() < $this->bots_limit;
    }

    public function getRemainingBots(): int
    {
        return max(0, $this->bots_limit - $this->bots()->count());
    }

    public function getMessagesUsedThisMonth(): int
    {
        return $this->bots()
            ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
            ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
            ->whereMonth('messages.created_at', now()->month)
            ->whereYear('messages.created_at', now()->year)
            ->count('messages.id');
    }

    public function getRemainingMessages(): int
    {
        $used = $this->getMessagesUsedThisMonth();
        return max(0, $this->messages_limit - $used);
    }

    public function isWithinMessageLimit(): bool
    {
        return $this->getMessagesUsedThisMonth() < $this->messages_limit;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithStats($query)
    {
        return $query->withCount([
            'bots',
            'users',
            'bots as active_bots_count' => function ($q) {
                $q->where('is_active', true);
            }
        ]);
    }

    public function getStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_bots' => $this->bots()->count(),
            'active_bots' => $this->bots()->where('is_active', true)->count(),
            'total_users' => $this->users()->count(),
            'total_conversations' => $this->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->where('conversations.created_at', '>=', $startDate)
                ->count('conversations.id'),
            'total_messages' => $this->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
                ->where('messages.created_at', '>=', $startDate)
                ->count('messages.id'),
            'unique_users' => $this->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->where('conversations.created_at', '>=', $startDate)
                ->distinct('conversations.external_id')
                ->count('conversations.external_id'),
            'active_ab_tests' => $this->abTests()->active()->count(),
            'scheduled_reports' => $this->scheduledReports()->active()->count()
        ];
    }

    
}