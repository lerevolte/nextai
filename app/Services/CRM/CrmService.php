<?php

namespace App\Services\CRM;

use App\Models\CrmIntegration;
use App\Models\Conversation;
use App\Services\CRM\Providers\Bitrix24Provider;
use App\Services\CRM\Providers\AmoCRMProvider;
use App\Services\CRM\Providers\AvitoProvider;
use App\Services\CRM\Providers\SalebotProvider;
use Illuminate\Support\Facades\Log;

class CrmService
{
    protected array $providers = [
        'bitrix24' => Bitrix24Provider::class,
        'amocrm' => AmoCRMProvider::class,
        'avito' => AvitoProvider::class,
        'salebot' => SalebotProvider::class,
    ];

    /**
     * Получить провайдер для интеграции
     */
    public function getProvider(CrmIntegration $integration): ?CrmProviderInterface
    {
        $providerClass = $this->providers[$integration->type] ?? null;
        
        if (!$providerClass) {
            Log::error('Unknown CRM provider type', ['type' => $integration->type]);
            return null;
        }
        
        try {
            return new $providerClass($integration);
        } catch (\Exception $e) {
            Log::error('Failed to create CRM provider', [
                'type' => $integration->type,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Проверить подключение к CRM
     */
    public function testConnection(CrmIntegration $integration): bool
    {
        $provider = $this->getProvider($integration);
        
        if (!$provider) {
            return false;
        }
        
        return $provider->testConnection();
    }

    /**
     * Синхронизировать диалог со всеми подключенными CRM
     */
    public function syncConversation(Conversation $conversation): array
    {
        $results = [];
        
        // Получаем все активные интеграции для бота
        $integrations = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->get();
        
        foreach ($integrations as $integration) {
            $provider = $this->getProvider($integration);
            
            if (!$provider) {
                $results[$integration->type] = [
                    'success' => false,
                    'error' => 'Provider not available',
                ];
                continue;
            }
            
            try {
                $success = $provider->syncConversation($conversation);
                $results[$integration->type] = [
                    'success' => $success,
                ];
            } catch (\Exception $e) {
                Log::error('CRM sync failed', [
                    'integration_id' => $integration->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
                
                $results[$integration->type] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Создать лид во всех подключенных CRM
     */
    public function createLead(Conversation $conversation, array $additionalData = []): array
    {
        $results = [];
        
        $integrations = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->wherePivot('create_leads', true)
            ->get();
        
        foreach ($integrations as $integration) {
            $provider = $this->getProvider($integration);
            
            if (!$provider) {
                continue;
            }
            
            try {
                $settings = $integration->pivot;
                
                $leadData = array_merge($additionalData, [
                    'source_id' => $settings->lead_source,
                    'responsible_user_id' => $settings->responsible_user_id,
                ]);
                
                if ($settings->pipeline_settings) {
                    $leadData = array_merge($leadData, $settings->pipeline_settings);
                }
                
                $result = $provider->createLead($conversation, $leadData);
                $results[$integration->type] = $result;
                
            } catch (\Exception $e) {
                Log::error('Failed to create lead', [
                    'integration_id' => $integration->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Создать сделку во всех подключенных CRM
     */
    public function createDeal(Conversation $conversation, array $additionalData = []): array
    {
        $results = [];
        
        $integrations = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->wherePivot('create_deals', true)
            ->get();
        
        foreach ($integrations as $integration) {
            $provider = $this->getProvider($integration);
            
            if (!$provider) {
                continue;
            }
            
            try {
                $settings = $integration->pivot;
                
                $dealData = array_merge($additionalData, [
                    'responsible_user_id' => $settings->responsible_user_id,
                ]);
                
                if ($settings->pipeline_settings) {
                    $dealData = array_merge($dealData, $settings->pipeline_settings);
                }
                
                $result = $provider->createDeal($conversation, $dealData);
                $results[$integration->type] = $result;
                
            } catch (\Exception $e) {
                Log::error('Failed to create deal', [
                    'integration_id' => $integration->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Обработать webhook от CRM
     */
    public function handleWebhook(string $integrationType, array $data, string $signature = null): bool
    {
        // Находим интеграцию по типу и подписи
        $integration = $this->findIntegrationByWebhook($integrationType, $signature);
        
        if (!$integration) {
            Log::warning('CRM integration not found for webhook', [
                'type' => $integrationType,
            ]);
            return false;
        }
        
        $provider = $this->getProvider($integration);
        
        if (!$provider) {
            return false;
        }
        
        try {
            $provider->handleWebhook($data);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to handle CRM webhook', [
                'integration_type' => $integrationType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить доступные типы CRM
     */
    public function getAvailableTypes(): array
    {
        return [
            'bitrix24' => [
                'name' => 'Битрикс24',
                'description' => 'Интеграция с Битрикс24 CRM и открытыми линиями',
                'icon' => '🏢',
                'features' => [
                    'leads' => true,
                    'deals' => true,
                    'contacts' => true,
                    'tasks' => true,
                    'open_lines' => true,
                ],
            ],
            'amocrm' => [
                'name' => 'AmoCRM',
                'description' => 'Интеграция с AmoCRM',
                'icon' => '📊',
                'features' => [
                    'leads' => true,
                    'deals' => true,
                    'contacts' => true,
                    'tasks' => true,
                    'pipelines' => true,
                ],
            ],
            'avito' => [
                'name' => 'Avito',
                'description' => 'Интеграция с Avito Messenger',
                'icon' => '🏪',
                'features' => [
                    'chats' => true,
                    'messages' => true,
                    'items' => true,
                    'statistics' => true,
                ],
            ],
        ];
        return [
            'bitrix24' => [
                'name' => 'Битрикс24',
                'description' => 'Интеграция с Битрикс24 CRM и открытыми линиями',
                'icon' => '🏢',
                'features' => [
                    'leads' => true,
                    'deals' => true,
                    'contacts' => true,
                    'tasks' => true,
                    'open_lines' => true,
                ],
            ],
            'salebot' => [
                'name' => 'Salebot',
                'description' => 'Интеграция с воронками Salebot',
                'icon' => '🤖',
                'features' => [
                    'funnels' => true,
                    'variables' => true,
                    'operators' => true,
                    'broadcast' => true,
                    'analytics' => true,
                ],
            ],
            'amocrm' => [
                'name' => 'AmoCRM',
                'description' => 'Интеграция с AmoCRM',
                'icon' => '📊',
                'features' => [
                    'leads' => true,
                    'deals' => true,
                    'contacts' => true,
                    'tasks' => true,
                    'pipelines' => true,
                ],
            ],
            'avito' => [
                'name' => 'Avito',
                'description' => 'Интеграция с Avito Messenger',
                'icon' => '🏪',
                'features' => [
                    'chats' => true,
                    'messages' => true,
                    'items' => true,
                    'statistics' => true,
                ],
            ]
        ];
    }

    /**
     * Получить настройки для типа CRM
     */
    public function getIntegrationSettings(string $type): array
    {
        return match($type) {
            'bitrix24' => [
                'credentials' => [
                    'webhook_url' => [
                        'label' => 'Webhook URL',
                        'type' => 'text',
                        'required' => true,
                        'help' => 'Входящий вебхук из Битрикс24',
                        'placeholder' => 'https://your-domain.bitrix24.ru/rest/1/xxxxx/',
                    ],
                ],
                'settings' => [
                    'default_responsible_id' => [
                        'label' => 'ID ответственного по умолчанию',
                        'type' => 'number',
                        'required' => false,
                        'default' => 1,
                    ],
                    'openline_config_id' => [
                        'label' => 'ID конфигурации открытой линии',
                        'type' => 'text',
                        'required' => false,
                    ],
                ],
            ],
            'salebot' => [
                'credentials' => [
                    'api_key' => [
                        'label' => 'API ключ',
                        'type' => 'password',
                        'required' => true,
                        'help' => 'API ключ из настроек бота Salebot',
                    ],
                    'bot_id' => [
                        'label' => 'ID бота',
                        'type' => 'text',
                        'required' => true,
                        'help' => 'Идентификатор бота в Salebot',
                    ],
                ],
                'settings' => [
                    'default_funnel_id' => [
                        'label' => 'ID воронки по умолчанию',
                        'type' => 'text',
                        'required' => false,
                        'help' => 'Воронка, которая запускается для новых клиентов',
                    ],
                    'webhook_url' => [
                        'label' => 'URL для webhook',
                        'type' => 'text',
                        'required' => false,
                        'readonly' => true,
                        'value' => url('/webhooks/crm/salebot'),
                        'help' => 'Укажите этот URL в настройках webhook Salebot',
                    ],
                    'auto_start_funnel' => [
                        'label' => 'Автоматически запускать воронку',
                        'type' => 'checkbox',
                        'default' => true,
                    ],
                    'sync_variables' => [
                        'label' => 'Синхронизировать переменные',
                        'type' => 'checkbox',
                        'default' => true,
                    ],
                ],
            ],
            'amocrm' => [
                'credentials' => [
                    'subdomain' => [
                        'label' => 'Поддомен AmoCRM',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'yourcompany',
                        'help' => 'Без .amocrm.ru',
                    ],
                    'access_token' => [
                        'label' => 'Access Token',
                        'type' => 'password',
                        'required' => true,
                    ],
                    'refresh_token' => [
                        'label' => 'Refresh Token',
                        'type' => 'password',
                        'required' => true,
                    ],
                    'client_id' => [
                        'label' => 'Client ID',
                        'type' => 'text',
                        'required' => true,
                    ],
                    'client_secret' => [
                        'label' => 'Client Secret',
                        'type' => 'password',
                        'required' => true,
                    ],
                    'redirect_uri' => [
                        'label' => 'Redirect URI',
                        'type' => 'text',
                        'required' => true,
                        'help' => 'URL для OAuth redirect',
                    ],
                ],
                'settings' => [
                    'default_pipeline_id' => [
                        'label' => 'ID воронки по умолчанию',
                        'type' => 'number',
                        'required' => false,
                    ],
                ],
            ],
            'avito' => [
                'credentials' => [
                    'client_id' => [
                        'label' => 'Client ID',
                        'type' => 'text',
                        'required' => true,
                        'help' => 'ID приложения из личного кабинета Avito',
                    ],
                    'client_secret' => [
                        'label' => 'Client Secret',
                        'type' => 'password',
                        'required' => true,
                        'help' => 'Секретный ключ приложения',
                    ],
                ],
                'settings' => [
                    'welcome_message' => [
                        'label' => 'Приветственное сообщение',
                        'type' => 'textarea',
                        'required' => false,
                        'help' => 'Отправляется при открытии нового чата',
                    ],
                    'auto_reply' => [
                        'label' => 'Автоматические ответы',
                        'type' => 'checkbox',
                        'default' => true,
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * Массовая синхронизация диалогов
     */
    public function bulkSyncConversations(array $conversationIds): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        foreach ($conversationIds as $conversationId) {
            try {
                $conversation = Conversation::find($conversationId);
                if (!$conversation) {
                    $results['failed']++;
                    continue;
                }
                
                $syncResults = $this->syncConversation($conversation);
                
                $hasSuccess = false;
                foreach ($syncResults as $crmType => $result) {
                    if ($result['success']) {
                        $hasSuccess = true;
                        break;
                    }
                }
                
                if ($hasSuccess) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'conversation_id' => $conversationId,
                        'results' => $syncResults,
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'conversation_id' => $conversationId,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Получить статистику синхронизации
     */
    public function getSyncStats(CrmIntegration $integration, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $query = $integration->syncLogs();
        
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        
        $logs = $query->get();
        
        $stats = [
            'total_syncs' => $logs->count(),
            'successful_syncs' => $logs->where('status', 'success')->count(),
            'failed_syncs' => $logs->where('status', 'error')->count(),
            'by_entity_type' => [],
            'by_action' => [],
            'errors' => [],
        ];
        
        // Группируем по типу сущности
        foreach ($logs->groupBy('entity_type') as $entityType => $entityLogs) {
            $stats['by_entity_type'][$entityType] = [
                'total' => $entityLogs->count(),
                'success' => $entityLogs->where('status', 'success')->count(),
                'failed' => $entityLogs->where('status', 'error')->count(),
            ];
        }
        
        // Группируем по действию
        foreach ($logs->groupBy('action') as $action => $actionLogs) {
            $stats['by_action'][$action] = [
                'total' => $actionLogs->count(),
                'success' => $actionLogs->where('status', 'success')->count(),
                'failed' => $actionLogs->where('status', 'error')->count(),
            ];
        }
        
        // Собираем ошибки
        $errorLogs = $logs->where('status', 'error')->take(10);
        foreach ($errorLogs as $log) {
            $stats['errors'][] = [
                'datetime' => $log->created_at->toIso8601String(),
                'entity_type' => $log->entity_type,
                'action' => $log->action,
                'error' => $log->error_message,
            ];
        }
        
        return $stats;
    }

    /**
     * Найти интеграцию по webhook
     */
    protected function findIntegrationByWebhook(string $type, string $signature = null): ?CrmIntegration
    {
        $query = CrmIntegration::where('type', $type)
            ->where('is_active', true);
        
        // Для разных CRM разные способы проверки
        if ($signature) {
            // Можно добавить проверку подписи
            // Пока просто берем первую активную интеграцию
        }
        
        return $query->first();
    }

    /**
     * Автоматическая настройка интеграции
     */
    public function autoSetup(CrmIntegration $integration): array
    {
        $provider = $this->getProvider($integration);
        
        if (!$provider) {
            return [
                'success' => false,
                'error' => 'Provider not available',
            ];
        }
        
        try {
            // Проверяем подключение
            if (!$provider->testConnection()) {
                return [
                    'success' => false,
                    'error' => 'Connection test failed',
                ];
            }
            
            $result = [
                'success' => true,
                'data' => [],
            ];
            
            // Получаем пользователей
            $users = $provider->getUsers();
            if (!empty($users)) {
                $result['data']['users'] = $users;
            }
            
            // Получаем воронки
            $pipelines = $provider->getPipelines();
            if (!empty($pipelines)) {
                $result['data']['pipelines'] = $pipelines;
                
                // Получаем этапы для каждой воронки
                foreach ($pipelines as $pipeline) {
                    $stages = $provider->getPipelineStages($pipeline['id']);
                    if (!empty($stages)) {
                        $result['data']['stages'][$pipeline['id']] = $stages;
                    }
                }
            }
            
            // Получаем поля
            $leadFields = $provider->getFields('lead');
            if (!empty($leadFields)) {
                $result['data']['lead_fields'] = $leadFields;
            }
            
            $contactFields = $provider->getFields('contact');
            if (!empty($contactFields)) {
                $result['data']['contact_fields'] = $contactFields;
            }
            
            // Сохраняем маппинг полей
            $fieldMapping = $this->generateFieldMapping($result['data']);
            $integration->update(['field_mapping' => $fieldMapping]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Auto setup failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Генерация маппинга полей
     */
    protected function generateFieldMapping(array $data): array
    {
        $mapping = [];
        
        // Маппинг для контактов
        if (!empty($data['contact_fields'])) {
            foreach ($data['contact_fields'] as $field) {
                $code = $field['code'] ?? $field['field_code'] ?? '';
                $id = $field['id'] ?? $field['field_id'] ?? '';
                
                if (in_array(strtoupper($code), ['EMAIL', 'PHONE', 'NAME'])) {
                    $mapping['contact'][$code] = $id;
                }
            }
        }
        
        // Маппинг для лидов
        if (!empty($data['lead_fields'])) {
            foreach ($data['lead_fields'] as $field) {
                $code = $field['code'] ?? $field['field_code'] ?? '';
                $id = $field['id'] ?? $field['field_id'] ?? '';
                
                if (in_array(strtoupper($code), ['SOURCE', 'STATUS'])) {
                    $mapping['lead'][$code] = $id;
                }
            }
        }
        
        // Маппинг воронок
        if (!empty($data['pipelines'])) {
            $mapping['pipelines'] = [];
            foreach ($data['pipelines'] as $pipeline) {
                $mapping['pipelines'][$pipeline['id']] = $pipeline['name'];
            }
        }
        
        return $mapping;
    }

    /**
     * Экспорт диалогов в CRM
     */
    public function exportConversations(CrmIntegration $integration, array $filters = []): array
    {
        $provider = $this->getProvider($integration);
        
        if (!$provider) {
            return [
                'success' => false,
                'error' => 'Provider not available',
            ];
        }
        
        $results = [
            'exported' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        // Получаем диалоги для экспорта
        $query = Conversation::query();
        
        if (!empty($filters['bot_id'])) {
            $query->where('bot_id', $filters['bot_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Исключаем уже синхронизированные
        if ($filters['skip_synced'] ?? true) {
            $query->whereNull('crm_lead_id')
                  ->whereNull('crm_deal_id');
        }
        
        $conversations = $query->limit($filters['limit'] ?? 100)->get();
        
        foreach ($conversations as $conversation) {
            try {
                $provider->syncConversation($conversation);
                $results['exported']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
}