<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeItemVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_item_id',
        'version',
        'title',
        'content',
        'embedding',
        'metadata',
        'created_by',
        'change_notes',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'knowledge_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}