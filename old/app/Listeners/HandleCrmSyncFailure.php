<?
namespace App\Listeners;

use App\Events\CrmSyncFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HandleCrmSyncFailure implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CrmSyncFailed $event): void
    {
        // Логируем ошибку
        Log::error('CRM Sync Failed', [
            'conversation_id' => $event->conversation->id,
            'integration' => $event->integration->type,
            'error' => $event->error,
        ]);

        // Считаем количество ошибок за последний час
        $cacheKey = "crm_sync_failures_{$event->integration->id}";
        $failures = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $failures, now()->addHour());

        // Если слишком много ошибок, деактивируем интеграцию
        if ($failures > 10) {
            $event->integration->update(['is_active' => false]);
            
            Log::critical('CRM integration deactivated due to multiple failures', [
                'integration_id' => $event->integration->id,
                'failures_count' => $failures,
            ]);

            // Отправляем уведомление администратору
            $this->notifyAdministrator($event->integration, $failures);
        }

        // Помечаем диалог для повторной синхронизации
        $event->conversation->update([
            'metadata' => array_merge($event->conversation->metadata ?? [], [
                'crm_sync_failed' => true,
                'crm_sync_error' => $event->error,
                'crm_sync_failed_at' => now()->toIso8601String(),
            ])
        ]);
    }

    /**
     * Notify administrator about integration failure
     */
    protected function notifyAdministrator($integration, $failuresCount): void
    {
        // Здесь можно отправить email или другое уведомление
        $admins = $integration->organization->users()
            ->whereHas('roles', function($q) {
                $q->where('name', 'admin');
            })
            ->get();

        foreach ($admins as $admin) {
            // Mail::to($admin->email)->queue(new CrmIntegrationFailedMail($integration, $failuresCount));
        }
    }
}