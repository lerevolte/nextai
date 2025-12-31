<?php

namespace App\Services;

use App\Models\BotFunction;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\FunctionTrigger;
use Illuminate\Support\Collection;

class TriggerMatchingService
{
    protected IntentDetectionService $intentService;
    
    public function __construct(IntentDetectionService $intentService)
    {
        $this->intentService = $intentService;
    }
    
    /**
     * Найти подходящие функции для сообщения
     */
    public function findMatchingFunctions(Message $message): Collection
    {
        $conversation = $message->conversation;
        $bot = $conversation->bot;
        
        // Получаем активные функции бота
        $functions = $bot->functions()
            ->where('is_active', true)
            ->with(['triggers' => function($query) {
                $query->where('is_active', true)
                      ->orderBy('priority', 'desc');
            }])
            ->get();
        
        $matchingFunctions = collect();
        
        foreach ($functions as $function) {
            $matchedTrigger = $this->findMatchingTrigger($function, $message, $conversation);
            
            if ($matchedTrigger) {
                $matchingFunctions->push([
                    'function' => $function,
                    'trigger' => $matchedTrigger,
                    'priority' => $matchedTrigger->priority
                ]);
            }
        }
        
        // Сортируем по приоритету
        return $matchingFunctions->sortByDesc('priority');
    }
    
    /**
     * Найти подходящий триггер для функции
     */
    protected function findMatchingTrigger(BotFunction $function, Message $message, Conversation $conversation): ?FunctionTrigger
    {
        foreach ($function->triggers as $trigger) {
            if ($trigger->matches($message, $conversation)) {
                // Логируем срабатывание триггера
                $this->logTriggerMatch($trigger, $message, $conversation);
                return $trigger;
            }
        }
        
        return null;
    }
    
    /**
     * Логировать срабатывание триггера
     */
    protected function logTriggerMatch(FunctionTrigger $trigger, Message $message, Conversation $conversation): void
    {
        \DB::table('trigger_logs')->insert([
            'trigger_id' => $trigger->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'matched' => true,
            'match_details' => json_encode([
                'trigger_type' => $trigger->type,
                'message_content' => $message->content,
                'conditions' => $trigger->conditions
            ]),
            'triggered_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}