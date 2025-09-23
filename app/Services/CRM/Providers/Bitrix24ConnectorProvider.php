<?php

namespace App\Services\CRM\Providers;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Models\Message;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Bitrix24ConnectorProvider
{
    protected Client $client;
    protected CrmIntegration $integration;
    protected array $config;
    protected string $connectorId;
    protected ?string $webhookUrl = null;
    protected ?string $accessToken = null;
    protected ?string $refreshToken = null;
    protected ?string $oauthRestUrl = null;

    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->client = new Client(['timeout' => 30, 'http_errors' => false,]);
        $this->config = $integration->credentials ?? [];
        $this->connectorId = 'chatbot_' . $integration->organization_id . '_' . $integration->id;
        
        if (isset($this->config['webhook_url'])) {
            $this->webhookUrl = rtrim($this->config['webhook_url'], '/') . '/';
        }
        
        if (isset($this->config['auth_id']) && isset($this->config['domain'])) {
            $this->accessToken = $this->config['auth_id'];
            $this->refreshToken = $this->config['refresh_id'] ?? null;
            $this->oauthRestUrl = 'https://' . $this->config['domain'] . '/rest/';
        }
    }

    /**
     * Отправка первого сообщения в открытую линию
     * Это создаст чат и автоматически создаст лид в Битрикс24
     */
    public function sendInitialMessage(Conversation $conversation): array
    {
        // Проверяем, не было ли уже отправлено сообщение
        $cacheKey = "bitrix24_initial_sent_{$conversation->id}";
        if (Cache::has($cacheKey)) {
            Log::info('Initial message already sent for conversation', [
                'conversation_id' => $conversation->id
            ]);
            return ['success' => false, 'error' => 'Already sent'];
        }
        
        // Устанавливаем блокировку на 5 минут
        Cache::put($cacheKey, true, 300);
        
        Log::info('sendInitialMessage to open line');
        
        try {
            $bot = $conversation->bot;
            $connectorId = $this->getConnectorIdForBot($bot);
            
            // Получаем настройки коннектора из pivot таблицы
            $botIntegration = $this->integration->bots()
                ->where('bot_id', $bot->id)
                ->first();
            
            if (!$botIntegration) {
                throw new \Exception('Bot not connected to integration');
            }
            
            $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
            $lineId = $connectorSettings['line_id'] ?? null;
            
            if (!$lineId) {
                throw new \Exception('Line ID not configured');
            }

            // Формируем данные пользователя
            $userData = [
                'id' => $conversation->external_id ?? 'user_' . $conversation->id,
                'name' => $conversation->user_name ?? 'Гость',
                'last_name' => '',
                'email' => $conversation->user_email,
                'phone' => $conversation->user_phone,
                'picture' => [
                    'url' => ''
                ],
            ];

            // Получаем первое сообщение пользователя или создаем приветственное
            $firstMessage = $conversation->messages()
                ->where('role', 'user')
                ->orderBy('created_at', 'asc')
                ->first();

            $messageText = $firstMessage ? $firstMessage->content : 
                          ($bot->welcome_message ?? 'Пользователь начал диалог');

            // Формируем сообщение
            $messageData = [
                'user' => $userData,
                'message' => [
                    'id' => $firstMessage ? (string)$firstMessage->id : Str::uuid()->toString(),
                    'date' => $firstMessage ? $firstMessage->created_at->timestamp : now()->timestamp,
                    'text' => $messageText,
                ],
                'chat' => [
                    'id' => 'chat_' . $conversation->id,
                    'name' => 'Чат #' . $conversation->id,
                    'url' => route('conversations.show', [
                        $conversation->bot->organization,
                        $conversation->bot,
                        $conversation
                    ]),
                ],
            ];

            // Отправляем сообщение в Битрикс24
            $result = $this->makeRequest('imconnector.send.messages', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => [$messageData],
            ]);

            if (empty($result['result'])) {
                throw new \Exception('Failed to send message to Bitrix24: ' . json_encode($result));
            }

            // Сохраняем метаданные
            $conversation->update([
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'bitrix24_connector_id' => $connectorId,
                    'bitrix24_line_id' => $lineId,
                    'bitrix24_chat_id' => 'chat_' . $conversation->id,
                    'bitrix24_initial_message_sent' => true,
                    'bitrix24_sent_at' => now()->toIso8601String(),
                ])
            ]);

            Log::info('Initial message sent to Bitrix24 open line', [
                'conversation_id' => $conversation->id,
                'connector_id' => $connectorId,
                'line_id' => $lineId,
            ]);

            return [
                'success' => true,
                'chat_id' => 'chat_' . $conversation->id,
                'connector_id' => $connectorId,
                'line_id' => $lineId,
            ];

        } catch (\Exception $e) {
            // Убираем блокировку при ошибке
            Cache::forget($cacheKey);
            
            Log::error('Failed to send initial message to Bitrix24', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Отправка сообщения от пользователя в Битрикс24
     */
    public function sendUserMessage(Conversation $conversation, Message $message): array
    {
        try {
            // Проверяем, не отправляли ли это сообщение уже
            $cacheKey = "bitrix24_msg_sent_{$message->id}";
            if (Cache::has($cacheKey)) {
                return ['success' => true, 'cached' => true];
            }
            
            $bot = $conversation->bot;
            
            if ($message->role === 'assistant') {
                // Сообщения бота отправляем через API бота
                $result = $this->sendAsBotMessage($conversation, $message);
            } else {
                // Сообщения пользователя отправляем через коннектор
                $result = $this->sendAsUserMessage($conversation, $message);
            }
            
            // Кешируем успешную отправку
            if ($result['success']) {
                Cache::put($cacheKey, true, 3600);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Failed to send message to Bitrix24', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Отправка как сообщение бота
     */
    protected function sendAsBotMessage(Conversation $conversation, Message $message): array
    {
        $bot = $conversation->bot;
        $botId = $bot->metadata['bitrix24_bot_id'] ?? null;
        
        if (!$botId) {
            Log::warning('Bitrix24 bot not registered, sending as system message');
            return $this->sendAsSystemMessage($conversation, $message);
        }

        // ИСПРАВЛЕНИЕ: Для открытых линий используем другой подход
        // Получаем line_id из настроек коннектора
        $botIntegration = $this->integration->bots()
            ->where('bot_id', $bot->id)
            ->first();
        
        if (!$botIntegration) {
            throw new \Exception('Bot not connected to integration');
        }
        
        $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
        $lineId = $connectorSettings['line_id'] ?? null;
        
        if (!$lineId) {
            throw new \Exception('Line ID not configured');
        }

        // Отправляем через открытые линии, а не через API бота
        $result = $this->makeRequest('imopenlines.bot.message.send', [
            'CHAT_ID' => str_replace('chat_', '', $conversation->metadata['bitrix24_chat_id'] ?? $conversation->id),
            'BOT_ID' => $botId,
            'MESSAGE' => $message->content,
        ]);

        // Если этот метод не работает, используем альтернативный подход
        if (empty($result['result'])) {
            // Отправляем как системное сообщение с префиксом бота
            return $this->sendAsSystemMessage($conversation, $message);
        }

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                'bitrix24_bot_message_id' => $result['result'],
                'bitrix24_sent_as' => 'openline_bot',
                'bitrix24_sent_at' => now()->toIso8601String(),
            ])
        ]);

        return [
            'success' => !empty($result['result']),
            'message_id' => $result['result'] ?? null,
        ];
    }

    protected function sendAsUserMessage(Conversation $conversation, Message $message): array
    {
        Log::info('sendAsUserMessage to Bitrix24', [
            'message_id' => $message->id,
            'role' => $message->role
        ]);
        
        $bot = $conversation->bot;
        $connectorId = $this->getConnectorIdForBot($bot);
        
        // Получаем настройки коннектора
        $botIntegration = $this->integration->bots()
            ->where('bot_id', $bot->id)
            ->first();
        
        if (!$botIntegration) {
            throw new \Exception('Bot not connected to integration');
        }
        
        $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
        $lineId = $connectorSettings['line_id'] ?? null;
        
        if (!$lineId) {
            // Если линия не настроена, пытаемся отправить первое сообщение
            if ($message->role === 'user') {
                $initResult = $this->sendInitialMessage($conversation);
                if (!$initResult['success']) {
                    throw new \Exception('Failed to initialize chat: ' . ($initResult['error'] ?? 'Unknown error'));
                }
                $lineId = $initResult['line_id'];
            } else {
                throw new \Exception('Line ID not configured and message is not from user');
            }
        }

        // Только для пользователя и системы - НЕ для assistant
        $sender = [
            'id' => $conversation->external_id ?? 'user_' . $conversation->id,
            'name' => $conversation->user_name ?? 'Гость',
        ];

        // Формируем данные сообщения
        $messageData = [
            'user' => $sender,
            'message' => [
                'id' => (string)$message->id,
                'date' => $message->created_at->timestamp,
                'text' => $message->content,
            ],
            'chat' => [
                'id' => 'chat_' . $conversation->id,
            ],
        ];

        // Отправляем сообщение
        $result = $this->makeRequest('imconnector.send.messages', [
            'CONNECTOR' => $connectorId,
            'LINE' => $lineId,
            'MESSAGES' => [$messageData],
        ]);

        if (empty($result['result'])) {
            throw new \Exception('Failed to send message to Bitrix24');
        }

        // Сохраняем ID сообщения в Битрикс24
        if (!empty($result['result']['MESSAGES'][0])) {
            $message->update([
                'metadata' => array_merge($message->metadata ?? [], [
                    'bitrix24_message_id' => $result['result']['MESSAGES'][0],
                    'bitrix24_sent_at' => now()->toIso8601String(),
                ])
            ]);
        }

        Log::info('User message sent to Bitrix24', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'connector_id' => $connectorId,
        ]);

        return [
            'success' => true,
            'bitrix24_message_id' => $result['result']['MESSAGES'][0] ?? null,
        ];
    }

    /**
     * Fallback - отправляем как системное сообщение с префиксом
     */
    protected function sendAsSystemMessage(Conversation $conversation, Message $message): array
    {
        // Создаем копию сообщения с префиксом бота
        $botName = $conversation->bot->name;
        $messageWithPrefix = "🤖 {$botName}: {$message->content}";
        
        // Отправляем через коннектор как обычное сообщение
        $bot = $conversation->bot;
        $connectorId = $this->getConnectorIdForBot($bot);
        
        $botIntegration = $this->integration->bots()
            ->where('bot_id', $bot->id)
            ->first();
        
        $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
        $lineId = $connectorSettings['line_id'] ?? null;

        $messageData = [
            'user' => [
                'id' => $conversation->external_id ?? 'user_' . $conversation->id,
                'name' => $conversation->user_name ?? 'Гость',
            ],
            'message' => [
                'id' => (string)$message->id . '_bot',
                'date' => $message->created_at->timestamp,
                'text' => $messageWithPrefix, // С префиксом бота
            ],
            'chat' => [
                'id' => 'chat_' . $conversation->id,
            ],
        ];

        $result = $this->makeRequest('imconnector.send.messages', [
            'CONNECTOR' => $connectorId,
            'LINE' => $lineId,
            'MESSAGES' => [$messageData],
        ]);

        return [
            'success' => !empty($result['result']),
            'message_id' => $result['result']['MESSAGES'][0] ?? null,
        ];
    }

    /**
     * Обработка входящего сообщения от оператора из Битрикс24
     */
    public function handleOperatorMessage(array $data): void
    {
        try {
            $messages = $data['MESSAGES'] ?? [];
            
            foreach ($messages as $messageData) {
                $chatId = str_replace('chat_', '', $messageData['chat']['id'] ?? '');
                $conversation = Conversation::find($chatId);
                
                if (!$conversation) {
                    Log::warning('Conversation not found for Bitrix24 message', [
                        'chat_id' => $chatId,
                    ]);
                    continue;
                }

                // Проверяем, не создавали ли мы уже это сообщение
                $bitrix24MessageId = $messageData['message']['id'] ?? null;
                if ($bitrix24MessageId) {
                    $existingMessage = $conversation->messages()
                        ->where('metadata->bitrix24_message_id', $bitrix24MessageId)
                        ->first();
                    
                    if ($existingMessage) {
                        Log::info('Message already exists, skipping', [
                            'bitrix24_message_id' => $bitrix24MessageId
                        ]);
                        continue;
                    }
                }

                // Сохраняем сообщение от оператора
                $message = $conversation->messages()->create([
                    'role' => 'operator',
                    'content' => $messageData['message']['text'] ?? '',
                    'metadata' => [
                        'from_bitrix24' => true,
                        'bitrix24_message_id' => $bitrix24MessageId,
                        'bitrix24_user_id' => $messageData['user']['id'] ?? null,
                        'operator_name' => $messageData['user']['name'] ?? 'Оператор',
                    ]
                ]);

                // Подтверждаем доставку
                $this->confirmMessageDelivery(
                    $conversation->bot,
                    $messageData
                );

                // Обновляем статус диалога
                if ($conversation->status === 'active') {
                    $conversation->update(['status' => 'waiting_operator']);
                }

                Log::info('Operator message processed', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle operator message from Bitrix24', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Подтверждение доставки сообщения
     */
    protected function confirmMessageDelivery(Bot $bot, array $messageData): void
    {
        try {
            $connectorId = $this->getConnectorIdForBot($bot);
            
            $botIntegration = $this->integration->bots()
                ->where('bot_id', $bot->id)
                ->first();
            
            if (!$botIntegration) {
                return;
            }
            
            $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
            $lineId = $connectorSettings['line_id'] ?? null;
            
            if (!$lineId) {
                return;
            }

            $this->makeRequest('imconnector.send.status.delivery', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => [
                    [
                        'im' => $messageData['im'] ?? null,
                        'message' => [
                            'id' => [$messageData['message']['id'] ?? null]
                        ],
                        'chat' => [
                            'id' => $messageData['chat']['id'] ?? null
                        ],
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to confirm message delivery', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Выполнение запроса к API Битрикс24
     */
    protected function makeRequest(string $method, array $params = []): array
    {
        $isOauth = $this->oauthRestUrl && $this->accessToken;
        $url = $isOauth ? ($this->oauthRestUrl . $method) : ($this->webhookUrl . $method);

        if (!$url) {
            throw new \Exception('Bitrix24 ConnectorProvider is not configured.');
        }

        if ($isOauth) {
            $params['auth'] = $this->accessToken;
        }

        try {
            $response = $this->client->post($url, ['json' => $params]);
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (isset($result['error']) && $result['error'] === 'expired_token') {
                if ($this->refreshToken) {
                    $this->refreshAccessToken();
                    $params['auth'] = $this->accessToken;
                    $retryUrl = $this->oauthRestUrl . $method;
                    $retryResponse = $this->client->post($retryUrl, ['json' => $params]);
                    $finalResult = json_decode($retryResponse->getBody()->getContents(), true);

                    if (!empty($finalResult['error'])) {
                        throw new \Exception($finalResult['error_description'] ?? $finalResult['error']);
                    }
                    return $finalResult;
                }
            }

            if (!empty($result['error'])) {
                throw new \Exception($result['error_description'] ?? $result['error']);
            }
            return $result;
        } catch (\Exception $e) {
            Log::error('Bitrix24 ConnectorProvider API request failed', [
                'method' => $method, 
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Обновление access токена
     */
    protected function refreshAccessToken(): void
    {
        try {
            $response = $this->client->get('https://oauth.bitrix.info/oauth/token/', [
                'query' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('services.bitrix24.client_id'),
                    'client_secret' => config('services.bitrix24.client_secret'),
                    'refresh_token' => $this->refreshToken,
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                $this->refreshToken = $data['refresh_token'];

                $newCredentials = array_merge($this->config, [
                    'auth_id' => $this->accessToken,
                    'refresh_id' => $this->refreshToken,
                ]);

                $this->integration->update(['credentials' => $newCredentials]);
                $this->config = $newCredentials;
            } else {
                throw new \Exception('Failed to get new access token from refresh token response.');
            }
        } catch (\Exception $e) {
            Log::error('Bitrix24 token refresh failed in ConnectorProvider', [
                'integration_id' => $this->integration->id, 
                'error' => $e->getMessage()
            ]);
            $this->integration->update(['is_active' => false]);
            throw $e;
        }
    }
    
    /**
     * Получение ID коннектора для бота
     */
    protected function getConnectorIdForBot(Bot $bot): string
    {
        return 'chatbot_' . $bot->organization_id . '_' . $bot->id;
    }
}