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
        $mapping = $action->field_mapping;
        
        // Подготавливаем данные для создания лида
        $leadData = [];
        
        // Мапим параметры на поля CRM
        foreach ($mapping as $crmField => $paramCode) {
            if (isset($parameters[$paramCode])) {
                $leadData[$crmField] = $parameters[$paramCode];
            }
        }
        
        // Добавляем статичные значения из конфига
        if (isset($config['title'])) {
            $leadData['title'] = $this->replaceVariables($config['title'], $parameters);
        }
        
        if (isset($config['status_id'])) {
            $leadData['status_id'] = $config['status_id'];
        }
        
        if (isset($config['assigned_by_id'])) {
            $leadData['assigned_by_id'] = $config['assigned_by_id'];
        }
        
        // Получаем интеграцию
        $bot = $action->function->bot;
        $integration = $bot->crmIntegrations()
            ->where('type', $action->provider)
            ->where('is_active', true)
            ->first();
        
        if (!$integration) {
            throw new \Exception("CRM integration not found: {$action->provider}");
        }
        
        // Создаем лид через провайдера
        $provider = $this->crmService->getProvider($integration);
        $result = $provider->createLeadFromData($leadData);
        
        return [
            'success' => true,
            'data' => [
                'lead_id' => $result['id'] ?? null,
                'lead_url' => $result['url'] ?? null,
            ],
        ];
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