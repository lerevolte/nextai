<?php

namespace App\Services\CRM\Providers;

use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Services\CRM\CrmProviderInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalebotProvider implements CrmProviderInterface
{
    protected Client $client;
    protected CrmIntegration $integration;
    protected string $apiKey;
    protected ?string $botId;
    protected array $config;

    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->config = $integration->credentials ?? [];
        
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->botId = $this->config['bot_id'] ?? null;
        
        $this->client = new Client([
            'base_uri' => 'https://salebot.pro/api/',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Проверка соединения с CRM
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', 'bots', [
                'api_key' => $this->apiKey,
            ]);
            
            // Проверяем, есть ли наш бот в списке
            if ($this->botId) {
                foreach ($response['bots'] ?? [] as $bot) {
                    if ($bot['id'] == $this->botId) {
                        return true;
                    }
                }
            }
            
            return !empty($response['bots']);
        } catch (\Exception $e) {
            Log::error('Salebot connection test failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Создание или обновление контакта (клиента в Salebot)
     */
    public function syncContact(array $contactData): array
    {
        try {
            // В Salebot клиенты создаются автоматически при первом сообщении
            // Но мы можем обновить информацию о клиенте
            $clientData = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $contactData['external_id'] ?? null,
                'variables' => [],
            ];

            if (!empty($contactData['name'])) {
                $clientData['variables']['name'] = $contactData['name'];
            }
            
            if (!empty($contactData['email'])) {
                $clientData['variables']['email'] = $contactData['email'];
            }
            
            if (!empty($contactData['phone'])) {
                $clientData['variables']['phone'] = $contactData['phone'];
            }

            // Если у нас есть client_id, обновляем
            if (!empty($clientData['client_id'])) {
                $response = $this->makeRequest('POST', 'clients/update_variables', $clientData);
            } else {
                // Иначе просто возвращаем успех, т.к. клиент создастся при первом сообщении
                return ['synced' => true];
            }

            return [
                'id' => $response['client_id'] ?? $contactData['external_id'] ?? null,
                'synced' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Salebot sync contact failed', [
                'error' => $e->getMessage(),
                'data' => $contactData,
            ]);
            throw $e;
        }
    }

    /**
     * Создание лида (запуск воронки в Salebot)
     */
    public function createLead(Conversation $conversation, array $additionalData = []): array
    {
        try {
            // Определяем канал
            $platform = $this->getPlatformFromChannel($conversation->channel->type);
            
            // Подготавливаем данные клиента
            $clientData = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'platform' => $platform,
                'client_id' => $conversation->external_id ?? Str::uuid()->toString(),
            ];

            // Добавляем переменные клиента
            $variables = [];
            
            if ($conversation->user_name) {
                $variables['name'] = $conversation->user_name;
            }
            
            if ($conversation->user_email) {
                $variables['email'] = $conversation->user_email;
            }
            
            if ($conversation->user_phone) {
                $variables['phone'] = $conversation->user_phone;
                $variables['phone_number'] = preg_replace('/[^0-9]/', '', $conversation->user_phone);
            }

            // Добавляем информацию о канале и боте
            $variables['source'] = $conversation->channel->name;
            $variables['bot_name'] = $conversation->bot->name;
            $variables['conversation_id'] = $conversation->id;
            
            // Добавляем кастомные переменные
            if (!empty($additionalData['variables'])) {
                $variables = array_merge($variables, $additionalData['variables']);
            }

            $clientData['variables'] = $variables;

            // Создаем или обновляем клиента
            $response = $this->makeRequest('POST', 'clients/save', $clientData);
            
            $clientId = $response['client_id'] ?? $clientData['client_id'];

            // Если указана воронка для запуска
            if (!empty($additionalData['funnel_id'])) {
                $this->startFunnel($clientId, $additionalData['funnel_id'], $additionalData['block_id'] ?? null);
            }

            // Если есть сообщение для отправки
            if (!empty($additionalData['message'])) {
                $this->sendMessage($clientId, $additionalData['message']);
            }

            // Сохраняем связь
            $this->integration->createSyncEntity(
                'lead',
                $conversation->id,
                $clientId,
                ['platform' => $platform, 'bot_id' => $this->botId]
            );

            // Обновляем диалог
            $conversation->update([
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'salebot_client_id' => $clientId,
                    'salebot_platform' => $platform,
                ])
            ]);

            return [
                'client_id' => $clientId,
                'platform' => $platform,
                'success' => true,
            ];
            
        } catch (\Exception $e) {
            Log::error('Salebot create lead failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Обновление лида (обновление переменных клиента)
     */
    public function updateLead(string $leadId, array $data): array
    {
        try {
            $response = $this->makeRequest('POST', 'clients/update_variables', [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $leadId,
                'variables' => $data,
            ]);

            return ['client_id' => $leadId, 'updated' => true];
        } catch (\Exception $e) {
            Log::error('Salebot update lead failed', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Создание сделки (в Salebot это тоже воронка)
     */
    public function createDeal(Conversation $conversation, array $additionalData = []): array
    {
        // В Salebot нет отдельных сделок, используем воронки
        return $this->createLead($conversation, $additionalData);
    }

    /**
     * Обновление сделки
     */
    public function updateDeal(string $dealId, array $data): array
    {
        return $this->updateLead($dealId, $data);
    }

    /**
     * Добавление примечания (отправка сообщения клиенту)
     */
    public function addNote(string $entityType, string $entityId, string $note): bool
    {
        try {
            return $this->sendMessage($entityId, $note);
        } catch (\Exception $e) {
            Log::error('Salebot add note failed', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получение списка пользователей (операторов бота)
     */
    public function getUsers(): array
    {
        try {
            $response = $this->makeRequest('GET', 'bot/operators', [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
            ]);

            $users = [];
            foreach ($response['operators'] ?? [] as $operator) {
                $users[] = [
                    'id' => $operator['id'],
                    'name' => $operator['name'] ?? 'Оператор',
                    'email' => $operator['email'] ?? null,
                ];
            }

            return $users;
        } catch (\Exception $e) {
            Log::error('Salebot get users failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка воронок
     */
    public function getPipelines(): array
    {
        try {
            $response = $this->makeRequest('GET', 'funnels', [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
            ]);

            $pipelines = [];
            foreach ($response['funnels'] ?? [] as $funnel) {
                $pipelines[] = [
                    'id' => $funnel['id'],
                    'name' => $funnel['name'],
                    'description' => $funnel['description'] ?? null,
                    'is_active' => $funnel['is_active'] ?? true,
                ];
            }

            return $pipelines;
        } catch (\Exception $e) {
            Log::error('Salebot get pipelines failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение блоков воронки (этапов)
     */
    public function getPipelineStages(string $pipelineId): array
    {
        try {
            $response = $this->makeRequest('GET', 'funnel/blocks', [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'funnel_id' => $pipelineId,
            ]);

            $stages = [];
            foreach ($response['blocks'] ?? [] as $block) {
                $stages[] = [
                    'id' => $block['id'],
                    'name' => $block['name'] ?? $block['type'],
                    'type' => $block['type'],
                    'position' => $block['position'] ?? 0,
                ];
            }

            return $stages;
        } catch (\Exception $e) {
            Log::error('Salebot get pipeline stages failed', [
                'pipeline_id' => $pipelineId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получение информации о сущности
     */
    public function getEntity(string $entityType, string $entityId): ?array
    {
        try {
            if ($entityType === 'client' || $entityType === 'lead') {
                $response = $this->makeRequest('GET', 'client', [
                    'api_key' => $this->apiKey,
                    'bot_id' => $this->botId,
                    'client_id' => $entityId,
                ]);
                
                return $response['client'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Salebot get entity failed', [
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
        try {
            $filters = [];
            
            if ($email) {
                $filters['email'] = $email;
            }
            
            if ($phone) {
                $filters['phone'] = preg_replace('/[^0-9]/', '', $phone);
            }

            if (empty($filters)) {
                return null;
            }

            $response = $this->makeRequest('POST', 'clients/search', [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'filters' => $filters,
            ]);

            return $response['clients'][0] ?? null;
        } catch (\Exception $e) {
            Log::error('Salebot find contact failed', [
                'email' => $email,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Синхронизация диалога
     */
    public function syncConversation(Conversation $conversation): bool
    {
        try {
            // Получаем или создаем клиента в Salebot
            $clientId = $conversation->metadata['salebot_client_id'] ?? null;
            
            if (!$clientId) {
                // Создаем нового клиента
                $result = $this->createLead($conversation, [
                    'funnel_id' => $this->config['default_funnel_id'] ?? null,
                ]);
                $clientId = $result['client_id'] ?? null;
            }
            
            if (!$clientId) {
                return false;
            }

            // Синхронизируем сообщения
            $this->syncMessages($conversation, $clientId);

            // Обновляем переменные клиента
            $variables = [];
            
            if ($conversation->user_name) {
                $variables['name'] = $conversation->user_name;
            }
            
            if ($conversation->user_email) {
                $variables['email'] = $conversation->user_email;
            }
            
            if ($conversation->user_phone) {
                $variables['phone'] = $conversation->user_phone;
            }

            if (!empty($variables)) {
                $this->updateLead($clientId, $variables);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Salebot sync conversation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Обработка webhook от Salebot
     */
    public function handleWebhook(array $data): void
    {
        try {
            $event = $data['event'] ?? $data['type'] ?? null;
            
            Log::info('Salebot webhook received', [
                'event' => $event,
                'data' => $data,
            ]);

            switch ($event) {
                case 'message':
                case 'new_message':
                    $this->handleIncomingMessage($data);
                    break;
                    
                case 'funnel_started':
                    $this->handleFunnelStarted($data);
                    break;
                    
                case 'funnel_completed':
                    $this->handleFunnelCompleted($data);
                    break;
                    
                case 'variable_changed':
                    $this->handleVariableChanged($data);
                    break;
                    
                case 'operator_connected':
                    $this->handleOperatorConnected($data);
                    break;
                    
                default:
                    Log::info('Unhandled Salebot webhook event', ['event' => $event]);
            }

            // Логируем webhook
            $this->integration->logSync(
                'incoming',
                $event ?? 'unknown',
                'webhook',
                $data,
                [],
                'success'
            );
        } catch (\Exception $e) {
            Log::error('Salebot webhook handling failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $this->integration->logSync(
                'incoming',
                $data['event'] ?? 'unknown',
                'webhook',
                $data,
                [],
                'error',
                $e->getMessage()
            );
        }
    }

    /**
     * Получение настроек полей
     */
    public function getFields(string $entityType): array
    {
        try {
            if ($entityType === 'client' || $entityType === 'lead') {
                // Возвращаем стандартные переменные Salebot
                return [
                    ['code' => 'name', 'name' => 'Имя', 'type' => 'string'],
                    ['code' => 'email', 'name' => 'Email', 'type' => 'email'],
                    ['code' => 'phone', 'name' => 'Телефон', 'type' => 'phone'],
                    ['code' => 'phone_number', 'name' => 'Номер телефона', 'type' => 'string'],
                    ['code' => 'city', 'name' => 'Город', 'type' => 'string'],
                    ['code' => 'source', 'name' => 'Источник', 'type' => 'string'],
                ];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Salebot get fields failed', [
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
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
                switch ($entity['type']) {
                    case 'contact':
                    case 'client':
                        $this->syncContact($entity['data']);
                        $results['success']++;
                        break;
                        
                    case 'lead':
                        if (isset($entity['conversation'])) {
                            $this->createLead($entity['conversation'], $entity['data'] ?? []);
                            $results['success']++;
                        }
                        break;
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

    // ===================== СПЕЦИФИЧНЫЕ МЕТОДЫ SALEBOT =====================

    /**
     * Запуск воронки для клиента
     */
    public function startFunnel(string $clientId, string $funnelId, string $blockId = null): bool
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $clientId,
                'funnel_id' => $funnelId,
            ];

            if ($blockId) {
                $data['block_id'] = $blockId;
            }

            $response = $this->makeRequest('POST', 'funnel/start', $data);
            
            return !empty($response['success']);
        } catch (\Exception $e) {
            Log::error('Salebot start funnel failed', [
                'client_id' => $clientId,
                'funnel_id' => $funnelId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Остановка воронки для клиента
     */
    public function stopFunnel(string $clientId, string $funnelId = null): bool
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $clientId,
            ];

            if ($funnelId) {
                $data['funnel_id'] = $funnelId;
            }

            $response = $this->makeRequest('POST', 'funnel/stop', $data);
            
            return !empty($response['success']);
        } catch (\Exception $e) {
            Log::error('Salebot stop funnel failed', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Отправка сообщения клиенту
     */
    public function sendMessage(string $clientId, string $message, array $options = []): bool
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $clientId,
                'message' => $message,
            ];

            // Добавляем кнопки если есть
            if (!empty($options['buttons'])) {
                $data['keyboard'] = $this->formatKeyboard($options['buttons']);
            }

            // Добавляем вложения если есть
            if (!empty($options['attachments'])) {
                $data['attachments'] = $options['attachments'];
            }

            $response = $this->makeRequest('POST', 'message/send', $data);
            
            return !empty($response['message_id']);
        } catch (\Exception $e) {
            Log::error('Salebot send message failed', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получение истории сообщений клиента
     */
    public function getClientMessages(string $clientId, int $limit = 50): array
    {
        try {
            $response = $this->makeRequest('GET', 'messages', [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $clientId,
                'limit' => $limit,
            ]);

            return $response['messages'] ?? [];
        } catch (\Exception $e) {
            Log::error('Salebot get client messages failed', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Передача клиента оператору
     */
    public function transferToOperator(string $clientId, string $operatorId = null): bool
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'client_id' => $clientId,
            ];

            if ($operatorId) {
                $data['operator_id'] = $operatorId;
            }

            $response = $this->makeRequest('POST', 'operator/connect', $data);
            
            return !empty($response['success']);
        } catch (\Exception $e) {
            Log::error('Salebot transfer to operator failed', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ===================== PRIVATE МЕТОДЫ =====================

    /**
     * Выполнение запроса к API
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $url = $endpoint;
            
            if (!str_contains($url, '?')) {
                $url .= '?';
            } else {
                $url .= '&';
            }

            // Добавляем API ключ в URL для GET запросов
            if ($method === 'GET') {
                $url .= http_build_query($data);
                $response = $this->client->request($method, $url);
            } else {
                $response = $this->client->request($method, $endpoint, [
                    'json' => $data,
                ]);
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // Проверяем на ошибки
            if (!empty($result['error'])) {
                throw new \Exception($result['error_description'] ?? $result['error']);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Salebot API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Определение платформы по типу канала
     */
    protected function getPlatformFromChannel(string $channelType): string
    {
        return match($channelType) {
            'telegram' => 'telegram',
            'whatsapp' => 'whatsapp',
            'vk' => 'vk',
            'instagram' => 'instagram',
            'web' => 'widget',
            default => 'api',
        };
    }

    /**
     * Форматирование клавиатуры для сообщения
     */
    protected function formatKeyboard(array $buttons): array
    {
        $keyboard = [];
        
        foreach ($buttons as $button) {
            if (is_string($button)) {
                $keyboard[] = ['text' => $button];
            } else {
                $keyboard[] = $button;
            }
        }

        return ['buttons' => $keyboard];
    }

    /**
     * Синхронизация сообщений
     */
    protected function syncMessages(Conversation $conversation, string $clientId): void
    {
        try {
            $lastSyncedMessageId = $conversation->metadata['last_synced_message_id'] ?? 0;
            
            $messages = $conversation->messages()
                ->where('id', '>', $lastSyncedMessageId)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($messages as $message) {
                // Отправляем только сообщения от бота
                if ($message->role === 'assistant') {
                    $this->sendMessage($clientId, $message->content);
                    
                    $conversation->update([
                        'metadata' => array_merge($conversation->metadata ?? [], [
                            'last_synced_message_id' => $message->id,
                        ])
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Salebot sync messages failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработка входящего сообщения
     */
    protected function handleIncomingMessage(array $data): void
    {
        try {
            $clientId = $data['client_id'] ?? null;
            $message = $data['message'] ?? $data['text'] ?? '';
            
            if (!$clientId) return;

            // Находим связанный диалог
            $syncEntity = $this->integration->getSyncEntity('lead', $clientId);
            
            if (!$syncEntity) {
                // Создаем новый диалог
                $this->createConversationFromSalebot($clientId, $data);
                return;
            }

            $conversation = Conversation::find($syncEntity->local_id);
            if (!$conversation) return;

            // Сохраняем сообщение
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $message,
                'metadata' => [
                    'salebot_message_id' => $data['message_id'] ?? null,
                    'salebot_client_id' => $clientId,
                ],
            ]);

            // Обновляем счетчики
            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);

            Log::info('Salebot message processed', [
                'client_id' => $clientId,
                'conversation_id' => $conversation->id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle Salebot message', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработка запуска воронки
     */
    protected function handleFunnelStarted(array $data): void
    {
        Log::info('Salebot funnel started', [
            'client_id' => $data['client_id'] ?? null,
            'funnel_id' => $data['funnel_id'] ?? null,
        ]);
    }

    /**
     * Обработка завершения воронки
     */
    protected function handleFunnelCompleted(array $data): void
    {
        $clientId = $data['client_id'] ?? null;
        
        if (!$clientId) return;

        // Находим связанный диалог
        $syncEntity = $this->integration->getSyncEntity('lead', $clientId);
        if ($syncEntity) {
            $conversation = Conversation::find($syncEntity->local_id);
            if ($conversation && $conversation->isActive()) {
                $conversation->close();
                
                $conversation->messages()->create([
                    'role' => 'system',
                    'content' => 'Воронка Salebot завершена',
                ]);
            }
        }

        Log::info('Salebot funnel completed', [
            'client_id' => $clientId,
            'funnel_id' => $data['funnel_id'] ?? null,
        ]);
    }

    /**
     * Обработка изменения переменной
     */
    protected function handleVariableChanged(array $data): void
    {
        Log::info('Salebot variable changed', [
            'client_id' => $data['client_id'] ?? null,
            'variable' => $data['variable_name'] ?? null,
            'value' => $data['variable_value'] ?? null,
        ]);

        // Обновляем информацию в диалоге если нужно
        $clientId = $data['client_id'] ?? null;
        $variableName = $data['variable_name'] ?? null;
        $variableValue = $data['variable_value'] ?? null;

        if ($clientId && $variableName) {
            $syncEntity = $this->integration->getSyncEntity('lead', $clientId);
            if ($syncEntity) {
                $conversation = Conversation::find($syncEntity->local_id);
                if ($conversation) {
                    // Обновляем контактные данные
                    $updates = [];
                    
                    if ($variableName === 'name' && $variableValue) {
                        $updates['user_name'] = $variableValue;
                    } elseif ($variableName === 'email' && $variableValue) {
                        $updates['user_email'] = $variableValue;
                    } elseif (in_array($variableName, ['phone', 'phone_number']) && $variableValue) {
                        $updates['user_phone'] = $variableValue;
                    }

                    if (!empty($updates)) {
                        $conversation->update($updates);
                    }
                }
            }
        }
    }

    /**
     * Обработка подключения оператора
     */
    protected function handleOperatorConnected(array $data): void
    {
        $clientId = $data['client_id'] ?? null;
        $operatorName = $data['operator_name'] ?? 'Оператор';
        
        if (!$clientId) return;

        $syncEntity = $this->integration->getSyncEntity('lead', $clientId);
        if ($syncEntity) {
            $conversation = Conversation::find($syncEntity->local_id);
            if ($conversation) {
                $conversation->update([
                    'status' => 'waiting_operator',
                    'metadata' => array_merge($conversation->metadata ?? [], [
                        'salebot_operator_id' => $data['operator_id'] ?? null,
                        'salebot_operator_name' => $operatorName,
                        'operator_connected_at' => now()->toIso8601String(),
                    ])
                ]);

                $conversation->messages()->create([
                    'role' => 'system',
                    'content' => "Оператор {$operatorName} подключился к диалогу",
                ]);
            }
        }

        Log::info('Salebot operator connected', [
            'client_id' => $clientId,
            'operator_id' => $data['operator_id'] ?? null,
        ]);
    }

    /**
     * Создание диалога из Salebot клиента
     */
    protected function createConversationFromSalebot(string $clientId, array $data): void
    {
        try {
            // Получаем информацию о клиенте
            $client = $this->getEntity('client', $clientId);
            if (!$client) return;

            // Находим подходящего бота для Salebot
            $bot = $this->integration->bots()
                ->where('is_active', true)
                ->first();
                
            if (!$bot) {
                Log::warning('No active bot found for Salebot integration');
                return;
            }

            // Находим или создаем канал Salebot для бота
            $platform = $data['platform'] ?? 'api';
            $channel = $bot->channels()
                ->where('type', 'salebot')
                ->first();
                
            if (!$channel) {
                $channel = $bot->channels()->create([
                    'type' => 'salebot',
                    'name' => 'Salebot',
                    'is_active' => true,
                    'settings' => [
                        'platform' => $platform,
                    ],
                ]);
            }

            // Создаем диалог
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel_id' => $channel->id,
                'external_id' => $clientId,
                'status' => 'active',
                'user_name' => $client['variables']['name'] ?? null,
                'user_email' => $client['variables']['email'] ?? null,
                'user_phone' => $client['variables']['phone'] ?? null,
                'user_data' => [
                    'salebot_client_id' => $clientId,
                    'platform' => $platform,
                    'variables' => $client['variables'] ?? [],
                ],
                'metadata' => [
                    'salebot_client_id' => $clientId,
                    'salebot_platform' => $platform,
                    'created_from_webhook' => true,
                ],
            ]);

            // Создаем связь
            $this->integration->createSyncEntity(
                'lead',
                $conversation->id,
                $clientId,
                $client
            );

            Log::info('Conversation created from Salebot client', [
                'conversation_id' => $conversation->id,
                'client_id' => $clientId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create conversation from Salebot client', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получение статистики по воронкам
     */
    public function getFunnelStats(string $funnelId = null): array
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
            ];

            if ($funnelId) {
                $data['funnel_id'] = $funnelId;
            }

            $response = $this->makeRequest('GET', 'funnel/stats', $data);

            return $response['stats'] ?? [];
        } catch (\Exception $e) {
            Log::error('Salebot get funnel stats failed', [
                'funnel_id' => $funnelId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получение списка ботов
     */
    public function getBots(): array
    {
        try {
            $response = $this->makeRequest('GET', 'bots', [
                'api_key' => $this->apiKey,
            ]);

            return $response['bots'] ?? [];
        } catch (\Exception $e) {
            Log::error('Salebot get bots failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Создание переменной для бота
     */
    public function createVariable(string $name, string $type = 'string', $defaultValue = null): bool
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'name' => $name,
                'type' => $type,
            ];

            if ($defaultValue !== null) {
                $data['default_value'] = $defaultValue;
            }

            $response = $this->makeRequest('POST', 'variable/create', $data);

            return !empty($response['success']);
        } catch (\Exception $e) {
            Log::error('Salebot create variable failed', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Массовая отправка сообщений
     */
    public function broadcastMessage(string $message, array $filters = [], array $options = []): array
    {
        try {
            $data = [
                'api_key' => $this->apiKey,
                'bot_id' => $this->botId,
                'message' => $message,
            ];

            if (!empty($filters)) {
                $data['filters'] = $filters;
            }

            if (!empty($options['buttons'])) {
                $data['keyboard'] = $this->formatKeyboard($options['buttons']);
            }

            if (!empty($options['delay'])) {
                $data['delay'] = $options['delay'];
            }

            $response = $this->makeRequest('POST', 'broadcast/send', $data);

            return [
                'broadcast_id' => $response['broadcast_id'] ?? null,
                'recipients_count' => $response['recipients_count'] ?? 0,
                'success' => !empty($response['success']),
            ];
        } catch (\Exception $e) {
            Log::error('Salebot broadcast message failed', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}