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
        $this->config = [
            'credentials' => $integration->credentials ?? [],
            'settings' => $integration->settings ?? [],
        ];
        
        $credentials = $integration->credentials ?? [];
        
        $this->subdomain = $credentials['subdomain'] ?? '';
        $this->accessToken = $credentials['access_token'] ?? null;
        $this->refreshToken = $credentials['refresh_token'] ?? null;
        
        $this->client = new Client([
            'base_uri' => "https://{$this->subdomain}.amocrm.ru",
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
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
            $response = $this->makeRequest('GET', 'api/v4/account');
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
     * Создание сделки (реализация интерфейса)
     */
    public function createDeal(Conversation $conversation, array $additionalData = []): array
    {
        try {
            $result = [];
            
            // 1. Собираем данные пользователя
            $userData = $this->extractUserData($conversation);
            
            // 2. Создаем/обновляем контакт
            $contact = null;
            if (!empty($userData['email']) || !empty($userData['phone'])) {
                $contact = $this->createOrUpdateContact($userData);
                $result['contact_id'] = $contact['id'] ?? null;
            }
            
            // 3. Подготавливаем данные лида
            $leadData = array_merge([
                'name' => $this->generateLeadName($conversation),
                'price' => $this->estimateLeadValue($conversation),
            ], $additionalData);
            
            if (isset($contact['id'])) {
                $leadData['contacts_id'] = [$contact['id']];
            }
            
            // 4. Создаем лид через внутренний метод
            $lead = $this->createLeadInternal($leadData);
            $leadId = $lead['_embedded']['leads'][0]['id'] ?? null;
            
            if (!$leadId) {
                throw new \Exception('Failed to create lead - no ID returned');
            }
            
            $result['lead_id'] = $leadId;
            
            // 5. Добавляем примечания
            $this->addConversationNotes($leadId, $conversation);
            
            // 6. Обновляем кастомные поля
            $this->updateLeadCustomFields($leadId, $conversation);
            
            // 7. Сохраняем связь в metadata
            $metadata = is_array($conversation->metadata) 
                ? $conversation->metadata 
                : json_decode($conversation->metadata ?? '[]', true);
                
            $metadata['amocrm_lead_id'] = $leadId;
            $conversation->update(['metadata' => $metadata]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('AmoCRM create deal failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Внутренний метод создания лида (для использования внутри класса)
     */
    protected function createLeadInternal(array $data): array
    {
        info('createLeadInternal');
        info($this->config['settings']);
        // Берем pipeline_id из настроек интеграции
        $pipelineId = $this->config['settings']['default_pipeline_id'] ?? null;
        
        if (!$pipelineId) {
            throw new \Exception('Pipeline ID not configured in CRM settings');
        }
        
        $leadData = [
            'name' => $data['name'] ?? 'Новая заявка из чата',
            'pipeline_id' => (int) $pipelineId,
            'status_id' => (int) ($this->config['settings']['default_status_id'] ?? 0),
            'created_by' => 0,
        ];

        if (isset($data['price'])) {
            $leadData['price'] = (int) $data['price'];
        }

        if (isset($data['responsible_user_id'])) {
            $leadData['responsible_user_id'] = (int) $data['responsible_user_id'];
        } elseif (isset($this->config['settings']['default_responsible_id'])) {
            $leadData['responsible_user_id'] = (int) $this->config['settings']['default_responsible_id'];
        }
        info($leadData);
        
        // Привязка контактов к лиду
        if (isset($data['contacts_id']) && is_array($data['contacts_id'])) {
            $leadData['_embedded'] = [
                'contacts' => array_map(fn($id) => ['id' => (int) $id], $data['contacts_id'])
            ];
        }

        $response = $this->client->post('api/v4/leads', [
            'json' => [$leadData]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Создание лида (реализация интерфейса)
     */
    public function createLead(Conversation $conversation, array $additionalData = []): array
    {
        // В AmoCRM лид и сделка - это одно и то же
        return $this->createDeal($conversation, $additionalData);
    }

    /**
     * Обновление лида (реализация интерфейса)
     */
    public function updateLead(string $leadId, array $data): array
    {
        // В AmoCRM лид и сделка - это одно и то же
        return $this->updateDeal($leadId, $data);
    }
    /**
     * Обновляет существующий лид
     */
    public function updateDeal(string $dealId, array $data): array
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['price'])) {
            $updateData['price'] = (int) $data['price'];
        }
        
        if (isset($data['status_id'])) {
            $updateData['status_id'] = (int) $data['status_id'];
        }
        
        if (isset($data['custom_fields_values'])) {
            $updateData['custom_fields_values'] = $data['custom_fields_values'];
        }
        
        $response = $this->client->patch("api/v4/leads/" . (int) $dealId, [
            'json' => $updateData
        ]);
        
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Добавление примечания/комментария
     */
    public function addNote(string $entityType, string $entityId, string $note): bool
    {
        try {
            $noteData = [
                'entity_id' => (int) $entityId,
                'note_type' => 'common',
                'params' => [
                    'text' => $note
                ]
            ];

            $response = $this->makeRequest('POST', "api/v4/{$entityType}/notes", [$noteData]);
            
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
            $response = $this->makeRequest('GET', 'api/v4/users');
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
            $response = $this->makeRequest('GET', 'api/v4/leads/pipelines');
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
            $response = $this->makeRequest('GET', "api/v4/leads/pipelines/{$pipelineId}");
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
                'lead', 'deal' => "api/v4/leads/{$entityId}",
                'contact' => "api/v4/contacts/{$entityId}",
                'company' => "api/v4/companies/{$entityId}",
                'task' => "api/v4/tasks/{$entityId}",
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
     * Синхронизация conversation с AmoCRM
     */
    public function syncConversation(Conversation $conversation): bool
    {
        try {
            // Проверяем, есть ли уже лид для этого conversation
            $metadata = is_array($conversation->metadata) 
                ? $conversation->metadata 
                : json_decode($conversation->metadata ?? '[]', true);
                
            $existingLeadId = $metadata['amocrm_lead_id'] ?? null;
            
            if ($existingLeadId) {
                // Обновляем существующий лид
                $result = $this->updateExistingLead($existingLeadId, $conversation);
            } else {
                // Создаем новый лид через интерфейсный метод
                $result = $this->createDeal($conversation);
            }
            
            // Логируем успешную синхронизацию
            $this->integration->logSync(
                'outgoing',
                'conversation',
                $existingLeadId ? 'update' : 'create',
                ['conversation_id' => $conversation->id],
                $result,
                'success'
            );
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('AmoCRM sync conversation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            
            // Логируем ошибку синхронизации
            $this->integration->logSync(
                'outgoing',
                'conversation',
                'sync',
                ['conversation_id' => $conversation->id],
                [],
                'error',
                $e->getMessage()
            );
            
            return false;
        }
    }

    /**
     * Обновление существующего лида
     */
    protected function updateExistingLead(int $leadId, Conversation $conversation): array
    {
        try {
            // Сначала проверяем, существует ли лид
            try {
                $lead = $this->getEntity('lead', (string) $leadId);
                
                if (!$lead) {
                    throw new \Exception('Lead not found');
                }
                
            } catch (\Exception $e) {
                // Лид не найден - создаем новый
                Log::warning('Lead not found during update, creating new', [
                    'old_lead_id' => $leadId,
                    'conversation_id' => $conversation->id
                ]);
                
                // Очищаем старый ID
                $metadata = is_array($conversation->metadata) 
                    ? $conversation->metadata 
                    : json_decode($conversation->metadata ?? '[]', true);
                
                unset($metadata['amocrm_lead_id']);
                $conversation->update(['metadata' => $metadata]);
                
                // Создаем новый лид
                $result = $this->createDeal($conversation);
                return array_merge($result, ['action' => 'recreated']);
            }
            
            // Лид существует - обновляем
            $this->addConversationNotes($leadId, $conversation);
            
            // Обновляем статус если нужно
            $statusId = $this->determineStatusFromConversation($conversation);
            if ($statusId) {
                $this->updateDeal((string) $leadId, ['status_id' => $statusId]);
            }
            
            return ['lead_id' => $leadId, 'action' => 'updated'];
            
        } catch (\Exception $e) {
            Log::error('AmoCRM update existing lead failed', [
                'lead_id' => $leadId,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Извлекает данные пользователя из conversation
     */
    protected function extractUserData(Conversation $conversation): array
    {
        $data = [
            'name' => $conversation->user_name ?? 'Клиент из чата',
            'email' => $conversation->user_email ?? null,
            'phone' => $conversation->user_phone ?? null,
        ];
        
        // Пытаемся извлечь данные из metadata
        if ($conversation->metadata) {
            $metadata = is_string($conversation->metadata) 
                ? json_decode($conversation->metadata, true) 
                : $conversation->metadata;
                
            $data['email'] = $data['email'] ?? ($metadata['email'] ?? null);
            $data['phone'] = $data['phone'] ?? ($metadata['phone'] ?? null);
            
            // Если есть данные из Avito
            if (isset($metadata['avito_user'])) {
                $data['name'] = $metadata['avito_user']['name'] ?? $data['name'];
                $data['phone'] = $metadata['avito_user']['phone'] ?? $data['phone'];
            }
        }
        
        // Пытаемся найти email/phone в сообщениях (только последние 10 от пользователя)
        $messages = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        foreach ($messages as $message) {
            if (!$data['email'] && preg_match('/[\w\-\.]+@[\w\-\.]+\.\w+/', $message->content, $matches)) {
                $data['email'] = $matches[0];
            }
            if (!$data['phone'] && preg_match('/\+?[0-9]{10,15}/', $message->content, $matches)) {
                $data['phone'] = $matches[0];
            }
            
            // Если нашли оба - прерываем
            if ($data['email'] && $data['phone']) {
                break;
            }
        }
        
        return array_filter($data); // Убираем null значения
    }

    /**
     * Генерирует название лида на основе conversation
     */
    protected function generateLeadName(Conversation $conversation): string
    {
        $source = '';
        if ($conversation->metadata) {
            $metadata = is_string($conversation->metadata) 
                ? json_decode($conversation->metadata, true) 
                : $conversation->metadata;
            
            if (isset($metadata['source'])) {
                $source = " ({$metadata['source']})";
            }
            
            // Для Avito добавляем название товара
            if (isset($metadata['avito_item_title'])) {
                return "Заявка: {$metadata['avito_item_title']}" . $source;
            }
        }
        
        $botName = $conversation->bot->name ?? 'бот';
        return "Обращение к {$botName}" . $source . " #{$conversation->id}";
    }

    /**
     * Оценивает потенциальную ценность лида
     */
    protected function estimateLeadValue(Conversation $conversation): ?int
    {
        if ($conversation->metadata) {
            $metadata = is_string($conversation->metadata) 
                ? json_decode($conversation->metadata, true) 
                : $conversation->metadata;
            
            if (isset($metadata['avito_item_price'])) {
                return (int) $metadata['avito_item_price'];
            }
        }
        
        return null; // AmoCRM позволяет создавать лиды без цены
    }

    /**
     * Добавляет историю диалога в примечания лида
     */
    protected function addConversationNotes(int $leadId, Conversation $conversation): void
    {
        try {
            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Формируем текст диалога
            $dialogText = "📝 История диалога (ID: {$conversation->id})\n";
            $dialogText .= "Дата: " . $conversation->created_at->format('d.m.Y H:i') . "\n";
            $dialogText .= "Пользователь: " . ($conversation->user_name ?? 'Не указан') . "\n\n";
            $dialogText .= "--- ДИАЛОГ ---\n\n";
            
            foreach ($messages as $message) {
                $sender = $message->is_bot ? '🤖 Бот' : '👤 Клиент';
                $time = $message->created_at->format('H:i');
                $dialogText .= "[{$time}] {$sender}:\n{$message->content}\n\n";
            }
            
            // Добавляем метаданные если есть
            if ($conversation->metadata) {
                $metadata = is_string($conversation->metadata) 
                    ? json_decode($conversation->metadata, true) 
                    : $conversation->metadata;
                
                $dialogText .= "\n--- ДОПОЛНИТЕЛЬНО ---\n";
                if (isset($metadata['source'])) {
                    $dialogText .= "Источник: {$metadata['source']}\n";
                }
                if (isset($metadata['avito_item_url'])) {
                    $dialogText .= "Объявление: {$metadata['avito_item_url']}\n";
                }
            }
            
            // Отправляем как примечание
            $this->client->post('api/v4/leads/notes', [
                'json' => [
                    [
                        'entity_id' => (int) $leadId,
                        'note_type' => 'common',
                        'params' => [
                            'text' => $dialogText
                        ]
                    ]
                ]
            ]);
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Проверяем, что лид не найден (код 226 - лид удален)
            if ($e->getResponse()->getStatusCode() === 400) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                
                // Если лид удален или не существует
                if (strpos($responseBody, '"code":226') !== false || 
                    strpos($responseBody, 'element_id') !== false) {
                    
                    Log::warning('Lead not found or deleted, recreating', [
                        'lead_id' => $leadId,
                        'conversation_id' => $conversation->id
                    ]);
                    
                    // Очищаем старый ID из metadata
                    $metadata = is_array($conversation->metadata) 
                        ? $conversation->metadata 
                        : json_decode($conversation->metadata ?? '[]', true);
                    
                    unset($metadata['amocrm_lead_id']);
                    $conversation->update(['metadata' => $metadata]);
                    
                    // Создаем лид заново
                    try {
                        $result = $this->createDeal($conversation);
                        
                        Log::info('Lead recreated successfully', [
                            'old_lead_id' => $leadId,
                            'new_lead_id' => $result['lead_id'] ?? null,
                            'conversation_id' => $conversation->id
                        ]);
                        
                    } catch (\Exception $createError) {
                        Log::error('Failed to recreate lead', [
                            'conversation_id' => $conversation->id,
                            'error' => $createError->getMessage()
                        ]);
                    }
                    
                    return;
                }
            }
            
            Log::error('AmoCRM add conversation notes failed', [
                'lead_id' => $leadId,
                'conversation_id' => $conversation->id,
                'status_code' => $e->getResponse()->getStatusCode(),
                'error' => $e->getMessage()
            ]);
            
        } catch (\Exception $e) {
            Log::error('AmoCRM add conversation notes failed', [
                'lead_id' => $leadId,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Обновляет кастомные поля лида
     */
    protected function updateLeadCustomFields(int $leadId, Conversation $conversation): void
    {
        try {
            $customFields = [];
            
            // Добавляем ссылку на чат в вашей системе
            $chatUrl = config('app.url') . "/conversations/{$conversation->id}";
            
            if ($fieldId = $this->getCustomFieldId('chat_url')) {
                $customFields[] = [
                    'field_id' => $fieldId,
                    'values' => [['value' => $chatUrl]]
                ];
            }
            
            if ($fieldId = $this->getCustomFieldId('bot_id')) {
                $customFields[] = [
                    'field_id' => $fieldId,
                    'values' => [['value' => (string) $conversation->bot_id]]
                ];
            }
            
            if (!empty($customFields)) {
                $this->client->patch("api/v4/leads/{$leadId}", [
                    'json' => [
                        'custom_fields_values' => $customFields
                    ]
                ]);
            }
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Если лид не найден - пропускаем, он будет пересоздан в addConversationNotes
            if ($e->getResponse()->getStatusCode() === 400) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                if (strpos($responseBody, '"code":226') !== false) {
                    Log::warning('Lead deleted, skipping custom fields update', [
                        'lead_id' => $leadId,
                        'conversation_id' => $conversation->id
                    ]);
                    return;
                }
            }
            
            Log::error('AmoCRM update custom fields failed', [
                'lead_id' => $leadId,
                'error' => $e->getMessage()
            ]);
            
        } catch (\Exception $e) {
            Log::error('AmoCRM update custom fields failed', [
                'lead_id' => $leadId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Получает ID кастомного поля по его коду
     */
    protected function getCustomFieldId(string $code): ?int
    {
        // Кешируем поля чтобы не запрашивать каждый раз
        $cacheKey = "amocrm_custom_fields_{$this->config['credentials']['subdomain']}";
        
        try {
            $fields = cache()->remember($cacheKey, 3600, function() {
                $response = $this->client->get('api/v4/leads/custom_fields');
                $data = json_decode($response->getBody()->getContents(), true);
                
                $fieldsMap = [];
                foreach ($data['_embedded']['custom_fields'] ?? [] as $field) {
                    $fieldsMap[$field['code']] = $field['id'];
                }
                return $fieldsMap;
            });
            
            return $fields[$code] ?? null;
        } catch (\Exception $e) {
            Log::error('AmoCRM get custom field ID failed', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Определяет статус лида на основе состояния conversation
     */
    protected function determineStatusFromConversation(Conversation $conversation): ?int
    {
        return match($conversation->status) {
            'completed' => $this->config['settings']['completed_status_id'] ?? null,
            'active' => $this->config['settings']['active_status_id'] ?? null,
            'pending' => $this->config['settings']['pending_status_id'] ?? null,
            default => null,
        };
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
                'lead', 'deal' => 'api/v4/leads/custom_fields',
                'contact' => 'api/v4/contacts/custom_fields',
                'company' => 'api/v4/companies/custom_fields',
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
                        $response = $this->makeRequest('POST', "api/v4/{$type}", $items);
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
                $emailFieldId = $this->getFieldId('contacts', 'EMAIL');
                if ($emailFieldId) {
                    $customFields[] = [
                        'field_id' => $emailFieldId,
                        'values' => [
                            [
                                'value' => $contactData['email'],
                                'enum_code' => 'WORK'
                            ]
                        ]
                    ];
                }
            }
            
            if (!empty($contactData['phone'])) {
                $phoneFieldId = $this->getFieldId('contacts', 'PHONE');
                if ($phoneFieldId) {
                    $customFields[] = [
                        'field_id' => $phoneFieldId,
                        'values' => [
                            [
                                'value' => $contactData['phone'],
                                'enum_code' => 'WORK'
                            ]
                        ]
                    ];
                }
            }

            $data = [
                'name' => $contactData['name'] ?? 'Без имени',
            ];
            
            if (!empty($customFields)) {
                $data['custom_fields_values'] = $customFields;
            }

            if ($existingContact) {
                // Обновляем существующий контакт
                $response = $this->makeRequest('PATCH', "api/v4/contacts/{$existingContact['id']}", $data);
                return ['id' => $existingContact['id'], 'action' => 'updated'];
            } else {
                // Создаем новый контакт
                $response = $this->makeRequest('POST', 'api/v4/contacts', [$data]);
                $contactId = $response['_embedded']['contacts'][0]['id'] ?? null;
                return ['id' => $contactId, 'action' => 'created'];
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
     * Алиас для syncContact
     */
    protected function createOrUpdateContact(array $userData): array
    {
        return $this->syncContact($userData);
    }

    // ===================== PRIVATE МЕТОДЫ =====================

    /**
     * Выполнение запроса к API
     */
    protected function makeRequest(string $method, string $endpoint, $data = null, array $queryParams = []): array
    {
        try {
            $options = [];
            
            if ($data !== null) {
                $options['json'] = $data;
            }
            
            if (!empty($queryParams)) {
                $options['query'] = $queryParams;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $result = json_decode($response->getBody()->getContents(), true);

            // Проверяем на ошибку токена
            if ($statusCode === 401) {
                $this->refreshAccessToken();
                return $this->makeRequest($method, $endpoint, $data, $queryParams);
            }

            return $result ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Если токен истек, пробуем обновить
            if ($e->getResponse()->getStatusCode() === 401 && $this->refreshToken) {
                $this->refreshAccessToken();
                return $this->makeRequest($method, $endpoint, $data, $queryParams);
            }
            Log::error('AmoCRM API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status_code' => $e->getResponse()->getStatusCode(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
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
            $client = new Client(['timeout' => 30]);
            
            $response = $client->post("https://{$this->subdomain}.amocrm.ru/oauth2/access_token", [
                'json' => [
                    'client_id' => $this->config['credentials']['client_id'],
                    'client_secret' => $this->config['credentials']['client_secret'],
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                    'redirect_uri' => $this->config['credentials']['redirect_uri'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                $this->refreshToken = $data['refresh_token'];

                // Обновляем в БД
                $credentials = $this->config['credentials'];
                $credentials['access_token'] = $this->accessToken;
                $credentials['refresh_token'] = $this->refreshToken;
                
                $this->integration->update([
                    'credentials' => $credentials,
                ]);
                
                $this->config['credentials'] = $credentials;

                // Обновляем заголовки клиента
                $this->client = new Client([
                    'base_uri' => "https://{$this->subdomain}.amocrm.ru",
                    'timeout' => 30,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json',
                    ],
                ]);
                
                Log::info('AmoCRM token refreshed successfully', [
                    'integration_id' => $this->integration->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('AmoCRM token refresh failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Поиск контакта по email
     */
    protected function findContactByEmail(string $email): ?array
    {
        try {
            $response = $this->makeRequest('GET', 'api/v4/contacts', null, ['query' => $email]);
            return $response['_embedded']['contacts'][0] ?? null;
        } catch (\Exception $e) {
            Log::error('AmoCRM find contact by email failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Поиск контакта по телефону
     */
    protected function findContactByPhone(string $phone): ?array
    {
        try {
            $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
            $response = $this->makeRequest('GET', 'api/v4/contacts', null, ['query' => $normalizedPhone]);
            return $response['_embedded']['contacts'][0] ?? null;
        } catch (\Exception $e) {
            Log::error('AmoCRM find contact by phone failed', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Получение ID поля по коду
     */
    protected function getFieldId(string $entityType, string $fieldCode): ?int
    {
        $cacheKey = "amocrm_field_ids_{$this->subdomain}_{$entityType}";
        
        try {
            $fields = cache()->remember($cacheKey, 3600, function() use ($entityType) {
                $endpoint = match($entityType) {
                    'contacts' => 'api/v4/contacts/custom_fields',
                    'leads' => 'api/v4/leads/custom_fields',
                    'companies' => 'api/v4/companies/custom_fields',
                    default => null,
                };
                
                if (!$endpoint) {
                    return [];
                }
                
                $response = $this->client->get($endpoint);
                $data = json_decode($response->getBody()->getContents(), true);
                
                $fieldsMap = [];
                foreach ($data['_embedded']['custom_fields'] ?? [] as $field) {
                    $fieldsMap[$field['code']] = $field['id'];
                }
                return $fieldsMap;
            });
            
            return $fields[$fieldCode] ?? null;
        } catch (\Exception $e) {
            Log::error('AmoCRM get field ID failed', [
                'entity_type' => $entityType,
                'field_code' => $fieldCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Получение ID enum значения
     */
    protected function getEnumId(string $fieldType, string $enumCode): ?int
    {
        // Стандартные enum коды для AmoCRM
        $enums = [
            'EMAIL' => ['WORK' => 'WORK', 'PRIV' => 'PRIV', 'OTHER' => 'OTHER'],
            'PHONE' => ['WORK' => 'WORK', 'WORKDD' => 'WORKDD', 'MOB' => 'MOB', 'FAX' => 'FAX', 'HOME' => 'HOME', 'OTHER' => 'OTHER'],
        ];
        
        return $enums[$fieldType][$enumCode] ?? null;
    }

    /**
     * Получение ID типа сущности
     */
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

    /**
     * Форматирование диалога для CRM
     */
    protected function formatConversationForCRM(Conversation $conversation): string
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        $text = "=== Диалог из чат-бота ===\n";
        
        // Безопасная проверка наличия канала
        if ($conversation->channel) {
            $text .= "Канал: {$conversation->channel->getTypeName()}\n";
        }
        
        $text .= "Начат: {$conversation->created_at->format('d.m.Y H:i')}\n";
        
        // Безопасное получение имени пользователя
        $userName = method_exists($conversation, 'getUserDisplayName') 
            ? $conversation->getUserDisplayName() 
            : ($conversation->user_name ?? 'Не указан');
        
        $text .= "Пользователь: {$userName}\n";
        $text .= "\n--- История сообщений ---\n\n";

        foreach ($messages as $message) {
            // Безопасное получение роли
            $role = method_exists($message, 'getRoleName') 
                ? $message->getRoleName() 
                : ($message->role != 'user' ? 'Бот' : 'Пользователь');
            
            $time = $message->created_at->format('H:i:s');
            $text .= "[{$time}] {$role}: {$message->content}\n\n";
        }

        return $text;
    }

    /**
     * Обработка изменения статуса лида
     */
    protected function handleLeadStatusChange(string $leadId, array $event): void
    {
        try {
            $newStatusId = $event['status_id'] ?? null;
            
            Log::info('AmoCRM lead status changed', [
                'lead_id' => $leadId,
                'new_status' => $newStatusId,
            ]);
            
            // Здесь можно добавить логику обновления статуса conversation
            // на основе изменения статуса в AmoCRM
        } catch (\Exception $e) {
            Log::error('AmoCRM handle lead status change failed', [
                'lead_id' => $leadId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Обработка обновления контакта
     */
    protected function handleContactUpdate(string $contactId, array $event): void
    {
        Log::info('AmoCRM contact updated', [
            'contact_id' => $contactId,
        ]);
    }

    /**
     * Обработка создания задачи
     */
    protected function handleTaskCreated(string $taskId, array $event): void
    {
        Log::info('AmoCRM task created', [
            'task_id' => $taskId,
        ]);
    }

    /**
     * Обработка входящего сообщения из чата AmoCRM
     */
    protected function handleIncomingMessage(array $event): void
    {
        Log::info('AmoCRM incoming message', [
            'event' => $event,
        ]);
    }

    /**
     * Подготовка данных контакта для батч-операции
     */
    protected function prepareContactData(array $data): array
    {
        $contactData = [
            'name' => $data['name'] ?? 'Без имени',
        ];
        
        if (!empty($data['custom_fields'])) {
            $contactData['custom_fields_values'] = $data['custom_fields'];
        }
        
        return $contactData;
    }

    /**
     * Подготовка данных лида для батч-операции
     */
    protected function prepareLeadData(array $data): array
    {
        $pipelineId = $this->config['settings']['default_pipeline_id'] ?? null;
        
        $leadData = [
            'name' => $data['name'] ?? 'Новый лид',
            'price' => $data['price'] ?? 0,
        ];
        
        if (isset($data['status_id'])) {
            $leadData['status_id'] = (int) $data['status_id'];
        } elseif (isset($this->config['settings']['default_status_id'])) {
            $leadData['status_id'] = (int) $this->config['settings']['default_status_id'];
        }
        
        if ($pipelineId) {
            $leadData['pipeline_id'] = (int) $pipelineId;
        }
        
        return $leadData;
    }
}