<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'temperature' => 'float',
        'is_active' => 'boolean',
        'knowledge_base_enabled' => 'boolean',
        'collect_contacts' => 'boolean',
        'human_handoff_enabled' => 'boolean',
        'working_hours' => 'array',
        'settings' => 'array',
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
}