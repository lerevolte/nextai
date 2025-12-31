<?php

namespace App\Services;

use App\Models\BotFunction;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class ParameterExtractorService
{
    protected $aiService;
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    /**
     * Извлечение параметров из диалога
     */
    public function extractParameters(BotFunction $function, Conversation $conversation): array
    {
        Log::info('Starting parameter extraction', [
            'function_id' => $function->id,
            'function_name' => $function->name,
            'parameters_count' => $function->parameters->count(),
            'messages_count' => $conversation->messages->count()
        ]);
        
        $extractedParams = [];
        
        if ($function->parameters->isEmpty()) {
            Log::warning('No parameters defined for function');
            return $extractedParams;
        }
        
        // Формируем текст диалога
        $conversationText = $conversation->messages
            ->map(fn($msg) => "{$msg->role}: {$msg->content}")
            ->join("\n");
        
        Log::info('Conversation text for extraction', [
            'text' => $conversationText
        ]);
        
        // Описание параметров
        $parametersDescription = $function->parameters->map(function($param) {
            $required = $param->is_required ? '(обязательный)' : '(необязательный)';
            return "- {$param->code} {$required}: {$param->description} (тип: {$param->type})";
        })->join("\n");
        
        // Формируем промпт для извлечения
        $extractionMessage = <<<PROMPT
    Задача: Извлечь параметры из диалога и вернуть ТОЛЬКО JSON.

    Параметры для извлечения:
    {$parametersDescription}

    Диалог:
    {$conversationText}

    ВАЖНО:
    1. Верни ТОЛЬКО JSON объект, без дополнительного текста
    2. Формат: {"parameter_code": "value"}
    3. Если параметр не найден, не включай его в ответ
    4. НЕ добавляй markdown, комментарии или пояснения

    Пример правильного ответа:
    {"client_name": "Иван Петров", "client_phone": "+7 999 123-45-67"}

    Твой JSON ответ:
    PROMPT;

        Log::info('Extraction message created', [
            'message' => $extractionMessage
        ]);
        
        try {
            // Получаем бот
            $bot = $conversation->bot ?? $function->bot;
            
            if (!$bot) {
                Log::error('No bot found for extraction');
                return $extractedParams;
            }
            
            // Создаем временный диалог для извлечения параметров
            $tempConversation = new Conversation([
                'bot_id' => $bot->id,
                'external_id' => 'extract_' . uniqid(),
                'status' => 'active',
            ]);
            $tempConversation->id = 0;
            $tempConversation->setRelation('bot', $bot);
            
            // Добавляем системный промпт как первое сообщение
            $systemMessage = new Message([
                'role' => 'system',
                'content' => 'Ты - система извлечения параметров. Возвращай ТОЛЬКО JSON без дополнительного текста.',
                'created_at' => now()
            ]);
            
            $tempConversation->setRelation('messages', collect([$systemMessage]));
            
            // Вызываем AI с правильными параметрами (3 аргумента!)
            $aiResponse = $this->aiService->generateResponse(
                $bot, 
                $tempConversation, 
                $extractionMessage
            );
            
            Log::info('AI response received', [
                'response' => $aiResponse,
                'length' => strlen($aiResponse)
            ]);
            
            // Извлекаем JSON из ответа
            $extracted = $this->parseJsonFromResponse($aiResponse);
            
            if (!empty($extracted)) {
                // Валидируем и фильтруем параметры
                foreach ($function->parameters as $param) {
                    if (isset($extracted[$param->code])) {
                        $value = $extracted[$param->code];
                        
                        // Валидация типа
                        $validatedValue = $this->validateParameterType($value, $param->type);
                        
                        if ($validatedValue !== null) {
                            $extractedParams[$param->code] = $validatedValue;
                        }
                    }
                }
                
                Log::info('Parameters extracted successfully', [
                    'extracted' => $extractedParams
                ]);
            } else {
                Log::warning('No parameters extracted from AI response', [
                    'response' => $aiResponse
                ]);
            }
            
            return $extractedParams;
            
        } catch (\Exception $e) {
            Log::error('Parameter extraction failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Возвращаем пустой массив при ошибке
            return $extractedParams;
        }
    }

    /**
     * Извлечение JSON из ответа AI
     */
    protected function parseJsonFromResponse(string $response): array
    {
        // Удаляем markdown блоки кода если есть
        $response = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $response);
        $response = preg_replace('/```\s*(.*?)\s*```/s', '$1', $response);
        
        // Удаляем возможные пояснения до или после JSON
        $response = trim($response);
        
        // Ищем JSON объект
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $json = $matches[0];
            
            Log::info('JSON found in response', ['json' => $json]);
            
            $decoded = json_decode($json, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            } else {
                Log::error('JSON decode error', [
                    'error' => json_last_error_msg(),
                    'json' => $json
                ]);
            }
        } else {
            Log::warning('No JSON object found in response', [
                'response' => substr($response, 0, 500) // Логируем первые 500 символов
            ]);
        }
        
        return [];
    }

    /**
     * Валидация типа параметра
     */
    protected function validateParameterType($value, string $type)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        switch ($type) {
            case 'string':
                return (string) $value;
                
            case 'number':
                // Очищаем от пробелов и нечисловых символов кроме +, -, .
                $cleaned = preg_replace('/[^\d+\-.]/', '', $value);
                if (is_numeric($cleaned)) {
                    return floatval($cleaned);
                }
                return null;
                
            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                $lower = strtolower((string)$value);
                if (in_array($lower, ['true', '1', 'yes', 'да', 'yes'])) {
                    return true;
                }
                if (in_array($lower, ['false', '0', 'no', 'нет'])) {
                    return false;
                }
                return null;
                
            case 'date':
                try {
                    $date = new \DateTime($value);
                    return $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Log::warning('Invalid date format', [
                        'value' => $value,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
                
            default:
                return $value;
        }
    }

    /**
     * Проверка, все ли обязательные параметры извлечены
     */
    public function hasRequiredParameters(BotFunction $function, array $extractedParams): bool
    {
        $requiredParams = $function->parameters
            ->where('is_required', true)
            ->pluck('code')
            ->toArray();
        
        foreach ($requiredParams as $paramCode) {
            if (!isset($extractedParams[$paramCode]) || empty($extractedParams[$paramCode])) {
                Log::info('Missing required parameter', [
                    'parameter' => $paramCode,
                    'extracted' => $extractedParams
                ]);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Получить список недостающих обязательных параметров
     */
    public function getMissingRequiredParameters(BotFunction $function, array $extractedParams): array
    {
        $requiredParams = $function->parameters
            ->where('is_required', true);
        
        $missing = [];
        foreach ($requiredParams as $param) {
            if (!isset($extractedParams[$param->code]) || empty($extractedParams[$param->code])) {
                $missing[] = [
                    'code' => $param->code,
                    'name' => $param->name,
                    'description' => $param->description
                ];
            }
        }
        
        return $missing;
    }
}