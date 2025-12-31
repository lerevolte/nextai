<?php

namespace App\Services\Providers;

use App\Services\AIProviderInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    protected Client $client;
    protected string $apiKey;

    public function __construct()
    {
        // 1. Собираем базовую конфигурацию для Guzzle
        $config = [
            'timeout' => 60, // OpenAI может работать медленнее
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
        
        $this->apiKey = env('OPENAI_API_KEY');

    }

    public function generateResponse(
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens
    ): string {
        try {
            // URL для OpenAI API
            $url = "https://api.openai.com/v1/chat/completions";
            
            // OpenAI поддерживает system роль нативно, поэтому не нужно склеивать сообщения
            // Просто очищаем от дублирующихся последовательных сообщений пользователя
            $cleanedMessages = [];
            foreach ($messages as $message) {
                if (isset($message['role']) && $message['role'] === 'user' && 
                    !empty($cleanedMessages) && end($cleanedMessages)['role'] === 'user') {
                    $lastKey = array_key_last($cleanedMessages);
                    $cleanedMessages[$lastKey]['content'] .= "\n" . $message['content'];
                } else {
                    $cleanedMessages[] = $message;
                }
            }

            Log::info('OpenAI API Request', [
                'url' => $url,
                'model' => $model,
                //'messages' => $cleanedMessages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);
            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $cleanedMessages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'top_p' => 1,
                    'frequency_penalty' => 0,
                    'presence_penalty' => 0,
                ],
            ]);
             

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('OpenAI API Response', [
                //'response' => $data,
            ]);
            
            // Проверяем наличие ответа
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            }
            
            // Проверяем причину завершения
            $finishReason = $data['choices'][0]['finish_reason'] ?? null;
            if ($finishReason === 'length') {
                return 'Ответ был обрезан из-за ограничения длины. Попробуйте задать более короткий вопрос или увеличьте лимит токенов.';
            } elseif ($finishReason === 'content_filter') {
                return 'Извините, не могу ответить на этот вопрос из-за ограничений безопасности.';
            }
            
            // Если есть ошибка в ответе
            if (isset($data['error'])) {
                Log::error('OpenAI API Error', ['error' => $data['error']]);
                
                $errorCode = $data['error']['code'] ?? '';
                $errorMessage = $data['error']['message'] ?? '';
                
                // Обрабатываем конкретные ошибки OpenAI
                switch ($errorCode) {
                    case 'insufficient_quota':
                        return 'Извините, исчерпан лимит запросов к AI. Обратитесь к администратору.';
                    case 'invalid_request_error':
                        return 'Извините, неверный запрос к AI. Проверьте настройки бота.';
                    case 'context_length_exceeded':
                        return 'Извините, слишком длинный контекст. Попробуйте задать более короткий вопрос.';
                    default:
                        return 'Извините, произошла ошибка при генерации ответа: ' . $errorMessage;
                }
            }
            
            return 'Извините, не удалось сгенерировать ответ. Попробуйте еще раз.';
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = $e->getResponse()->getBody()->getContents();
            
            Log::error('OpenAI Client Error', [
                'status_code' => $statusCode,
                'response' => $responseBody,
                'message' => $e->getMessage(),
            ]);
            
            // Обрабатываем HTTP ошибки
            switch ($statusCode) {
                case 400:
                    return 'Извините, неверный запрос к AI. Проверьте настройки бота.';
                case 401:
                    return 'Извините, проблема с авторизацией AI сервиса. Проверьте API ключ.';
                case 403:
                    return 'Извините, доступ к AI сервису запрещен. Проверьте права доступа.';
                case 429:
                    return 'Извините, превышен лимит запросов. Попробуйте позже.';
                case 500:
                case 502:
                case 503:
                    return 'Извините, проблемы на стороне AI сервиса. Попробуйте позже.';
                default:
                    return 'Извините, временная проблема с AI сервисом. Попробуйте еще раз.';
            }
            
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            Log::error('OpenAI Server Error', [
                'status_code' => $e->getResponse()->getStatusCode(),
                'message' => $e->getMessage(),
            ]);
            
            return 'Извините, проблемы на стороне AI сервиса. Попробуйте позже.';
            
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('OpenAI Connection Error', [
                'message' => $e->getMessage(),
            ]);
            
            return 'Извините, не удалось подключиться к AI сервису. Проверьте интернет-соединение.';
            
        } catch (\Exception $e) {
            Log::error('OpenAI Provider Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 'Извините, произошла неожиданная ошибка. Попробуйте еще раз.';
        }
    }

    public function countTokens(string $text): int
    {
        // Более точный подсчет для OpenAI (1 токен ≈ 4 символа для английского, ~3 для русского)
        // Учитываем что русский текст более "плотный"
        $russianChars = preg_match_all('/[а-яё]/iu', $text);
        $totalChars = mb_strlen($text);
        
        if ($russianChars > $totalChars * 0.5) {
            // Преимущественно русский текст
            return (int) ($totalChars / 3);
        } else {
            // Преимущественно английский или смешанный
            return (int) ($totalChars / 4);
        }
    }
}