<?php

namespace App\Services\CRM\Providers;

use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Services\CRM\CrmProviderInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AvitoProvider implements CrmProviderInterface
{
    protected Client $client;
    protected CrmIntegration $integration;
    protected string $clientId;
    protected string $clientSecret;
    protected ?string $accessToken = null;
    protected array $config;

    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->config = $integration->credentials ?? [];
        
        $this->clientId = $this->config['client_id'] ?? '';
        $this->clientSecret = $this->config['client_secret'] ?? '';
        $this->accessToken = $this->config['access_token'] ?? null;
        
        $this->client = new Client([
            'base_uri' => 'https://api.avito.ru/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
        
        // Обновляем токен если нужно
        if (!$this->accessToken || $this->isTokenExpired()) {
            $this->refreshAccessToken();
        }

    /**
     * Обработка webhook от CRM
     */
    public function handleWebhook(array $data): void
    {
        try {
            $type = $data['type'] ?? '';
            
            Log::info('Avito webhook received', [
                'type' => $type,
                'data' => $data,
            ]);

            switch ($type) {
                case 'message':
                    $this->handleIncomingMessage($data);
                    break;
                    
                case 'chat_opened':
                    $this->handleChatOpened($data);
                    break;
                    
                case 'chat_closed':
                    $this->handleChatClosed($data);
                    break;
                    
                case 'item_view':
                    $this->handleItemView($data);
                    break;
                    
                case 'item_phone_call':
                    $this->handlePhoneCall($data);
                    break;
                    
                default:
                    Log::info('Unhandled Avito webhook type', ['type' => $type]);
            }

            // Логируем webhook
            $this->integration->logSync(
                'incoming',
                $type,
                'webhook',
                $data,
                [],
                'success'
            );
        } catch (\Exception $e) {
            Log::error('Avito webhook handling failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $this->integration->logSync(
                'incoming',
                $data['type'] ?? 'unknown',
                'webhook',
                $data,
                [],
                'error',
                $e->getMessage()
            );
        }
    }

    /**
     * Получение настроек полей CRM
     */
    public function getFields(string $entityType): array
    {
        // В Avito нет кастомных полей
        return [];
    }

    /**
     * Массовая синхронизация
     */
    public function bulkSync(array $entities): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($entities as $entity) {
            try {
                if ($entity['type'] === 'message' && !empty($entity['chat_id'])) {
                    $this->sendMessage($entity['chat_id'], $entity['message']);
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'entity' => $entity,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    // ===================== СПЕЦИФИЧНЫЕ МЕТОДЫ AVITO =====================

    /**
     * Получение списка чатов
     */
    public function getChats(array $params = []): array
    {
        try {
            $query = [
                'limit' => $params['limit'] ?? 50,
                'offset' => $params['offset'] ?? 0,
            ];
            
            if (!empty($params['item_id'])) {
                $query['item_id'] = $params['item_id'];
            }
            
            if (!empty($params['unread_only'])) {
                $query['unread_only'] = 'true';
            }
            
            $response = $this->makeRequest('GET', 'messenger/v2/accounts/self/chats', $query);
            
            return $response['chats'] ?? [];
        } catch (\Exception $e) {
            Log::error('Avito get chats failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получение сообщений чата
     */
    public function getChatMessages(string $chatId, array $params = []): array
    {
        try {
            $query = [
                'limit' => $params['limit'] ?? 50,
                'offset' => $params['offset'] ?? 0,
            ];
            
            $response = $this->makeRequest(
                'GET', 
                "messenger/v2/accounts/self/chats/{$chatId}/messages",
                $query
            );
            
            return $response['messages'] ?? [];
        } catch (\Exception $e) {
            Log::error('Avito get chat messages failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Отправка сообщения в чат
     */
    public function sendMessage(string $chatId, string $message, array $params = []): bool
    {
        try {
            $data = [
                'message' => [
                    'text' => $message,
                ],
                'type' => $params['type'] ?? 'text',
            ];
            
            // Добавляем быстрые ответы если есть
            if (!empty($params['quick_replies'])) {
                $data['quick_replies'] = $params['quick_replies'];
            }
            
            // Добавляем вложения если есть
            if (!empty($params['attachments'])) {
                $data['attachments'] = $params['attachments'];
            }
            
            $response = $this->makeRequest(
                'POST',
                "messenger/v2/accounts/self/chats/{$chatId}/messages",
                $data
            );
            
            return !empty($response['id']);
        } catch (\Exception $e) {
            Log::error('Avito send message failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Отметка сообщений как прочитанных
     */
    public function markAsRead(string $chatId): bool
    {
        try {
            $this->makeRequest(
                'POST',
                "messenger/v2/accounts/self/chats/{$chatId}/read"
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Avito mark as read failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Блокировка чата/пользователя
     */
    public function blockChat(string $chatId, string $reason = ''): bool
    {
        try {
            $this->makeRequest(
                'POST',
                "messenger/v2/accounts/self/chats/{$chatId}/block",
                ['reason' => $reason]
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Avito block chat failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получение информации об объявлении
     */
    public function getItem(string $itemId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "core/v1/items/{$itemId}");
            return $response;
        } catch (\Exception $e) {
            Log::error('Avito get item failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получение статистики по объявлениям
     */
    public function getItemsStats(array $itemIds): array
    {
        try {
            $response = $this->makeRequest('POST', 'stats/v1/accounts/self/items', [
                'itemIds' => $itemIds,
                'fields' => ['views', 'contacts', 'favorites'],
            ]);
            
            return $response['items'] ?? [];
        } catch (\Exception $e) {
            Log::error('Avito get items stats failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ===================== PRIVATE МЕТОДЫ =====================

    /**
     * Выполнение запроса к API
     */
    protected function makeRequest(string $method, string $endpoint, $data = null): array
    {
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
            ];
            
            if ($method === 'GET' && $data) {
                $options['query'] = $data;
            } elseif ($data !== null) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $result = json_decode($response->getBody()->getContents(), true);

            return $result ?? [];
        } catch (\Exception $e) {
            // Если токен истек, пробуем обновить
            if (strpos($e->getMessage(), '401') !== false) {
                $this->refreshAccessToken();
                return $this->makeRequest($method, $endpoint, $data);
            }
            
            Log::error('Avito API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
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
            $response = $this->client->post('token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                
                // Сохраняем токен и время истечения
                $this->integration->update([
                    'credentials' => array_merge($this->config, [
                        'access_token' => $this->accessToken,
                        'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Avito token refresh failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Проверка истечения токена
     */
    protected function isTokenExpired(): bool
    {
        if (empty($this->config['token_expires_at'])) {
            return true;
        }
        
        return now()->isAfter($this->config['token_expires_at']);
    }

    /**
     * Обработка входящего сообщения
     */
    protected function handleIncomingMessage(array $data): void
    {
        try {
            $chatId = $data['chat_id'] ?? null;
            $message = $data['message'] ?? [];
            
            if (!$chatId) return;
            
            // Проверяем, есть ли у нас привязка к диалогу
            $syncEntity = $this->integration->getSyncEntity('lead', $chatId);
            
            if (!$syncEntity) {
                // Создаем новый диалог
                $this->createConversationFromAvitoChat($chatId, $data);
                return;
            }
            
            $conversation = Conversation::find($syncEntity->local_id);
            if (!$conversation) return;
            
            // Сохраняем сообщение
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $message['text'] ?? '',
                'metadata' => [
                    'avito_message_id' => $message['id'] ?? null,
                    'avito_user_id' => $data['user_id'] ?? null,
                ],
            ]);
            
            // Обновляем счетчики
            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);
            
            // Генерируем ответ если бот активен
            if ($conversation->bot->is_active && $conversation->status === 'active') {
                dispatch(function () use ($conversation, $message, $chatId) {
                    $aiService = app(\App\Services\AIService::class);
                    $response = $aiService->generateResponse(
                        $conversation->bot,
                        $conversation,
                        $message['text'] ?? ''
                    );
                    
                    // Отправляем ответ в Avito
                    $this->sendMessage($chatId, $response);
                })->afterResponse();
            }
            
            Log::info('Avito message processed', [
                'chat_id' => $chatId,
                'conversation_id' => $conversation->id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle Avito message', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработка открытия чата
     */
    protected function handleChatOpened(array $data): void
    {
        $chatId = $data['chat_id'] ?? null;
        
        Log::info('Avito chat opened', [
            'chat_id' => $chatId,
            'item_id' => $data['item_id'] ?? null,
        ]);
        
        // Отправляем приветствие если настроено
        if ($chatId && !empty($this->config['welcome_message'])) {
            $this->sendMessage($chatId, $this->config['welcome_message']);
        }
    }

    /**
     * Обработка закрытия чата
     */
    protected function handleChatClosed(array $data): void
    {
        $chatId = $data['chat_id'] ?? null;
        
        if (!$chatId) return;
        
        // Находим связанный диалог
        $syncEntity = $this->integration->getSyncEntity('lead', $chatId);
        if ($syncEntity) {
            $conversation = Conversation::find($syncEntity->local_id);
            if ($conversation && $conversation->isActive()) {
                $conversation->close();
            }
        }
        
        Log::info('Avito chat closed', ['chat_id' => $chatId]);
    }

    /**
     * Обработка просмотра объявления
     */
    protected function handleItemView(array $data): void
    {
        Log::info('Avito item viewed', [
            'item_id' => $data['item_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ]);
    }

    /**
     * Обработка звонка по объявлению
     */
    protected function handlePhoneCall(array $data): void
    {
        Log::info('Avito phone call', [
            'item_id' => $data['item_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'duration' => $data['duration'] ?? null,
        ]);
    }

    /**
     * Создание диалога из чата Avito
     */
    protected function createConversationFromAvitoChat(string $chatId, array $data): void
    {
        try {
            // Получаем полную информацию о чате
            $chat = $this->getEntity('chat', $chatId);
            if (!$chat) return;
            
            // Находим подходящего бота для Avito
            $bot = $this->integration->bots()
                ->where('is_active', true)
                ->first();
                
            if (!$bot) {
                Log::warning('No active bot found for Avito integration');
                return;
            }
            
            // Находим или создаем канал Avito для бота
            $channel = $bot->channels()
                ->where('type', 'avito')
                ->first();
                
            if (!$channel) {
                $channel = $bot->channels()->create([
                    'type' => 'avito',
                    'name' => 'Avito',
                    'is_active' => true,
                ]);
            }
            
            // Создаем диалог
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel_id' => $channel->id,
                'external_id' => $chatId,
                'status' => 'active',
                'user_name' => $chat['user']['name'] ?? 'Покупатель Avito',
                'user_data' => [
                    'avito_user_id' => $chat['user']['id'] ?? null,
                    'item_id' => $chat['context']['value']['id'] ?? null,
                    'item_title' => $chat['context']['value']['title'] ?? null,
                ],
                'metadata' => [
                    'avito_chat_id' => $chatId,
                    'created_from_webhook' => true,
                ],
            ]);
            
            // Создаем связь
            $this->integration->createSyncEntity(
                'lead',
                $conversation->id,
                $chatId,
                $chat
            );
            
            Log::info('Conversation created from Avito chat', [
                'conversation_id' => $conversation->id,
                'chat_id' => $chatId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create conversation from Avito chat', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Проверка соединения с CRM
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', 'core/v1/accounts/self');
            return !empty($response['id']);
        } catch (\Exception $e) {
            Log::error('Avito connection test failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Создание или обновление контакта
     */
    public function syncContact(array $contactData): array
    {
        // Avito не имеет отдельной сущности контактов
        // Контакты привязаны к чатам/диалогам
        return [
            'id' => md5($contactData['email'] ?? $contactData['phone'] ?? Str::random()),
            'synced' => true,
        ];
    }

    /**
     * Создание лида (в Avito это будет чат/диалог)
     */
    public function createLead(Conversation $conversation, array $additionalData = []): array
    {
        try {
            // В Avito лиды - это чаты с клиентами по объявлениям
            // Мы можем только отправлять сообщения в существующие чаты
            
            $chatId = $additionalData['chat_id'] ?? null;
            
            if (!$chatId) {
                // Если нет chat_id, создаем внутреннюю запись
                Log::warning('Avito: Cannot create lead without chat_id');
                return ['error' => 'Chat ID required'];
            }
            
            // Отправляем приветственное сообщение в чат
            $message = $additionalData['message'] ?? 
                "Здравствуйте! Спасибо за обращение. Мы получили ваш запрос и скоро ответим.";
            
            $this->sendMessage($chatId, $message);
            
            // Сохраняем связь
            $this->integration->createSyncEntity(
                'lead',
                $conversation->id,
                $chatId,
                ['chat_id' => $chatId]
            );
            
            $conversation->update([
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'avito_chat_id' => $chatId,
                ])
            ]);
            
            return ['chat_id' => $chatId];
            
        } catch (\Exception $e) {
            Log::error('Avito create lead failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Обновление лида
     */
    public function updateLead(string $leadId, array $data): array
    {
        // В Avito нельзя обновлять чаты
        return ['chat_id' => $leadId];
    }

    /**
     * Создание сделки
     */
    public function createDeal(Conversation $conversation, array $additionalData = []): array
    {
        // В Avito нет отдельной сущности сделок
        return $this->createLead($conversation, $additionalData);
    }

    /**
     * Обновление сделки
     */
    public function updateDeal(string $dealId, array $data): array
    {
        // В Avito нет сделок
        return ['id' => $dealId];
    }

    /**
     * Добавление примечания (отправка сообщения в чат)
     */
    public function addNote(string $entityType, string $entityId, string $note): bool
    {
        try {
            return $this->sendMessage($entityId, $note);
        } catch (\Exception $e) {
            Log::error('Avito add note failed', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получение списка пользователей (менеджеров аккаунта)
     */
    public function getUsers(): array
    {
        try {
            $response = $this->makeRequest('GET', 'core/v1/accounts/self');
            
            // В Avito API нет отдельного метода для получения пользователей
            // Возвращаем информацию об аккаунте
            return [[
                'id' => $response['id'] ?? 0,
                'name' => $response['name'] ?? 'Avito Account',
                'email' => $response['email'] ?? '',
            ]];
        } catch (\Exception $e) {
            Log::error('Avito get users failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка воронок (категорий объявлений)
     */
    public function getPipelines(): array
    {
        try {
            // Получаем категории объявлений
            $response = $this->makeRequest('GET', 'autoload/v1/categories');
            
            return array_map(function($category) {
                return [
                    'id' => $category['id'],
                    'name' => $category['name'],
                ];
            }, $response['categories'] ?? []);
        } catch (\Exception $e) {
            Log::error('Avito get pipelines failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка этапов воронки
     */
    public function getPipelineStages(string $pipelineId): array
    {
        // В Avito нет этапов воронки, возвращаем статусы чатов
        return [
            ['id' => 'active', 'name' => 'Активный'],
            ['id' => 'closed', 'name' => 'Закрытый'],
            ['id' => 'blocked', 'name' => 'Заблокирован'],
        ];
    }

    /**
     * Получение информации о сущности
     */
    public function getEntity(string $entityType, string $entityId): ?array
    {
        try {
            if ($entityType === 'chat' || $entityType === 'lead') {
                // Получаем информацию о чате
                $response = $this->makeRequest('GET', "messenger/v2/accounts/self/chats/{$entityId}");
                return $response;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Avito get entity failed', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Поиск контакта
     */
    public function findContact(string $email = null, string $phone = null): ?array
    {
        // В Avito нельзя искать контакты напрямую
        return null;
    }

    /**
     * Синхронизация диалога с CRM
     */
    public function syncConversation(Conversation $conversation): bool
    {
        try {
            // В Avito синхронизация происходит через webhook
            // Мы можем только отправлять сообщения в существующие чаты
            
            $chatId = $conversation->metadata['avito_chat_id'] ?? null;
            
            if (!$chatId) {
                Log::warning('Cannot sync conversation without Avito chat_id', [
                    'conversation_id' => $conversation->id
                ]);
                return false;
            }
            
            // Синхронизируем последние сообщения
            $lastSyncedMessageId = $conversation->metadata['last_synced_message_id'] ?? 0;
            
            $messages = $conversation->messages()
                ->where('id', '>', $lastSyncedMessageId)
                ->where('role', 'assistant')
                ->orderBy('created_at', 'asc')
                ->get();
            
            foreach ($messages as $message) {
                if ($this->sendMessage($chatId, $message->content)) {
                    $conversation->update([
                        'metadata' => array_merge($conversation->metadata ?? [], [
                            'last_synced_message_id' => $message->id,
                            'last_sync_at' => now()->toIso8601String(),
                        ])
                    ]);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Avito sync conversation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}