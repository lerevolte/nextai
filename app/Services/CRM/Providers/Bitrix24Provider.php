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
    protected string $webhookUrl;
    protected ?string $accessToken = null;
    protected ?string $refreshToken = null;
    protected array $config;

    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // В продакшене лучше использовать true
        ]);
        
        $this->config = $integration->credentials ?? [];
        
        // Определяем тип авторизации
        if (isset($this->config['webhook_url'])) {
            // Входящий вебхук (простой способ)
            $this->webhookUrl = rtrim($this->config['webhook_url'], '/') . '/';
        } elseif (isset($this->config['access_token'])) {
            // OAuth 2.0 авторизация
            $this->accessToken = $this->config['access_token'];
            $this->refreshToken = $this->config['refresh_token'] ?? null;
            $this->webhookUrl = 'https://' . $this->config['domain'] . '/rest/';
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
        try {
            // 1. Синхронизируем контакт
            $contactData = [
                'name' => $conversation->user_name,
                'email' => $conversation->user_email,
                'phone' => $conversation->user_phone,
            ];

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

            // 3. Синхронизируем сообщения
            $this->syncMessages($conversation);

            return true;
        } catch (\Exception $e) {
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
                default => throw new \Exception("Unsupported entity type: {$entityType}"),
            };

            $response = $this->makeRequest($method);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 get fields failed', [
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
        try {
            $url = $this->webhookUrl . $method;

            // Добавляем токен если используется OAuth
            if ($this->accessToken) {
                $params['auth'] = $this->accessToken;
            }

            $response = $this->client->post($url, [
                'json' => $params,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // Проверяем на ошибки
            if (!empty($result['error'])) {
                // Если токен истек, пробуем обновить
                if ($result['error'] === 'expired_token' && $this->refreshToken) {
                    $this->refreshAccessToken();
                    return $this->makeRequest($method, $params);
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
     * Обновление access токена
     */
    protected function refreshAccessToken(): void
    {
        try {
            $response = $this->client->get('https://oauth.bitrix.info/oauth/token/', [
                'query' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                $this->refreshToken = $data['refresh_token'];

                // Обновляем в БД
                $this->integration->update([
                    'credentials' => array_merge($this->config, [
                        'access_token' => $this->accessToken,
                        'refresh_token' => $this->refreshToken,
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Bitrix24 token refresh failed', [
                'error' => $e->getMessage(),
            ]);
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
        
    }
}