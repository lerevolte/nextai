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
    protected KnowledgeSearchService $searchService;

    public function __construct(EmbeddingService $embeddingService, KnowledgeSearchService $searchService)
    {
        $this->embeddingService = $embeddingService;
        $this->searchService = $searchService;
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
            // Используем Elasticsearch для поиска
            if ($this->searchService->isAvailable()) {
                return $this->getContextFromElasticsearch($knowledgeBase, $query);
            }

            // Fallback на старый метод если Elasticsearch недоступен
            return $this->getContextFromDatabase($knowledgeBase, $query);
            
        } catch (\Exception $e) {
            Log::error('Knowledge base search error', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            return '';
        }
    }

    /**
     * Получение контекста через Elasticsearch
     */
    protected function getContextFromElasticsearch($knowledgeBase, string $query): string
    {
        $results = $this->searchService->searchWithThreshold(
            query: $query,
            knowledgeBase: $knowledgeBase,
            minScore: 1.0,  // Минимальный порог релевантности
            limit: 3        // Количество чанков
        );

        if (empty($results)) {
            Log::info('Elasticsearch: no relevant results found', ['query' => $query]);
            return '';
        }

        Log::info('Elasticsearch: found relevant chunks', [
            'query' => $query,
            'count' => count($results),
            'top_score' => $results[0]['score'] ?? 0,
        ]);

        // Формируем контекст
        $context = "Используй следующую информацию из базы знаний для ответа:\n\n";
        $maxContextLength = 3000; // Увеличиваем лимит для более полных ответов
        $currentLength = 0;

        foreach ($results as $index => $result) {
            $chunkContent = "--- Источник " . ($index + 1) . " (релевантность: " . round($result['score'], 1) . ") ---\n";
            $chunkContent .= $result['content'] . "\n\n";

            if ($currentLength + strlen($chunkContent) <= $maxContextLength) {
                $context .= $chunkContent;
                $currentLength += strlen($chunkContent);
            } else {
                break;
            }
        }

        $context .= "---\nОтвечай на основе предоставленной информации. Если информации недостаточно, скажи об этом.";

        return $context;
    }

    /**
     * Fallback: получение контекста из базы данных
     */
    protected function getContextFromDatabase($knowledgeBase, string $query): string
    {
        // Сначала пробуем полнотекстовый поиск
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
            $items = $knowledgeBase->items()
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->get();
        }

        if ($items->isEmpty()) {
            return '';
        }

        $context = "Используй эту информацию для ответа:\n\n";
        $maxContextLength = 2000;
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
    }

    protected function prepareMessages(Bot $bot, Conversation $conversation, string $message, string $context): array
    {
        $messages = [];
        
        // Системный промпт
        $systemPrompt = $bot->system_prompt;
        
        // Добавляем информацию о пользователе в системный промпт, если она есть
        if ($conversation->user_name) {
            $systemPrompt .= "\n\nИмя пользователя: " . $conversation->user_name;
            $systemPrompt .= "\nЭто НЕ первое сообщение пользователя, диалог уже начат. Не нужно приветствовать снова.";
        }
        
        if ($bot->ai_provider === 'gemini') {
            $systemPrompt = Str::limit($systemPrompt, 500);
            if ($context) {
                $systemPrompt .= "\n\nКонтекст: " . Str::limit($context, 1500);
            }
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        } else {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
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

        // Проверяем, есть ли уже приветственное сообщение в истории
        $hasWelcomeMessage = false;
        foreach ($history as $msg) {
            $content = mb_strtolower($msg->content);
            if (stripos($content, 'здравствуйте') !== false || 
                stripos($content, 'добрый день') !== false ||
                stripos($content, 'меня зовут') !== false) {
                $hasWelcomeMessage = true;
                break;
            }
        }
        
        // Если уже было приветствие, добавляем инструкцию в контекст
        if ($hasWelcomeMessage) {
            $messages[] = [
                'role' => 'system', 
                'content' => 'ВАЖНО: Приветствие уже было в диалоге. Продолжай разговор естественно, БЕЗ повторного приветствия.'
            ];
        }

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
        $provider = $this->getProvider('openai');
        
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