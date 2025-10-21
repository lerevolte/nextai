<?php

namespace App\Services;

use App\Models\BotFunction;
use App\Models\FunctionAction;
use App\Models\Conversation;
use App\Models\FunctionExecution;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FunctionExecutionService
{
    /**
     * Выполнить функцию
     */
    public function execute(BotFunction $function, array $parameters, Conversation $conversation): array
    {
        Log::info('Executing function', [
            'function_id' => $function->id,
            'function_name' => $function->name,
            'parameters' => $parameters
        ]);
        
        // Создаем запись о выполнении
        $execution = FunctionExecution::create([
            'function_id' => $function->id,
            'conversation_id' => $conversation->id,
            'parameters' => $parameters,
            'status' => 'processing',
        ]);
        
        try {
            $results = [];
            
            // Выполняем каждое действие
            foreach ($function->actions as $action) {
                $actionResult = $this->executeAction($action, $parameters, $conversation);
                $results[] = $actionResult;
                
                // Если действие критично и провалилось, прерываем выполнение
                if (!$actionResult['success'] && ($action->is_critical ?? false)) {
                    break;
                }
            }
            
            $allSuccess = collect($results)->every(fn($r) => $r['success']);
            
            // Обновляем запись о выполнении
            $execution->update([
                'status' => $allSuccess ? 'completed' : 'failed',
                'result' => $results,
                'completed_at' => now()
            ]);
            
            return [
                'success' => $allSuccess,
                'results' => $results,
                'execution_id' => $execution->id
            ];
            
        } catch (\Exception $e) {
            Log::error('Function execution failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $execution->update([
                'status' => 'failed',
                'result' => ['error' => $e->getMessage()],
                'completed_at' => now()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_id' => $execution->id
            ];
        }
    }
    
    /**
     * Выполнить одно действие
     */
    public function executeAction(FunctionAction $action, array $parameters, Conversation $conversation): array
    {
        Log::info('Executing action', [
            'action_id' => $action->id,
            'action_type' => $action->type,
            'provider' => $action->provider,
            'parameters' => $parameters
        ]);
        
        try {
            switch ($action->provider) {
                case 'bitrix24':
                    return $this->executeBitrix24Action($action, $parameters, $conversation);
                    
                case 'webhook':
                    return $this->executeWebhookAction($action, $parameters);
                    
                case 'email':
                    return $this->executeEmailAction($action, $parameters);
                    
                default:
                    throw new \Exception("Unsupported provider: {$action->provider}");
            }
            
        } catch (\Exception $e) {
            Log::error('Action execution failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    

    /**
     * Выполнить действие в Битрикс24
     */
    protected function executeBitrix24Action(FunctionAction $action, array $parameters, Conversation $conversation): array
    {
        // Получаем интеграцию Битрикс24
        $bot = $conversation->bot;
        
        // Через промежуточную таблицу
        $pivotRecord = \DB::table('bot_crm_integrations')
            ->where('bot_id', $bot->id)
            ->where('is_active', 1)
            ->first();
        
        if (!$pivotRecord) {
            throw new \Exception('Активная CRM интеграция не найдена');
        }
        
        $bitrix24 = \App\Models\CrmIntegration::where('id', $pivotRecord->crm_integration_id)
            ->where('type', 'bitrix24')
            ->first();
        
        if (!$bitrix24) {
            throw new \Exception('Битрикс24 интеграция не найдена');
        }
        
        // Получаем credentials (может быть массив или JSON строка)
        $credentials = $bitrix24->credentials;
        if (is_string($credentials)) {
            $credentials = json_decode($credentials, true);
        }
        
        Log::info('Found Bitrix24 integration', [
            'integration_id' => $bitrix24->id,
            'credentials' => $credentials
        ]);
        
        // Формируем правильный URL для API
        $baseUrl = null;
        
        if (!empty($credentials['webhook_url'])) {
            // Если есть webhook_url - используем его
            $baseUrl = rtrim($credentials['webhook_url'], '/');
        } elseif (!empty($credentials['domain'])) {
            // Если есть domain - пытаемся сформировать URL
            throw new \Exception('Webhook URL не настроен. Пожалуйста, настройте вебхук в интеграции Битрикс24');
        } else {
            throw new \Exception('Не найден webhook URL в credentials интеграции Битрикс24');
        }
        
        // Проверяем формат URL
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Некорректный формат webhook URL: ' . $baseUrl);
        }
        
        // Подготавливаем данные для CRM
        $crmData = $this->prepareCrmData($action, $parameters, $conversation);
        
        Log::info('Prepared CRM data', [
            'action_type' => $action->type,
            'crm_data' => $crmData,
            'base_url' => $baseUrl
        ]);
        
        switch ($action->type) {
            case 'create_lead':
                $url = $baseUrl . '/crm.lead.add.json';
                Log::info('Calling Bitrix24 API', ['url' => $url, 'fields' => $crmData]);
                
                $result = $this->callBitrix24API($url, ['fields' => $crmData]);
                
                return [
                    'success' => true,
                    'message' => "Лид #{$result['result']} успешно создан в Битрикс24",
                    'data' => [
                        'lead_id' => $result['result'],
                        'crm_data' => $crmData,
                        'bitrix_url' => $baseUrl
                    ]
                ];
                
            case 'create_deal':
                $url = $baseUrl . '/crm.deal.add.json';
                $result = $this->callBitrix24API($url, ['fields' => $crmData]);
                
                return [
                    'success' => true,
                    'message' => "Сделка #{$result['result']} успешно создана в Битрикс24",
                    'data' => [
                        'deal_id' => $result['result'],
                        'crm_data' => $crmData
                    ]
                ];
                
            case 'create_contact':
                $url = $baseUrl . '/crm.contact.add.json';
                $result = $this->callBitrix24API($url, ['fields' => $crmData]);
                
                return [
                    'success' => true,
                    'message' => "Контакт #{$result['result']} успешно создан в Битрикс24",
                    'data' => [
                        'contact_id' => $result['result'],
                        'crm_data' => $crmData
                    ]
                ];
                
            case 'create_task':
                $url = $baseUrl . '/tasks.task.add.json';
                
                $taskData = array_merge($crmData, [
                    'TITLE' => $action->config['title'] ?? 'Задача из чат-бота',
                    'PRIORITY' => $action->config['priority'] ?? 1,
                ]);
                
                if (isset($action->config['deadline_days'])) {
                    $deadline = now()->addDays($action->config['deadline_days']);
                    $taskData['DEADLINE'] = $deadline->toIso8601String();
                }
                
                $result = $this->callBitrix24API($url, ['fields' => $taskData]);
                
                return [
                    'success' => true,
                    'message' => "Задача #{$result['result']['task']['id']} успешно создана в Битрикс24",
                    'data' => [
                        'task_id' => $result['result']['task']['id'],
                        'crm_data' => $taskData
                    ]
                ];
                
            default:
                throw new \Exception("Unsupported Bitrix24 action: {$action->type}");
        }
    }
    
    /**
     * Подготовить данные для CRM на основе field_mapping
     */
    protected function prepareCrmData(FunctionAction $action, array $parameters, Conversation $conversation): array
    {
        $crmData = [];
        
        // Обрабатываем field_mapping
        if (!empty($action->field_mapping) && is_array($action->field_mapping)) {
            foreach ($action->field_mapping as $mapping) {
                $crmField = $mapping['crm_field'] ?? null;
                $sourceType = $mapping['source_type'] ?? 'parameter';
                $value = $mapping['value'] ?? null;
                
                if (!$crmField || !$value) continue;
                
                $resolvedValue = null;
                
                switch ($sourceType) {
                    case 'parameter':
                        // Убираем фигурные скобки если есть
                        $paramCode = str_replace(['{', '}'], '', $value);
                        $resolvedValue = $parameters[$paramCode] ?? null;
                        break;
                        
                    case 'static':
                        $resolvedValue = $value;
                        break;
                        
                    case 'dynamic':
                        // Заменяем все плейсхолдеры параметров
                        $resolvedValue = $value;
                        foreach ($parameters as $key => $val) {
                            $resolvedValue = str_replace('{' . $key . '}', $val, $resolvedValue);
                        }
                        // Заменяем текущую дату
                        $resolvedValue = str_replace('{current_date}', now()->format('Y-m-d'), $resolvedValue);
                        $resolvedValue = str_replace('{current_datetime}', now()->format('Y-m-d H:i:s'), $resolvedValue);
                        break;
                        
                    case 'conversation':
                        // Извлекаем данные из диалога
                        if (strpos($value, '{conversation.') === 0) {
                            $field = str_replace(['{conversation.', '}'], '', $value);
                            $resolvedValue = $conversation->$field ?? null;
                        }
                        break;
                }
                
                if ($resolvedValue !== null) {
                    // Специальная обработка для телефонов и email (они множественные в Битрикс24)
                    if (in_array($crmField, ['PHONE', 'EMAIL'])) {
                        $crmData[$crmField] = [
                            ['VALUE' => $resolvedValue, 'VALUE_TYPE' => 'WORK']
                        ];
                    } else {
                        $crmData[$crmField] = $resolvedValue;
                    }
                }
            }
        }
        
        // Добавляем дополнительные поля из config
        if (!empty($action->config) && is_array($action->config)) {
            foreach ($action->config as $key => $value) {
                if (!isset($crmData[$key]) && $key !== 'field_mappings') {
                    $crmData[$key] = $value;
                }
            }
        }
        
        return $crmData;
    }
    
    /**
     * Вызов API Битрикс24
     */
    protected function callBitrix24API(string $url, array $data): array
    {
        Log::info('Calling Bitrix24 API', [
            'url' => $url,
            'data' => $data
        ]);
        
        $response = Http::timeout(30)->post($url, $data);
        
        if (!$response->successful()) {
            Log::error('Bitrix24 API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            throw new \Exception('Ошибка API Битрикс24: ' . $response->body());
        }
        
        $result = $response->json();
        
        Log::info('Bitrix24 API response', ['result' => $result]);
        
        if (isset($result['error'])) {
            throw new \Exception('Битрикс24 вернул ошибку: ' . $result['error_description'] ?? $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Выполнить webhook действие
     */
    protected function executeWebhookAction(FunctionAction $action, array $parameters): array
    {
        $url = $action->config['url'] ?? null;
        
        if (!$url) {
            throw new \Exception('URL не указан для webhook');
        }
        
        // Подготавливаем данные
        $data = [];
        if (isset($action->config['data'])) {
            $dataTemplate = is_string($action->config['data']) 
                ? json_decode($action->config['data'], true) 
                : $action->config['data'];
            
            // Заменяем плейсхолдеры
            $data = $this->replacePlaceholders($dataTemplate, $parameters);
        }
        
        // Выполняем запрос
        $method = strtolower($action->type === 'post' ? 'post' : 'get');
        $response = Http::$method($url, $data);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? 'Webhook выполнен успешно' 
                : 'Webhook вернул ошибку: ' . $response->status(),
            'data' => [
                'status' => $response->status(),
                'response' => $response->json()
            ]
        ];
    }
    
    /**
     * Отправить email
     */
    protected function executeEmailAction(FunctionAction $action, array $parameters): array
    {
        $to = $action->config['to'] ?? null;
        $subject = $action->config['subject'] ?? 'Уведомление от чат-бота';
        $body = $action->config['body'] ?? '';
        
        if (!$to) {
            throw new \Exception('Email получателя не указан');
        }
        
        // Заменяем плейсхолдеры
        $subject = $this->replacePlaceholders($subject, $parameters);
        $body = $this->replacePlaceholders($body, $parameters);
        
        // Отправляем email через Laravel Mail
        \Mail::raw($body, function($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
        
        return [
            'success' => true,
            'message' => "Email отправлен на {$to}",
            'data' => [
                'to' => $to,
                'subject' => $subject
            ]
        ];
    }
    
    /**
     * Замена плейсхолдеров в строке или массиве
     */
    protected function replacePlaceholders($data, array $parameters)
    {
        if (is_string($data)) {
            foreach ($parameters as $key => $value) {
                $data = str_replace('{' . $key . '}', $value, $data);
            }
            return $data;
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->replacePlaceholders($value, $parameters);
            }
            return $data;
        }
        
        return $data;
    }

    /**
     * Выполнить функцию с накоплением параметров
     */
    public function executeWithAccumulation(BotFunction $function, array $newParameters, Conversation $conversation): array
    {
        Log::info('Executing function with parameter accumulation', [
            'function_id' => $function->id,
            'new_parameters' => $newParameters,
            'conversation_id' => $conversation->id
        ]);
        
        // Получаем накопленные параметры из метаданных диалога
        $metadata = $conversation->metadata ?? [];
        $accumulatedParams = $metadata['accumulated_params'][$function->id] ?? [];
        
        // Объединяем с новыми параметрами (новые перезаписывают старые)
        $allParams = array_merge($accumulatedParams, $newParameters);
        
        Log::info('Accumulated parameters', [
            'previous' => $accumulatedParams,
            'new' => $newParameters,
            'merged' => $allParams
        ]);
        
        // Проверяем наличие всех обязательных параметров
        $paramExtractor = app(ParameterExtractorService::class);
        $hasRequired = $paramExtractor->hasRequiredParameters($function, $allParams);
        $missingParams = $paramExtractor->getMissingRequiredParameters($function, $allParams);
        
        if (!$hasRequired) {
            // Сохраняем накопленные параметры и ждем остальные
            $metadata['accumulated_params'][$function->id] = $allParams;
            $conversation->update(['metadata' => $metadata]);
            
            Log::info('Waiting for more parameters', [
                'missing' => $missingParams,
                'accumulated' => $allParams
            ]);
            
            return [
                'success' => false,
                'status' => 'waiting_for_parameters',
                'accumulated_params' => $allParams,
                'missing_params' => $missingParams,
                'message' => 'Ожидаем дополнительные параметры: ' . 
                            implode(', ', array_column($missingParams, 'name'))
            ];
        }
        
        // Все параметры собраны - выполняем функцию
        $result = $this->execute($function, $allParams, $conversation);
        
        // Очищаем накопленные параметры после успешного выполнения
        if ($result['success']) {
            unset($metadata['accumulated_params'][$function->id]);
            $conversation->update(['metadata' => $metadata]);
            
            Log::info('Function executed successfully, clearing accumulated params');
        }
        
        return $result;
    }
}