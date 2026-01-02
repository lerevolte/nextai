<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotIntent;
use Illuminate\Support\Facades\Cache;

class IntentDetectionService
{
    protected AIService $aiService;
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    /**
     * Определить намерение в сообщении
     */
    public function detect(string $message, Bot $bot): ?object
    {
        // Получаем активные намерения бота
        $intents = $bot->intents()->where('is_active', true)->get();
        
        if ($intents->isEmpty()) {
            return null;
        }
        
        // Кэшируем классификацию
        $cacheKey = 'intent:' . md5($message . $bot->id);
        
        return Cache::remember($cacheKey, 300, function () use ($message, $intents) {
            // Формируем промпт для AI
            $prompt = $this->buildClassificationPrompt($message, $intents);
            
            // Получаем ответ от AI
            $response = $this->aiService->classify($prompt);
            
            // Парсим результат
            return $this->parseIntentResponse($response, $intents);
        });
    }
    
    /**
     * Построить промпт для классификации
     */
    protected function buildClassificationPrompt(string $message, $intents): string
    {
        $prompt = "Определи намерение пользователя в сообщении.\n\n";
        $prompt .= "Сообщение: \"{$message}\"\n\n";
        $prompt .= "Возможные намерения:\n";
        
        foreach ($intents as $intent) {
            $prompt .= "- {$intent->name}: {$intent->display_name}\n";
            
            if ($intent->training_phrases) {
                $prompt .= "  Примеры: " . implode(', ', array_slice($intent->training_phrases, 0, 3)) . "\n";
            }
        }
        
        $prompt .= "\nВерни ответ в формате JSON:\n";
        $prompt .= "{\"intent\": \"название_намерения\", \"confidence\": 0.95, \"entities\": {}}\n";
        $prompt .= "Если намерение не определено, верни {\"intent\": null, \"confidence\": 0}\n";
        
        return $prompt;
    }
    
    /**
     * Распарсить ответ AI
     */
    protected function parseIntentResponse(string $response, $intents): ?object
    {
        try {
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0]);
                
                if ($data && isset($data->intent) && $data->intent) {
                    // Проверяем, что намерение существует
                    $intent = $intents->firstWhere('name', $data->intent);
                    if ($intent) {
                        return (object)[
                            'name' => $data->intent,
                            'confidence' => $data->confidence ?? 0,
                            'entities' => $data->entities ?? [],
                            'model' => $intent,
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to parse intent response', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);
        }
        
        return null;
    }
}