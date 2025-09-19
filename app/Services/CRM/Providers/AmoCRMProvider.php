<?php

namespace App\Services\CRM\Providers;

use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Services\CRM\CrmProviderInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AmoCRMProvider implements CrmProviderInterface
{
    protected Client $client;
    protected CrmIntegration $integration;
    protected string $subdomain;
    protected ?string $accessToken = null;
    protected ?string $refreshToken = null;
    protected array $config;

    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->config = $integration->credentials ?? [];
        
        $this->subdomain = $this->config['subdomain'] ?? '';
        $this->accessToken = $this->config['access_token'] ?? null;
        $this->refreshToken = $this->config['refresh_token'] ?? null;
        
        $this->client = new Client([
            'base_uri' => "https://{$this->subdomain}.amocrm.ru/api/v4/",
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Обновление лида
     */
    public function updateLead(string $leadId, array $data): array
    {
        try {
            $response = $this->makeRequest('PATCH', "leads/{$leadId}", $data);
            return $response['_embedded']['leads'][0] ?? [];
        } catch (\Exception $e) {
            Log::error('AmoCRM update lead failed', [
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
        // В AmoCRM сделки и лиды - это одна сущность
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
     * Добавление примечания/комментария
     */
    public function addNote(string $entityType, string $entityId, string $note): bool
    {
        try {
            $entityTypeId = $this->getEntityTypeId($entityType);
            
            $noteData = [
                'entity_id' => (int)$entityId,
                'note_type' => 'common',
                'params' => [
                    'text' => $note
                ]
            ];

            $response = $this->makeRequest('POST', "/{$entityType}/{$entityId}/notes", [$noteData]);
            
            return !empty($response['_embedded']['notes']);
        } catch (\Exception $e) {
            Log::error('AmoCRM add note failed', [
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
            $response = $this->makeRequest('GET', 'users');
            return $response['_embedded']['users'] ?? [];
        } catch (\Exception $e) {
            Log::error('AmoCRM get users failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка воронок/пайплайнов
     */
    public function getPipelines(): array
    {
        try {
            $response = $this->makeRequest('GET', 'leads/pipelines');
            return $response['_embedded']['pipelines'] ?? [];
        } catch (\Exception $e) {
            Log::error('AmoCRM get pipelines failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Получение списка этапов воронки
     */
    public function getPipelineStages(string $pipelineId): array
    {
        try {
            $response = $this->makeRequest('GET', "leads/pipelines/{$pipelineId}");
            return $response['_embedded']['statuses'] ?? [];
        } catch (\Exception $e) {
            Log::error('AmoCRM get pipeline stages failed', [
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
            $endpoint = match($entityType) {
                'lead', 'deal' => "leads/{$entityId}",
                'contact' => "contacts/{$entityId}",
                'company' => "companies/{$entityId}",
                'task' => "tasks/{$entityId}",
                default => throw new \Exception("Unsupported entity type: {$entityType}"),
            };

            $response = $this->makeRequest('GET', $endpoint);
            return $response;
        } catch (\Exception $e) {
            Log::error('AmoCRM get entity failed', [
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
            Log::error('AmoCRM find contact failed', [
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
            
            if (!empty($contact['id'])) {
                $conversation->update(['crm_contact_id' => $contact['id']]);
            }

            // 2. Создаем или обновляем лид
            $botSettings = $conversation->bot->crmIntegrations()
                ->where('crm_integration_id', $this->integration->id)
                ->first();

            if ($botSettings) {
                $settings = $botSettings->pivot;

                if ($settings->create_leads && !$conversation->crm_lead_id) {
                    $this->createLead($conversation, [
                        'source_id' => $settings->lead_source,
                        'responsible_user_id' => $settings->responsible_user_id,
                        'pipeline_id' => $settings->pipeline_settings['pipeline_id'] ?? null,
                        'status_id' => $settings->pipeline_settings['status_id'] ?? null,
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('AmoCRM sync conversation failed', [
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
            // AmoCRM отправляет данные в специфичном формате
            $events = $data['events'] ?? [];
            
            foreach ($events as $event) {
                $eventType = $event['type'] ?? '';
                $entityType = $event['entity'] ?? '';
                $entityId = $event['entity_id'] ?? '';
                
                Log::info('AmoCRM webhook received', [
                    'event' => $eventType,
                    'entity' => $entityType,
                    'entity_id' => $entityId,
                ]);

                switch ($eventType) {
                    case 'lead_status_changed':
                        $this->handleLeadStatusChange($entityId, $event);
                        break;
                        
                    case 'contact_updated':
                        $this->handleContactUpdate($entityId, $event);
                        break;
                        
                    case 'task_created':
                        $this->handleTaskCreated($entityId, $event);
                        break;
                        
                    case 'incoming_chat_message':
                        $this->handleIncomingMessage($event);
                        break;
                        
                    default:
                        Log::info('Unhandled AmoCRM webhook event', ['event' => $eventType]);
                }
            }

            // Логируем webhook
            $this->integration->logSync(
                'incoming',
                'webhook',
                'process',
                $data,
                [],
                'success'
            );
        } catch (\Exception $e) {
            Log::error('AmoCRM webhook handling failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $this->integration->logSync(
                'incoming',
                'webhook',
                'process',
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
            $endpoint = match($entityType) {
                'lead', 'deal' => 'leads/custom_fields',
                'contact' => 'contacts/custom_fields',
                'company' => 'companies/custom_fields',
                default => throw new \Exception("Unsupported entity type: {$entityType}"),
            };

            $response = $this->makeRequest('GET', $endpoint);
            return $response['_embedded']['custom_fields'] ?? [];
        } catch (\Exception $e) {
            Log::error('AmoCRM get fields failed', [
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

        // AmoCRM поддерживает батч-операции
        $batches = array_chunk($entities, 50); // Максимум 50 за раз
        
        foreach ($batches as $batch) {
            try {
                $batchData = [];
                
                foreach ($batch as $entity) {
                    switch ($entity['type']) {
                        case 'contact':
                            $batchData['contacts'][] = $this->prepareContactData($entity['data']);
                            break;
                        case 'lead':
                            $batchData['leads'][] = $this->prepareLeadData($entity['data']);
                            break;
                    }
                }
                
                // Отправляем батч
                foreach ($batchData as $type => $items) {
                    if (!empty($items)) {
                        $response = $this->makeRequest('POST', $type, $items);
                        $results['success'] += count($response['_embedded'][$type] ?? []);
                    }
                }
            } catch (\Exception $e) {
                $results['failed'] += count($batch);
                $results['errors'][] = [
                    'batch' => $batch,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    // ===================== PRIVATE МЕТОДЫ =====================

    /**
     * Выполнение запроса к API
     */
    protected function makeRequest(string $method, string $endpoint, $data = null): array
    {
        try {
            $options = [];
            
            if ($data !== null) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $result = json_decode($response->getBody()->getContents(), true);

            // Проверяем на ошибку токена
            if ($response->getStatusCode() === 401) {
                $this->refreshAccessToken();
                return $this->makeRequest($method, $endpoint, $data);
            }

            return $result ?? [];
        } catch (\Exception $e) {
            // Если токен истек, пробуем обновить
            if (strpos($e->getMessage(), '401') !== false && $this->refreshToken) {
                $this->refreshAccessToken();
                return $this->makeRequest($method, $endpoint, $data);
            }
            
            Log::error('AmoCRM API request failed', [
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
            $response = $this->client->post("https://{$this->subdomain}.amocrm.ru/oauth2/access_token", [
                'json' => [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                    'redirect_uri' => $this->config['redirect_uri'],
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

                // Обновляем заголовки клиента
                $this->client = new Client([
                    'base_uri' => "https://{$this->subdomain}.amocrm.ru/api/v4/",
                    'timeout' => 30,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('AmoCRM token refresh failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // Вспомогательные методы для AmoCRM
    
    protected function findContactByEmail(string $email): ?array
    {
        $response = $this->makeRequest('GET', 'contacts', [
            'query' => ['query' => $email]
        ]);
        return $response['_embedded']['contacts'][0] ?? null;
    }

    protected function findContactByPhone(string $phone): ?array
    {
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
        $response = $this->makeRequest('GET', 'contacts', [
            'query' => ['query' => $normalizedPhone]
        ]);
        return $response['_embedded']['contacts'][0] ?? null;
    }

    protected function getFieldId(string $entityType, string $fieldCode): ?int
    {
        // Здесь должен быть маппинг ID полей из настроек интеграции
        // Для примера возвращаем стандартные ID
        $fields = [
            'contacts' => [
                'EMAIL' => 265789,
                'PHONE' => 265791,
            ],
            'leads' => [
                'SOURCE' => 685521,
            ],
        ];
        
        return $fields[$entityType][$fieldCode] ?? null;
    }

    protected function getEnumId(string $fieldType, string $enumCode): ?int
    {
        // Маппинг enum значений
        $enums = [
            'EMAIL' => ['WORK' => 138289],
            'PHONE' => ['WORK' => 138291],
        ];
        
        return $enums[$fieldType][$enumCode] ?? null;
    }

    protected function getDefaultStatusId(string $entityType): int
    {
        return 143; // ID первого этапа воронки
    }

    protected function getDefaultPipelineId(): int
    {
        return 1; // ID основной воронки
    }

    protected function getEntityTypeId(string $type): int
    {
        return match($type) {
            'leads' => 2,
            'contacts' => 1,
            'companies' => 3,
            'tasks' => 4,
            default => 0,
        };
    }

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

    protected function handleLeadStatusChange(string $leadId, array $event): void
    {
        // Обработка изменения статуса лида
        $syncEntity = $this->integration->getSyncEntity('lead', $leadId);
        if (!$syncEntity) return;
        
        $newStatusId = $event['status_id'] ?? null;
        
        Log::info('AmoCRM lead status changed', [
            'lead_id' => $leadId,
            'new_status' => $newStatusId,
        ]);
    }

    protected function handleContactUpdate(string $contactId, array $event): void
    {
        // Обработка обновления контакта
        Log::info('AmoCRM contact updated', [
            'contact_id' => $contactId,
        ]);
    }

    protected function handleTaskCreated(string $taskId, array $event): void
    {
        // Обработка создания задачи
        Log::info('AmoCRM task created', [
            'task_id' => $taskId,
        ]);
    }

    protected function handleIncomingMessage(array $event): void
    {
        // Обработка входящего сообщения из чата AmoCRM
        Log::info('AmoCRM incoming message', [
            'event' => $event,
        ]);
    }

    protected function prepareContactData(array $data): array
    {
        // Подготовка данных контакта для батч-операции
        return [
            'name' => $data['name'] ?? 'Без имени',
            'custom_fields_values' => $data['custom_fields'] ?? [],
        ];
    }

    protected function prepareLeadData(array $data): array
    {
        // Подготовка данных лида для батч-операции
        return [
            'name' => $data['name'] ?? 'Новый лид',
            'price' => $data['price'] ?? 0,
            'status_id' => $data['status_id'] ?? $this->getDefaultStatusId('leads'),
            'pipeline_id' => $data['pipeline_id'] ?? $this->getDefaultPipelineId(),
        ];
    }

    /**
     * Проверка соединения с CRM
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', 'account');
            return !empty($response['id']);
        } catch (\Exception $e) {
            Log::error('AmoCRM connection test failed', [
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

            $customFields = [];
            
            // Подготавливаем кастомные поля
            if (!empty($contactData['email'])) {
                $customFields[] = [
                    'field_id' => $this->getFieldId('contacts', 'EMAIL'),
                    'values' => [
                        ['value' => $contactData['email'], 'enum_id' => $this->getEnumId('EMAIL', 'WORK')]
                    ]
                ];
            }
            
            if (!empty($contactData['phone'])) {
                $customFields[] = [
                    'field_id' => $this->getFieldId('contacts', 'PHONE'),
                    'values' => [
                        ['value' => $contactData['phone'], 'enum_id' => $this->getEnumId('PHONE', 'WORK')]
                    ]
                ];
            }

            $data = [
                'name' => $contactData['name'] ?? 'Без имени',
                'custom_fields_values' => $customFields,
            ];

            if ($existingContact) {
                // Обновляем существующий контакт
                $response = $this->makeRequest('PATCH', "contacts/{$existingContact['id']}", $data);
                return ['id' => $existingContact['id']];
            } else {
                // Создаем новый контакт
                $response = $this->makeRequest('POST', 'contacts', [$data]);
                return ['id' => $response['_embedded']['contacts'][0]['id'] ?? null];
            }
        } catch (\Exception $e) {
            Log::error('AmoCRM sync contact failed', [
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
                'name' => 'Обращение из чат-бота #' . $conversation->id,
                'price' => $additionalData['price'] ?? 0,
                'status_id' => $additionalData['status_id'] ?? $this->getDefaultStatusId('leads'),
                'pipeline_id' => $additionalData['pipeline_id'] ?? $this->getDefaultPipelineId(),
                'created_by' => 0,
                'custom_fields_values' => [],
            ];

            // Добавляем источник
            if (!empty($additionalData['source_id'])) {
                $leadData['custom_fields_values'][] = [
                    'field_id' => $this->getFieldId('leads', 'SOURCE'),
                    'values' => [['value' => $additionalData['source_id']]]
                ];
            }

            // Привязываем контакт
            if ($conversation->crm_contact_id) {
                $leadData['_embedded'] = [
                    'contacts' => [
                        ['id' => (int)$conversation->crm_contact_id]
                    ]
                ];
            }

            // Добавляем ответственного
            if (!empty($additionalData['responsible_user_id'])) {
                $leadData['responsible_user_id'] = $additionalData['responsible_user_id'];
            }

            // Добавляем теги
            if (!empty($additionalData['tags'])) {
                $leadData['_embedded']['tags'] = array_map(function($tag) {
                    return ['name' => $tag];
                }, $additionalData['tags']);
            }

            // Добавляем примечание с историей диалога
            $noteText = $this->formatConversationForCRM($conversation);

            $response = $this->makeRequest('POST', 'leads', [$leadData]);

            if (!empty($response['_embedded']['leads'][0]['id'])) {
                $leadId = $response['_embedded']['leads'][0]['id'];
                
                // Добавляем примечание
                $this->addNote('leads', $leadId, $noteText);
                
                // Сохраняем связь в БД
                $this->integration->createSyncEntity(
                    'lead',
                    $conversation->id,
                    $leadId,
                    $leadData
                );

                // Обновляем диалог
                $conversation->update(['crm_lead_id' => $leadId]);
            }

            return $response['_embedded']['leads'][0] ?? [];
        } catch (\Exception $e) {
            Log::error('AmoCRM create lead failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

    

    