<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // Добавьте этот импорт

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

    // Исправленный метод versions с правильной типизацией
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

    /**
     * Methods
     */
    public function getTypeName(): string
    {
        $types = [
            'manual' => 'Ручной ввод',
            'url' => 'Веб-страница',
            'file' => 'Файл',
            'notion' => 'Notion',
            'api' => 'API',
        ];

        return $types[$this->type] ?? $this->type;
    }

    public function getTypeIcon(): string
    {
        $icons = [
            'manual' => '✏️',
            'url' => '🔗',
            'file' => '📄',
            'notion' => '📝',
            'api' => '🔌',
        ];

        return $icons[$this->type] ?? '📋';
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
        return strlen($this->content);
    }

    public function updateEmbedding(array $embedding): void
    {
        $this->update(['embedding' => $embedding]);
    }
}