<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Providers\OpenAIProvider;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\DeepSeekProvider;
use OpenAI\Laravel\Facades\OpenAI;

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

    protected function prepareMessages(Bot $bot, Conversation $conversation, string $message, string $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => $bot->system_prompt]
        ];

        if ($context) {
            $messages[] = [
                'role' => 'system', 
                'content' => "Используй следующую информацию из базы знаний для ответа:\n" . $context
            ];
        }

        // Добавляем историю последних сообщений (максимум 10)
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role === 'user' ? 'user' : 'assistant',
                'content' => $msg->content
            ];
        }

        // Добавляем текущее сообщение
        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    protected function getRelevantContext(Bot $bot, string $query): string
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return '';
        }

        // Используем гибридный поиск
        $results = $this->embeddingService->hybridSearch($query, $knowledgeBase, 3);

        if (empty($results)) {
            // Fallback на простой поиск если ничего не нашли
            $items = $knowledgeBase->items()
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->get();
                
            $context = "Информация из базы знаний:\n\n";
            foreach ($items as $item) {
                $context .= "## " . $item->title . "\n";
                $context .= $item->content . "\n\n";
            }
            
            return $context;
        }

        // Формируем контекст из найденных документов
        $context = "Релевантная информация из базы знаний:\n\n";
        
        foreach ($results as $result) {
            $item = $result['item'];
            $score = isset($result['score']) ? round($result['score'], 2) : 0;
            
            $context .= "## " . $item->title . "\n";
            $context .= $item->content . "\n\n";
            
            Log::debug('Knowledge base item used', [
                'item_id' => $item->id,
                'title' => $item->title,
                'relevance_score' => $score,
            ]);
        }

        return $context;
    }
}