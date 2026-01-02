<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_id',
        'knowledge_source_id',
        'type',
        'title',
        'content',
        'source_url',
        'external_id',
        'version',
        'metadata',
        'embedding',
        'is_active',
        'last_synced_at',
        'sync_metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding' => 'array',
        'sync_metadata' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Доступные типы элементов базы знаний
     */
    public const TYPES = [
        'manual' => [
            'name' => 'Ручной ввод',
            'icon' => '✏️',
        ],
        'url' => [
            'name' => 'Веб-страница',
            'icon' => '🔗',
        ],
        'file' => [
            'name' => 'Файл',
            'icon' => '📄',
        ],
        'notion' => [
            'name' => 'Notion',
            'icon' => '📝',
        ],
        'google_docs' => [
            'name' => 'Google Docs',
            'icon' => '📘',
        ],
        'google_drive' => [
            'name' => 'Google Drive',
            'icon' => '📁',
        ],
        'github' => [
            'name' => 'GitHub',
            'icon' => '🐙',
        ],
        'api' => [
            'name' => 'API',
            'icon' => '🔌',
        ],
    ];

    /**
     * Relationships
     */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeItemVersion::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFromSource($query, int $sourceId)
    {
        return $query->where('knowledge_source_id', $sourceId);
    }

    /**
     * Methods
     */
    public function getTypeName(): string
    {
        return self::TYPES[$this->type]['name'] ?? $this->type;
    }

    public function getTypeIcon(): string
    {
        return self::TYPES[$this->type]['icon'] ?? '📋';
    }

    public function getExcerpt(int $length = 150): string
    {
        return \Str::limit($this->content, $length);
    }

    public function getWordCount(): int
    {
        return str_word_count($this->content);
    }

    public function getCharacterCount(): int
    {
        return mb_strlen($this->content);
    }

    public function updateEmbedding(array $embedding): void
    {
        $this->update(['embedding' => $embedding]);
    }

    /**
     * Проверяет, является ли элемент синхронизируемым из внешнего источника
     */
    public function isSyncable(): bool
    {
        return in_array($this->type, ['notion', 'google_docs', 'google_drive', 'github', 'url']);
    }

    /**
     * Проверяет, нужно ли обновить элемент
     */
    public function needsSync(): bool
    {
        if (!$this->isSyncable() || !$this->source) {
            return false;
        }

        $interval = $this->source->sync_settings['interval'] ?? 'daily';
        
        if ($interval === 'manual') {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        $nextSync = match($interval) {
            'hourly' => $this->last_synced_at->addHour(),
            'daily' => $this->last_synced_at->addDay(),
            'weekly' => $this->last_synced_at->addWeek(),
            'monthly' => $this->last_synced_at->addMonth(),
            default => $this->last_synced_at->addDay(),
        };

        return now()->gte($nextSync);
    }

    /**
     * Получить URL источника для отображения
     */
    public function getSourceDisplayUrl(): ?string
    {
        if ($this->source_url) {
            return $this->source_url;
        }

        // Для Google Docs формируем URL из external_id
        if ($this->type === 'google_docs' && $this->external_id) {
            return "https://docs.google.com/document/d/{$this->external_id}/edit";
        }

        // Для Notion
        if ($this->type === 'notion' && isset($this->sync_metadata['notion_url'])) {
            return $this->sync_metadata['notion_url'];
        }

        return null;
    }
}