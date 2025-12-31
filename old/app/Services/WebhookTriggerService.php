<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotFunction;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebhookTriggerService
{
    protected FunctionExecutionService $executionService;
    protected ParameterExtractorService $extractorService;
    
    public function __construct(
        FunctionExecutionService $executionService,
        ParameterExtractorService $extractorService
    ) {
        $this->executionService = $executionService;
        $this->extractorService = $extractorService;
    }
    
    /**
     * Обработать входящий webhook
     */
    public function handleWebhook(Request $request, string $webhookKey): array
    {
        // Находим функцию по ключу webhook
        $function = $this->findFunctionByWebhookKey($webhookKey);
        
        if (!$function) {
            Log::warning('Webhook function not found', ['key' => $webhookKey]);
            return [
                'success' => false,
                'error' => 'Webhook not found'
            ];
        }
        
        // Проверяем активность функции
        if (!$function->is_active) {
            Log::info('Webhook function is inactive', ['function_id' => $function->id]);
            return [
                'success' => false,
                'error' => 'Function is inactive'
            ];
        }
        
        // Валидируем webhook подпись если настроена
        if (!$this->validateWebhookSignature($request, $function)) {
            Log::warning('Invalid webhook signature', [
                'function_id' => $function->id,
                'ip' => $request->ip()
            ]);
            return [
                'success' => false,
                'error' => 'Invalid signature'
            ];
        }
        
        // Проверяем rate limiting
        if (!$this->checkRateLimit($function, $request)) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded'
            ];
        }
        
        // Логируем webhook
        $this->logWebhookReceived($function, $request);
        
        try {
            // Извлекаем параметры из webhook данных
            $parameters = $this->extractWebhookParameters($function, $request);
            
            // Находим или создаем диалог для webhook
            $conversation = $this->findOrCreateConversation($function, $request);
            
            // Создаем системное сообщение о webhook
            $message = $this->createWebhookMessage($conversation, $request);
            
            // Выполняем функцию
            $result = $this->executionService->execute($function, $parameters, $conversation, $message);
            
            // Формируем ответ
            return $this->formatWebhookResponse($function, $result);
            
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Регистрировать webhook URL для функции
     */
    public function registerWebhook(BotFunction $function): string
    {
        // Генерируем уникальный ключ для webhook
        $webhookKey = $this->generateWebhookKey($function);
        
        // Сохраняем в триггере функции
        $trigger = $function->triggers()
            ->where('type', 'webhook')
            ->first();
            
        if (!$trigger) {
            $trigger = $function->triggers()->create([
                'type' => 'webhook',
                'name' => 'Webhook Trigger',
                'conditions' => [],
                'priority' => 100,
                'is_active' => true
            ]);
        }
        
        $conditions = $trigger->conditions;
        $conditions['webhook_key'] = $webhookKey;
        $conditions['webhook_url'] = $this->getWebhookUrl($webhookKey);
        $trigger->update(['conditions' => $conditions]);
        
        // Кэшируем для быстрого доступа
        Cache::put(
            "webhook_function:{$webhookKey}",
            $function->id,
            now()->addDays(30)
        );
        
        return $conditions['webhook_url'];
    }
    
    /**
     * Найти функцию по ключу webhook
     */
    protected function findFunctionByWebhookKey(string $webhookKey): ?BotFunction
    {
        // Сначала проверяем кэш
        $functionId = Cache::get("webhook_function:{$webhookKey}");
        
        if ($functionId) {
            return BotFunction::find($functionId);
        }
        
        // Если не в кэше, ищем в БД
        $trigger = \DB::table('function_triggers')
            ->where('type', 'webhook')
            ->whereJsonContains('conditions->webhook_key', $webhookKey)
            ->first();
            
        if ($trigger) {
            $function = BotFunction::find($trigger->function_id);
            
            // Кэшируем для следующих запросов
            if ($function) {
                Cache::put(
                    "webhook_function:{$webhookKey}",
                    $function->id,
                    now()->addDays(30)
                );
            }
            
            return $function;
        }
        
        return null;
    }
    
    /**
     * Валидировать подпись webhook
     */
    protected function validateWebhookSignature(Request $request, BotFunction $function): bool
    {
        $trigger = $function->triggers()
            ->where('type', 'webhook')
            ->first();
            
        if (!$trigger) {
            return true; // Если триггер не найден, пропускаем валидацию
        }
        
        $config = $trigger->conditions;
        
        // Если не настроена проверка подписи
        if (empty($config['verify_signature'])) {
            return true;
        }
        
        $secret = $config['webhook_secret'] ?? '';
        $signatureHeader = $config['signature_header'] ?? 'X-Webhook-Signature';
        $signature = $request->header($signatureHeader);
        
        if (!$signature) {
            return false;
        }
        
        // Вычисляем ожидаемую подпись
        $payload = $request->getContent();
        $algorithm = $config['signature_algorithm'] ?? 'sha256';
        
        switch ($algorithm) {
            case 'sha256':
                $expectedSignature = hash_hmac('sha256', $payload, $secret);
                break;
            case 'sha1':
                $expectedSignature = hash_hmac('sha1', $payload, $secret);
                break;
            default:
                $expectedSignature = hash_hmac('sha256', $payload, $secret);
        }
        
        // Сравниваем подписи (защита от timing attack)
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Проверить rate limiting
     */
    protected function checkRateLimit(BotFunction $function, Request $request): bool
    {
        $trigger = $function->triggers()
            ->where('type', 'webhook')
            ->first();
            
        if (!$trigger) {
            return true;
        }
        
        $config = $trigger->conditions;
        $rateLimit = $config['rate_limit'] ?? 60; // По умолчанию 60 запросов в минуту
        
        $key = sprintf(
            'webhook_rate:%s:%s',
            $function->id,
            $request->ip()
        );
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $rateLimit) {
            Log::warning('Webhook rate limit exceeded', [
                'function_id' => $function->id,
                'ip' => $request->ip(),
                'attempts' => $attempts
            ]);
            return false;
        }
        
        Cache::put($key, $attempts + 1, now()->addMinute());
        
        return true;
    }
    
    /**
     * Извлечь параметры из webhook данных
     */
    protected function extractWebhookParameters(BotFunction $function, Request $request): array
    {
        $parameters = [];
        $data = $request->all();
        
        // Получаем маппинг параметров из конфигурации триггера
        $trigger = $function->triggers()
            ->where('type', 'webhook')
            ->first();
            
        if ($trigger && isset($trigger->conditions['parameter_mapping'])) {
            $mapping = $trigger->conditions['parameter_mapping'];
            
            foreach ($mapping as $paramCode => $dataPath) {
                // Извлекаем значение по пути (поддержка dot notation)
                $value = data_get($data, $dataPath);
                
                if ($value !== null) {
                    $parameters[$paramCode] = $value;
                }
            }
        } else {
            // Автоматический маппинг по именам параметров
            foreach ($function->parameters as $param) {
                if (isset($data[$param->code])) {
                    $parameters[$param->code] = $data[$param->code];
                }
                // Также проверяем camelCase и snake_case варианты
                $camelCase = Str::camel($param->code);
                $snakeCase = Str::snake($param->code);
                
                if (isset($data[$camelCase])) {
                    $parameters[$param->code] = $data[$camelCase];
                } elseif (isset($data[$snakeCase])) {
                    $parameters[$param->code] = $data[$snakeCase];
                }
            }
        }
        
        // Валидируем обязательные параметры
        $this->validateRequiredParameters($function, $parameters);
        
        return $parameters;
    }
    
    /**
     * Найти или создать диалог для webhook
     */
    protected function findOrCreateConversation(BotFunction $function, Request $request): Conversation
    {
        $bot = $function->bot;
        $data = $request->all();
        
        // Пытаемся найти идентификатор пользователя в данных
        $userId = data_get($data, 'user_id') 
            ?? data_get($data, 'customer_id')
            ?? data_get($data, 'client_id')
            ?? 'webhook_' . md5($request->ip());
            
        // Ищем существующий диалог
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('external_id', $userId)
            ->where('channel', 'webhook')
            ->first();
            
        if (!$conversation) {
            // Создаем новый диалог
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel' => 'webhook',
                'external_id' => $userId,
                'user_name' => data_get($data, 'user_name') ?? 'Webhook User',
                'user_email' => data_get($data, 'user_email'),
                'user_phone' => data_get($data, 'user_phone'),
                'metadata' => [
                    'source' => 'webhook',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'first_webhook_at' => now()->toIso8601String()
                ]
            ]);
        }
        
        return $conversation;
    }
    
    /**
     * Создать сообщение о webhook
     */
    protected function createWebhookMessage(Conversation $conversation, Request $request): Message
    {
        $data = $request->all();
        
        $content = 'Webhook received: ' . ($data['event'] ?? $data['action'] ?? 'unknown');
        
        return $conversation->messages()->create([
            'role' => 'system',
            'content' => $content,
            'metadata' => [
                'type' => 'webhook',
                'data' => $data,
                'headers' => $request->headers->all(),
                'ip' => $request->ip()
            ]
        ]);
    }
    
    /**
     * Валидировать обязательные параметры
     */
    protected function validateRequiredParameters(BotFunction $function, array $parameters): void
    {
        $missing = [];
        
        foreach ($function->parameters as $param) {
            if ($param->is_required && !isset($parameters[$param->code])) {
                $missing[] = $param->code;
            }
        }
        
        if (!empty($missing)) {
            throw new \Exception(
                'Missing required parameters: ' . implode(', ', $missing)
            );
        }
    }
    
    /**
     * Сформировать ответ webhook
     */
    protected function formatWebhookResponse(BotFunction $function, array $result): array
    {
        $trigger = $function->triggers()
            ->where('type', 'webhook')
            ->first();
            
        if ($trigger && isset($trigger->conditions['response_format'])) {
            $format = $trigger->conditions['response_format'];
            
            switch ($format) {
                case 'custom':
                    // Используем кастомный шаблон ответа
                    $template = $trigger->conditions['response_template'] ?? [];
                    return $this->applyResponseTemplate($template, $result);
                    
                case 'minimal':
                    return [
                        'success' => $result['success'] ?? false
                    ];
                    
                default:
                    return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Применить шаблон ответа
     */
    protected function applyResponseTemplate(array $template, array $result): array
    {
        $response = [];
        
        foreach ($template as $key => $value) {
            if (is_string($value) && Str::startsWith($value, '{') && Str::endsWith($value, '}')) {
                // Это переменная, извлекаем значение из результата
                $path = trim($value, '{}');
                $response[$key] = data_get($result, $path);
            } else {
                $response[$key] = $value;
            }
        }
        
        return $response;
    }
    
    /**
     * Генерировать уникальный ключ webhook
     */
    protected function generateWebhookKey(BotFunction $function): string
    {
        return Str::random(32) . '_' . $function->id;
    }
    
    /**
     * Получить URL webhook
     */
    protected function getWebhookUrl(string $webhookKey): string
    {
        return route('webhook.handle', ['key' => $webhookKey]);
    }
    
    /**
     * Логировать получение webhook
     */
    protected function logWebhookReceived(BotFunction $function, Request $request): void
    {
        Log::info('Webhook received', [
            'function_id' => $function->id,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'data_size' => strlen($request->getContent())
        ]);
        
        // Сохраняем в БД для аналитики
        \DB::table('webhook_logs')->insert([
            'function_id' => $function->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'headers' => json_encode($request->headers->all()),
            'payload' => json_encode($request->all()),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}