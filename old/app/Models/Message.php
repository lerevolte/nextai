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
        $content = $this->content;
        
        // 1. Сначала обрабатываем markdown ссылки [текст](url)
        $content = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/i',
            '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline">$1</a>',
            $content
        );
        
        // 2. Затем обрабатываем обычные ссылки
        $content = preg_replace(
            '/(https?:\/\/[^\s\<\>\[\]]+)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline">$1</a>',
            $content
        );
        
        // 3. Обрабатываем изображения отдельно (ссылки на .jpg, .png, .gif, .webp)
        $content = preg_replace_callback(
            '/<a[^>]+href="([^"]+\.(jpg|jpeg|png|gif|webp|svg))"[^>]*>([^<]+)<\/a>/i',
            function($matches) {
                $imageUrl = $matches[1];
                $linkText = $matches[3];
                return '<div class="image-container my-2">
                            <img src="' . htmlspecialchars($imageUrl) . '" 
                                 alt="' . htmlspecialchars($linkText) . '" 
                                 class="max-w-full h-auto rounded-lg cursor-pointer"
                                 onclick="window.open(\'' . htmlspecialchars($imageUrl) . '\', \'_blank\')" 
                                 loading="lazy">
                            <div class="text-xs text-gray-500 mt-1">' . htmlspecialchars($linkText) . '</div>
                        </div>';
            },
            $content
        );
        
        // 4. Обрабатываем прямые ссылки на изображения (без markdown)
        $content = preg_replace_callback(
            '/(https?:\/\/[^\s\<\>]+\.(jpg|jpeg|png|gif|webp|svg))/i',
            function($matches) {
                $imageUrl = $matches[1];
                return '<div class="image-container my-2">
                            <img src="' . htmlspecialchars($imageUrl) . '" 
                                 alt="Изображение" 
                                 class="max-w-full h-auto rounded-lg cursor-pointer"
                                 onclick="window.open(\'' . htmlspecialchars($imageUrl) . '\', \'_blank\')" 
                                 loading="lazy">
                        </div>';
            },
            $content
        );
        
        // 5. Преобразуем markdown bold (**текст**) в HTML
        $content = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $content);
        
        // 6. Преобразуем переносы строк в <br>
        $content = nl2br($content);
        
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