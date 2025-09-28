<?php

namespace App\Services\Providers;

use App\Services\AIProviderInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    protected Client $client;
    protected string $apiKey;

    public function __construct()
    {
        $config = [
            'timeout' => 60, // Увеличим таймаут на всякий случай
            'verify' => false,
        ];

        $proxyUrl = config('chatbot.proxy_url');
        if (!empty($proxyUrl)) {
            $config['proxy'] = $proxyUrl;
        }

        $this->client = new Client($config);
        $this->apiKey = config('chatbot.ai_providers.gemini.api_key');
    }

    public function generateResponse(
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens
    ): string {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            
            // --- НАЧАЛО НОВОЙ ЛОГИКИ ФОРМИРОВАНИЯ ЗАПРОСА ---

            $contents = [];
            $systemPrompt = '';
            $history = [];

            // 1. Извлекаем системный промпт и отделяем его от истории сообщений
            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $systemPrompt = $message['content'];
                } else {
                    $history[] = $message;
                }
            }

            // 2. Добавляем системный промпт (персонажа) как первое сообщение от пользователя
            if ($systemPrompt) {
                 $contents[] = [
                    'role' => 'user', 
                    'parts' => [['text' => $systemPrompt]]
                ];
            }
           
            // 3. "Внедряем" жесткое правило форматирования через пример
            $formatting_rule = 'КРАЙНЕ ВАЖНОЕ ПРАВИЛО: В своем ответе ты НИКОГДА не используешь Markdown. ЗАПРЕЩЕНО использовать символы `*` и `**`. Форматируй списки только с новой строки, используя тире. Пример: "- Название букета - 500 руб."';
            $contents[] = [
                'role' => 'user', 
                'parts' => [['text' => $formatting_rule]]
            ];
            // Добавляем "фейковый" ответ, где модель подтверждает правило
            $contents[] = [
                'role' => 'model', 
                'parts' => [['text' => 'Правило понятны. Никакого Markdown, только простой текст с тире для списков.']]
            ];

            // 4. Добавляем реальную историю диалога
            foreach ($history as $message) {
                $role = ($message['role'] === 'user') ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $message['content']]]
                ];
            }
            
            // --- КОНЕЦ НОВОЙ ЛОГИКИ ---

            Log::info('Gemini API Request (Strict Formatting)', [
                'url' => $url,
                'model' => $model,
                'contents' => $contents,
            ]);

            $response = $this->client->post($url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'query' => [ 'key' => $this->apiKey ],
                'json' => [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'maxOutputTokens' => $maxTokens,
                        'topK' => 40,
                        'topP' => 0.95,
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
                    ]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Gemini API Response', ['response' => $data]);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
            
            if (isset($data['error'])) {
                Log::error('Gemini API Error', ['error' => $data['error']]);
                return 'Извините, произошла ошибка при генерации ответа.';
            }
            
            // Обработка случая, когда ответ заблокирован по соображениям безопасности
            if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
                Log::warning('Gemini response blocked due to safety settings.', ['response' => $data]);
                return 'К сожалению, я не могу предоставить ответ на этот запрос.';
            }

            return 'Извините, не удалось сгенерировать ответ.';
            
        } catch (\Exception $e) {
            Log::error('Gemini Provider Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 'Извините, возникла техническая проблема с AI сервисом. Попробуйте еще раз.';
        }
    }

    public function countTokens(string $text): int
    {
        // Приблизительный подсчет (1 токен ≈ 4 символа)
        return (int) (mb_strlen($text) / 4);
    }
}