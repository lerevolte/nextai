<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'attachments',
        'tokens_used',
        'response_time',
        'metadata',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'response_time' => 'float',
    ];

    /**
     * Relationships
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Scopes
     */
    public function scopeFromUser($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeFromAssistant($query)
    {
        return $query->where('role', 'assistant');
    }

    public function scopeFromSystem($query)
    {
        return $query->where('role', 'system');
    }

    public function scopeFromOperator($query)
    {
        return $query->where('role', 'operator');
    }

    /**
     * Methods
     */
    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isFromSystem(): bool
    {
        return $this->role === 'system';
    }

    public function isFromOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function getFormattedContent(): string
    {
        // Форматирование контента для отображения
        $content = e($this->content);
        
        // Преобразуем переносы строк в <br>
        $content = nl2br($content);
        
        // Преобразуем ссылки в кликабельные
        $content = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" class="text-blue-600 hover:underline">$1</a>',
            $content
        );
        
        return $content;
    }

    public function getRoleName(): string
    {
        $roles = [
            'user' => 'Пользователь',
            'assistant' => 'Бот',
            'system' => 'Система',
            'operator' => 'Оператор',
        ];

        return $roles[$this->role] ?? $this->role;
    }

    public function getRoleColor(): string
    {
        $colors = [
            'user' => 'blue',
            'assistant' => 'green',
            'system' => 'gray',
            'operator' => 'purple',
        ];

        return $colors[$this->role] ?? 'gray';
    }
}