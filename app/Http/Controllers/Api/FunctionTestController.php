<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TriggerMatchingService;
use App\Services\FunctionExecutionService;
use App\Services\ParameterExtractorService;
use App\Models\BotFunction;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FunctionTestController extends Controller
{
    protected TriggerMatchingService $triggerService;
    protected FunctionExecutionService $executionService;
    protected ParameterExtractorService $extractorService;
    
    public function __construct(
        TriggerMatchingService $triggerService,
        FunctionExecutionService $executionService,
        ParameterExtractorService $extractorService
    ) {
        $this->triggerService = $triggerService;
        $this->executionService = $executionService;
        $this->extractorService = $extractorService;
    }
    
    public function createTestConversation(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->organization) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $bot = $user->organization->bots()->findOrFail($request->bot_id);
            
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel_id' => $bot->channels()->where('type', 'web')->first()->id ?? null,
                'external_id' => 'test_' . Str::uuid(),
                'status' => 'active',
                'user_name' => 'Test User',
                'metadata' => [
                    'is_test' => true,
                    'function_id' => $request->function_id
                ]
            ]);
            
            return response()->json([
                'conversation_id' => $conversation->id
            ]);
        } catch (\Exception $e) {
            Log::error('Test conversation creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function testTriggers(Request $request)
    {
        try {
            $functionData = $request->input('function');
            $message = $request->input('message');
            $history = $request->input('conversation_history', []);
            
            Log::info('Testing triggers', [
                'message' => $message,
                'trigger_type' => $functionData['trigger_type'] ?? null,
                'keywords' => $functionData['trigger_keywords'] ?? null
            ]);
            
            // Загружаем реальную функцию из БД
            $function = BotFunction::with(['parameters', 'actions', 'behavior'])
                ->findOrFail($functionData['id']);
            
            // Проверяем триггер
            $matched = false;
            $trigger = null;
            
            if ($function->trigger_type === 'keyword' && $function->trigger_keywords) {
                foreach ($function->trigger_keywords as $keyword) {
                    if (stripos($message, $keyword) !== false) {
                        $matched = true;
                        $trigger = $keyword;
                        break;
                    }
                }
            } elseif ($function->trigger_type === 'auto') {
                $matched = true;
                $trigger = 'auto';
            }
            
            // Создаем временный диалог для извлечения параметров
            $tempConversation = $this->createTempConversation($function->bot_id, $history, $message);
            
            // Извлекаем параметры
            $parameters = [];
            if ($matched && $function->parameters->count() > 0) {
                $parameters = $this->extractorService->extractParameters($function, $tempConversation);
                
                Log::info('Parameters extracted', [
                    'parameters' => $parameters,
                    'function_params' => $function->parameters->pluck('code')->toArray()
                ]);
            }
            
            return response()->json([
                'matched' => $matched,
                'trigger' => $trigger,
                'parameters' => $parameters,
                'debug' => [
                    'trigger_type' => $function->trigger_type,
                    'keywords' => $function->trigger_keywords,
                    'message' => $message,
                    'function_params_count' => $function->parameters->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Test triggers failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function testExecute(Request $request)
    {
        try {
            $functionData = $request->input('function');
            $message = $request->input('message');
            $history = $request->input('conversation_history', []);
            $extractOnly = $request->input('extractOnly', false);
            $realExecution = $request->input('realExecution', false);
            
            Log::info('Testing execution', [
                'message' => $message,
                'extractOnly' => $extractOnly,
                'realExecution' => $realExecution
            ]);
            
            // Загружаем функцию
            $function = BotFunction::with(['parameters', 'actions', 'behavior'])
                ->findOrFail($functionData['id']);
            
            // Создаем временный диалог
            $tempConversation = $this->createTempConversation($function->bot_id, $history, $message);
            
            // Извлекаем параметры
            $extractedParams = [];
            if ($function->parameters->count() > 0) {
                try {
                    $extractedParams = $this->extractorService->extractParameters($function, $tempConversation);
                    
                    Log::info('Parameters extracted in test', [
                        'params' => $extractedParams,
                        'message' => $message
                    ]);
                } catch (\Exception $e) {
                    Log::error('Parameter extraction failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Если только извлечение
            if ($extractOnly) {
                return response()->json([
                    'status' => 'extracted',
                    'extractedParams' => $extractedParams
                ]);
            }
            
            // ПРОВЕРКА ОБЯЗАТЕЛЬНЫХ ПАРАМЕТРОВ
            $hasRequiredParams = $this->extractorService->hasRequiredParameters($function, $extractedParams);
            $missingParams = $this->extractorService->getMissingRequiredParameters($function, $extractedParams);
            
            if (!$hasRequiredParams) {
                Log::warning('Missing required parameters', [
                    'missing' => $missingParams,
                    'extracted' => $extractedParams
                ]);
                
                return response()->json([
                    'status' => 'waiting_for_parameters',
                    'extractedParams' => $extractedParams,
                    'missingParams' => $missingParams,
                    'executedActions' => [],
                    'executionLog' => [
                        [
                            'time' => now()->format('H:i:s'),
                            'level' => 'warning',
                            'message' => '⚠️ Недостаточно данных для выполнения. Ожидаем: ' . 
                                        implode(', ', array_column($missingParams, 'name'))
                        ]
                    ]
                ]);
            }
            
            // Выполняем действия только если все обязательные параметры есть
            $executedActions = [];
            $executionLog = [];
            
            foreach ($function->actions as $action) {
                $actionStart = microtime(true);
                
                if ($realExecution) {
                    // Реальное выполнение
                    $actionResult = $this->executeRealAction($action, $extractedParams, $tempConversation);
                } else {
                    // Симуляция
                    $actionResult = $this->simulateAction($action, $extractedParams);
                }
                
                $actionTime = round((microtime(true) - $actionStart) * 1000, 2);
                $actionResult['execution_time'] = $actionTime . 'ms';
                
                $executedActions[] = $actionResult;
                
                $executionLog[] = [
                    'time' => now()->format('H:i:s'),
                    'level' => $actionResult['status'] === 'success' ? 'info' : 'error',
                    'message' => "Action {$action->type}: {$actionResult['result']} ({$actionTime}ms)"
                ];
            }
            
            $allSuccess = collect($executedActions)->every(fn($a) => $a['status'] === 'success');
            
            return response()->json([
                'status' => $allSuccess ? 'success' : 'failed',
                'extractedParams' => $extractedParams,
                'missingParams' => [],
                'executedActions' => $executedActions,
                'executionLog' => $executionLog
            ]);
        } catch (\Exception $e) {
            Log::error('Test execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * Создание временного диалога для тестирования
     */
    protected function createTempConversation($botId, array $history, string $currentMessage): Conversation
    {
        // Загружаем бот
        $bot = \App\Models\Bot::findOrFail($botId);
        
        $conversation = new Conversation([
            'bot_id' => $botId,
            'external_id' => 'test_' . Str::uuid(),
            'status' => 'active',
            'user_name' => 'Test User',
        ]);
        $conversation->id = 0;
        
        // Устанавливаем связь с ботом
        $conversation->setRelation('bot', $bot);
        
        $messages = collect();
        
        // Добавляем историю
        foreach ($history as $msg) {
            $messages->push(new Message([
                'conversation_id' => 0,
                'role' => $msg['role'],
                'content' => $msg['content'],
                'created_at' => now()
            ]));
        }
        
        // Добавляем текущее сообщение
        $messages->push(new Message([
            'conversation_id' => 0,
            'role' => 'user',
            'content' => $currentMessage,
            'created_at' => now()
        ]));
        
        $conversation->setRelation('messages', $messages);
        
        return $conversation;
    }

    /**
     * Реальное выполнение действия в CRM
     */
    protected function executeRealAction($action, $parameters, $conversation)
    {
        try {
            Log::info('Executing real action', [
                'action_type' => $action->type,
                'action_provider' => $action->provider,
                'parameters' => $parameters,
                'action_id' => $action->id
            ]);
            
            // Проверяем наличие параметров
            if (empty($parameters)) {
                Log::warning('No parameters for real action execution');
                return [
                    'name' => $action->type,
                    'status' => 'failed',
                    'result' => '✗ Нет извлеченных параметров для выполнения действия'
                ];
            }
            
            // Подготавливаем данные для CRM на основе field_mapping
            $crmData = [];
            
            if (!empty($action->field_mapping) && is_array($action->field_mapping)) {
                foreach ($action->field_mapping as $mapping) {
                    $crmField = $mapping['crm_field'] ?? null;
                    $sourceType = $mapping['source_type'] ?? 'parameter';
                    $value = $mapping['value'] ?? null;
                    
                    if (!$crmField) continue;
                    
                    // Получаем значение в зависимости от типа источника
                    switch ($sourceType) {
                        case 'parameter':
                            // Убираем фигурные скобки если есть
                            $paramCode = str_replace(['{', '}'], '', $value);
                            if (isset($parameters[$paramCode])) {
                                $crmData[$crmField] = $parameters[$paramCode];
                            }
                            break;
                            
                        case 'static':
                            $crmData[$crmField] = $value;
                            break;
                            
                        case 'dynamic':
                            // Заменяем плейсхолдеры в динамическом значении
                            $resolvedValue = $value;
                            foreach ($parameters as $key => $val) {
                                $resolvedValue = str_replace('{' . $key . '}', $val, $resolvedValue);
                            }
                            $crmData[$crmField] = $resolvedValue;
                            break;
                            
                        case 'conversation':
                            // Данные из диалога (conversation.user_name и т.д.)
                            if (strpos($value, '{conversation.') === 0) {
                                $field = str_replace(['{conversation.', '}'], '', $value);
                                $crmData[$crmField] = $conversation->$field ?? null;
                            }
                            break;
                    }
                }
            }
            
            Log::info('Prepared CRM data from field_mapping', [
                'crm_data' => $crmData,
                'field_mapping' => $action->field_mapping
            ]);
            
            // Добавляем дополнительные настройки из config
            if (!empty($action->config) && is_array($action->config)) {
                foreach ($action->config as $key => $value) {
                    if ($key !== 'field_mappings' && !isset($crmData[$key])) {
                        $crmData[$key] = $value;
                    }
                }
            }
            
            // Выполняем действие через FunctionExecutionService
            $result = $this->executionService->executeAction($action, $parameters, $conversation);
            
            Log::info('Real action executed successfully', [
                'result' => $result
            ]);
            
            return [
                'name' => $action->type,
                'status' => 'success',
                'result' => '✓ ' . ($result['message'] ?? 'Действие выполнено успешно'),
                'data' => $result['data'] ?? $crmData
            ];
            
        } catch (\Exception $e) {
            Log::error('Real action execution failed', [
                'action_type' => $action->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'name' => $action->type,
                'status' => 'failed',
                'result' => '✗ Ошибка: ' . $e->getMessage()
            ];
        }
    }
    

    /**
     * Симуляция выполнения действия
     */
    protected function simulateAction($action, $parameters)
    {
        $type = $action->type;
        $provider = $action->provider;
        
        Log::info('Simulating action', [
            'type' => $type,
            'provider' => $provider,
            'parameters' => $parameters
        ]);
        
        switch ($type) {
            case 'create_lead':
                // Проверяем любые параметры, не только name/phone/email
                $hasAnyParams = !empty($parameters) && count(array_filter($parameters)) > 0;
                
                Log::info('Create lead simulation', [
                    'has_params' => $hasAnyParams,
                    'params_count' => count($parameters),
                    'params' => $parameters
                ]);
                
                return [
                    'name' => "Создание лида в {$provider}",
                    'status' => $hasAnyParams ? 'success' : 'failed',
                    'result' => $hasAnyParams 
                        ? "✓ Лид #" . rand(1000, 9999) . " создан (симуляция)"
                        : "✗ Недостаточно данных для создания лида",
                    'data' => $hasAnyParams ? [
                        'lead_id' => rand(1000, 9999),
                        'used_params' => $parameters
                    ] : null
                ];
                
            case 'create_deal':
                $hasParams = !empty($parameters);
                return [
                    'name' => "Создание сделки в {$provider}",
                    'status' => $hasParams ? 'success' : 'failed',
                    'result' => $hasParams 
                        ? "✓ Сделка #" . rand(1000, 9999) . " создана (симуляция)"
                        : "✗ Недостаточно данных для создания сделки",
                    'data' => $hasParams ? ['deal_id' => rand(1000, 9999)] : null
                ];
                
            case 'create_contact':
                $hasParams = !empty($parameters);
                return [
                    'name' => "Создание контакта в {$provider}",
                    'status' => $hasParams ? 'success' : 'failed',
                    'result' => $hasParams 
                        ? "✓ Контакт #" . rand(1000, 9999) . " создан (симуляция)"
                        : "✗ Недостаточно данных для создания контакта",
                    'data' => $hasParams ? ['contact_id' => rand(1000, 9999)] : null
                ];
                
            case 'create_task':
                $hasParams = !empty($parameters);
                return [
                    'name' => "Создание задачи в {$provider}",
                    'status' => $hasParams ? 'success' : 'failed',
                    'result' => $hasParams 
                        ? "✓ Задача #" . rand(1000, 9999) . " создана (симуляция)"
                        : "✗ Недостаточно данных для создания задачи"
                ];
                
            case 'send_email':
                return [
                    'name' => 'Отправка email',
                    'status' => 'success',
                    'result' => '✓ Email отправлен (симуляция)'
                ];
                
            case 'webhook':
            case 'post':
            case 'get':
                return [
                    'name' => 'Webhook',
                    'status' => 'success',
                    'result' => '✓ Webhook вызван успешно (симуляция)'
                ];
                
            default:
                return [
                    'name' => $type,
                    'status' => 'success',
                    'result' => '✓ Действие выполнено (симуляция)'
                ];
        }
    }
}