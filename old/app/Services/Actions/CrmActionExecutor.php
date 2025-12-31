<?php

namespace App\Services\Actions;

use App\Models\FunctionAction;
use App\Models\Conversation;
use App\Services\CRM\CrmService;
use Illuminate\Support\Facades\Log;

class CrmActionExecutor
{
    protected CrmService $crmService;
    
    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }
    
    /**
     * Создать лид с динамическим маппингом полей
     */
    public function createLead(FunctionAction $action, array $parameters, Conversation $conversation): array
    {
        // Получаем интеграцию
        $bot = $action->function->bot;
        $integration = $bot->crmIntegrations()
            ->where('type', $action->provider)
            ->where('is_active', true)
            ->first();
            
        if (!$integration) {
            throw new \Exception("CRM integration not found");
        }
        
        // Подготавливаем данные для лида
        $leadData = $this->prepareFieldData(
            $action->field_mapping ?? [],
            $parameters,
            $conversation
        );
        
        // Добавляем дополнительные настройки
        if (isset($action->config['status_id'])) {
            $leadData['STATUS_ID'] = $action->config['status_id'];
        }
        
        if (isset($action->config['assigned_by_id'])) {
            $leadData['ASSIGNED_BY_ID'] = $action->config['assigned_by_id'];
        }
        
        // Создаем лид через провайдера
        $provider = $this->crmService->getProvider($integration);
        
        Log::info('Creating lead with data', [
            'action_id' => $action->id,
            'lead_data' => $leadData
        ]);
        
        $result = $provider->createLeadFromFieldMapping($leadData);
        
        // Сохраняем ID лида в диалоге
        if (isset($result['result'])) {
            $conversation->update([
                'crm_lead_id' => $result['result'],
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'lead_created_at' => now()->toIso8601String(),
                    'lead_created_by_function' => $action->function_id
                ])
            ]);
        }
        
        return [
            'success' => true,
            'data' => [
                'lead_id' => $result['result'] ?? null,
                'lead_data' => $leadData
            ]
        ];
    }
    
    /**
     * Подготовить данные полей
     */
    protected function prepareFieldData(array $fieldMappings, array $parameters, Conversation $conversation): array
    {
        $data = [];
        
        foreach ($fieldMappings as $mapping) {
            if (empty($mapping['crm_field'])) continue;
            
            $value = $this->resolveFieldValue(
                $mapping['source_type'] ?? 'static',
                $mapping['value'] ?? '',
                $parameters,
                $conversation
            );
            
            if ($value !== null) {
                $crmField = $mapping['crm_field'];
                
                // Обработка специальных полей (телефон, email - множественные)
                if (in_array($crmField, ['PHONE', 'EMAIL'])) {
                    $data[$crmField] = [
                        ['VALUE' => $value, 'VALUE_TYPE' => 'WORK']
                    ];
                } else {
                    $data[$crmField] = $value;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Разрешить значение поля
     */
    protected function resolveFieldValue(string $sourceType, string $value, array $parameters, Conversation $conversation)
    {
        switch ($sourceType) {
            case 'parameter':
                // Извлекаем значение параметра
                if (preg_match('/\{(\w+)\}/', $value, $matches)) {
                    return $parameters[$matches[1]] ?? null;
                }
                return null;
                
            case 'static':
                // Статичное значение
                return $value;
                
            case 'dynamic':
                // Динамическое значение с подстановками
                return $this->replacePlaceholders($value, $parameters, $conversation);
                
            case 'conversation':
                // Данные из диалога
                return $this->getConversationValue($value, $conversation);
                
            default:
                return $value;
        }
    }
    
    /**
     * Заменить плейсхолдеры
     */
    protected function replacePlaceholders(string $template, array $parameters, Conversation $conversation): string
    {
        // Заменяем параметры
        foreach ($parameters as $key => $value) {
            $template = str_replace("{{$key}}", $value ?? '', $template);
        }
        
        // Заменяем данные диалога
        $template = str_replace('{conversation.id}', $conversation->id, $template);
        $template = str_replace('{conversation.user_name}', $conversation->user_name ?? '', $template);
        $template = str_replace('{conversation.user_email}', $conversation->user_email ?? '', $template);
        $template = str_replace('{conversation.user_phone}', $conversation->user_phone ?? '', $template);
        
        // Заменяем системные переменные
        $template = str_replace('{current_date}', now()->format('d.m.Y'), $template);
        $template = str_replace('{current_time}', now()->format('H:i'), $template);
        $template = str_replace('{current_datetime}', now()->format('d.m.Y H:i'), $template);
        
        return $template;
    }
    
    /**
     * Получить значение из диалога
     */
    protected function getConversationValue(string $path, Conversation $conversation)
    {
        $path = trim($path, '{}');
        
        return match($path) {
            'conversation.id' => $conversation->id,
            'conversation.user_name' => $conversation->user_name,
            'conversation.user_email' => $conversation->user_email,
            'conversation.user_phone' => $conversation->user_phone,
            'conversation.messages_count' => $conversation->messages_count,
            'conversation.channel' => $conversation->channel->getTypeName(),
            'conversation.created_at' => $conversation->created_at->format('d.m.Y H:i'),
            default => null
        };
    }
}