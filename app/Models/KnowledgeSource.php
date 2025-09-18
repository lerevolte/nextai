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
}