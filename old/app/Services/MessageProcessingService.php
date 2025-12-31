<?php

namespace App\Services;

use App\Models\Message;
use App\Models\BotFunction;
use Illuminate\Support\Facades\Log;

class MessageProcessingService
{
    protected TriggerMatchingService $triggerService;
    protected ParameterExtractorService $extractorService;
    protected FunctionExecutionService $executionService;
    
    public function __construct(
        TriggerMatchingService $triggerService,
        ParameterExtractorService $extractorService,
        FunctionExecutionService $executionService
    ) {
        $this->triggerService = $triggerService;
        $this->extractorService = $extractorService;
        $this->executionService = $executionService;
    }
    
    /**
     * Обработать сообщение пользователя
     */
    public function processMessage(Message $message): void
    {
        $conversation = $message->conversation;
        $bot = $conversation->bot;
        
        Log::info('Processing message for functions', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'content' => $message->content
        ]);
        
        // Получаем активные функции бота
        $functions = $bot->functions()
            ->where('is_active', true)
            ->with(['parameters', 'actions', 'behavior'])
            ->get();
            
        if ($functions->isEmpty()) {
            Log::info('No active functions for bot', ['bot_id' => $bot->id]);
            return;
        }
        
        // Проверяем каждую функцию
        foreach ($functions as $function) {
            if ($this->shouldTriggerFunction($function, $message)) {
                $this->executeFunction($function, $message);
            }
        }
    }
    
    /**
     * Проверить, должна ли функция сработать
     */
    protected function shouldTriggerFunction(BotFunction $function, Message $message): bool
    {
        switch ($function->trigger_type) {
            case 'keyword':
                return $this->checkKeywordTrigger($function, $message);
                
            case 'auto':
                return true;
                
            case 'manual':
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Проверить триггер по ключевым словам
     */
    protected function checkKeywordTrigger(BotFunction $function, Message $message): bool
    {
        if (empty($function->trigger_keywords)) {
            return false;
        }
        
        $content = mb_strtolower($message->content);
        
        foreach ($function->trigger_keywords as $keyword) {
            if (mb_stripos($content, mb_strtolower($keyword)) !== false) {
                Log::info('Keyword trigger matched', [
                    'function_id' => $function->id,
                    'keyword' => $keyword,
                    'message' => $message->content
                ]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Выполнить функцию
     */
    protected function executeFunction(BotFunction $function, Message $message): void
    {
        $conversation = $message->conversation;
        
        Log::info('Executing function', [
            'function_id' => $function->id,
            'function_name' => $function->name,
            'message_id' => $message->id
        ]);
        
        try {
            // Извлекаем параметры
            $parameters = [];
            if ($function->parameters->count() > 0) {
                $parameters = $this->extractorService->extractParameters($function, $conversation);
            }
            
            // Проверяем наличие обязательных параметров
            $hasRequired = $this->extractorService->hasRequiredParameters($function, $parameters);
            
            if (!$hasRequired) {
                $missing = $this->extractorService->getMissingRequiredParameters($function, $parameters);
                
                Log::info('Missing required parameters', [
                    'function_id' => $function->id,
                    'missing' => $missing,
                    'extracted' => $parameters
                ]);
                
                // Сохраняем накопленные параметры если включено накопление
                if ($function->behavior && $function->behavior->accumulate_parameters) {
                    $metadata = $conversation->metadata ?? [];
                    $metadata['accumulated_params'][$function->id] = $parameters;
                    $conversation->update(['metadata' => $metadata]);
                }
                
                return;
            }
            
            // Выполняем функцию
            $result = $this->executionService->execute($function, $parameters, $conversation);
            
            Log::info('Function executed', [
                'function_id' => $function->id,
                'success' => $result['success'] ?? false,
                'execution_id' => $result['execution_id'] ?? null
            ]);
            
            // Очищаем накопленные параметры после успешного выполнения
            if ($result['success'] && $function->behavior && $function->behavior->accumulate_parameters) {
                $metadata = $conversation->metadata ?? [];
                unset($metadata['accumulated_params'][$function->id]);
                $conversation->update(['metadata' => $metadata]);
            }
            
        } catch (\Exception $e) {
            Log::error('Function execution failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}