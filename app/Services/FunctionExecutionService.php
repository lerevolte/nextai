<?php

namespace App\Services;

use App\Models\BotFunction;
use App\Models\Conversation;
use App\Models\FunctionExecution;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class FunctionExecutionService
{
    protected ParameterExtractorService $extractor;
    protected ActionExecutorService $executor;
    
    public function __construct(
        ParameterExtractorService $extractor,
        ActionExecutorService $executor
    ) {
        $this->extractor = $extractor;
        $this->executor = $executor;
    }
    
    /**
     * Проверить и выполнить функции для сообщения
     */
    public function processMessage(Message $message): void
    {
        $conversation = $message->conversation;
        $bot = $conversation->bot;
        
        // Получаем активные функции бота
        $functions = $bot->functions()->where('is_active', true)->get();
        
        foreach ($functions as $function) {
            if ($this->shouldExecuteFunction($function, $message)) {
                $this->executeFunction($function, $message, $conversation);
            }
        }
    }
    
    /**
     * Определить, нужно ли выполнять функцию
     */
    protected function shouldExecuteFunction(BotFunction $function, Message $message): bool
    {
        // Только для сообщений пользователя
        if ($message->role !== 'user') {
            return false;
        }
        
        return $function->shouldTrigger($message->content);
    }
    
    /**
     * Выполнить функцию
     */
    public function executeFunction(BotFunction $function, Message $message, Conversation $conversation): FunctionExecution
    {
        $execution = FunctionExecution::create([
            'function_id' => $function->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'status' => 'pending',
        ]);
        
        try {
            // 1. Извлекаем параметры из диалога
            $extractedParams = $this->extractor->extractParameters(
                $function,
                $conversation
            );
            
            $execution->update(['extracted_params' => $extractedParams]);
            
            // 2. Валидируем обязательные параметры
            $this->validateParameters($function, $extractedParams);
            
            // 3. Выполняем действия
            $results = [];
            foreach ($function->actions as $action) {
                $result = $this->executor->execute($action, $extractedParams);
                $results[] = $result;
                
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'Action failed');
                }
            }
            
            $execution->update([
                'status' => 'success',
                'action_results' => $results,
                'executed_at' => now(),
            ]);
            
            // 4. Применяем поведение после успешного выполнения
            $this->applyBehavior($function, $conversation, true, $results);
            
        } catch (\Exception $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'executed_at' => now(),
            ]);
            
            // Применяем поведение после ошибки
            $this->applyBehavior($function, $conversation, false, null, $e->getMessage());
            
            Log::error('Function execution failed', [
                'function_id' => $function->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        return $execution;
    }
    
    /**
     * Валидация параметров
     */
    protected function validateParameters(BotFunction $function, array $extractedParams): void
    {
        foreach ($function->parameters as $parameter) {
            if ($parameter->is_required && empty($extractedParams[$parameter->code])) {
                throw new \Exception("Обязательный параметр '{$parameter->name}' не найден");
            }
            
            // Валидация типов
            if (isset($extractedParams[$parameter->code])) {
                $value = $extractedParams[$parameter->code];
                
                switch ($parameter->type) {
                    case 'number':
                        if (!is_numeric($value)) {
                            throw new \Exception("Параметр '{$parameter->name}' должен быть числом");
                        }
                        break;
                    case 'boolean':
                        if (!in_array(strtolower($value), ['true', 'false', '1', '0', 'да', 'нет'])) {
                            throw new \Exception("Параметр '{$parameter->name}' должен быть логическим");
                        }
                        break;
                }
            }
        }
    }
    
    /**
     * Применить поведение после выполнения
     */
    protected function applyBehavior(
        BotFunction $function,
        Conversation $conversation,
        bool $success,
        ?array $results = null,
        ?string $error = null
    ): void {
        $behavior = $function->behavior;
        if (!$behavior) {
            return;
        }
        
        if ($success) {
            // Успешное выполнение
            if ($behavior->success_message) {
                $conversation->messages()->create([
                    'role' => 'system',
                    'content' => $this->formatMessage($behavior->success_message, $results),
                ]);
            }
            
            switch ($behavior->on_success) {
                case 'pause':
                    $conversation->update(['status' => 'paused']);
                    break;
                case 'enhance_prompt':
                    if ($behavior->prompt_enhancement) {
                        $conversation->update([
                            'context' => array_merge(
                                $conversation->context ?? [],
                                ['enhanced_prompt' => $behavior->prompt_enhancement]
                            ),
                        ]);
                    }
                    break;
            }
        } else {
            // Ошибка выполнения
            if ($behavior->error_message) {
                $conversation->messages()->create([
                    'role' => 'system',
                    'content' => str_replace('{error}', $error, $behavior->error_message),
                ]);
            }
            
            switch ($behavior->on_error) {
                case 'pause':
                    $conversation->update(['status' => 'paused']);
                    break;
                case 'notify':
                    // Отправить уведомление администратору
                    break;
            }
        }
    }
    
    protected function formatMessage(string $template, ?array $results): string
    {
        if (!$results) {
            return $template;
        }
        
        // Заменяем плейсхолдеры результатами
        foreach ($results as $key => $result) {
            if (isset($result['data'])) {
                foreach ($result['data'] as $field => $value) {
                    $template = str_replace("{{$field}}", $value, $template);
                }
            }
        }
        
        return $template;
    }
}