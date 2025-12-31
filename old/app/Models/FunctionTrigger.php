<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunctionTrigger extends Model
{
    protected $fillable = [
        'function_id',
        'type',
        'name',
        'conditions',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function function(): BelongsTo
    {
        return $this->belongsTo(BotFunction::class, 'function_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(TriggerCondition::class, 'trigger_id')->orderBy('position');
    }

    /**
     * Проверить, срабатывает ли триггер
     */
    public function matches(Message $message, Conversation $conversation): bool
    {
        switch ($this->type) {
            case 'intent':
                return $this->matchesIntent($message);
                
            case 'keyword':
                return $this->matchesKeywords($message);
                
            case 'pattern':
                return $this->matchesPattern($message);
                
            case 'entity':
                return $this->matchesEntity($message);
                
            case 'schedule':
                return $this->matchesSchedule();
                
            case 'webhook':
                return false; // Обрабатывается отдельно
                
            default:
                return $this->matchesConditions($message, $conversation);
        }
    }

    /**
     * Проверка намерения через AI
     */
    protected function matchesIntent(Message $message): bool
    {
        $intent = $this->conditions['intent'] ?? null;
        if (!$intent) return false;

        // Используем AI для определения намерения
        $detectedIntent = app(IntentDetectionService::class)->detect(
            $message->content,
            $message->conversation->bot
        );

        return $detectedIntent && 
               $detectedIntent->name === $intent &&
               $detectedIntent->confidence >= ($this->conditions['min_confidence'] ?? 0.7);
    }

    /**
     * Проверка ключевых слов
     */
    protected function matchesKeywords(Message $message): bool
    {
        $keywords = $this->conditions['keywords'] ?? [];
        $mode = $this->conditions['mode'] ?? 'any'; // any|all|exact
        
        $content = mb_strtolower($message->content);
        
        switch ($mode) {
            case 'all':
                foreach ($keywords as $keyword) {
                    if (mb_strpos($content, mb_strtolower($keyword)) === false) {
                        return false;
                    }
                }
                return true;
                
            case 'exact':
                return in_array($content, array_map('mb_strtolower', $keywords));
                
            default: // any
                foreach ($keywords as $keyword) {
                    if (mb_strpos($content, mb_strtolower($keyword)) !== false) {
                        return true;
                    }
                }
                return false;
        }
    }

    /**
     * Проверка паттерна (regex)
     */
    protected function matchesPattern(Message $message): bool
    {
        $pattern = $this->conditions['pattern'] ?? null;
        if (!$pattern) return false;

        return (bool)preg_match($pattern, $message->content);
    }
}