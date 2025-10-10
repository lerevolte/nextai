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

            // Сохраняем метаданные о создании чата
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
        // НЕ отправляем через коннектор если чат уже создан
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
                $conversation = \App\Models\Conversation::find($chatId);
                
                if (!$conversation) continue;

                $rawText = $messageData['message']['text'] ?? '';
                $bitrix24MessageId = $messageData['message']['id'] ?? null;
                $authorId = $messageData['user']['id'] ?? null;

                // --- ПРОВЕРКА 1: Сравнение с последним сообщением ассистента ---
                $lastAssistantMessage = $conversation->messages()
                    ->where('role', 'assistant')
                    ->latest()
                    ->first();

                if ($lastAssistantMessage && trim($lastAssistantMessage->content) === trim($rawText)) {
                    Log::info("Echo message from bot detected by content match and skipped", [
                        'conversation_id' => $conversation->id,
                        'content' => substr($rawText, 0, 50),
                        'bitrix24_message_id' => $bitrix24MessageId
                    ]);
                    $this->confirmMessageDelivery($conversation->bot, $messageData);
                    continue;
                }
                
                // --- ПРОВЕРКА 2: Проверяем последние N сообщений на совпадение ---
                $recentMessages = $conversation->messages()
                    ->whereIn('role', ['assistant', 'user'])
                    ->latest()
                    ->take(5)
                    ->get();
                    
                $isDuplicate = false;
                foreach ($recentMessages as $recentMsg) {
                    if (trim($recentMsg->content) === trim($rawText)) {
                        Log::info("Duplicate message detected in recent history", [
                            'conversation_id' => $conversation->id,
                            'matching_message_id' => $recentMsg->id,
                            'matching_message_role' => $recentMsg->role,
                            'content' => substr($rawText, 0, 50)
                        ]);
                        $isDuplicate = true;
                        break;
                    }
                }
                
                if ($isDuplicate) {
                    $this->confirmMessageDelivery($conversation->bot, $messageData);
                    continue;
                }

                // --- ПРОВЕРКА 3: Игнорируем приветствие от Открытой Линии ---
                if ($conversation->messages()->count() <= 1 && str_starts_with(trim($rawText), 'Добро пожаловать')) {
                    Log::info("Ignoring B24 Open Line welcome message");
                    $this->confirmMessageDelivery($conversation->bot, $messageData);
                    continue;
                }
                
                // --- ПРОВЕРКА 4: Проверка на автоматические сообщения Битрикс24 ---
                if (stripos($rawText, 'Меня зовут') !== false && stripos($rawText, 'консультант') !== false) {
                    Log::info("Ignoring Bitrix24 auto-greeting", [
                        'conversation_id' => $conversation->id,
                        'text_preview' => substr($rawText, 0, 50)
                    ]);
                    $this->confirmMessageDelivery($conversation->bot, $messageData);
                    continue;
                }
                
                // --- ПРОВЕРКА 5: Проверка по ID сообщения ---
                if ($bitrix24MessageId && $conversation->messages()->where('metadata->bitrix24_message_id', $bitrix24MessageId)->exists()) {
                    Log::info("Message already exists by ID", [
                        'bitrix24_message_id' => $bitrix24MessageId
                    ]);
                    $this->confirmMessageDelivery($conversation->bot, $messageData);
                    continue;
                }
                
                // --- ПРОВЕРКА 6: Проверяем, не является ли автор ботом ---
                $bot = $conversation->bot;
                if ($bot->metadata && isset($bot->metadata['bitrix24_bot_id'])) {
                    if ($authorId == $bot->metadata['bitrix24_bot_id']) {
                        Log::info("Message from our bot detected and skipped", [
                            'conversation_id' => $conversation->id,
                            'bot_id' => $bot->metadata['bitrix24_bot_id'],
                            'author_id' => $authorId
                        ]);
                        $this->confirmMessageDelivery($conversation->bot, $messageData);
                        continue;
                    }
                }
                
                // Парсим текст и определяем имя оператора
                $operatorName = $messageData['user']['name'] ?? 'Оператор';
                $messageText = $rawText;
                $isRealOperator = false; // Флаг: это реальный оператор, а не бот

                if (preg_match('/\[b\](.+?):\[\/b\]\s*\[br\](.+)/s', $rawText, $matches)) {
                    $operatorName = $matches[1];
                    $messageText = trim($matches[2]);
                    
                    // --- ПРОВЕРКА 7: Проверяем имя отправителя ---
                    $botNames = ['бот', 'bot', 'арина', 'ассистент', 'assistant', 'виртуальный помощник'];
                    $isRealOperator = true;
                    // foreach ($botNames as $botName) {

                    //     if (stripos($operatorName, $botName) !== false) {
                    //         Log::info("Bot-like name detected in operator name, skipping", [
                    //             'operator_name' => $operatorName,
                    //             'conversation_id' => $conversation->id
                    //         ]);
                    //         $this->confirmMessageDelivery($conversation->bot, $messageData);
                    //         continue 2; // Выходим из обоих циклов
                    //     }
                    // }
                    foreach ($botNames as $botName) {
                        if (stripos($operatorName, $botName) !== false) {
                            $isRealOperator = false;
                            break;
                        }
                    }
                    
                    if (!$isRealOperator) {
                        Log::info("Skipping non-operator formatted message", [
                            'sender_name' => $operatorName,
                            'chat_id' => $chatId,
                            'text_preview' => substr($messageText, 0, 50)
                        ]);
                        continue;
                    }
                } else {
                    $messageText = preg_replace(['/\[br\]/i', '/\[\/?b\]/i'], ["\n", ''], $messageText);
                }
                
                // --- ПРОВЕРКА 8: Финальная проверка на совпадение с любым недавним сообщением ---
                $veryRecentMessage = $conversation->messages()
                    ->where('created_at', '>', now()->subSeconds(30))
                    ->where('content', trim($messageText))
                    ->first();
                    
                if ($veryRecentMessage) {
                    Log::info("Very recent duplicate detected (within 30 seconds)", [
                        'conversation_id' => $conversation->id,
                        'original_message_id' => $veryRecentMessage->id,
                        'original_role' => $veryRecentMessage->role,
                        'content' => substr($messageText, 0, 50)
                    ]);
                    $this->confirmMessageDelivery($conversation->bot, $messageData);
                    continue;
                }

                // Если все проверки пройдены, создаем сообщение оператора
                Log::info("Creating operator message", [
                    'conversation_id' => $conversation->id,
                    'operator_name' => $operatorName,
                    'content_preview' => substr($messageText, 0, 50)
                ]);

                $conversation->messages()->create([
                    'role' => 'operator',
                    'content' => $messageText,
                    'metadata' => [
                        'from_bitrix24' => true,
                        'bitrix24_message_id' => $bitrix24MessageId,
                        'bitrix24_user_id' => $authorId,
                        'operator_name' => $operatorName,
                    ]
                ]);

                $this->confirmMessageDelivery($conversation->bot, $messageData);

                // Меняем статус только если это действительно сообщение от человека-оператора
                if ($isRealOperator && $conversation->status === 'active') {
                    // Дополнительная проверка: убедимся, что это не автоматическое сообщение
                    $isAutoMessage = false;
                    $autoKeywords = ['виртуальный помощник', 'чем могу помочь', 'добро пожаловать'];
                    foreach ($autoKeywords as $keyword) {
                        if (stripos($messageText, $keyword) !== false) {
                            $isAutoMessage = true;
                            break;
                        }
                    }
                    
                    if (!$isAutoMessage) {
                        $conversation->update(['status' => 'waiting_operator']);
                        Log::info('Conversation status changed to waiting_operator', [
                            'conversation_id' => $conversation->id,
                            'operator_name' => $operatorName
                        ]);
                    }
                }

                Log::info('Operator message processed successfully', [
                    'conversation_id' => $conversation->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle operator message from Bitrix24 provider', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

    /**
     * Подтверждение доставки сообщения, инициированное виджетом
     */
    public function confirmMessageDeliveryFromWidget(Conversation $conversation, array $bitrix24MessageIds): array
    {
        try {
            $bot = $conversation->bot;
            $connectorId = $this->getConnectorIdForBot($bot);

            $botIntegration = $this->integration->bots()->where('bot_id', $bot->id)->first();
            $lineId = $conversation->metadata['bitrix24_line_id'] ?? null;
            $chatId = $conversation->metadata['bitrix24_chat_id'] ?? null; // Реальный ID чата (число)
            $ourChatId = 'chat_' . $conversation->id; // Наш ID чата (строка)

            if (!$lineId || !$chatId) {
                Log::channel('bitrix24')->info('[ConfirmDelivery] Missing critical metadata for B24 confirmation', [
                    'conversation_id' => $conversation->id,
                    'line_id_found' => $lineId,
                    'chat_id_found' => $chatId,
                ]);
                throw new \Exception('Line ID or Chat ID not found in conversation metadata');
            }



            $messagesPayload = [];
            foreach ($bitrix24MessageIds as $msgId) {
                $messagesPayload[] = [
                    'im' => ['chat_id' => $chatId, 'message_id' => $msgId],
                    'message' => ['id' => [$msgId]],
                    'chat' => ['id' => $ourChatId],
                ];
            }

            Log::channel('bitrix24')->info('[ConfirmDelivery] Sending confirmation payload to Bitrix24', [
                'connector_id' => $connectorId,
                'line_id' => $lineId,
                'chat_id' => $chatId,
                'messages_payload' => $messagesPayload,
            ]);
            Log::info('Confirming message delivery to Bitrix24', [
                'connector_id' => $connectorId,
                'line_id' => $lineId,
                'messages_count' => count($messagesPayload)
            ]);

            return $this->makeRequest('imconnector.send.status.delivery', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => $messagesPayload,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to confirm message delivery from widget', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}