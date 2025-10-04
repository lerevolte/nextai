<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Services\CRM\CrmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncConversationToCrm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Conversation $conversation;
    protected ?CrmIntegration $integration;
    protected string $action;

    /**
     * Количество попыток выполнения
     */
    public $tries = 3;

    /**
     * Таймаут выполнения (секунды)
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(Conversation $conversation, CrmIntegration $integration = null, string $action = 'sync')
    {
        $this->conversation = $conversation;
        $this->integration = $integration;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(CrmService $crmService): void
    {
        Log::info('🚀 SyncConversationToCrm Job started', [
            'conversation_id' => $this->conversation->id,
            'bot_id' => $this->conversation->bot_id,
            'action' => $this->action,
            'has_integration' => $this->integration ? 'yes' : 'no'
        ]);
        if ($this->action === 'create_lead' && $this->conversation->crm_lead_id) {
            Log::info('Lead already exists, skipping sync', [
                'conversation_id' => $this->conversation->id,
                'lead_id' => $this->conversation->crm_lead_id
            ]);
            return;
        }

        // Проверяем блокировку на уровне Job
        $lockKey = "crm_sync_job_{$this->conversation->id}_{$this->action}";
        if (Cache::has($lockKey)) {
            Log::info('Sync job already running', ['conversation_id' => $this->conversation->id]);
            return;
        }
        
        Cache::put($lockKey, true, 300);

        try {
            Log::info('Starting CRM sync', [
                'conversation_id' => $this->conversation->id,
                'integration_id' => $this->integration?->id,
                'action' => $this->action,
            ]);

            if ($this->integration) {
                // Синхронизация с конкретной CRM
                $this->syncWithIntegration($crmService, $this->integration);
            } else {
                // Синхронизация со всеми подключенными CRM
                $integrations = $this->conversation->bot->crmIntegrations()
                    ->wherePivot('is_active', true)
                    ->get();

                foreach ($integrations as $integration) {
                    $this->syncWithIntegration($crmService, $integration);
                }
            }

            Log::info('CRM sync completed', [
                'conversation_id' => $this->conversation->id,
            ]);

        } catch (\Exception $e) {
            Log::error('CRM sync failed', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Повторная попытка через некоторое время
            if ($this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts()); // Увеличиваем задержку с каждой попыткой
            }
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Синхронизация с конкретной интеграцией
     */
    protected function syncWithIntegration(CrmService $crmService, CrmIntegration $integration): void
    {
        $provider = $crmService->getProvider($integration);
        
        if (!$provider) {
            Log::warning('CRM provider not available', [
                'integration_id' => $integration->id,
                'type' => $integration->type,
            ]);
            return;
        }

        $settings = $integration->bots()
            ->wherePivot('bot_id', $this->conversation->bot_id)
            ->first()?->pivot;

        if (!$settings || !$settings->is_active) {
            return;
        }

        switch ($this->action) {
            case 'create_lead':
                if ($settings->create_leads && !$this->conversation->crm_lead_id) {
                    $leadData = [
                        'source_id' => $settings->lead_source,
                        'responsible_user_id' => $settings->responsible_user_id,
                    ];
                    
                    if ($settings->pipeline_settings) {
                        $leadData = array_merge($leadData, $settings->pipeline_settings);
                    }
                    
                    $provider->createLead($this->conversation, $leadData);
                }
                break;

            case 'create_deal':
                if ($settings->create_deals && !$this->conversation->crm_deal_id) {
                    $dealData = [
                        'responsible_user_id' => $settings->responsible_user_id,
                    ];
                    
                    if ($settings->pipeline_settings) {
                        $dealData = array_merge($dealData, $settings->pipeline_settings);
                    }
                    
                    $provider->createDeal($this->conversation, $dealData);
                }
                break;

            case 'sync':
            default:
                info('syncConversatio1n');
                $provider->syncConversation($this->conversation);
                break;
        }
    }

    /**
     * Обработка неудачного выполнения
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CRM sync job failed permanently', [
            'conversation_id' => $this->conversation->id,
            'integration_id' => $this->integration?->id,
            'error' => $exception->getMessage(),
        ]);

        // Можно отправить уведомление администратору
        // Notification::send($admins, new CrmSyncFailedNotification($this->conversation, $exception));
    }
}