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
        // 1. Собираем базовую конфигурацию для Guzzle
        $config = [
            'timeout' => 30,
            // Внимание: в продакшене 'verify' => false может быть небезопасно.
            // Лучше установить сертификат или использовать 'verify' => '/path/to/cacert.pem'
            'verify' => false,
        ];

        // 2. Получаем URL прокси из вашего конфигурационного файла
        $proxyUrl = config('chatbot.proxy_url');

        // 3. Если URL прокси задан в конфигурации, добавляем его в настройки клиента
        if (!empty($proxyUrl)) {
            $config['proxy'] = $proxyUrl;
        }

        // 4. Создаем Guzzle клиент с итоговой конфигурацией
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
            // URL для Gemini API
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            
            // --- НАЧАЛО ИЗМЕНЕНИЙ: Склеиваем последовательные сообщения от пользователя ---
            $cleanedMessages = [];
            foreach ($messages as $message) {
                if (isset($message['role']) && $message['role'] === 'user' && !empty($cleanedMessages) && end($cleanedMessages)['role'] === 'user') {
                    $lastKey = array_key_last($cleanedMessages);
                    $cleanedMessages[$lastKey]['content'] .= "\n" . $message['content'];
                } else {
                    $cleanedMessages[] = $message;
                }
            }
            // --- КОНЕЦ ИЗМЕНЕНИЙ ---

            // Преобразуем формат сообщений для Gemini, используя очищенный массив
            $contents = [];
            $systemPrompt = '';
            
            foreach ($cleanedMessages as $message) { // Используем $cleanedMessages
                if ($message['role'] === 'system') {
                    // Gemini не имеет отдельной системной роли, добавляем в начало
                    $systemPrompt = $message['content'] . "\n\n";
                } elseif ($message['role'] === 'user') {
                    // Добавляем системный промпт к первому сообщению пользователя
                    $content = $systemPrompt ? $systemPrompt . $message['content'] : $message['content'];
                    $contents[] = [
                        'parts' => [
                            ['text' => $content]
                        ],
                        'role' => 'user'
                    ];
                    $systemPrompt = ''; // Используем только один раз
                } elseif ($message['role'] === 'assistant') {
                    $contents[] = [
                        'parts' => [
                            ['text' => $message['content']]
                        ],
                        'role' => 'model'
                    ];
                }
            }

            // Если contents пустой, добавляем хотя бы системный промпт
            if (empty($contents) && $systemPrompt) {
                $contents[] = [
                    'parts' => [
                        ['text' => $systemPrompt]
                    ],
                    'role' => 'user'
                ];
            }

            Log::info('Gemini API Request', [
                'url' => $url,
                'model' => $model,
                'contents' => $contents,
            ]);

            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'key' => $this->apiKey,
                ],
                'json' => [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'maxOutputTokens' => $maxTokens,
                        'topK' => 40,
                        'topP' => 0.95,
                    ],
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_NONE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_NONE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_NONE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_NONE'
                        ]
                    ]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Gemini API Response', [
                'response' => $data,
            ]);
            
            // Проверяем наличие ответа
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
            
            // Если есть ошибка в ответе
            if (isset($data['error'])) {
                Log::error('Gemini API Error', ['error' => $data['error']]);
                return 'Извините, произошла ошибка при генерации ответа. Попробуйте еще раз.';
            }
            
            return 'Извините, не удалось сгенерировать ответ. Попробуйте еще раз.';
            
        } catch (\Exception $e) {
            Log::error('Gemini Provider Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Возвращаем дружелюбное сообщение об ошибке
            if (strpos($e->getMessage(), '400') !== false) {
                return 'Извините, неверный запрос к AI. Проверьте настройки бота.';
            } elseif (strpos($e->getMessage(), '403') !== false || strpos($e->getMessage(), '401') !== false) {
                return 'Извините, проблема с доступом к AI сервису. Проверьте API ключ.';
            } elseif (strpos($e->getMessage(), '429') !== false) {
                return 'Извините, превышен лимит запросов. Попробуйте позже.';
            }
            
            return 'Извините, временная проблема с AI сервисом. Попробуйте еще раз.';
        }
    }

    public function countTokens(string $text): int
    {
        // Приблизительный подсчет для Gemini (1 токен ≈ 4 символа)
        return (int) (mb_strlen($text) / 4);
    }
}