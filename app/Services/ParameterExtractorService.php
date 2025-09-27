<?php

namespace App\Services;

use App\Models\BotFunction;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ParameterExtractorService
{
    protected AIService $aiService;
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    /**
     * Извлечь параметры из диалога используя AI
     */
    public function extractParameters(BotFunction $function, Conversation $conversation): array
    {
        $parameters = $function->parameters;
        if ($parameters->isEmpty()) {
            return [];
        }
        
        // Получаем последние сообщения диалога
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();
        
        // Формируем промпт для AI
        $prompt = $this->buildExtractionPrompt($parameters, $messages);
        
        // Используем AI для извлечения
        $response = $this->aiService->extractData($prompt);
        
        // Парсим ответ
        return $this->parseExtractedData($response, $parameters);
    }
    
    /**
     * Построить промпт для извлечения данных
     */
    protected function buildExtractionPrompt($parameters, $messages): string
    {
        $prompt = "Извлеки следующие данные из диалога:\n\n";
        
        foreach ($parameters as $param) {
            $prompt .= "- {$param->code} ({$param->type}): {$param->description}\n";
        }
        
        $prompt .= "\n--- ДИАЛОГ ---\n";
        
        foreach ($messages as $message) {
            $role = $message->role === 'user' ? 'Клиент' : 'Бот';
            $prompt .= "{$role}: {$message->content}\n";
        }
        
        $prompt .= "\n--- ИНСТРУКЦИЯ ---\n";
        $prompt .= "Верни извлеченные данные в формате JSON. ";
        $prompt .= "Если параметр не найден, используй null. ";
        $prompt .= "Пример: {\"client_name\": \"Иван Иванов\", \"phone\": \"+79001234567\", \"email\": null}\n";
        
        return $prompt;
    }
    
    /**
     * Распарсить извлеченные данные
     */
    protected function parseExtractedData(string $response, $parameters): array
    {
        try {
            // Пытаемся извлечь JSON из ответа
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0], true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Валидируем и приводим типы
                    return $this->validateAndCastTypes($data, $parameters);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse extracted data', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);
        }
        
        // Возвращаем пустой массив если не удалось распарсить
        return [];
    }
    
    /**
     * Валидация и приведение типов
     */
    protected function validateAndCastTypes(array $data, $parameters): array
    {
        $result = [];
        
        foreach ($parameters as $param) {
            $value = $data[$param->code] ?? null;
            
            if ($value !== null) {
                switch ($param->type) {
                    case 'number':
                        $result[$param->code] = is_numeric($value) ? floatval($value) : null;
                        break;
                    case 'boolean':
                        $result[$param->code] = $this->parseBoolean($value);
                        break;
                    case 'date':
                        $result[$param->code] = $this->parseDate($value);
                        break;
                    default:
                        $result[$param->code] = strval($value);
                }
            } else {
                $result[$param->code] = null;
            }
        }
        
        return $result;
    }
    
    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (bool)$value;
        
        $value = strtolower(trim($value));
        return in_array($value, ['true', '1', 'да', 'yes']);
    }
    
    protected function parseDate($value): ?string
    {
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}