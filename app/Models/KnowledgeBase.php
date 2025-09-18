<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Methods
     */
    public function getItemsCount(): int
    {
        return $this->items()->count();
    }

    public function getActiveItemsCount(): int
    {
        return $this->items()->where('is_active', true)->count();
    }

    public function getTotalCharacters(): int
    {
        return $this->items()->sum(\DB::raw('LENGTH(content)'));
    }

    public function search(string $query, int $limit = 5)
    {
        return $this->items()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', '%' . $query . '%')
                  ->orWhere('content', 'LIKE', '%' . $query . '%');
            })
            ->limit($limit)
            ->get();
    }

    public function searchWithFullText(string $query, int $limit = 5)
    {
        return $this->items()
            ->where('is_active', true)
            ->whereRaw("MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)", [$query])
            ->limit($limit)
            ->get();
    }
}