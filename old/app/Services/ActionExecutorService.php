<?php

namespace App\Services;

use App\Models\FunctionAction;
use App\Services\CRM\CrmService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ActionExecutorService
{
    protected CrmService $crmService;
    protected Client $httpClient;
    
    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
        $this->httpClient = new Client();
    }
    
    /**
     * Выполнить действие
     */
    public function execute(FunctionAction $action, array $parameters): array
    {
        try {
            switch ($action->type) {
                case 'create_lead':
                    return $this->createLead($action, $parameters);
                    
                case 'create_deal':
                    return $this->createDeal($action, $parameters);
                    
                case 'create_contact':
                    return $this->createContact($action, $parameters);
                    
                case 'create_task':
                    return $this->createTask($action, $parameters);
                    
                case 'post':
                case 'get':
                    return $this->sendWebhook($action, $parameters);
                    
                case 'send':
                    return $this->sendEmail($action, $parameters);
                    
                default:
                    throw new \Exception("Unknown action type: {$action->type}");
            }
        } catch (\Exception $e) {
            Log::error('Action execution failed', [
                'action_id' => $action->id,
                'type' => $action->type,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Создать лид в CRM
     */
    protected function createLead(FunctionAction $action, array $parameters): array
    {
        $config = $action->config;
        
        \Log::info('Creating lead', [
            'config' => $config,
            'parameters' => $parameters
        ]);
        
        // Подготавливаем данные для создания лида
        $leadData = [];
        
        // Обрабатываем field_mappings из конфига
        if (isset($config['field_mappings']) && is_array($config['field_mappings'])) {
            foreach ($config['field_mappings'] as $mapping) {
                \Log::info('Processing field mapping', ['mapping' => $mapping]);
                
                // $mapping - это массив вида:
                // ['crm_field' => 'TITLE', 'source_type' => 'parameter', 'value' => 'client_name']
                // или
                // ['crm_field' => 'TITLE', 'source_type' => 'static', 'value' => 'Новый лид']
                
                if (!isset($mapping['crm_field'])) {
                    continue;
                }
                
                $crmField = $mapping['crm_field'];
                $sourceType = $mapping['source_type'] ?? 'static';
                $value = $mapping['value'] ?? '';
                
                if ($sourceType === 'parameter') {
                    // Берем значение из параметров функции
                    if (isset($parameters[$value])) {
                        $leadData[$crmField] = $parameters[$value];
                    }
                } elseif ($sourceType === 'static') {
                    // Используем статическое значение
                    $leadData[$crmField] = $value;
                }
            }
        }
        
        // Добавляем статичные значения из конфига (статус, ответственный и т.д.)
        if (isset($config['status_id'])) {
            $leadData['STATUS_ID'] = $config['status_id'];
        }
        
        if (isset($config['assigned_by_id'])) {
            $leadData['ASSIGNED_BY_ID'] = $config['assigned_by_id'];
        }
        
        \Log::info('Prepared lead data', ['leadData' => $leadData]);
        
        // Получаем интеграцию
        $integration = $action->function->organization->crmIntegrations()
            ->where('type', $action->provider)
            ->where('is_active', true)
            ->first();
        
        if (!$integration) {
            throw new \Exception("CRM integration not found: {$action->provider}");
        }
        
        // Создаем лид через провайдера
        $provider = $this->crmService->getProvider($integration);
        $result = $provider->createLead($leadData);
        
        return [
            'success' => true,
            'data' => [
                'lead_id' => $result['id'] ?? null,
                'lead_url' => $result['url'] ?? null,
            ],
        ];
    }

    protected function createDeal(FunctionAction $action, array $parameters): array
    {
        $config = $action->config;
        
        \Log::info('Creating deal', [
            'config' => $config,
            'parameters' => $parameters
        ]);
        
        $dealData = [];
        
        // Обрабатываем field_mappings
        if (isset($config['field_mappings']) && is_array($config['field_mappings'])) {
            foreach ($config['field_mappings'] as $mapping) {
                if (!isset($mapping['crm_field'])) {
                    continue;
                }
                
                $crmField = $mapping['crm_field'];
                $sourceType = $mapping['source_type'] ?? 'static';
                $value = $mapping['value'] ?? '';
                
                if ($sourceType === 'parameter') {
                    if (isset($parameters[$value])) {
                        $dealData[$crmField] = $parameters[$value];
                    }
                } elseif ($sourceType === 'static') {
                    $dealData[$crmField] = $value;
                }
            }
        }
        
        // Добавляем дополнительные поля
        if (isset($config['stage_id'])) {
            $dealData['STAGE_ID'] = $config['stage_id'];
        }
        
        if (isset($config['category_id'])) {
            $dealData['CATEGORY_ID'] = $config['category_id'];
        }
        
        if (isset($config['assigned_by_id'])) {
            $dealData['ASSIGNED_BY_ID'] = $config['assigned_by_id'];
        }
        
        \Log::info('Prepared deal data', ['dealData' => $dealData]);
        
        // Получаем интеграцию
        $integration = $action->function->organization->crmIntegrations()
            ->where('type', $action->provider)
            ->where('is_active', true)
            ->first();
        
        if (!$integration) {
            throw new \Exception("CRM integration not found: {$action->provider}");
        }
        
        // Создаем сделку через провайдера
        $provider = $this->crmService->getProvider($integration);
        $result = $provider->createDeal($dealData);
        
        return [
            'success' => true,
            'data' => [
                'deal_id' => $result['id'] ?? null,
                'deal_url' => $result['url'] ?? null,
            ],
        ];
    }

    protected function createContact(FunctionAction $action, array $parameters): array
    {
        $config = $action->config;
        
        \Log::info('Creating contact', [
            'config' => $config,
            'parameters' => $parameters
        ]);
        
        $contactData = [];
        
        // Обрабатываем field_mappings
        if (isset($config['field_mappings']) && is_array($config['field_mappings'])) {
            foreach ($config['field_mappings'] as $mapping) {
                if (!isset($mapping['crm_field'])) {
                    continue;
                }
                
                $crmField = $mapping['crm_field'];
                $sourceType = $mapping['source_type'] ?? 'static';
                $value = $mapping['value'] ?? '';
                
                if ($sourceType === 'parameter') {
                    if (isset($parameters[$value])) {
                        $contactData[$crmField] = $parameters[$value];
                    }
                } elseif ($sourceType === 'static') {
                    $contactData[$crmField] = $value;
                }
            }
        }
        
        if (isset($config['assigned_by_id'])) {
            $contactData['ASSIGNED_BY_ID'] = $config['assigned_by_id'];
        }
        
        \Log::info('Prepared contact data', ['contactData' => $contactData]);
        
        // Получаем интеграцию
        $integration = $action->function->organization->crmIntegrations()
            ->where('type', $action->provider)
            ->where('is_active', true)
            ->first();
        
        if (!$integration) {
            throw new \Exception("CRM integration not found: {$action->provider}");
        }
        
        // Создаем контакт через провайдера
        $provider = $this->crmService->getProvider($integration);
        $result = $provider->createContact($contactData);
        
        return [
            'success' => true,
            'data' => [
                'contact_id' => $result['id'] ?? null,
                'contact_url' => $result['url'] ?? null,
            ],
        ];
    }

    protected function createTask(FunctionAction $action, array $parameters): array
    {
        // Аналогично для задач
        throw new \Exception("createTask not implemented yet");
    }
    
    /**
     * Отправить webhook
     */
    protected function sendWebhook(FunctionAction $action, array $parameters): array
    {
        $config = $action->config;
        $url = $this->replaceVariables($config['url'], $parameters);
        
        // Подготавливаем данные
        $data = [];
        if (isset($config['data'])) {
            foreach ($config['data'] as $key => $value) {
                $data[$key] = $this->replaceVariables($value, $parameters);
            }
        }
        
        // Отправляем запрос
        $response = $this->httpClient->request(
            $action->type === 'post' ? 'POST' : 'GET',
            $url,
            [
                'json' => $data,
                'timeout' => 10,
            ]
        );
        
        return [
            'success' => $response->getStatusCode() < 400,
            'data' => [
                'status_code' => $response->getStatusCode(),
                'response' => json_decode($response->getBody()->getContents(), true),
            ],
        ];
    }
    
    /**
     * Отправить email
     */
    protected function sendEmail(FunctionAction $action, array $parameters): array
    {
        $config = $action->config;
        
        $to = $this->replaceVariables($config['to'], $parameters);
        $subject = $this->replaceVariables($config['subject'], $parameters);
        $body = $this->replaceVariables($config['body'], $parameters);
        
        Mail::raw($body, function ($message) use ($to, $subject) {
            $message->to($to)
                    ->subject($subject);
        });
        
        return [
            'success' => true,
            'data' => [
                'to' => $to,
                'subject' => $subject,
            ],
        ];
    }
    
    /**
     * Заменить переменные в строке
     */
    protected function replaceVariables(string $template, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $template = str_replace("{{$key}}", $value ?? '', $template);
        }
        
        return $template;
    }
}