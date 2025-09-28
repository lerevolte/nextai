<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Providers\OpenAIProvider;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\DeepSeekProvider;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIService
{
    protected array $providers = [
        'openai' => OpenAIProvider::class,
        'gemini' => GeminiProvider::class,
        'deepseek' => DeepSeekProvider::class,
    ];

    protected EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    public function generateResponse(Bot $bot, Conversation $conversation, string $message): string
    {
        $provider = $this->getProvider($bot->ai_provider);
        
        // Получаем контекст из базы знаний если включена
        $context = '';
        if ($bot->knowledge_base_enabled && $bot->knowledgeBase) {
            $context = $this->getRelevantContext($bot, $message);
        }

        // Формируем историю сообщений
        $messages = $this->prepareMessages($bot, $conversation, $message, $context);

        // Генерируем ответ
        return $provider->generateResponse(
            model: $bot->ai_model,
            messages: $messages,
            temperature: $bot->temperature,
            maxTokens: $bot->max_tokens
        );
    }

    protected function getProvider(string $providerName): AIProviderInterface
    {
        $providerClass = $this->providers[$providerName] ?? $this->providers['openai'];
        return app($providerClass);
    }

    protected function getRelevantContext(Bot $bot, string $query): string
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return '';
        }

        try {
            // Сначала пробуем полнотекстовый поиск (быстрее и не требует эмбеддингов)
            $items = $knowledgeBase->items()
                ->where('is_active', true)
                ->where(function($q) use ($query) {
                    $keywords = explode(' ', $query);
                    foreach ($keywords as $keyword) {
                        if (strlen($keyword) > 2) {
                            $q->orWhere('title', 'LIKE', '%' . $keyword . '%')
                              ->orWhere('content', 'LIKE', '%' . $keyword . '%');
                        }
                    }
                })
                ->limit(2)
                ->get();

            if ($items->isEmpty()) {
                // Если ничего не нашли, берем последние добавленные
                $items = $knowledgeBase->items()
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(2)
                    ->get();
            }

            if ($items->isEmpty()) {
                return '';
            }

            // Ограничиваем размер контекста для Gemini
            $context = "Используй эту информацию для ответа:\n\n";
            $maxContextLength = 2000; // Максимальная длина контекста
            $currentLength = 0;
            
            foreach ($items as $item) {
                $itemContent = "Тема: " . $item->title . "\n" . 
                              Str::limit($item->content, 500) . "\n\n";
                
                if ($currentLength + strlen($itemContent) <= $maxContextLength) {
                    $context .= $itemContent;
                    $currentLength += strlen($itemContent);
                } else {
                    break;
                }
            }
            
            return $context;
            
        } catch (\Exception $e) {
            Log::error('Knowledge base search error', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            return '';
        }
    }

    protected function prepareMessages(Bot $bot, Conversation $conversation, string $message, string $context): array
    {
        $messages = [];
        
        // Системный промпт - сокращаем если используем Gemini
        if ($bot->ai_provider === 'gemini') {
            $systemPrompt = Str::limit($bot->system_prompt, 500);
            if ($context) {
                $systemPrompt .= "\n\nКонтекст: " . Str::limit($context, 1000);
            }
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        } else {
            $messages[] = ['role' => 'system', 'content' => $bot->system_prompt];
            if ($context) {
                $messages[] = ['role' => 'system', 'content' => $context];
            }
        }

        // История - ограничиваем для Gemini
        $historyLimit = $bot->ai_provider === 'gemini' ? 5 : 10;
        
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->take($historyLimit)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role === 'user' ? 'user' : 'assistant',
                'content' => Str::limit($msg->content, 500)
            ];
        }

        // Текущее сообщение
        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }


    /**
     * Извлечь данные из текста используя AI
     */
    public function extractData(string $prompt): string
    {
        $provider = $this->getProvider('openai'); // или другой провайдер
        
        return $provider->generateResponse(
            model: 'gpt-4o-mini',
            messages: [
                ['role' => 'system', 'content' => 'Ты помощник для извлечения структурированных данных. Всегда возвращай результат в формате JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            temperature: 0.3,
            maxTokens: 500
        );
    }

    /**
     * Классифицировать текст
     */
    public function classify(string $prompt): string
    {
        $provider = $this->getProvider('openai');
        
        return $provider->generateResponse(
            model: 'gpt-4o-mini',
            messages: [
                ['role' => 'system', 'content' => 'Ты классификатор текста. Определяй намерения и возвращай результат в формате JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            temperature: 0.3,
            maxTokens: 200
        );
    }

    /**
     * Анализ тональности текста
     */
    public function analyzeSentiment(string $text): array
    {
        $provider = $this->getProvider('openai');
        
        $prompt = "Проанализируй тональность следующего текста и верни результат в формате JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "label": "positive|neutral|negative",\n';
        $prompt .= '  "score": -1.0 до 1.0,\n';
        $prompt .= '  "explanation": "краткое объяснение"\n';
        $prompt .= "}\n\n";
        $prompt .= "Текст: {$text}";
        
        $response = $provider->generateResponse(
            model: 'gpt-4o-mini',
            messages: [
                ['role' => 'system', 'content' => 'Ты анализатор тональности текста.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            temperature: 0.3,
            maxTokens: 200
        );
        
        // Парсим JSON из ответа
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        // Значение по умолчанию
        return [
            'label' => 'neutral',
            'score' => 0,
            'explanation' => 'Не удалось определить тональность'
        ];
    }
}