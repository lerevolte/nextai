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
        $conversationId = $conversation->id;
        Log::info("=== START sendInitialMessage for conversation #{$conversationId} ===");
        
        // Проверяем блокировку
        $cacheKey = "bitrix24_initial_sent_{$conversationId}";
        if (Cache::has($cacheKey)) {
            Log::warning("SKIP: Initial message already sent (cache exists)", [
                'conversation_id' => $conversationId,
                'cache_key' => $cacheKey
            ]);
            return ['success' => false, 'error' => 'Already sent'];
        }
        
        Cache::put($cacheKey, true, 300);
        Log::info("STEP 1: Cache lock set", ['conversation_id' => $conversationId]);
        
        try {
            $bot = $conversation->bot;
            $connectorId = $this->getConnectorIdForBot($bot);
            Log::info("STEP 2: Got connector ID", [
                'conversation_id' => $conversationId,
                'connector_id' => $connectorId,
                'bot_id' => $bot->id,
                'bot_name' => $bot->name
            ]);
            
            // Получаем настройки коннектора
            $botIntegration = $this->integration->bots()
                ->where('bot_id', $bot->id)
                ->first();
            
            if (!$botIntegration) {
                throw new \Exception('Bot not connected to integration');
            }
            
            $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
            $lineId = $connectorSettings['line_id'] ?? null;
            
            Log::info("STEP 3: Got line settings", [
                'conversation_id' => $conversationId,
                'line_id' => $lineId,
                'connector_settings' => $connectorSettings
            ]);
            
            if (!$lineId) {
                throw new \Exception('Line ID not configured');
            }

            // Формируем данные пользователя
            $userData = [
                'id' => $conversation->external_id ?? 'user_' . $conversationId,
                'name' => $conversation->user_name ?? 'Гость',
                'last_name' => '',
                'email' => $conversation->user_email,
                'phone' => $conversation->user_phone,
            ];
            
            Log::info("STEP 4: Prepared user data", [
                'conversation_id' => $conversationId,
                'user_data' => $userData
            ]);

            // Получаем первое сообщение пользователя
            $firstMessage = $conversation->messages()
                ->where('role', 'user')
                ->orderBy('created_at', 'asc')
                ->first();

            // Получаем приветственное сообщение бота если есть
            $welcomeMessage = $conversation->messages()
                ->where('role', 'assistant')
                ->orderBy('created_at', 'asc')
                ->first();

            // Формируем массив сообщений для отправки
            $messagesToSend = [];
            
            // Добавляем сообщение пользователя
            if ($firstMessage) {
                $messagesToSend[] = [
                    'user' => $userData,
                    'message' => [
                        'id' => (string)$firstMessage->id,
                        'date' => $firstMessage->created_at->timestamp,
                        'text' => $firstMessage->content,
                    ],
                    'chat' => [
                        'id' => 'chat_' . $conversationId,
                        'name' => 'Чат #' . $conversationId,
                        'url' => route('conversations.show', [
                            $conversation->bot->organization,
                            $conversation->bot,
                            $conversation
                        ]),
                    ],
                ];
                Log::info("STEP 5A: Added user message to queue", [
                    'conversation_id' => $conversationId,
                    'message_id' => $firstMessage->id,
                    'message_text' => substr($firstMessage->content, 0, 50) . '...'
                ]);
            } else {
                // Если нет сообщения пользователя, создаем начальное
                $messagesToSend[] = [
                    'user' => $userData,
                    'message' => [
                        'id' => Str::uuid()->toString(),
                        'date' => now()->timestamp,
                        'text' => 'Начало диалога',
                    ],
                    'chat' => [
                        'id' => 'chat_' . $conversationId,
                        'name' => 'Чат #' . $conversationId,
                        'url' => route('conversations.show', [
                            $conversation->bot->organization,
                            $conversation->bot,
                            $conversation
                        ]),
                    ],
                ];
                Log::info("STEP 5B: Added default start message", [
                    'conversation_id' => $conversationId
                ]);
            }

            // НЕ ОТПРАВЛЯЕМ приветственное сообщение бота здесь
            // Оно будет отправлено отдельно через sendUserMessage после создания чата

            Log::info("STEP 6: Sending messages batch", [
                'conversation_id' => $conversationId,
                'messages_count' => count($messagesToSend)
            ]);

            // Отправляем сообщения
            $result = $this->makeRequest('imconnector.send.messages', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => $messagesToSend,
            ]);

            Log::info("STEP 7: Messages sent result", [
                'conversation_id' => $conversationId,
                'result' => $result
            ]);

            if (empty($result['result'])) {
                throw new \Exception('Failed to send message to Bitrix24: ' . json_encode($result));
            }

            if (!empty($result['result']['DATA']['RESULT'][0])) {
                $resultData = $result['result']['DATA']['RESULT'][0];
                $sessionData = $resultData['session'] ?? null;
                
                if ($sessionData) {
                    $sessionId = $sessionData['ID'];
                    $realChatId = $sessionData['CHAT_ID']; // Это будет числовой ID (309348)
                    
                    $conversation->update([
                        'metadata' => array_merge($conversation->metadata ?? [], [
                            'bitrix24_connector_id' => $connectorId,
                            'bitrix24_line_id' => $lineId,
                            'bitrix24_chat_id' => $realChatId, // Сохраняем числовой ID
                            'bitrix24_session_id' => $sessionId,
                            'bitrix24_initial_message_sent' => true,
                            'bitrix24_sent_at' => now()->toIso8601String(),
                        ])
                    ]);
                    
                    Log::info("STEP 8: Metadata updated with real chat ID", [
                        'conversation_id' => $conversationId,
                        'session_id' => $sessionId,
                        'real_chat_id' => $realChatId,
                        'is_numeric' => is_numeric($realChatId)
                    ]);
                }
            }


            Log::info("=== END sendInitialMessage SUCCESS ===", [
                'conversation_id' => $conversationId,
                'chat_id' => 'chat_' . $conversationId,
                'connector_id' => $connectorId,
                'line_id' => $lineId,
            ]);

            return [
                'success' => true,
                'chat_id' => 'chat_' . $conversationId,
                'connector_id' => $connectorId,
                'line_id' => $lineId,
            ];

        } catch (\Exception $e) {
            Cache::forget($cacheKey);
            
            Log::error("=== ERROR in sendInitialMessage ===", [
                'conversation_id' => $conversationId,
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
        $conversationId = $conversation->id;
        $messageId = $message->id;
        $messageRole = $message->role;
        
        Log::info("=== START sendUserMessage ===", [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'role' => $messageRole,
            'content_preview' => substr($message->content, 0, 50) . '...'
        ]);
        
        try {
            // Проверяем, не отправляли ли это сообщение уже
            $cacheKey = "bitrix24_msg_sent_{$messageId}";
            if (Cache::has($cacheKey)) {
                Log::info("SKIP: Message already sent (cache exists)", [
                    'message_id' => $messageId,
                    'cache_key' => $cacheKey
                ]);
                return ['success' => true, 'cached' => true];
            }
            
            $bot = $conversation->bot;
            
            Log::info("STEP 1: Determining send method", [
                'message_role' => $messageRole,
                'bot_id' => $bot->id,
                'has_bitrix24_bot_id' => !empty($bot->metadata['bitrix24_bot_id'])
            ]);
            
            if ($message->role === 'assistant') {
                Log::info("ROUTE: Sending as bot message");
                $result = $this->sendAsBotMessage($conversation, $message);
            } else {
                Log::info("ROUTE: Sending as user message");
                $result = $this->sendAsUserMessage($conversation, $message);
            }
            
            // Кешируем успешную отправку
            if ($result['success']) {
                Cache::put($cacheKey, true, 3600);
                Log::info("SUCCESS: Message sent and cached", [
                    'message_id' => $messageId,
                    'result' => $result
                ]);
            } else {
                Log::warning("FAILED: Message not sent", [
                    'message_id' => $messageId,
                    'result' => $result
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("=== ERROR in sendUserMessage ===", [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
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
     * Отправка как сообщение бота
     */
    protected function sendAsBotMessage(Conversation $conversation, Message $message): array
    {
        $conversationId = $conversation->id;
        $messageId = $message->id;
        
        Log::info("=== START sendAsBotMessage ===", [
            'conversation_id' => $conversationId,
            'message_id' => $messageId
        ]);
        
        $bot = $conversation->bot;
        $botId = $bot->metadata['bitrix24_bot_id'] ?? null;
        
        // Получаем реальный ID чата из метаданных
        $chatId = $conversation->metadata['bitrix24_chat_id'] ?? null;
        $sessionId = $conversation->metadata['bitrix24_session_id'] ?? null;
        
        Log::info("STEP 1: Check existing chat", [
            'chat_id' => $chatId,
            'session_id' => $sessionId,
            'bitrix24_bot_id' => $botId
        ]);
        
        if (!$chatId || !$botId) {
            Log::warning("No chat_id or bot_id, cannot send bot message");
            return ['success' => false, 'error' => 'No chat_id or bot_id'];
        }
        
        // Убеждаемся, что chat_id - это число
        $numericChatId = is_numeric($chatId) ? $chatId : str_replace('chat_', '', $chatId);
        
        // Попытка 1: Отправляем через imbot.message.add с правильным форматом
        try {
            $dialogId = 'chat' . $numericChatId; // Формат должен быть "chat309348"
            
            $result = $this->makeRequest('imbot.message.add', [
                'BOT_ID' => $botId,
                'DIALOG_ID' => $dialogId,
                'MESSAGE' => $message->content,
            ]);
            
            Log::info("Bot message send attempt via imbot.message.add", [
                'result' => $result,
                'bot_id' => $botId,
                'dialog_id' => $dialogId
            ]);
            
            if (!empty($result['result'])) {
                $message->update([
                    'metadata' => array_merge($message->metadata ?? [], [
                        'bitrix24_message_id' => $result['result'],
                        'bitrix24_sent_as' => 'bot_message',
                        'bitrix24_sent_at' => now()->toIso8601String(),
                    ])
                ]);
                
                Log::info("=== SUCCESS sendAsBotMessage via imbot ===", [
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId,
                    'bitrix24_message_id' => $result['result']
                ]);
                
                return [
                    'success' => true,
                    'message_id' => $result['result'],
                ];
            }
        } catch (\Exception $e) {
            Log::warning("imbot.message.add failed", [
                'error' => $e->getMessage(),
                'chat_id' => $numericChatId
            ]);
        }
        
        // Попытка 2: Используем imopenlines.bot.session.message.send
        if ($sessionId && $numericChatId) {
            try {
                $result = $this->makeRequest('imopenlines.bot.session.message.send', [
                    'CHAT_ID' => $numericChatId, // Числовой ID
                    'SESSION_ID' => $sessionId,
                    'MESSAGE' => $message->content,
                ]);
                
                Log::info("Bot message send attempt via imopenlines.bot.session.message.send", [
                    'result' => $result,
                    'session_id' => $sessionId,
                    'chat_id' => $numericChatId
                ]);
                
                if (!empty($result['result'])) {
                    $message->update([
                        'metadata' => array_merge($message->metadata ?? [], [
                            'bitrix24_message_sent' => true,
                            'bitrix24_sent_as' => 'openline_bot',
                            'bitrix24_sent_at' => now()->toIso8601String(),
                        ])
                    ]);
                    
                    return [
                        'success' => true,
                        'message_id' => $result['result'],
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("imopenlines.bot.session.message.send failed", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Попытка 3: Отправляем через REST API напрямую в чат
        try {
            $result = $this->makeRequest('im.message.add', [
                'DIALOG_ID' => 'chat' . $numericChatId,
                'MESSAGE' => '[b]Бот:[/b] ' . $message->content,
                'SYSTEM' => 'Y',
            ]);
            
            if (!empty($result['result'])) {
                Log::info("Bot message sent via im.message.add", [
                    'result' => $result
                ]);
                
                return ['success' => true, 'message_id' => $result['result']];
            }
        } catch (\Exception $e) {
            Log::error("im.message.add failed", [
                'error' => $e->getMessage()
            ]);
        }
        
        Log::error("All bot API methods failed", [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'numeric_chat_id' => $numericChatId
        ]);
        
        return [
            'success' => false,
            'error' => 'All API methods failed'
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
        if ($conversation->metadata['bitrix24_chat_id'] ?? false) {
            Log::warning("Cannot send system message - chat already exists, would create duplicate", [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id
            ]);
            
            return [
                'success' => false,
                'error' => 'Cannot send bot message to existing chat via connector'
            ];
        }
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