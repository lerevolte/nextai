<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_id',
        'type',
        'name',
        'config',
        'sync_settings',
        'is_active',
        'last_sync_at',
        'next_sync_at',
        'sync_status',
    ];

    protected $casts = [
        'config' => 'array',
        'sync_settings' => 'array',
        'sync_status' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
    ];

    /**
     * Типы источников
     */
    public const TYPES = [
        'notion' => [
            'name' => 'Notion',
            'icon' => '📝',
        ],
        'google_docs' => [
            'name' => 'Google Docs',
            'icon' => '📘',
        ],
        'url' => [
            'name' => 'Веб-страницы',
            'icon' => '🌐',
        ],
        'google_drive' => [
            'name' => 'Google Drive',
            'icon' => '📁',
        ],
        'github' => [
            'name' => 'GitHub',
            'icon' => '🐙',
        ],
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(KnowledgeSyncLog::class);
    }

    /**
     * Получить название типа источника
     */
    public function getTypeName(): string
    {
        return self::TYPES[$this->type]['name'] ?? $this->type;
    }

    /**
     * Получить иконку типа источника
     */
    public function getTypeIcon(): string
    {
        return self::TYPES[$this->type]['icon'] ?? '📊';
    }

    /**
     * Проверить, активна ли автосинхронизация
     */
    public function isAutoSyncEnabled(): bool
    {
        return ($this->sync_settings['auto_sync'] ?? false) 
            && ($this->sync_settings['interval'] ?? 'manual') !== 'manual';
    }

    /**
     * Получить последний лог синхронизации
     */
    public function getLastSyncLog(): ?KnowledgeSyncLog
    {
        return $this->syncLogs()->latest()->first();
    }
}