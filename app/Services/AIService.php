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
        // Здесь будет логика поиска релевантной информации из базы знаний
        // Используем полнотекстовый поиск или векторный поиск
        
        $items = $bot->knowledgeBase
            ->items()
            ->where('is_active', true)
            ->whereRaw("MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)", [$query])
            ->limit(3)
            ->get();

        return $items->pluck('content')->implode("\n\n");
    }
}