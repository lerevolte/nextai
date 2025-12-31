<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmIntegration;
use App\Services\CRM\CrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CrmFieldsController extends Controller
{
    protected CrmService $crmService;
    
    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }
    
    /**
     * Получить поля CRM для сущности
     */
    public function getFields(Request $request, string $provider, string $entityType)
    {
        // Получаем интеграцию текущей организации
        $organization = $request->user()->organization;
        $integration = $organization->crmIntegrations()
            ->where('type', $provider)
            ->where('is_active', true)
            ->first();
            
        if (!$integration) {
            return response()->json([
                'fields' => $this->getDefaultFields($provider, $entityType)
            ]);
        }
        
        
        // Кэшируем поля на 1 час
        $cacheKey = "crm_fields_{$integration->id}_{$entityType}";
        
        \Log::info('Skipping cache for debugging');

        $provider = $this->crmService->getProvider($integration);

        \Log::info('Provider loaded', [
            'provider_exists' => $provider !== null,
            'provider_class' => $provider ? get_class($provider) : null
        ]);

        if (!$provider) {
            \Log::warning('Provider not found, returning default fields');
            $fields = $this->getDefaultFields($integration->type, $entityType);
        } else {
            try {
                \Log::info('Calling provider->getFields()', ['entityType' => $entityType]);
                
                $rawFields = $provider->getFields($entityType);
                //dd($rawFields);
                \Log::info('Raw fields received', [
                    'count' => is_array($rawFields) ? count($rawFields) : 'not array',
                    'sample' => is_array($rawFields) && count($rawFields) > 0 ? array_keys(array_slice($rawFields, 0, 3)) : 'empty or not array'
                ]);
                
                $fields = $this->formatFields($rawFields, $integration->type);
                
                \Log::info('Fields formatted', ['count' => count($fields)]);
            } catch (\Exception $e) {
                \Log::error('Failed to load CRM fields', [
                    'provider' => $integration->type,
                    'entity' => $entityType,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $fields = $this->getDefaultFields($integration->type, $entityType);
            }
        }
        
        return response()->json(['fields' => $fields]);
    }
    
    /**
     * Получить статусы лидов
     */
    public function getLeadStatuses(Request $request, string $provider)
    {
        $organization = $request->user()->organization;
        $integration = $organization->crmIntegrations()
            ->where('type', $provider)
            ->where('is_active', true)
            ->first();
            
        if (!$integration) {
            return response()->json(['statuses' => $this->getDefaultStatuses()]);
        }
        
        $cacheKey = "crm_lead_statuses_{$integration->id}";
        
        $statuses = Cache::remember($cacheKey, 3600, function () use ($integration) {
            $provider = $this->crmService->getProvider($integration);
            
            try {
                return $provider->getLeadStatuses();
            } catch (\Exception $e) {
                return $this->getDefaultStatuses();
            }
        });
        
        return response()->json(['statuses' => $statuses]);
    }
    
    /**
     * Получить пользователей CRM
     */
    public function getUsers(Request $request, string $provider)
    {
        $organization = $request->user()->organization;
        $integration = $organization->crmIntegrations()
            ->where('type', $provider)
            ->where('is_active', true)
            ->first();
            
        if (!$integration) {
            return response()->json(['users' => []]);
        }
        
        $cacheKey = "crm_users_{$integration->id}";
        
        $users = Cache::remember($cacheKey, 3600, function () use ($integration) {
            $provider = $this->crmService->getProvider($integration);
            
            try {
                return $provider->getUsers();
            } catch (\Exception $e) {
                return [];
            }
        });
        
        return response()->json(['users' => $users]);
    }
    
    /**
     * Форматирование полей для унификации
     */
    protected function formatFields(array $rawFields, string $provider): array
    {
        $formatted = [];
        
        foreach ($rawFields as $key => $field) {
            // Битрикс24 формат
            if ($provider === 'bitrix24') {
                $formatted[] = [
                    'code' => $key,
                    'title' => $field['title'] ?? $field['formLabel'] ?? $key,
                    'type' => $this->mapFieldType($field['type'] ?? 'string'),
                    'isRequired' => $field['isRequired'] ?? false,
                    'isReadOnly' => $field['isReadOnly'] ?? false,
                    'isMultiple' => $field['isMultiple'] ?? false,
                    'items' => $field['items'] ?? [],
                    'settings' => $field['settings'] ?? []
                ];
            }
            // AmoCRM формат
            elseif ($provider === 'amocrm') {
                $formatted[] = [
                    'code' => $field['code'] ?? $field['id'],
                    'title' => $field['name'],
                    'type' => $this->mapFieldType($field['type'] ?? 'text'),
                    'isRequired' => $field['is_required'] ?? false,
                    'isReadOnly' => !($field['is_editable'] ?? true),
                    'isMultiple' => $field['is_multiple'] ?? false,
                    'enums' => $field['enums'] ?? []
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Маппинг типов полей
     */
    protected function mapFieldType(string $type): string
    {
        $map = [
            'crm_status' => 'select',
            'crm_multifield' => 'multiple',
            'crm' => 'relation',
            'enumeration' => 'select',
            'date' => 'date',
            'datetime' => 'datetime',
            'double' => 'number',
            'integer' => 'number',
            'boolean' => 'checkbox',
            'money' => 'money',
            'url' => 'url',
            'address' => 'address',
            'location' => 'location'
        ];
        
        return $map[$type] ?? 'string';
    }
    
    /**
     * Поля по умолчанию
     */
    protected function getDefaultFields(string $provider, string $entityType): array
    {
        if ($entityType === 'lead') {
            return [
                ['code' => 'TITLE', 'title' => 'Название', 'type' => 'string', 'isRequired' => true],
                ['code' => 'NAME', 'title' => 'Имя', 'type' => 'string', 'isRequired' => false],
                ['code' => 'PHONE', 'title' => 'Телефон', 'type' => 'phone', 'isRequired' => false],
                ['code' => 'EMAIL', 'title' => 'Email', 'type' => 'email', 'isRequired' => false],
                ['code' => 'COMMENTS', 'title' => 'Комментарий', 'type' => 'text', 'isRequired' => false],
            ];
        }
        
        return [];
    }
    
    /**
     * Статусы по умолчанию
     */
    protected function getDefaultStatuses(): array
    {
        return [
            ['STATUS_ID' => 'NEW', 'NAME' => 'Новый'],
            ['STATUS_ID' => 'IN_PROCESS', 'NAME' => 'В работе'],
            ['STATUS_ID' => 'PROCESSED', 'NAME' => 'Обработан'],
        ];
    }

    public function getPipelineStages(Request $request, string $provider)
    {
        $pipelineId = $request->query('pipeline_id');
        
        if (!$pipelineId) {
            return response()->json(['stages' => []]);
        }
        
        $organization = $request->user()->organization;
        $integration = $organization->crmIntegrations()
            ->where('type', $provider)
            ->where('is_active', true)
            ->first();
            
        if (!$integration) {
            return response()->json(['stages' => []]);
        }
        
        $cacheKey = "crm_pipeline_stages_{$integration->id}_{$pipelineId}";
        
        $stages = Cache::remember($cacheKey, 3600, function () use ($integration, $pipelineId) {
            $provider = $this->crmService->getProvider($integration);
            
            if (!$provider) {
                return [];
            }
            
            try {
                return $provider->getPipelineStages($pipelineId);
            } catch (\Exception $e) {
                \Log::error('Failed to load pipeline stages', [
                    'provider' => $integration->type,
                    'pipeline_id' => $pipelineId,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
        
        return response()->json(['stages' => $stages]);
    }
}