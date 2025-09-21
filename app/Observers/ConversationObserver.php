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
        // Проверяем, есть ли активные CRM интеграции для бота
        $bitrix24Integration = $conversation->bot->crmIntegrations()
            ->where('type', 'bitrix24')
            ->wherePivot('is_active', true)
            ->first();

        if ($bitrix24Integration) {
            // Для Битрикс24 с коннектором не нужна задержка
            // Сразу начинаем синхронизацию
            $this->syncWithBitrix24Connector($conversation, $bitrix24Integration);
        }
        
        // Для других CRM используем старый подход
        $otherCrmIntegrations = $conversation->bot->crmIntegrations()
            ->where('type', '!=', 'bitrix24')
            ->wherePivot('is_active', true)
            ->wherePivot('sync_conversations', true)
            ->exists();

        if ($otherCrmIntegrations) {
            // Запускаем синхронизацию через 5 секунд после создания
            SyncConversationToCrm::dispatch($conversation, null, 'create_lead')
                ->delay(now()->addSeconds(5));
        }
    }

    /**
     * Handle the Conversation "updated" event.
     */
    // public function updated(Conversation $conversation): void
    // {
    //     // Если изменился статус на "closed", синхронизируем с CRM
    //     if ($conversation->isDirty('status') && $conversation->status === 'closed') {
    //         $this->syncIfNeeded($conversation, 'sync');
    //     }

    //     // Если добавлена контактная информация
    //     if ($conversation->isDirty(['user_email', 'user_phone', 'user_name'])) {
    //         $this->syncIfNeeded($conversation, 'sync');
    //     }
    // }

    public function updated(Conversation $conversation)
    {
        // Проверяем, были ли обновлены контактные данные пользователя.
        if ($conversation->isDirty('user_name') || $conversation->isDirty('user_email') || $conversation->isDirty('user_phone')) {
            
            // Убедимся, что имя пользователя не пустое и не "Гость".
            if (!empty($conversation->user_name) && $conversation->user_name !== 'Гость') {
                
                $crmIntegration = $conversation->bot->organization->crmIntegration;

                if ($crmIntegration && $crmIntegration->is_active && !$conversation->crm_deal_id) {
                    Log::info('Dispatching SyncConversationToCrm job.', ['conversation_id' => $conversation->id]);
                    // Отправляем задачу на синхронизацию с CRM в очередь.
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
        try {
            // Проверяем, зарегистрирован ли коннектор для бота
            $bot = $conversation->bot;
            if (!($bot->metadata['bitrix24_connector_registered'] ?? false)) {
                Log::info('Bitrix24 connector not registered for bot, registering now', [
                    'bot_id' => $bot->id
                ]);
                
                // Регистрируем коннектор
                $provider = new Bitrix24ConnectorProvider($integration);
                $result = $provider->registerConnector($bot);
                
                if (!$result['success']) {
                    Log::error('Failed to register Bitrix24 connector', [
                        'bot_id' => $bot->id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                    return;
                }
            }
            
            // Синхронизируем существующие сообщения если есть
            if ($conversation->messages()->exists()) {
                $provider = new Bitrix24ConnectorProvider($integration);
                $provider->syncConversationMessages($conversation);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to sync with Bitrix24 connector', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}