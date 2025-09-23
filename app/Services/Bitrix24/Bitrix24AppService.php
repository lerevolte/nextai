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
                'ONAPPUNINSTALL' => url('/bitrix24/event-handler'),
            ];
            
            foreach ($events as $event => $handler) {
                $this->makeRequest($integration, 'event.bind', [
                    'event' => $event,
                    'handler' => $handler,
                    'auth_type' => 0,
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
            
            // Проверяем, не зарегистрирован ли уже бот
            if ($bot->metadata['bitrix24_bot_id'] ?? false) {
                return [
                    'success' => true,
                    'bot_id' => $bot->metadata['bitrix24_bot_id'],
                    'message' => 'Bot already registered'
                ];
            }
            
            // Регистрируем бота с обязательными обработчиками
            $result = $this->makeRequest($integration, 'imbot.register', [
                'CODE' => $botCode,
                'TYPE' => 'B',
                'EVENT_MESSAGE_ADD' => url('/bitrix24/bot-handler'),
                'EVENT_WELCOME_MESSAGE' => url('/bitrix24/bot-welcome'), // Обязательное поле!
                'EVENT_BOT_DELETE' => url('/bitrix24/bot-delete'),
                'PROPERTIES' => [
                    'NAME' => $bot->name,
                    'COLOR' => 'AQUA',
                ]
            ]);

            if (!empty($result['result'])) {
                // Сохраняем ID бота
                $bot->update([
                    'metadata' => array_merge($bot->metadata ?? [], [
                        'bitrix24_bot_id' => $result['result'],
                        'bitrix24_bot_registered_at' => now()->toIso8601String(),
                    ])
                ]);

                Log::info('Chatbot registered in Bitrix24', [
                    'bot_id' => $bot->id,
                    'bitrix24_bot_id' => $result['result']
                ]);

                return [
                    'success' => true,
                    'bot_id' => $result['result'],
                    'message' => 'Чат-бот успешно зарегистрирован'
                ];
            }

            throw new \Exception('Failed to register bot: ' . json_encode($result));

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
            }

            $connectorId = $this->getConnectorId($bot);
            $bitrix24BotId = $bot->metadata['bitrix24_bot_id'];
            
            // Регистрируем коннектор с привязкой к боту
            $result = $this->makeRequest($integration, 'imconnector.register', [
                'ID' => $connectorId,
                'NAME' => $bot->name,
                'BOT_ID' => $bitrix24BotId, // Привязываем к зарегистрированному боту
                'ICON' => [
                    'DATA_IMAGE' => $this->getBotIcon($bot),
                    'COLOR' => '#6366F1',
                ],
                'PLACEMENT_HANDLER' => url('/bitrix24/activate-connector'),
            ]);
            
            if (empty($result['result'])) {
                throw new \Exception('Failed to register connector');
            }

            // Обновляем метаданные бота
            $bot->update([
                'metadata' => array_merge($bot->metadata ?? [], [
                    'bitrix24_connector_registered' => true,
                    'bitrix24_connector_id' => $connectorId,
                    'bitrix24_connector_registered_at' => now()->toIso8601String(),
                ])
            ]);
            
            Cache::flush();

            return [
                'success' => true,
                'connector_id' => $connectorId,
                'bot_id' => $bitrix24BotId,
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
            
            // Активируем коннектор
            $result = $this->makeRequest($integration, 'imconnector.activate', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'ACTIVE' => $active ? 1 : 0,
            ]);
            
            if (empty($result['result'])) {
                throw new \Exception('Failed to activate connector');
            }
            
            // Передаем данные виджета
            if ($active) {
                $this->makeRequest($integration, 'imconnector.connector.data.set', [
                    'CONNECTOR' => $connectorId,
                    'LINE' => $lineId,
                    'DATA' => [
                        'id' => $connectorId . '_line_' . $lineId,
                        'url_im' => route('widget.show', $bot->slug),
                        'name' => $bot->name . ' Widget',
                    ],
                ]);
            }
            
            // Обновляем настройки
            $botIntegration = $integration->bots()->where('bot_id', $bot->id)->first();
            $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
            if ($botIntegration) {
                $integration->bots()->updateExistingPivot($bot->id, [
                    'connector_settings' => array_merge($connectorSettings, [
                        'line_id' => $lineId,
                        'active' => $active,
                        'activated_at' => now()->toIso8601String(),
                    ])
                ]);
            }
            
            Log::info('Connector activated', [
                'bot_id' => $bot->id,
                'line_id' => $lineId,
                'active' => $active,
            ]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Failed to activate connector', [
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
            
            Log::info('Connector messages received', [
                'messages_count' => count($messages),
                'data' => $data
            ]);
            
            foreach ($messages as $messageData) {
                $chatId = str_replace('chat_', '', $messageData['chat']['id'] ?? '');
                $conversation = Conversation::find($chatId);
                
                if (!$conversation) {
                    Log::warning('Conversation not found for Bitrix24 message', [
                        'chat_id' => $chatId,
                        'full_chat_id' => $messageData['chat']['id'] ?? null,
                    ]);
                    continue;
                }

                // ИСПРАВЛЕНИЕ: Проверяем тип автора сообщения
                $authorType = $messageData['user']['type'] ?? 'USER';
                $authorId = $messageData['user']['id'] ?? null;
                
                Log::info('Processing message', [
                    'author_type' => $authorType,
                    'author_id' => $authorId,
                    'message' => $messageData['message']['text'] ?? ''
                ]);

                // Пропускаем сообщения от нашего бота
                if ($authorType === 'BOT') {
                    Log::info('Skipping bot message');
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

                // Определяем роль автора
                $role = match($authorType) {
                    'OPERATOR' => 'operator',
                    'USER', 'GUEST' => 'user',
                    default => 'system'
                };

                // Сохраняем сообщение
                $newMessage = $conversation->messages()->create([
                    'role' => $role,
                    'content' => $messageData['message']['text'] ?? '',
                    'metadata' => [
                        'from_bitrix24' => true,
                        'bitrix24_message_id' => $bitrix24MessageId,
                        'bitrix24_user_id' => $authorId,
                        'author_type' => $authorType,
                        'author_name' => $messageData['user']['name'] ?? null,
                    ]
                ]);

                // Обновляем статус диалога
                if ($role === 'operator' && $conversation->status === 'active') {
                    $conversation->update(['status' => 'waiting_operator']);
                }

                // Обновляем счетчики
                $conversation->increment('messages_count');
                $conversation->update(['last_message_at' => now()]);

                Log::info('Message from Bitrix24 processed', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $newMessage->id,
                    'role' => $role,
                    'author_type' => $authorType
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle connector message from Bitrix24', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }
    
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
}