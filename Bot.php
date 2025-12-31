<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bot extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'avatar_url',
        'ai_provider',
        'ai_model',
        'system_prompt',
        'welcome_message',
        'temperature',
        'max_tokens',
        'is_active',
        'knowledge_base_enabled',
        'collect_contacts',
        'human_handoff_enabled',
        'working_hours',
        'settings',
        'metadata'
    ];

    protected $casts = [
        'temperature' => 'float',
        'is_active' => 'boolean',
        'knowledge_base_enabled' => 'boolean',
        'collect_contacts' => 'boolean',
        'human_handoff_enabled' => 'boolean',
        'working_hours' => 'array',
        'settings' => 'array',
        'metadata' => 'array'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function knowledgeBase(): HasOne
    {
        return $this->hasOne(KnowledgeBase::class);
    }


    public function crmIntegrations()
    {
        return $this->belongsToMany(CrmIntegration::class, 'bot_crm_integrations')
            ->withPivot([
                'settings',
                'sync_contacts',
                'sync_conversations',
                'create_leads',
                'create_deals',
                'lead_source',
                'responsible_user_id',
                'pipeline_settings',
                'is_active'
            ])
            ->withTimestamps();
    }


    public function getActiveChannels()
    {
        return $this->channels()->where('is_active', true)->get();
    }

    public function isWorkingHours(): bool
    {
        if (empty($this->working_hours)) {
            return true;
        }

        // Логика проверки рабочих часов
        $now = now();
        $dayOfWeek = strtolower($now->format('l'));
        
        if (!isset($this->working_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->working_hours[$dayOfWeek];
        if (!$hours['enabled']) {
            return false;
        }

        $currentTime = $now->format('H:i');
        return $currentTime >= $hours['from'] && $currentTime <= $hours['to'];
    }

    public function getStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_conversations' => $this->conversations()
                ->where('created_at', '>=', $startDate)
                ->count(),
            'active_conversations' => $this->conversations()
                ->where('status', 'active')
                ->count(),
            'completed_conversations' => $this->conversations()
                ->where('status', 'closed')
                ->where('created_at', '>=', $startDate)
                ->count(),
            'avg_response_time' => $this->conversations()
                ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
                ->where('messages.role', 'assistant')
                ->where('messages.created_at', '>=', $startDate)
                ->avg('messages.response_time'),
            'total_messages' => $this->conversations()
                ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
                ->where('messages.created_at', '>=', $startDate)
                ->count('messages.id'),
            'unique_users' => $this->conversations()
                ->where('created_at', '>=', $startDate)
                ->distinct('external_id')
                ->count('external_id'),
            'satisfaction_rate' => $this->calculateSatisfactionRate($startDate),
            'tokens_used' => $this->conversations()
                ->where('created_at', '>=', $startDate)
                ->sum('ai_tokens_used')
        ];
    }

    public function calculateSatisfactionRate($startDate): float
    {
        $total = $this->conversations()
            ->join('feedback', 'conversations.id', '=', 'feedback.conversation_id')
            ->where('feedback.created_at', '>=', $startDate)
            ->count();
        
        if ($total == 0) {
            return 0;
        }
        
        $positive = $this->conversations()
            ->join('feedback', 'conversations.id', '=', 'feedback.conversation_id')
            ->where('feedback.created_at', '>=', $startDate)
            ->where('feedback.type', 'positive')
            ->count();
        
        return round(($positive / $total) * 100, 2);
    }



    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithStats($query, int $days = 30)
    {
        $startDate = now()->subDays($days);
        
        return $query->withCount([
            'conversations',
            'conversations as active_conversations_count' => function ($q) {
                $q->where('status', 'active');
            },
            'conversations as recent_conversations_count' => function ($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate);
            }
        ]);
    }
}