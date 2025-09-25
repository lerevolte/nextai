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

            // Определяем лимиты в зависимости от провайдера
            $maxContextLength = $bot->ai_provider === 'gemini' ? 800 : 2000;
            $maxItemLength = $bot->ai_provider === 'gemini' ? 300 : 500;
            
            $context = "Используй эту информацию для ответа:\n\n";
            $currentLength = strlen($context);
            
            foreach ($items as $item) {

                $itemContent = "Тема: " . $item->title . "\n" . 
                              Str::limit($item->content, $maxItemLength) . "\n\n";
                
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
        
        // Системный промпт - сокращаем для Gemini
        if ($bot->ai_provider === 'gemini') {
            $systemPrompt = Str::limit($bot->system_prompt, 300); // сокращаем еще больше
            if ($context) {
                // Если контекст + промпт слишком большие, сокращаем контекст
                $maxContext = 800 - strlen($systemPrompt);
                $context = Str::limit($context, max(200, $maxContext));
            }
            $messages[] = ['role' => 'system', 'content' => $systemPrompt . ($context ? "\n\n" . $context : '')];
        } else {
            $messages[] = ['role' => 'system', 'content' => $bot->system_prompt];
            if ($context) {
                $messages[] = ['role' => 'system', 'content' => $context];
            }
        }

        // История - еще меньше для Gemini при наличии контекста
        $historyLimit = $bot->ai_provider === 'gemini' && !empty($context) ? 3 : 
                       ($bot->ai_provider === 'gemini' ? 5 : 10);
        
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->take($historyLimit)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role === 'user' ? 'user' : 'assistant',
                'content' => Str::limit($msg->content, $bot->ai_provider === 'gemini' ? 300 : 500)
            ];
        }

        // Текущее сообщение
        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }
}