<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Jobs\SyncConversationToCrm;
use Illuminate\Support\Facades\Log;

class ConversationObserver
{
    /**
     * Handle the Conversation "created" event.
     */
    public function created(Conversation $conversation): void
    {
        // Проверяем, есть ли активные CRM интеграции для бота
        $hasActiveCrm = $conversation->bot->crmIntegrations()
            ->wherePivot('is_active', true)
            ->wherePivot('sync_conversations', true)
            ->exists();

        if ($hasActiveCrm) {
            // Запускаем синхронизацию через 5 секунд после создания
            SyncConversationToCrm::dispatch($conversation, null, 'create_lead')
                ->delay(now()->addSeconds(5));
        }
    }

    /**
     * Handle the Conversation "updated" event.
     */
    public function updated(Conversation $conversation): void
    {
        // Если изменился статус на "closed", синхронизируем с CRM
        if ($conversation->isDirty('status') && $conversation->status === 'closed') {
            $this->syncIfNeeded($conversation, 'sync');
        }

        // Если добавлена контактная информация
        if ($conversation->isDirty(['user_email', 'user_phone', 'user_name'])) {
            $this->syncIfNeeded($conversation, 'sync');
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
}