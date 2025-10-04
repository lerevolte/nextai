<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Jobs\SyncConversationToCrm;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ConversationObserver
{
    // Статическая переменная для предотвращения дублирования
    private static $processingConversations = [];
    
    public function created(Conversation $conversation): void
    {
        if ($conversation->messages()->where('role', 'user')->doesntExist()) {
            Log::info("Conversation {$conversation->id} created but has no user messages. Skipping initial CRM sync.");
            return;
        }
        // Предотвращаем дублирование обработки
        $lockKey = "conversation_processing_" . $conversation->id;
        
        if (Cache::has($lockKey)) {
            Log::info("Conversation {$conversation->id} already being processed, skipping");
            return;
        }
        
        // Устанавливаем блокировку на 30 секунд
        Cache::put($lockKey, true, 30);
        
        Log::info('Conversation created, starting CRM sync', [
            'conversation_id' => $conversation->id,
            'bot_id' => $conversation->bot_id
        ]);

        // Получаем все активные интеграции для бота
        $activeIntegrations = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->wherePivot('sync_conversations', true)
            ->get();

        if ($activeIntegrations->isEmpty()) {
            Log::info("No active CRM integrations for bot {$conversation->bot_id}");
            return;
        }

        foreach ($activeIntegrations as $integration) {
            if ($integration->type === 'bitrix24') {
                $this->handleBitrix24Integration($conversation, $integration);
            } else {
                // Для других CRM - стандартная синхронизация
                Log::info("Dispatching SyncConversationToCrm for {$integration->type}");
                SyncConversationToCrm::dispatch($conversation, $integration, 'create_lead')
                    ->delay(now()->addSeconds(10))->onQueue('high_priority');
            }
        }
    }
    
    private function handleBitrix24Integration(Conversation $conversation, $integration): void
    {
        if ($conversation->crm_lead_id || 
            ($conversation->metadata['bitrix24_initial_message_sent'] ?? false)) {
            Log::info("Bitrix24 sync already completed for conversation {$conversation->id}");
            return;
        }

        $existingSync = $integration->syncEntities()
            ->where('entity_type', 'conversation')
            ->where('local_id', $conversation->id)
            ->exists();
        
        if ($existingSync) {
            Log::info("Conversation {$conversation->id} already synced with Bitrix24");
            return;
        }

        // Получаем настройки привязки бота к интеграции
        $botIntegration = $integration->bots()
            ->where('bot_id', $conversation->bot_id)
            ->first();
            
        if (!$botIntegration) {
            Log::warning("Bot not connected to integration", [
                'bot_id' => $conversation->bot_id,
                'integration_id' => $integration->id
            ]);
            return;
        }
        
        // Получаем настройки коннектора из pivot таблицы
        $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
        
        Log::info("Connector settings for bot {$conversation->bot_id}", [
            'connector_settings' => $connectorSettings,
            'has_line_id' => !empty($connectorSettings['line_id']),
            'is_active' => !empty($connectorSettings['active'])
        ]);
        
        $credentials = $integration->credentials ?? [];
        $hasWebhook = !empty($credentials['webhook_url']);
        $hasOAuth = !empty($credentials['auth_id']) && !empty($credentials['domain']);
        $hasActiveConnector = !empty($connectorSettings['line_id']) && !empty($connectorSettings['active']);
        
        // Решаем, какой метод использовать
        if ($hasOAuth && $hasActiveConnector) {
            // Используем коннектор открытых линий (приоритетный метод)
            Log::info("Using Bitrix24 connector for conversation {$conversation->id}");
            $this->syncWithBitrix24Connector($conversation, $integration);
        } elseif ($hasWebhook) {
            // Используем webhook для создания лида напрямую
            Log::info("Using webhook for conversation {$conversation->id} (connector not available)");
            SyncConversationToCrm::dispatch($conversation, $integration, 'create_lead')
                ->delay(now()->addSeconds(5));
        } else {
            Log::error("No valid connection method for Bitrix24", [
                'conversation_id' => $conversation->id,
                'integration_id' => $integration->id,
                'has_webhook' => $hasWebhook,
                'has_oauth' => $hasOAuth,
                'has_connector' => $hasActiveConnector
            ]);
        }
    }

    public function updated(Conversation $conversation): void
    {
        // Пропускаем если обновляется только metadata или crm поля
        if ($conversation->wasChanged(['metadata', 'crm_lead_id', 'crm_deal_id', 'crm_contact_id'])) {
            Log::debug('Conversation metadata updated, skipping sync trigger');
            return;
        }

        // Проверяем блокировку на уровне observer
        $lockKey = "conversation_update_sync_{$conversation->id}";
        
        if (Cache::has($lockKey)) {
            Log::info('Update sync already triggered, skipping', [
                'conversation_id' => $conversation->id
            ]);
            return;
        }
        
        // Устанавливаем блокировку на 60 секунд
        Cache::put($lockKey, true, 60);
        
        Log::info('Conversation updated', ['conversation_id' => $conversation->id]);
        
        // Синхронизируем только если нет лида и данные пользователя заполнены
        if (!$conversation->crm_lead_id/* && 
            !empty($conversation->user_name) && 
            $conversation->user_name !== 'Гость'*/) {
            
            Log::info('Conversation user data updated, checking for sync');
            
            $activeIntegrations = $conversation->bot->crmIntegrations()
                ->wherePivot('is_active', true)
                ->wherePivot('sync_conversations', true)
                ->get();
                
            if ($activeIntegrations->isEmpty()) {
                Cache::forget($lockKey);
                return;
            }
            
            foreach ($activeIntegrations as $integration) {
                // Проверяем, не была ли уже запущена синхронизация для этой интеграции
                $integrationLockKey = "conversation_sync_{$conversation->id}_{$integration->id}";
                
                if (Cache::has($integrationLockKey)) {
                    Log::info('Sync already triggered for this integration', [
                        'conversation_id' => $conversation->id,
                        'integration_id' => $integration->id
                    ]);
                    continue;
                }
                
                Cache::put($integrationLockKey, true, 120);
                
                if ($integration->type === 'bitrix24') {
                    $this->handleBitrix24Integration($conversation, $integration);
                } else {
                    Log::info("Dispatching SyncConversationToCrm for {$integration->type}");
                    SyncConversationToCrm::dispatch($conversation, $integration, 'sync')
                        ->delay(now()->addSeconds(10))
                        ->onQueue('high_priority');
                }
            }
        }
    }

    // public function updated(Conversation $conversation): void
    // {
    //     // Обновляем данные только если изменились контакты и лид еще не создан
    //     // if (($conversation->isDirty('user_name') || 
    //     //      $conversation->isDirty('user_email') || 
    //     //      $conversation->isDirty('user_phone')) &&
    //     //     !$conversation->crm_lead_id) {
    //     info('Conversation updated');
    //     if (!$conversation->crm_lead_id) {
    //         Log::info('Conversation user data updated, checking for sync');
            
    //         if (!empty($conversation->user_name) && $conversation->user_name !== 'Гость') {
    //             $activeIntegrations = $conversation->bot->crmIntegrations()
    //                 ->wherePivot('is_active', true)
    //                 ->wherePivot('sync_conversations', true)
    //                 ->get();

    //             foreach ($activeIntegrations as $integration) {
    //                 if ($integration->type === 'bitrix24') {
    //                     $this->handleBitrix24Integration($conversation, $integration);
    //                 } else {
    //                     Log::info("Dispatching SyncConversationToCrm for {$integration->type}");
    //                     SyncConversationToCrm::dispatch($conversation, $integration, 'sync')->delay(now()->addSeconds(10))->onQueue('high_priority');
    //                 }
    //             }
    //         }
    //     }
    // }

    public function deleting(Conversation $conversation): void
    {
        if ($conversation->crm_lead_id || $conversation->crm_deal_id || $conversation->crm_contact_id) {
            Log::warning('Deleting conversation that was synced with CRM', [
                'conversation_id' => $conversation->id,
                'crm_lead_id' => $conversation->crm_lead_id,
                'crm_deal_id' => $conversation->crm_deal_id,
                'crm_contact_id' => $conversation->crm_contact_id,
            ]);
        }
    }

    /**
     * Синхронизация с Битрикс24 через коннектор открытых линий
     */
    protected function syncWithBitrix24Connector(Conversation $conversation, $integration): void
    {
        Log::info('syncWithBitrix24Connector');
        
        // Проверяем, не создан ли уже лид
        if ($conversation->crm_lead_id) {
            Log::info("Lead already exists for conversation {$conversation->id}: {$conversation->crm_lead_id}");
            return;
        }
        
        try {
            $provider = new Bitrix24ConnectorProvider($integration);
            
            // Отправляем первое сообщение в открытую линию
            // Это автоматически создаст чат и лид
            $result = $provider->sendInitialMessage($conversation);
            
            if ($result['success']) {
                Log::info('Message sent to open line successfully', [
                    'conversation_id' => $conversation->id,
                    'chat_id' => $result['chat_id'] ?? null,
                ]);
                
                // Битрикс24 автоматически создаст лид при первом сообщении
                // Мы получим его ID через webhook или при следующей синхронизации
            } else {
                // Если не удалось отправить через коннектор, пробуем webhook
                Log::warning('Failed to send message to open line, falling back to webhook', [
                    'conversation_id' => $conversation->id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                
                $credentials = $integration->credentials ?? [];
                if (isset($credentials['webhook_url'])) {
                    SyncConversationToCrm::dispatch($conversation, $integration, 'create_lead')
                        ->delay(now()->addSeconds(5));
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to sync with Bitrix24 connector', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to webhook
            $credentials = $integration->credentials ?? [];
            if (isset($credentials['webhook_url'])) {
                SyncConversationToCrm::dispatch($conversation, $integration, 'create_lead')
                    ->delay(now()->addSeconds(5));
            }
        }
    }
}