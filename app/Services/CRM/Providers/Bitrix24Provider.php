<?php

namespace App\Services\CRM\Providers;

use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Services\CRM\CrmProviderInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Bitrix24Provider implements CrmProviderInterface
{
    protected Client $client;
    protected CrmIntegration $integration;
    protected ?string $webhookUrl = null;
    protected ?string $accessToken = null;
    protected ?string $refreshToken = null;
    protected array $config;
    protected ?string $oauthRestUrl = null;


    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'http_errors' => false, // Добавить это для лучшей обработки ошибок
        ]);
        
        $this->config = $integration->credentials ?? [];

        // Инициализация Webhook
        if (!empty($this->config['webhook_url'])) {
            $this->webhookUrl = rtrim($this->config['webhook_url'], '/') . '/';
            Log::info('Bitrix24Provider: Webhook URL configured', [
                'integration_id' => $integration->id,
                'url_prefix' => substr($this->webhookUrl, 0, 30) . '...'
            ]);
        }
        
        // Инициализация OAuth
        if (!empty($this->config['auth_id']) && !empty($this->config['domain'])) {
            $this->accessToken = $this->config['auth_id'];
            $this->refreshToken = $this->config['refresh_id'] ?? null;
            $this->oauthRestUrl = 'https://' . $this->config['domain'] . '/rest/';
            Log::info('Bitrix24Provider: OAuth configured', [
                'integration_id' => $integration->id,
                'domain' => $this->config['domain']
            ]);
        }
        
        // Проверяем, что хотя бы один метод настроен
        if (empty($this->webhookUrl) && empty($this->oauthRestUrl)) {
            Log::error('Bitrix24Provider: No authentication method configured', [
                'integration_id' => $integration->id,
                'config_keys' => array_keys($this->config)
            ]);
        }
    }

    /**
     * Проверка соединения с CRM
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('profile');
            return !empty($response['result']);
        } catch (\Exception $e) {
            Log::error('Bitrix24 connection test failed', [
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
        try {
            // Проверяем существующий контакт
            $existingContact = null;
            
            if (!empty($contactData['email'])) {
                $existingContact = $this->findContactByEmail($contactData['email']);
            }
            
            if (!$existingContact && !empty($contactData['phone'])) {
                $existingContact = $this->findContactByPhone($contactData['phone']);
            }

            if ($existingContact) {
                // Обновляем существующий контакт
                return $this->updateContact($existingContact['ID'], $contactData);
            } else {
                // Создаем новый контакт
                return $this->createContact($contactData);
            }
        } catch (\Exception $e) {
            Log::error('Bitrix24 sync contact failed', [
                'error' => $e->getMessage(),
                'data' => $contactData,
            ]);
            throw $e;
        }
    }

    /**
     * Создание лида
     */
    public function createLead(Conversation $conversation, array $additionalData = []): array
    {
        try {
            $leadData = [
                'TITLE' => 'Обращение из чат-бота #' . $conversation->id,
                'SOURCE_ID' => $additionalData['source_id'] ?? 'CHATBOT',
                'SOURCE_DESCRIPTION' => 'Канал: ' . $conversation->channel->getTypeName(),
                'STATUS_ID' => $additionalData['status_id'] ?? 'NEW',
                'OPENED' => 'Y',
                'COMMENTS' => $this->formatConversationForCRM($conversation),
            ];

            // Добавляем контактную информацию
            if ($conversation->user_name) {
                $leadData['NAME'] = $conversation->user_name;
            }
            
            if ($conversation->user_email) {
                $leadData['EMAIL'] = [
                    ['VALUE' => $conversation->user_email, 'VALUE_TYPE' => 'WORK']
                ];
            }
            
            if ($conversation->user_phone) {
                $leadData['PHONE'] = [
                    ['VALUE' => $conversation->user_phone, 'VALUE_TYPE' => 'WORK']
                ];
            }

            // Добавляем ответственного
            if (!empty($additionalData['responsible_user_id'])) {
                $leadData['ASSIGNED_BY_ID'] = $additionalData['responsible_user_id'];
            }

            // Дополнительные поля
            if (!empty($additionalData['custom_fields'])) {
                $leadData = array_merge($leadData, $additionalData['custom_fields']);
            }

            $response = $this->makeRequest('crm.lead.add', [
                'fields' => $leadData,
            ]);

            if (!empty($response['result'])) {
                // Сохраняем связь в БД
                $this->integration->createSyncEntity(
                    'lead',
                    $conversation->id,
                    $response['result'],
                    $leadData
                );

                // Обновляем диалог
                $conversation->update([
                    'crm_lead_id' => $response['result'],
                ]);

                // Добавляем историю диалога в таймлайн
                $this->addTimelineHistory($response['result'], 'lead', $conversation);
            }

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 create lead failed', [
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
        try {
            $response = $this->makeRequest('crm.lead.update', [
                'id' => $leadId,
                'fields' => $data,
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 update lead failed', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Создание сделки
     */
    public function createDeal(Conversation $conversation, array $additionalData = []): array
    {
        try {
            $dealData = [
                'TITLE' => 'Сделка из чат-бота #' . $conversation->id,
                'STAGE_ID' => $additionalData['stage_id'] ?? 'NEW',
                'OPENED' => 'Y',
                'PROBABILITY' => 50,
                'COMMENTS' => $this->formatConversationForCRM($conversation),
            ];

            // Связываем с контактом если есть
            if ($conversation->crm_contact_id) {
                $dealData['CONTACT_ID'] = $conversation->crm_contact_id;
            }

            // Добавляем ответственного
            if (!empty($additionalData['responsible_user_id'])) {
                $dealData['ASSIGNED_BY_ID'] = $additionalData['responsible_user_id'];
            }

            // Воронка и стадия
            if (!empty($additionalData['category_id'])) {
                $dealData['CATEGORY_ID'] = $additionalData['category_id'];
            }

            $response = $this->makeRequest('crm.deal.add', [
                'fields' => $dealData,
            ]);

            if (!empty($response['result'])) {
                // Сохраняем связь
                $this->integration->createSyncEntity(
                    'deal',
                    $conversation->id,
                    $response['result'],
                    $dealData
                );

                // Обновляем диалог
                $conversation->update([
                    'crm_deal_id' => $response['result'],
                ]);

                // Добавляем историю
                $this->addTimelineHistory($response['result'], 'deal', $conversation);
            }

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 create deal failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Обновление сделки
     */
    public function updateDeal(string $dealId, array $data): array
    {
        try {
            $response = $this->makeRequest('crm.deal.update', [
                'id' => $dealId,
                'fields' => $data,
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 update deal failed', [
                'deal_id' => $dealId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Добавление примечания/комментария
     */
    public function addNote(string $entityType, string $entityId, string $note): bool
    {
        try {
            $entityTypeId = $this->getEntityTypeId($entityType);
            
            $response = $this->makeRequest('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_ID' => $entityId,
                    'ENTITY_TYPE' => $entityType,
                    'COMMENT' => $note,
                ],
            ]);

            return !empty($response['result']);
        } catch (\Exception $e) {
            Log::error('Bitrix24 add note failed', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получение списка пользователей CRM
     */
    public function getUsers(): array
    {
        try {
            $response = $this->makeRequest('user.get', [
                'ACTIVE' => true,
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 get users failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка воронок/пайплайнов
     */
    public function getPipelines(): array
    {
        try {
            $response = $this->makeRequest('crm.category.list', [
                'entityTypeId' => 2, // Сделки
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 get pipelines failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка этапов воронки
     */
    public function getPipelineStages(string $pipelineId): array
    {
        try {
            $response = $this->makeRequest('crm.dealcategory.stage.list', [
                'id' => $pipelineId,
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 get pipeline stages failed', [
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
            $method = match($entityType) {
                'lead' => 'crm.lead.get',
                'deal' => 'crm.deal.get',
                'contact' => 'crm.contact.get',
                'company' => 'crm.company.get',
                default => throw new \Exception("Unsupported entity type: {$entityType}"),
            };

            $response = $this->makeRequest($method, ['id' => $entityId]);

            return $response['result'] ?? null;
        } catch (\Exception $e) {
            Log::error('Bitrix24 get entity failed', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Поиск контакта по email или телефону
     */
    public function findContact(string $email = null, string $phone = null): ?array
    {
        try {
            if ($email) {
                $contact = $this->findContactByEmail($email);
                if ($contact) return $contact;
            }

            if ($phone) {
                $contact = $this->findContactByPhone($phone);
                if ($contact) return $contact;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Bitrix24 find contact failed', [
                'email' => $email,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Синхронизация диалога с CRM
     */
    public function syncConversation(Conversation $conversation): bool
    {
        info('syncConversation in Bitrix24Provider');
        if ($conversation->crm_lead_id) {
            Log::info('Lead already exists for conversation', [
                'conversation_id' => $conversation->id,
                'lead_id' => $conversation->crm_lead_id
            ]);
            return true; // Возвращаем успех, так как синхронизация уже выполнена
        }
        try {
            // 1. Синхронизируем контакт
            $contactData = [
                'name' => $conversation->user_name,
                'email' => $conversation->user_email,
                'phone' => $conversation->user_phone,
            ];
            info('syncContact');
            $contact = $this->syncContact($contactData);
            
            if (!empty($contact['ID'])) {
                $conversation->update(['crm_contact_id' => $contact['ID']]);
            }

            // 2. Создаем или обновляем лид/сделку
            $botSettings = $conversation->bot->crmIntegrations()
                ->where('crm_integration_id', $this->integration->id)
                ->first();

            if ($botSettings) {
                $settings = $botSettings->pivot;

                if ($settings->create_leads && !$conversation->crm_lead_id) {
                    $this->createLead($conversation, [
                        'source_id' => $settings->lead_source,
                        'responsible_user_id' => $settings->responsible_user_id,
                    ]);
                }

                if ($settings->create_deals && !$conversation->crm_deal_id) {
                    $this->createDeal($conversation, [
                        'responsible_user_id' => $settings->responsible_user_id,
                        'category_id' => $settings->pipeline_settings['category_id'] ?? null,
                        'stage_id' => $settings->pipeline_settings['stage_id'] ?? null,
                    ]);
                }
            }
            info('syncMessages');
            // 3. Синхронизируем сообщения
            $this->syncMessages($conversation);

            return true;
        } catch (\Exception $e) {
            info('syncConversation '.$e->getMessage());
            Log::error('Bitrix24 sync conversation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Обработка webhook от CRM
     */
    public function handleWebhook(array $data): void
    {
        try {
            $event = $data['event'] ?? null;
            $eventData = $data['data'] ?? [];

            Log::info('Bitrix24 webhook received', [
                'event' => $event,
                'data' => $eventData,
            ]);

            switch ($event) {
                case 'ONCRMLEADUPDATE':
                    $this->handleLeadUpdate($eventData);
                    break;
                    
                case 'ONCRMDEALUPDATE':
                    $this->handleDealUpdate($eventData);
                    break;
                    
                case 'ONCRMCONTACTUPDATE':
                    $this->handleContactUpdate($eventData);
                    break;
                    
                case 'ONIMCONNECTORMESSAGEADD':
                    // Открытые линии - новое сообщение
                    $this->handleOpenLineMessage($eventData);
                    break;
                    
                case 'ONIMOPENLINESJOIN':
                    // Открытые линии - оператор подключился
                    $this->handleOpenLineOperatorJoin($eventData);
                    break;
                    
                default:
                    Log::info('Unhandled Bitrix24 webhook event', ['event' => $event]);
            }

            // Логируем webhook
            $this->integration->logSync(
                'incoming',
                $event,
                'webhook',
                $data,
                [],
                'success'
            );
        } catch (\Exception $e) {
            Log::error('Bitrix24 webhook handling failed', [
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
     * Получение настроек полей CRM
     */
    public function getFields(string $entityType): array
    {
        try {
            $method = match($entityType) {
                'lead' => 'crm.lead.fields',
                'deal' => 'crm.deal.fields',
                'contact' => 'crm.contact.fields',
                'company' => 'crm.company.fields',
                'task' => 'crm.task.fields',
                default => throw new \Exception("Unsupported entity type: {$entityType}"),
            };

            \Log::info('Bitrix24 getFields called', [
                'entity_type' => $entityType,
                'method' => $method
            ]);

            $response = $this->makeRequest($method);
            //dd($response);

            \Log::info('Bitrix24 getFields raw response', [
                'entity_type' => $entityType,
                'method' => $method,
                'response_type' => gettype($response),
                'response_is_array' => is_array($response),
                'response_keys' => is_array($response) ? array_keys($response) : 'not array',
                'has_result' => isset($response['result']),
                'result_type' => isset($response['result']) ? gettype($response['result']) : 'not set',
                'result_count' => isset($response['result']) && is_array($response['result']) ? count($response['result']) : 0,
                'full_response' => $response // ОСТОРОЖНО: может быть большой
            ]);

            return $response['result'] ?? [];
            
        } catch (\Exception $e) {
            \Log::error('Bitrix24 get fields failed', [
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
                        $this->syncContact($entity['data']);
                        break;
                    case 'lead':
                        $this->createLead($entity['conversation'], $entity['data'] ?? []);
                        break;
                    case 'deal':
                        $this->createDeal($entity['conversation'], $entity['data'] ?? []);
                        break;
                }
                $results['success']++;
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

    /**
     * Интеграция с открытыми линиями
     */
    public function createOpenLineChat(Conversation $conversation): array
    {
        try {
            $chatData = [
                'USER_ID' => $this->getOrCreateOpenLineUser($conversation),
                'MESSAGE' => $conversation->messages()->first()->content ?? 'Начало диалога',
                'SYSTEM' => 'chatbot',
                'CHAT' => [
                    'TITLE' => 'Чат-бот: ' . $conversation->bot->name,
                    'DESCRIPTION' => 'Диалог #' . $conversation->id,
                ],
            ];

            // Если есть конфигурация открытой линии
            if (!empty($this->config['openline_config_id'])) {
                $chatData['CONFIG_ID'] = $this->config['openline_config_id'];
            }

            $response = $this->makeRequest('imopenlines.crm.chat.add', $chatData);

            if (!empty($response['result']['CHAT_ID'])) {
                // Сохраняем ID чата
                $conversation->update([
                    'metadata' => array_merge($conversation->metadata ?? [], [
                        'bitrix24_chat_id' => $response['result']['CHAT_ID'],
                        'bitrix24_dialog_id' => $response['result']['DIALOG_ID'] ?? null,
                    ]),
                ]);
            }

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 create open line chat failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Отправка сообщения в открытую линию
     */
    public function sendOpenLineMessage(Conversation $conversation, string $message, string $role = 'bot'): bool
    {
        try {
            $chatId = $conversation->metadata['bitrix24_chat_id'] ?? null;
            
            if (!$chatId) {
                // Создаем чат если его нет
                $chat = $this->createOpenLineChat($conversation);
                $chatId = $chat['CHAT_ID'] ?? null;
            }

            if (!$chatId) {
                throw new \Exception('Failed to get or create chat ID');
            }

            $messageData = [
                'CHAT_ID' => $chatId,
                'MESSAGE' => $message,
                'SYSTEM' => $role === 'bot' ? 'Y' : 'N',
            ];

            $response = $this->makeRequest('imopenlines.message.send', $messageData);

            return !empty($response['result']);
        } catch (\Exception $e) {
            Log::error('Bitrix24 send open line message failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ===================== PRIVATE МЕТОДЫ =====================

    /**
     * Выполнение запроса к API
     */
    protected function makeRequest(string $method, array $params = []): array
    {
        // Определяем, какой метод авторизации использовать
        $url = null;
        $useOauth = false;
        
        // Приоритет 1: OAuth если есть токен И домен
        if (!empty($this->accessToken) && !empty($this->oauthRestUrl)) {
            $url = $this->oauthRestUrl . $method;
            $useOauth = true;
            Log::info('Using OAuth for Bitrix24 request', [
                'method' => $method,
                'has_token' => !empty($this->accessToken),
                'oauth_url' => $this->oauthRestUrl
            ]);
        }
        // Приоритет 2: Webhook если есть URL
        elseif (!empty($this->webhookUrl)) {
            $url = $this->webhookUrl . $method;
            Log::info('Using Webhook for Bitrix24 request', [
                'method' => $method, 
                'webhook_url' => $this->webhookUrl
            ]);
        }
        
        if (!$url) {
            Log::error('Bitrix24 makeRequest failed - no valid URL', [
                'method' => $method,
                'has_webhook' => !empty($this->webhookUrl),
                'has_oauth' => !empty($this->oauthRestUrl),
                'has_token' => !empty($this->accessToken)
            ]);
            throw new \Exception('Bitrix24 integration is not configured');
        }

        // Добавляем auth параметр только для OAuth
        if ($useOauth) {
            $params['auth'] = $this->accessToken;
        }

        try {
            Log::info('Making Bitrix24 API request', [
                'url' => $url,
                'method' => $method,
                'use_oauth' => $useOauth,
                'params_count' => count($params)
            ]);

            $response = $this->client->post($url, ['json' => $params]);
            $result = json_decode($response->getBody()->getContents(), true);

            // Обработка ошибки токена только для OAuth
            if ($useOauth && isset($result['error']) && $result['error'] === 'expired_token') {
                if ($this->refreshToken) {
                    Log::info('Token expired, refreshing...');
                    $this->refreshAccessToken();
                    
                    // Повторяем запрос с новым токеном
                    $params['auth'] = $this->accessToken;
                    $response = $this->client->post($url, ['json' => $params]);
                    $result = json_decode($response->getBody()->getContents(), true);
                }
            }

            if (!empty($result['error'])) {
                Log::error('Bitrix24 API error', [
                    'method' => $method,
                    'error' => $result['error'],
                    'error_description' => $result['error_description'] ?? ''
                ]);
                throw new \Exception($result['error_description'] ?? $result['error']);
            }

            Log::info('Bitrix24 API request successful', [
                'method' => $method,
                'has_result' => isset($result['result'])
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Bitrix24 API request failed', [
                'method' => $method,
                'url' => $url,
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

                // Обновляем в БД
                $newCredentials = array_merge($this->config, [
                    'auth_id' => $this->accessToken, // Используем auth_id для совместимости
                    'refresh_id' => $this->refreshToken,
                ]);

                $this->integration->update(['credentials' => $newCredentials]);
                // Обновляем конфиг в текущем экземпляре класса
                $this->config = $newCredentials;

            } else {
                 throw new \Exception('Failed to get new access token from refresh token response.');
            }
        } catch (\Exception $e) {
            Log::error('Bitrix24 token refresh failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
            // Деактивируем интеграцию, чтобы избежать спама ошибками
            $this->integration->update(['is_active' => false]);
            throw $e;
        }
    }

    /**
     * Создание контакта
     */
    protected function createContact(array $data): array
    {
        $fields = [
            'NAME' => $data['name'] ?? '',
            'OPENED' => 'Y',
            'TYPE_ID' => 'CLIENT',
        ];

        if (!empty($data['email'])) {
            $fields['EMAIL'] = [
                ['VALUE' => $data['email'], 'VALUE_TYPE' => 'WORK']
            ];
        }

        if (!empty($data['phone'])) {
            $fields['PHONE'] = [
                ['VALUE' => $data['phone'], 'VALUE_TYPE' => 'WORK']
            ];
        }

        $response = $this->makeRequest('crm.contact.add', ['fields' => $fields]);

        return ['ID' => $response['result']] ?? [];
    }

    /**
     * Обновление контакта
     */
    protected function updateContact(string $contactId, array $data): array
    {
        $fields = [];

        if (!empty($data['name'])) {
            $fields['NAME'] = $data['name'];
        }

        if (!empty($data['email'])) {
            $fields['EMAIL'] = [
                ['VALUE' => $data['email'], 'VALUE_TYPE' => 'WORK']
            ];
        }

        if (!empty($data['phone'])) {
            $fields['PHONE'] = [
                ['VALUE' => $data['phone'], 'VALUE_TYPE' => 'WORK']
            ];
        }

        $this->makeRequest('crm.contact.update', [
            'id' => $contactId,
            'fields' => $fields,
        ]);

        return ['ID' => $contactId];
    }

    /**
     * Поиск контакта по email
     */
    protected function findContactByEmail(string $email): ?array
    {
        $response = $this->makeRequest('crm.contact.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'NAME', 'EMAIL', 'PHONE'],
        ]);

        return $response['result'][0] ?? null;
    }

    /**
     * Поиск контакта по телефону
     */
    protected function findContactByPhone(string $phone): ?array
    {
        // Нормализуем телефон
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        $response = $this->makeRequest('crm.contact.list', [
            'filter' => ['PHONE' => $normalizedPhone],
            'select' => ['ID', 'NAME', 'EMAIL', 'PHONE'],
        ]);

        return $response['result'][0] ?? null;
    }

    /**
     * Форматирование диалога для CRM
     */
    protected function formatConversationForCRM(Conversation $conversation): string
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        $text = "=== Диалог из чат-бота ===\n";
        $text .= "Канал: {$conversation->channel->getTypeName()}\n";
        $text .= "Начат: {$conversation->created_at->format('d.m.Y H:i')}\n";
        $text .= "Пользователь: {$conversation->getUserDisplayName()}\n";
        $text .= "\n--- История сообщений ---\n\n";

        foreach ($messages as $message) {
            $role = $message->getRoleName();
            $time = $message->created_at->format('H:i:s');
            $text .= "[{$time}] {$role}: {$message->content}\n\n";
        }

        return $text;
    }

    /**
     * Добавление истории диалога в таймлайн
     */
    protected function addTimelineHistory(string $entityId, string $entityType, Conversation $conversation): void
    {
        try {
            $this->makeRequest('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_ID' => $entityId,
                    'ENTITY_TYPE_ID' => $this->getEntityTypeId($entityType),
                    'COMMENT' => $this->formatConversationForCRM($conversation),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add timeline history', [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получение ID типа сущности
     */
    protected function getEntityTypeId(string $type): int
    {
        return match($type) {
            'lead' => 1,
            'deal' => 2,
            'contact' => 3,
            'company' => 4,
            'invoice' => 5,
            default => 0,
        };
    }

    /**
     * Синхронизация сообщений диалога
     */
    protected function syncMessages(Conversation $conversation): void
    {
        info('syncMessages 1');
        try {
            // Проверяем, есть ли чат в открытых линиях
            $chatId = $conversation->metadata['bitrix24_chat_id'] ?? null;
            
            if (!$chatId) {
                info('Создаем чат если его нет');
                // Создаем чат если его нет
                $chat = $this->createOpenLineChat($conversation);
                info($chat);
                $chatId = $chat['CHAT_ID'] ?? null;
            }
            
            if (!$chatId) {
                info('Unable to sync messages - no chat ID');
                Log::warning('Unable to sync messages - no chat ID', [
                    'conversation_id' => $conversation->id
                ]);
                return;
            }
            
            // Получаем последние несинхронизированные сообщения
            $lastSyncedMessageId = $conversation->metadata['last_synced_message_id'] ?? 0;
            
            $messages = $conversation->messages()
                ->where('id', '>', $lastSyncedMessageId)
                ->orderBy('created_at', 'asc')
                ->get();
            
            foreach ($messages as $message) {
                try {
                    // Определяем автора сообщения
                    $author = match($message->role) {
                        'user' => 'USER',
                        'assistant' => 'BOT',
                        'operator' => 'OPERATOR',
                        default => 'SYSTEM'
                    };
                    
                    // Отправляем сообщение в открытую линию
                    $messageData = [
                        'CHAT_ID' => $chatId,
                        'MESSAGE' => $message->content,
                        'AUTHOR' => $author,
                    ];
                    
                    // Если есть вложения
                    if ($message->attachments) {
                        $messageData['FILES'] = $this->prepareAttachments($message->attachments);
                    }
                    info('imopenlines.message.add');
                    $response = $this->makeRequest('imopenlines.message.add', $messageData);
                    info($response);
                    // Обновляем ID последнего синхронизированного сообщения
                    $conversation->update([
                        'metadata' => array_merge($conversation->metadata ?? [], [
                            'last_synced_message_id' => $message->id,
                            'last_sync_at' => now()->toIso8601String(),
                        ])
                    ]);
                    
                } catch (\Exception $e) {
                    info('Failed to sync message to Bitrix24 '.$e->getMessage());
                    Log::error('Failed to sync message to Bitrix24', [
                        'message_id' => $message->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            info('Messages sync failed'.$e->getMessage());
            Log::error('Messages sync failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Подготовка вложений для отправки
     */
    protected function prepareAttachments(array $attachments): array
    {
        $files = [];
        
        foreach ($attachments as $attachment) {
            if (isset($attachment['url'])) {
                $files[] = [
                    'NAME' => $attachment['name'] ?? 'file',
                    'LINK' => $attachment['url'],
                ];
            } elseif (isset($attachment['base64'])) {
                $files[] = [
                    'NAME' => $attachment['name'] ?? 'file',
                    'CONTENT' => $attachment['base64'],
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * Получение или создание пользователя открытой линии
     */
    protected function getOrCreateOpenLineUser(Conversation $conversation): int
    {
        try {
            // Ищем существующего пользователя
            if ($conversation->user_email) {
                $response = $this->makeRequest('user.search', [
                    'FILTER' => ['EMAIL' => $conversation->user_email],
                ]);
                
                if (!empty($response['result'][0]['ID'])) {
                    return $response['result'][0]['ID'];
                }
            }
            
            // Создаем нового пользователя открытой линии
            $userData = [
                'NAME' => $conversation->user_name ?? 'Гость',
                'EMAIL' => $conversation->user_email,
                'PHONE' => $conversation->user_phone,
                'EXTERNAL_AUTH_ID' => 'chatbot',
            ];
            
            $response = $this->makeRequest('user.add', $userData);
            
            return $response['result'] ?? 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to get/create open line user', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Обработка обновления лида
     */
    protected function handleLeadUpdate(array $data): void
    {
        try {
            $leadId = $data['FIELDS']['ID'] ?? null;
            if (!$leadId) return;
            
            // Находим связанную сущность
            $syncEntity = $this->integration->getSyncEntity('lead', $leadId);
            if (!$syncEntity) return;
            
            // Получаем полную информацию о лиде
            $lead = $this->getEntity('lead', $leadId);
            if (!$lead) return;
            
            // Обновляем кэшированные данные
            $syncEntity->update([
                'remote_data' => $lead,
                'last_synced_at' => now(),
            ]);
            
            // Если лид конвертирован в сделку
            if ($lead['STATUS_ID'] === 'CONVERTED') {
                $conversation = Conversation::find($syncEntity->local_id);
                if ($conversation && !empty($lead['ASSOCIATED_ENTITY']['DEAL'][0])) {
                    $dealId = $lead['ASSOCIATED_ENTITY']['DEAL'][0];
                    $conversation->update(['crm_deal_id' => $dealId]);
                    
                    // Создаем связь для сделки
                    $this->integration->createSyncEntity(
                        'deal',
                        $conversation->id,
                        $dealId,
                        []
                    );
                }
            }
            
            Log::info('Lead updated from webhook', [
                'lead_id' => $leadId,
                'status' => $lead['STATUS_ID'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle lead update', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Обработка обновления сделки
     */
    protected function handleDealUpdate(array $data): void
    {
        try {
            $dealId = $data['FIELDS']['ID'] ?? null;
            if (!$dealId) return;
            
            // Находим связанную сущность
            $syncEntity = $this->integration->getSyncEntity('deal', $dealId);
            if (!$syncEntity) return;
            
            // Получаем информацию о сделке
            $deal = $this->getEntity('deal', $dealId);
            if (!$deal) return;
            
            // Обновляем кэш
            $syncEntity->update([
                'remote_data' => $deal,
                'last_synced_at' => now(),
            ]);
            
            // Проверяем изменение стадии
            $oldStageId = $syncEntity->remote_data['STAGE_ID'] ?? null;
            $newStageId = $deal['STAGE_ID'] ?? null;
            
            if ($oldStageId !== $newStageId) {
                // Логируем изменение стадии
                Log::info('Deal stage changed', [
                    'deal_id' => $dealId,
                    'old_stage' => $oldStageId,
                    'new_stage' => $newStageId,
                ]);
                
                // Если сделка закрыта успешно
                if (in_array($newStageId, ['WON', 'C2:WON'])) {
                    $conversation = Conversation::find($syncEntity->local_id);
                    if ($conversation && $conversation->isActive()) {
                        $conversation->close();
                        $conversation->messages()->create([
                            'role' => 'system',
                            'content' => 'Сделка в CRM успешно закрыта. Диалог завершен.',
                        ]);
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to handle deal update', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Обработка обновления контакта
     */
    protected function handleContactUpdate(array $data): void
    {
        try {
            $contactId = $data['FIELDS']['ID'] ?? null;
            if (!$contactId) return;
            
            // Находим все связанные диалоги
            $conversations = Conversation::where('crm_contact_id', $contactId)->get();
            
            if ($conversations->isEmpty()) return;
            
            // Получаем обновленную информацию о контакте
            $contact = $this->getEntity('contact', $contactId);
            if (!$contact) return;
            
            // Обновляем информацию в диалогах
            foreach ($conversations as $conversation) {
                $updates = [];
                
                if (!empty($contact['NAME'])) {
                    $updates['user_name'] = $contact['NAME'];
                }
                
                if (!empty($contact['EMAIL'][0]['VALUE'])) {
                    $updates['user_email'] = $contact['EMAIL'][0]['VALUE'];
                }
                
                if (!empty($contact['PHONE'][0]['VALUE'])) {
                    $updates['user_phone'] = $contact['PHONE'][0]['VALUE'];
                }
                
                if (!empty($updates)) {
                    $conversation->update($updates);
                }
            }
            
            Log::info('Contact updated from webhook', [
                'contact_id' => $contactId,
                'affected_conversations' => $conversations->count(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle contact update', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Обработка сообщения из открытой линии
     */
    protected function handleOpenLineMessage(array $data): void
    {
        try {
            $chatId = $data['CHAT']['ID'] ?? null;
            $message = $data['MESSAGE'] ?? [];
            
            if (!$chatId || empty($message)) return;
            
            // Находим диалог по ID чата
            $conversation = Conversation::where('metadata->bitrix24_chat_id', $chatId)->first();
            
            if (!$conversation) {
                Log::warning('Conversation not found for open line message', [
                    'chat_id' => $chatId
                ]);
                return;
            }
            
            // Определяем роль отправителя
            $authorType = $data['AUTHOR']['TYPE'] ?? 'USER';
            $role = match($authorType) {
                'USER', 'GUEST' => 'user',
                'OPERATOR' => 'operator',
                'BOT' => 'assistant',
                default => 'system'
            };
            
            // Проверяем, не наше ли это сообщение (чтобы избежать дублирования)
            $lastMessage = $conversation->messages()->latest()->first();
            if ($lastMessage && 
                $lastMessage->content === $message['TEXT'] && 
                $lastMessage->created_at->diffInSeconds(now()) < 5) {
                return; // Пропускаем дублированное сообщение
            }
            
            // Сохраняем сообщение
            $newMessage = $conversation->messages()->create([
                'role' => $role,
                'content' => $message['TEXT'] ?? '',
                'metadata' => [
                    'bitrix24_message_id' => $message['ID'] ?? null,
                    'author_id' => $data['AUTHOR']['ID'] ?? null,
                    'author_name' => $data['AUTHOR']['NAME'] ?? null,
                ],
            ]);
            
            // Обновляем счетчики
            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);
            
            // Если сообщение от пользователя и бот активен, генерируем ответ
            if ($role === 'user' && $conversation->bot->is_active && $conversation->status === 'active') {
                dispatch(function () use ($conversation, $message) {
                    $aiService = app(\App\Services\AIService::class);
                    $response = $aiService->generateResponse(
                        $conversation->bot,
                        $conversation,
                        $message['TEXT']
                    );
                    
                    // Отправляем ответ обратно в открытую линию
                    $this->sendOpenLineMessage($conversation, $response, 'bot');
                })->afterResponse();
            }
            
            Log::info('Open line message processed', [
                'conversation_id' => $conversation->id,
                'message_id' => $newMessage->id,
                'role' => $role,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle open line message', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Обработка подключения оператора к открытой линии
     */
    protected function handleOpenLineOperatorJoin(array $data): void
    {
        try {
            $chatId = $data['CHAT']['ID'] ?? null;
            $operatorId = $data['USER']['ID'] ?? null;
            $operatorName = $data['USER']['NAME'] ?? 'Оператор';
            
            if (!$chatId) return;
            
            // Находим диалог
            $conversation = Conversation::where('metadata->bitrix24_chat_id', $chatId)->first();
            
            if (!$conversation) return;
            
            // Обновляем статус диалога
            $conversation->update([
                'status' => 'waiting_operator',
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'bitrix24_operator_id' => $operatorId,
                    'bitrix24_operator_name' => $operatorName,
                    'operator_joined_at' => now()->toIso8601String(),
                ])
            ]);
            
            // Добавляем системное сообщение
            $conversation->messages()->create([
                'role' => 'system',
                'content' => "Оператор {$operatorName} подключился к диалогу",
                'metadata' => [
                    'bitrix24_operator_id' => $operatorId,
                ]
            ]);
            
            Log::info('Operator joined open line chat', [
                'conversation_id' => $conversation->id,
                'operator_id' => $operatorId,
                'operator_name' => $operatorName,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle operator join', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Создание задачи в Битрикс24
     */
    public function createTask(Conversation $conversation, array $params = []): ?array
    {
        try {
            $taskData = [
                'TITLE' => $params['title'] ?? 'Обработать обращение из чат-бота #' . $conversation->id,
                'DESCRIPTION' => $params['description'] ?? $this->formatConversationForCRM($conversation),
                'RESPONSIBLE_ID' => $params['responsible_id'] ?? $this->config['default_responsible_id'] ?? 1,
                'DEADLINE' => $params['deadline'] ?? now()->addDay()->format('c'),
                'PRIORITY' => $params['priority'] ?? '1', // 0 - низкий, 1 - средний, 2 - высокий
                'GROUP_ID' => $params['group_id'] ?? 0,
            ];
            
            // Связываем с CRM сущностями если есть
            if ($conversation->crm_lead_id) {
                $taskData['UF_CRM_TASK'] = ['L_' . $conversation->crm_lead_id];
            } elseif ($conversation->crm_deal_id) {
                $taskData['UF_CRM_TASK'] = ['D_' . $conversation->crm_deal_id];
            } elseif ($conversation->crm_contact_id) {
                $taskData['UF_CRM_TASK'] = ['C_' . $conversation->crm_contact_id];
            }
            
            $response = $this->makeRequest('tasks.task.add', [
                'fields' => $taskData
            ]);
            
            if (!empty($response['result']['task']['id'])) {
                // Сохраняем ID задачи
                $conversation->update([
                    'metadata' => array_merge($conversation->metadata ?? [], [
                        'bitrix24_task_id' => $response['result']['task']['id'],
                        'task_created_at' => now()->toIso8601String(),
                    ])
                ]);
                
                Log::info('Task created in Bitrix24', [
                    'task_id' => $response['result']['task']['id'],
                    'conversation_id' => $conversation->id,
                ]);
                
                return $response['result']['task'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to create task in Bitrix24', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Получение списка каналов открытых линий
     */
    public function getOpenLineConfigs(): array
    {
        try {
            $response = $this->makeRequest('imopenlines.config.list.get');
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get open line configs', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Получение статистики по открытым линиям
     */
    public function getOpenLineStats(array $params = []): array
    {
        try {
            $filter = [
                'DATE_CREATE_from' => $params['date_from'] ?? now()->subMonth()->format('Y-m-d'),
                'DATE_CREATE_to' => $params['date_to'] ?? now()->format('Y-m-d'),
            ];
            
            if (!empty($params['config_id'])) {
                $filter['CONFIG_ID'] = $params['config_id'];
            }
            
            $response = $this->makeRequest('imopenlines.dialog.list', [
                'filter' => $filter,
                'select' => ['*', 'MESSAGES'],
            ]);
            
            $dialogs = $response['result'] ?? [];
            
            // Подсчитываем статистику
            $stats = [
                'total_dialogs' => count($dialogs),
                'closed_dialogs' => 0,
                'active_dialogs' => 0,
                'average_response_time' => 0,
                'average_close_time' => 0,
                'total_messages' => 0,
                'by_source' => [],
            ];
            
            $responseTimes = [];
            $closeTimes = [];
            
            foreach ($dialogs as $dialog) {
                if ($dialog['CLOSED'] === 'Y') {
                    $stats['closed_dialogs']++;
                    
                    // Время закрытия
                    if (!empty($dialog['DATE_CREATE']) && !empty($dialog['DATE_CLOSE'])) {
                        $created = new \DateTime($dialog['DATE_CREATE']);
                        $closed = new \DateTime($dialog['DATE_CLOSE']);
                        $closeTimes[] = $closed->getTimestamp() - $created->getTimestamp();
                    }
                } else {
                    $stats['active_dialogs']++;
                }
                
                // Подсчет сообщений
                $stats['total_messages'] += count($dialog['MESSAGES'] ?? []);
                
                // Группировка по источникам
                $source = $dialog['SOURCE'] ?? 'unknown';
                if (!isset($stats['by_source'][$source])) {
                    $stats['by_source'][$source] = 0;
                }
                $stats['by_source'][$source]++;
                
                // Время первого ответа оператора
                if (!empty($dialog['MESSAGES'])) {
                    $firstUserMessage = null;
                    $firstOperatorMessage = null;
                    
                    foreach ($dialog['MESSAGES'] as $message) {
                        if (!$firstUserMessage && $message['AUTHOR']['TYPE'] === 'USER') {
                            $firstUserMessage = new \DateTime($message['DATE_CREATE']);
                        }
                        if (!$firstOperatorMessage && $message['AUTHOR']['TYPE'] === 'OPERATOR') {
                            $firstOperatorMessage = new \DateTime($message['DATE_CREATE']);
                        }
                        
                        if ($firstUserMessage && $firstOperatorMessage) {
                            $responseTimes[] = $firstOperatorMessage->getTimestamp() - $firstUserMessage->getTimestamp();
                            break;
                        }
                    }
                }
            }
            
            // Вычисляем средние значения
            if (count($responseTimes) > 0) {
                $stats['average_response_time'] = round(array_sum($responseTimes) / count($responseTimes));
            }
            
            if (count($closeTimes) > 0) {
                $stats['average_close_time'] = round(array_sum($closeTimes) / count($closeTimes));
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get open line stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Массовая отправка сообщений через открытые линии
     */
    public function broadcastMessage(array $chatIds, string $message, array $params = []): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        foreach ($chatIds as $chatId) {
            try {
                $messageData = [
                    'CHAT_ID' => $chatId,
                    'MESSAGE' => $message,
                    'SYSTEM' => $params['system'] ?? 'N',
                ];
                
                if (!empty($params['keyboard'])) {
                    $messageData['KEYBOARD'] = $params['keyboard'];
                }
                
                $response = $this->makeRequest('imopenlines.message.send', $messageData);
                
                if (!empty($response['result'])) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'chat_id' => $chatId,
                        'error' => 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }


    /**
     * Создать лид с динамическим маппингом полей
     */
    public function createLeadFromFieldMapping(array $fieldData): array
    {
        try {
            // Валидация обязательных полей
            if (empty($fieldData['TITLE'])) {
                $fieldData['TITLE'] = 'Лид из чат-бота #' . uniqid();
            }
            
            Log::info('Creating Bitrix24 lead', [
                'fields' => $fieldData
            ]);
            
            $response = $this->makeRequest('crm.lead.add', [
                'fields' => $fieldData,
                'params' => ['REGISTER_SONET_EVENT' => 'Y']
            ]);
            
            if (!empty($response['result'])) {
                Log::info('Lead created successfully', [
                    'lead_id' => $response['result']
                ]);
                
                // Логируем в CRM
                $this->integration->logSync(
                    'outgoing',
                    'lead',
                    'create',
                    $fieldData,
                    $response,
                    'success'
                );
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Failed to create lead in Bitrix24', [
                'error' => $e->getMessage(),
                'field_data' => $fieldData
            ]);
            
            $this->integration->logSync(
                'outgoing',
                'lead',
                'create',
                $fieldData,
                [],
                'error',
                $e->getMessage()
            );
            
            throw $e;
        }
    }

    /**
     * Получить статусы лидов
     */
    public function getLeadStatuses(): array
    {
        try {
            $response = $this->makeRequest('crm.status.list', [
                'filter' => ['ENTITY_ID' => 'STATUS']
            ]);
            
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get lead statuses', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получить источники лидов
     */
    public function getLeadSources(): array
    {
        try {
            $response = $this->makeRequest('crm.status.list', [
                'filter' => ['ENTITY_ID' => 'SOURCE']
            ]);
            
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get lead sources', ['error' => $e->getMessage()]);
            return [];
        }
    }
}