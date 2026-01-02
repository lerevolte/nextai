<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class KnowledgeChunk extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'knowledge_base_id',
        'knowledge_item_id',
        'knowledge_source_id',
        'title',
        'content',
        'source_url',
        'chunk_index',
        'total_chunks',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Имя индекса в Elasticsearch
     */
    public function searchableAs(): string
    {
        return 'knowledge_chunks';
    }

    /**
     * Данные для индексации в Elasticsearch
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'knowledge_base_id' => $this->knowledge_base_id,
            'title' => $this->title,
            'content' => $this->content,
            'source_url' => $this->source_url,
            'chunk_index' => $this->chunk_index,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Индексировать только активные чанки
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active;
    }

    /**
     * Relationships
     */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function knowledgeItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    /**
     * Получить превью контента
     */
    public function getExcerpt(int $length = 200): string
    {
        return \Str::limit($this->content, $length);
    }
}