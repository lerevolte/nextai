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
        
        $fields = Cache::remember($cacheKey, 3600, function () use ($integration, $entityType) {
            $provider = $this->crmService->getProvider($integration);
            
            if (!$provider) {
                return $this->getDefaultFields($integration->type, $entityType);
            }
            
            try {
                $rawFields = $provider->getFields($entityType);
                return $this->formatFields($rawFields, $integration->type);
            } catch (\Exception $e) {
                \Log::error('Failed to load CRM fields', [
                    'provider' => $integration->type,
                    'entity' => $entityType,
                    'error' => $e->getMessage()
                ]);
                return $this->getDefaultFields($integration->type, $entityType);
            }
        });
        
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
}