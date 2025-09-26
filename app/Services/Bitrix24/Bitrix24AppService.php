<?php

namespace App\Services\Bitrix24;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Сервис для работы с приложением Битрикс24
 */
class Bitrix24AppService
{
    protected Client $client;
    
    // Параметры приложения для маркета
    const APP_CODE = 'chatbot.connector';
    const APP_VERSION = '1.0.0';
    
    public function __construct()
    {
        $this->client = new Client(['timeout' => 30, 'http_errors' => false,]);
    }
    
    /**
     * Регистрация обработчиков событий
     */
    public function registerEventHandlers(CrmIntegration $integration): void
    {
        try {
            $events = [
                'ONIMCONNECTORMESSAGEADD' => url('/bitrix24/event-handler'),
                'ONIMOPENLINESMESSAGEADD' => url('/bitrix24/event-handler'), // Сообщения в открытых линиях
                'ONIMCONNECTORMESSAGEUPDATE' => url('/bitrix24/event-handler'), // Обновления сообщений
                'ONIMBOTMESSAGEADD' => url('/bitrix24/event-handler'), // Сообщения к боту
                'ONAPPUNINSTALL' => url('/bitrix24/event-handler'),
                'ONIMOPENLINESMESSAGEUPDATE' => url('/bitrix24/event-handler'),
                'ONIMOPENLINEMESSAGEADD' => url('/bitrix24/event-handler'), // без S
                'ONIMMESSAGEADD' => url('/bitrix24/event-handler'),
            ];
            
            foreach ($events as $event => $handler) {
                $result = $this->makeRequest($integration, 'event.bind', [
                    'event' => $event,
                    'handler' => $handler,
                    'auth_type' => 0,
                ]);
                
                Log::info('Event handler registration result', [
                    'event' => $event,
                    'result' => $result
                ]);
            }
            
            Log::info('Event handlers registered', [
                'integration_id' => $integration->id,
                'events' => array_keys($events),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to register event handlers', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    /**
     * Регистрация чат-бота в Битрикс24
     */
    public function registerChatBot(CrmIntegration $integration, Bot $bot): array
    {
        try {
            $botCode = 'chatbot_' . $bot->organization_id . '_' . $bot->id;
            Log::info("[B24AppService] RegisterChatBot: Start", ['bot_id' => $bot->id, 'bot_code' => $botCode]);

            // 1. Сначала пытаемся удалить любого старого бота с тем же кодом, чтобы обеспечить чистоту.
            // Это безопаснее, чем пытаться найти и повторно использовать старого.
            try {
                Log::info("[B24AppService] RegisterChatBot: Attempting to unregister any existing bot with the same code", ['bot_code' => $botCode]);
                // Используем метод imbot.unregister, который может работать с CODE
                $this->makeRequest($integration, 'imbot.unregister', ['CODE' => $botCode]);
                // Нам не важен результат, это просто шаг очистки.
            } catch (\Exception $e) {
                Log::warning("[B24AppService] RegisterChatBot: Cleanup unregister failed (this is often OK)", ['error' => $e->getMessage()]);
            }
            
            // 2. Очищаем любой устаревший ID бота из нашей базы данных.
            if ($bot->metadata['bitrix24_bot_id'] ?? false) {
                 $metadata = $bot->metadata;
                 unset($metadata['bitrix24_bot_id']);
                 unset($metadata['bitrix24_bot_registered_at']);
                 $bot->update(['metadata' => $metadata]);
                 Log::info("[B24AppService] RegisterChatBot: Cleared stale bot ID from local DB", ['bot_id' => $bot->id]);
            }

            // 3. Теперь регистрируем бота.
            Log::info("[B24AppService] RegisterChatBot: Making 'imbot.register' request");
            $result = $this->makeRequest($integration, 'imbot.register', [
                'CODE' => $botCode,
                'TYPE' => 'O', // ВАЖНО: Тип 'O' для бота открытых линий
                'EVENT_MESSAGE_ADD' => url('/bitrix24/bot-handler'),
                'EVENT_WELCOME_MESSAGE' => url('/bitrix24/bot-welcome'),
                'EVENT_BOT_DELETE' => url('/bitrix24/bot-delete'),
                'OPENLINE' => 'Y', // Указываем, что это бот для открытых линий
                'PROPERTIES' => [
                    'NAME' => $bot->name,
                    'COLOR' => 'AQUA',
                    'EMAIL' => 'bot@' . parse_url(config('app.url'), PHP_URL_HOST),
                    'PERSONAL_PHOTO' => $bot->avatar_url ?? '',
                ]
            ]);

            Log::info("[B24AppService] RegisterChatBot: 'imbot.register' response", ['result' => $result]);

            // 4. Корректно обрабатываем ответ.
            if (!empty($result['result'])) {
                // УСПЕХ!
                $newBotId = $result['result'];
                $bot->update([
                    'metadata' => array_merge($bot->metadata ?? [], [
                        'bitrix24_bot_id' => $newBotId,
                        'bitrix24_bot_registered_at' => now()->toIso8601String(),
                    ])
                ]);

                Log::info("[B24AppService] RegisterChatBot: Success!", ['bot_id' => $bot->id, 'new_b24_bot_id' => $newBotId]);
                return [
                    'success' => true,
                    'bot_id' => $newBotId,
                    'message' => 'Чат-бот успешно зарегистрирован'
                ];
            }
            
            // Если мы дошли до сюда, это настоящая ошибка.
            throw new \Exception('Failed to register bot. API Response: ' . json_encode($result));

        } catch (\Exception $e) {
            Log::error('Failed to register chatbot', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    /**
     * Регистрация коннектора для бота
     */
    public function registerConnector(CrmIntegration $integration, Bot $bot): array
    {
        try {
            // СНАЧАЛА регистрируем чат-бота если не зарегистрирован
            if (!($bot->metadata['bitrix24_bot_id'] ?? false)) {
                $botResult = $this->registerChatBot($integration, $bot);
                if (!$botResult['success']) {
                    throw new \Exception('Failed to register chatbot: ' . $botResult['error']);
                }
                // Перезагружаем бота чтобы получить обновленные метаданные
                $bot->refresh();
            }

            $connectorId = $this->getConnectorId($bot);
            $bitrix24BotId = $bot->metadata['bitrix24_bot_id'];
            
            // Регистрируем коннектор
            $result = $this->makeRequest($integration, 'imconnector.register', [
                'ID' => $connectorId,
                'NAME' => $bot->name,
                'ICON' => [
                    'DATA_IMAGE' => $this->getBotIcon($bot),
                    'COLOR' => '#6366F1',
                ],
                'PLACEMENT_HANDLER' => url('/bitrix24/activate-connector'),
            ]);
            
            // Проверяем результат регистрации
            if (isset($result['error'])) {
                // Если ошибка "Connector already registered", это нормально
                if (strpos($result['error'], 'already registered') !== false || 
                    strpos($result['error'], 'CONNECTOR_ALREADY_REGISTERED') !== false) {
                    Log::info('Connector already registered', ['connector_id' => $connectorId]);
                } else {
                    throw new \Exception('Failed to register connector: ' . ($result['error_description'] ?? $result['error']));
                }
            }

            // Обновляем метаданные бота
            $bot->update([
                'metadata' => array_merge($bot->metadata ?? [], [
                    'bitrix24_connector_registered' => true,
                    'bitrix24_connector_id' => $connectorId,
                    'bitrix24_connector_registered_at' => now()->toIso8601String(),
                    'bitrix24_bot_id' => $bitrix24BotId,
                ])
            ]);
            
            // Очищаем кэш
            Cache::flush();
            
            Log::info('Connector registration completed', [
                'connector_id' => $connectorId,
                'bot_id' => $bitrix24BotId,
                'bot_name' => $bot->name
            ]);

            return [
                'success' => true,
                'connector_id' => $connectorId,
                'bot_id' => $bitrix24BotId,
                'message' => 'Коннектор зарегистрирован. Теперь подключите его в Битрикс24: CRM → Контакт-центр → Открытые линии → Подключить канал → ' . $bot->name
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to register connector', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Активация коннектора для линии
     */
    public function activateConnector(CrmIntegration $integration, Bot $bot, int $lineId, bool $active = true): array
    {
        try {
            $connectorId = $this->getConnectorId($bot);
            
            // Активируем коннектор без предварительной проверки
            // так как imconnector.list не показывает пользовательские коннекторы
            $result = $this->makeRequest($integration, 'imconnector.activate', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'ACTIVE' => $active ? 1 : 0,
            ]);
            
            // Проверяем ошибки
            if (isset($result['error'])) {
                // Некоторые ошибки можно игнорировать
                if (strpos($result['error'], 'CONNECTOR_NOT_FOUND') !== false) {
                    throw new \Exception('Connector not registered. Please register connector first.');
                }
                Log::warning('Activation warning', [
                    'error' => $result['error'],
                    'connector_id' => $connectorId,
                    'line_id' => $lineId
                ]);
            }
            
            // Передаем данные для отображения в виджете открытой линии
            if ($active) {
                $widgetData = [
                    'CONNECTOR' => $connectorId,
                    'LINE' => $lineId,
                    'DATA' => [
                        'id' => $connectorId,
                        'url_im' => route('widget.show', $bot->slug),
                        'url_handler' => url('/bitrix24/event-handler'),
                        'name' => $bot->name,
                        'desc' => $bot->description ?? 'AI Chat Bot',
                    ]
                ];
                
                $dataResult = $this->makeRequest($integration, 'imconnector.connector.data.set', $widgetData);
                $this->makeRequest($integration, 'event.bind', [
                    'event' => 'ONIMCONNECTORMESSAGEADD',
                    'handler' => url('/bitrix24/event-handler'),
                    'connector' => $connectorId, // Указываем конкретный коннектор
                ]);
                Log::info('Connector data set', [
                    'result' => $dataResult,
                    'widget_data' => $widgetData
                ]);
            }
            
            // Обновляем настройки в БД
            $botIntegration = $integration->bots()->where('bot_id', $bot->id)->first();
            if ($botIntegration) {
                $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
                $integration->bots()->updateExistingPivot($bot->id, [
                    'connector_settings' => json_encode(array_merge($connectorSettings, [
                        'line_id' => $lineId,
                        'active' => $active,
                        'activated_at' => now()->toIso8601String(),
                    ]))
                ]);
            } else {
                // Если связи нет, создаем её
                $integration->bots()->attach($bot->id, [
                    'sync_contacts' => true,
                    'sync_conversations' => true,
                    'create_leads' => true,
                    'create_deals' => false,
                    'is_active' => true,
                    'connector_settings' => json_encode([
                        'connector_id' => $connectorId,
                        'line_id' => $lineId,
                        'active' => $active,
                        'activated_at' => now()->toIso8601String(),
                    ])
                ]);
            }
            
            Log::info('Connector activated successfully', [
                'bot_id' => $bot->id,
                'connector_id' => $connectorId,
                'line_id' => $lineId,
                'active' => $active,
            ]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Failed to activate connector', [
                'bot_id' => $bot->id,
                'line_id' => $lineId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkConnectorStatus(CrmIntegration $integration, Bot $bot): array
    {
        try {
            $connectorId = $this->getConnectorId($bot);
            
            // Пробуем получить статус коннектора напрямую
            try {
                $statusResult = $this->makeRequest($integration, 'imconnector.status', [
                    'CONNECTOR' => $connectorId
                ]);
                
                if (!isset($statusResult['error'])) {
                    return [
                        'registered' => true,
                        'connector_id' => $connectorId,
                        'status' => $statusResult['result'] ?? null,
                    ];
                }
                
                // Если ошибка CONNECTOR_NOT_FOUND, значит не зарегистрирован
                if (strpos($statusResult['error'], 'CONNECTOR_NOT_FOUND') !== false) {
                    return [
                        'registered' => false,
                        'connector_id' => $connectorId,
                        'error' => 'Connector not registered'
                    ];
                }
                
            } catch (\Exception $e) {
                Log::warning('Could not get connector status', [
                    'connector_id' => $connectorId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Fallback: проверяем по метаданным бота
            if ($bot->metadata['bitrix24_connector_registered'] ?? false) {
                return [
                    'registered' => true,
                    'connector_id' => $connectorId,
                    'registered_at' => $bot->metadata['bitrix24_connector_registered_at'] ?? null,
                ];
            }
            
            return [
                'registered' => false,
                'connector_id' => $connectorId,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to check connector status', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Удаление регистрации коннектора
     */
    public function unregisterConnector(CrmIntegration $integration, Bot $bot): bool
    {
        try {
            $connectorId = $this->getConnectorId($bot);
            
            // Деактивируем коннектор
            $botIntegration = $integration->bots()->where('bot_id', $bot->id)->first();
            if ($botIntegration && ($botIntegration->pivot->connector_settings['line_id'] ?? null)) {
                $this->activateConnector($integration, $bot, $botIntegration->pivot->connector_settings['line_id'], false);
            }
            
            // Удаляем регистрацию
            $this->makeRequest($integration, 'imconnector.unregister', [
                'CONNECTOR' => $connectorId,
            ]);
            
            // Обновляем метаданные
            $bot->update([
                'metadata' => array_merge($bot->metadata ?? [], [
                    'bitrix24_connector_registered' => false,
                    'bitrix24_connector_unregistered_at' => now()->toIso8601String(),
                ])
            ]);

            Cache::flush();
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to unregister connector', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Получение списка зарегистрированных коннекторов
     */
    public function getRegisteredConnectors(CrmIntegration $integration): array
    {
        $connectors = [];
        
        foreach ($integration->bots as $bot) {
            if ($bot->metadata['bitrix24_connector_registered'] ?? false) {
                $connectors[] = [
                    'bot_id' => $bot->id,
                    'bot_name' => $bot->name,
                    'connector_id' => $this->getConnectorId($bot),
                    'line_id' => $bot->pivot->connector_settings['line_id'] ?? null,
                    'active' => $bot->pivot->connector_settings['active'] ?? false,
                ];
            }
        }
        
        return $connectors;
    }
    
    /**
     * Обработка сообщения от коннектора
     */
    public function handleConnectorMessage(CrmIntegration $integration, array $data): void
    {
        try {
            $messages = $data['MESSAGES'] ?? [];
            
            Log::info('=== Connector messages received ===', [
                'messages_count' => count($messages),
                'connector' => $data['CONNECTOR'] ?? null,
            ]);
            
            foreach ($messages as $messageData) {
                // Извлекаем ID чата
                $chatId = null;
                
                // Приоритет: сначала пробуем из im.chat_id (реальный ID чата в Битрикс24)
                if (isset($messageData['im']['chat_id'])) {
                    $chatId = $messageData['im']['chat_id'];
                }
                // Затем из chat.id (наш ID)
                elseif (isset($messageData['chat']['id'])) {
                    $chatId = str_replace('chat_', '', $messageData['chat']['id']);
                }
                
                // Получаем ID пользователя и текст
                $userId = $messageData['message']['user_id'] ?? null;
                $rawText = $messageData['message']['text'] ?? '';
                
                // Парсим текст от оператора (убираем BB-коды)
                $operatorName = null;
                $messageText = $rawText;
                
                // Проверяем, есть ли имя оператора в тексте
                if (preg_match('/\[b\](.+?):\[\/b\]\s*\[br\](.+)/s', $rawText, $matches)) {
                    $operatorName = $matches[1];
                    $messageText = trim($matches[2]);
                } else {
                    // Просто убираем BB-коды
                    $messageText = preg_replace('/\[br\]/i', "\n", $messageText);
                    $messageText = preg_replace('/\[\/?b\]/i', '', $messageText);
                }
                
                Log::info('Processing message', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'operator_name' => $operatorName,
                    'original_text' => substr($rawText, 0, 50),
                    'parsed_text' => substr($messageText, 0, 50)
                ]);
                
                if (!$chatId) {
                    Log::warning('No chat ID found in message data');
                    continue;
                }
                
                // Ищем диалог по реальному chat_id из Битрикс24
                $conversation = Conversation::where('metadata->bitrix24_chat_id', $chatId)
                    ->orWhere('metadata->bitrix24_chat_id', (string)$chatId)
                    ->first();
                
                if (!$conversation) {
                    // Пробуем найти по нашему ID
                    $ourChatId = str_replace('chat_', '', $messageData['chat']['id'] ?? '');
                    if (is_numeric($ourChatId)) {
                        $conversation = Conversation::find($ourChatId);
                    }
                }
                
                if (!$conversation) {
                    Log::warning('Conversation not found', [
                        'bitrix24_chat_id' => $chatId,
                        'our_chat_id' => $messageData['chat']['id'] ?? null
                    ]);
                    continue;
                }
                
                Log::info('Found conversation', [
                    'conversation_id' => $conversation->id,
                    'bitrix24_chat_id' => $conversation->metadata['bitrix24_chat_id'] ?? null
                ]);

                // Определяем роль: если есть имя оператора, значит это оператор
                $role = $operatorName ? 'operator' : 'user';
                
                // Проверяем, не наше ли это сообщение
                $bot = $conversation->bot;
                if ($userId == ($bot->metadata['bitrix24_bot_id'] ?? null)) {
                    Log::info('Skipping bot message');
                    continue;
                }
                
                // Проверяем на дубликаты
                $bitrix24MessageId = $messageData['im']['message_id'] ?? $messageData['message']['id'] ?? null;
                if ($bitrix24MessageId) {
                    $existingMessage = $conversation->messages()
                        ->where('metadata->bitrix24_message_id', $bitrix24MessageId)
                        ->first();
                    
                    if ($existingMessage) {
                        Log::info('Message already exists', ['message_id' => $bitrix24MessageId]);
                        continue;
                    }
                }

                // Создаем сообщение
                $newMessage = $conversation->messages()->create([
                    'role' => $role,
                    'content' => $messageText,
                    'metadata' => [
                        'from_bitrix24' => true,
                        'bitrix24_message_id' => $bitrix24MessageId,
                        'bitrix24_user_id' => $userId,
                        'operator_name' => $operatorName ?? ($role === 'operator' ? 'Оператор' : null),
                        'original_text' => $rawText,
                    ]
                ]);

                // Обновляем статус диалога
                if ($role === 'operator' && $conversation->status === 'active') {
                    $conversation->update(['status' => 'waiting_operator']);
                }

                $conversation->increment('messages_count');
                $conversation->update(['last_message_at' => now()]);

                Log::info('=== Message from Bitrix24 processed ===', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $newMessage->id,
                    'role' => $role,
                    'content' => substr($messageText, 0, 50)
                ]);
                
                // Отправляем подтверждение доставки обратно в Битрикс24
                $this->confirmMessageDelivery($integration, $messageData);
                
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle connector message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    /**
     * Подтверждение доставки сообщения
     */
    protected function confirmMessageDelivery(CrmIntegration $integration, Bot $bot, array $messageData): void
    {
        try {
            $connectorId = $this->getConnectorId($bot);
            
            $botIntegration = $integration->bots()->where('bot_id', $bot->id)->first();
            $lineId = $botIntegration->pivot->connector_settings['line_id'] ?? null;
            
            if (!$lineId) {
                return;
            }
            
            $this->makeRequest($integration, 'imconnector.send.status.delivery', [
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
     * Подтверждение доставки сообщения
     */
    // protected function confirmMessageDelivery(CrmIntegration $integration, array $messageData): void
    // {
    //     try {
    //         $connectorId = 'chatbot_1_1'; // или получите из данных
    //         $lineId = 26; // или получите из данных
            
    //         $result = $this->makeRequest($integration, 'imconnector.send.status.delivery', [
    //             'CONNECTOR' => $connectorId,
    //             'LINE' => $lineId,
    //             'MESSAGES' => [
    //                 [
    //                     'im' => [
    //                         'chat_id' => $messageData['im']['chat_id'] ?? null,
    //                         'message_id' => $messageData['im']['message_id'] ?? null,
    //                     ]
    //                 ]
    //             ]
    //         ]);
            
    //         Log::info('Message delivery confirmed', [
    //             'result' => $result,
    //             'message_id' => $messageData['im']['message_id'] ?? null
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::warning('Failed to confirm message delivery', [
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }
    
    /**
     * Отправка сообщения пользователя в Битрикс24
     */
    public function sendUserMessage(CrmIntegration $integration, Conversation $conversation, $message): array
    {
        try {
            $bot = $conversation->bot;
            $connectorId = $this->getConnectorId($bot);
            
            $botIntegration = $integration->bots()->where('bot_id', $bot->id)->first();
            $lineId = $botIntegration->pivot->connector_settings['line_id'] ?? null;
            
            if (!$lineId) {
                throw new \Exception('Line ID not configured');
            }
            
            $messageData = [
                'user' => [
                    'id' => $conversation->external_id ?? $conversation->id,
                    'name' => $conversation->user_name ?? 'Гость',
                    'last_name' => '',
                    'email' => $conversation->user_email,
                    'phone' => $conversation->user_phone,
                ],
                'message' => [
                    'id' => $message->id,
                    'date' => $message->created_at->timestamp,
                    'text' => $message->content,
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
            
            $result = $this->makeRequest($integration, 'imconnector.send.messages', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => [$messageData],
            ]);
            
            if (empty($result['result'])) {
                throw new \Exception('Failed to send message');
            }
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Failed to send user message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    
    
    /**
     * Обновление токенов авторизации
     */
    public function updateAuthTokens(CrmIntegration $integration, string $authId): void
    {
        try {
            $existingCredentials = $integration->credentials ?? [];

            $newOAuthCredentials = [];
            $newOAuthCredentials['auth_id'] = $authId;
            $newOAuthCredentials['auth_updated_at'] = now()->toIso8601String();
            $mergedCredentials = array_merge($existingCredentials, $newOAuthCredentials);

            $integration->update([
                'credentials' => $mergedCredentials
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update auth tokens', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Выполнение запроса к API Битрикс24
     */
    protected function makeRequest(CrmIntegration $integration, string $method, array $params = []): array
    {
        try {
            $credentials = $integration->credentials ?? [];
            $domain = $credentials['domain'] ?? '';
            $authId = $credentials['auth_id'] ?? '';
            
            if (!$domain || !$authId) {
                throw new \Exception('Missing auth credentials');
            }
            
            $url = "https://{$domain}/rest/{$method}";
            
            $response = $this->client->post($url, [
                'json' => array_merge($params, [
                    'auth' => $authId,
                ]),
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!empty($result['error'])) {
                // Если токен истек, пробуем обновить
                if ($result['error'] === 'expired_token' && !empty($credentials['refresh_id'])) {
                    $this->refreshToken($integration);
                    return $this->makeRequest($integration, $method, $params);
                }
                
                throw new \Exception($result['error_description'] ?? $result['error']);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 API request failed', [
                'method' => $method,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Обновление токена доступа
     */
    protected function refreshToken(CrmIntegration $integration): void
    {
        try {
            $credentials = $integration->credentials ?? [];
            
            $response = $this->client->get('https://oauth.bitrix.info/oauth/token/', [
                'query' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('services.bitrix24.client_id'),
                    'client_secret' => config('services.bitrix24.client_secret'),
                    'refresh_token' => $credentials['refresh_id'] ?? '',
                ],
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!empty($data['access_token'])) {

                $newOAuthCredentials = [];
                $newOAuthCredentials['auth_id'] = $data['access_token'];
                $newOAuthCredentials['refresh_id'] = $data['refresh_token'];
                $newOAuthCredentials['auth_expires'] = time() + $data['expires_in'];
                $mergedCredentials = array_merge($credentials, $newOAuthCredentials); 
                
                $integration->update(['credentials' => $mergedCredentials]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to refresh Bitrix24 token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Получение ID коннектора для бота
     */
    protected function getConnectorId(Bot $bot): string
    {
        return 'chatbot_' . $bot->organization_id . '_' . $bot->id;
    }
    
    /**
     * Получение иконки бота
     */
    protected function getBotIcon(Bot $bot): string
    {
        if ($bot->avatar_url) {
            // Конвертируем URL в data URL если возможно
            try {
                $imageData = file_get_contents($bot->avatar_url);
                $mimeType = mime_content_type($bot->avatar_url) ?: 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            } catch (\Exception $e) {
                // Используем иконку по умолчанию
            }
        }
        
        // SVG иконка по умолчанию
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg viewBox="0 0 70 71" xmlns="http://www.w3.org/2000/svg">
                <path fill="#6366F1" d="M35 10c-13.8 0-25 9.2-25 20.5 0 6.5 3.7 12.3 9.5 16.1l-2.4 7.4c-.2.7.5 1.3 1.2.9l8.2-4.1c2.4.5 5 .7 7.5.7 13.8 0 25-9.2 25-20.5S48.8 10 35 10zm-10 25c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3zm10 0c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3zm10 0c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3z"/>
            </svg>
        ');
    }

    public function handleOpenlineWebhook(array $data): void
    {
        try {
            $event = $data['event'] ?? null;
            
            Log::info('Processing openline webhook', [
                'event' => $event,
                'has_messages' => isset($data['data']['MESSAGES'])
            ]);
            
            // Обработка сообщений от оператора
            if ($event === 'ONIMCONNECTORMESSAGEADD' || isset($data['data']['MESSAGES'])) {
                $this->processOperatorMessages($data['data'] ?? []);
            }
            
        } catch (\Exception $e) {
            Log::error('Openline webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    protected function processOperatorMessages(array $data): void
    {
        $messages = $data['MESSAGES'] ?? [];
        
        foreach ($messages as $messageData) {
            try {
                // Извлекаем ID чата
                $chatId = $messageData['chat']['id'] ?? null;
                
                if (!$chatId) {
                    continue;
                }
                
                // Ищем диалог по chat_id
                $conversation = Conversation::where('metadata->bitrix24_chat_id', $chatId)
                    ->orWhere('metadata->bitrix24_chat_id', 'chat' . $chatId)
                    ->first();
                    
                if (!$conversation) {
                    Log::warning('Conversation not found for chat_id', ['chat_id' => $chatId]);
                    continue;
                }
                
                // Проверяем, что это сообщение от оператора
                $authorType = $messageData['user']['type'] ?? 'USER';
                
                if ($authorType !== 'OPERATOR') {
                    continue;
                }
                
                // Создаем сообщение от оператора в нашей системе
                $message = $conversation->messages()->create([
                    'role' => 'operator',
                    'content' => $messageData['message']['text'] ?? '',
                    'metadata' => [
                        'from_bitrix24' => true,
                        'bitrix24_message_id' => $messageData['message']['id'] ?? null,
                        'operator_name' => $messageData['user']['name'] ?? 'Оператор',
                    ]
                ]);
                
                Log::info('Operator message created from webhook', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to process operator message', [
                    'error' => $e->getMessage(),
                    'message_data' => $messageData
                ]);
            }
        }
    }

    public function checkRegisteredEvents(CrmIntegration $integration): array
    {
        try {
            $result = $this->makeRequest($integration, 'event.get');
            
            Log::info('Registered events check', [
                'integration_id' => $integration->id,
                'events' => $result['result'] ?? []
            ]);
            
            return $result['result'] ?? [];
            
        } catch (\Exception $e) {
            Log::error('Failed to get registered events', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}