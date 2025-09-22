<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Jobs\SyncConversationToCrm;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use Illuminate\Support\Facades\Log;

class ConversationObserver
{
    public function created(Conversation $conversation): void
    {
        info('conversation created');

        // Получаем все активные интеграции для бота
        $activeIntegrations = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->get();

        if ($activeIntegrations->isEmpty()) {
            return;
        }

        // --- ИСПРАВЛЕНИЕ: Логика разделена. Сначала всегда пытаемся создать лид/сделку, потом работаем с коннектором. ---

        // 1. Ставим в очередь задачу на создание лида/контакта для ВСЕХ активных CRM, включая Битрикс24.
        // Эта задача использует стандартный Bitrix24Provider, который умеет создавать лиды.
        foreach ($activeIntegrations as $integration) {
            // Проверяем, нужно ли синхронизировать диалоги для этой конкретной интеграции
            $pivot = $integration->bots()->where('bot_id', $conversation->bot_id)->first()->pivot;
            if ($pivot->sync_conversations) {
                 Log::info("Dispatching SyncConversationToCrm for conversation {$conversation->id} with integration {$integration->id}");
                 // Запускаем задачу с небольшой задержкой, чтобы успели собраться первые данные
                 SyncConversationToCrm::dispatch($conversation, $integration, 'create_lead')
                    ->delay(now()->addSeconds(10));
            }
        }
        
        // 2. Если есть интеграция с Битрикс24, ДОПОЛНИТЕЛЬНО выполняем логику коннектора для создания чата в "Открытых линиях".
        $bitrix24Integration = $activeIntegrations->firstWhere('type', 'bitrix24');

        if ($bitrix24Integration) {
            info('Bitrix24 integration found, syncing with connector.');
            // Сразу начинаем синхронизацию для "Открытых линий"
            $this->syncWithBitrix24Connector($conversation, $bitrix24Integration);
        }
    }

    /**
     * Handle the Conversation "updated" event.
     */
    // public function updated(Conversation $conversation): void
    // {
    //     info('conversation updated');
    //     // Если изменился статус на "closed", синхронизируем с CRM
    //     if ($conversation->isDirty('status') && $conversation->status === 'closed') {
    //         info('conversation updated2');
    //         $this->syncIfNeeded($conversation, 'sync');
    //     }

    //     // Если добавлена контактная информация
    //     if ($conversation->isDirty(['user_email', 'user_phone', 'user_name'])) {
    //         info('conversation updated1');
    //         $this->syncIfNeeded($conversation, 'sync');
    //     }
    // }

    public function updated(Conversation $conversation)
    {
        info('conversation updated');
        // Проверяем, были ли обновлены ключевые контактные данные пользователя.
        if ($conversation->isDirty('user_name') || $conversation->isDirty('user_email') || $conversation->isDirty('user_phone')) {
            info('conversation user data updated, checking for sync');
            
            // Убедимся, что имя пользователя не пустое и не "Гость", прежде чем синхронизировать.
            if (!empty($conversation->user_name) && $conversation->user_name !== 'Гость') {
                
                $activeIntegrations = $conversation->bot->crmIntegrations()
                    ->wherePivot('is_active', true)
                    ->wherePivot('sync_conversations', true)
                    ->get();

                if ($activeIntegrations->isNotEmpty()) {
                    Log::info('Dispatching SyncConversationToCrm job due to updated user info.', ['conversation_id' => $conversation->id]);
                    // Отправляем задачу на синхронизацию (обновление данных) в CRM.
                    SyncConversationToCrm::dispatch($conversation);
                }
            }
        }
    }

    

    /**
     * Handle the Conversation "deleting" event.
     */
    public function deleting(Conversation $conversation): void
    {
        // Логируем удаление диалога, если он был синхронизирован с CRM
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
     * Запуск синхронизации если необходимо
     */
    protected function syncIfNeeded(Conversation $conversation, string $action): void
    {
        $hasActiveCrm = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->wherePivot('sync_conversations', true)
            ->exists();

        if ($hasActiveCrm) {
            SyncConversationToCrm::dispatch($conversation, null, $action);
        }
    }

    /**
     * Синхронизация с Битрикс24 через коннектор
     */
    protected function syncWithBitrix24Connector(Conversation $conversation, $integration): void
    {
        info('syncWithBitrix24Connector');
        try {
            $provider = new Bitrix24ConnectorProvider($integration);
            
            // Синхронизируем существующие сообщения, если они есть. 
            // Это создаст чат в открытой линии и отправит в него историю переписки.
            if ($conversation->messages()->exists()) {
                info('syncWithBitrix24Connector: messages exist, syncing now');
                $provider->syncConversationMessages($conversation);
            } else {
                info('syncWithBitrix24Connector: no messages yet, skipping sync.');
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to sync with Bitrix24 connector on conversation creation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}