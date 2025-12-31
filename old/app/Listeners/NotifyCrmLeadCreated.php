<?
namespace App\Listeners;

use App\Events\CrmLeadCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyCrmLeadCreated implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CrmLeadCreated $event): void
    {
        Log::info('CRM Lead Created', [
            'conversation_id' => $event->conversation->id,
            'integration' => $event->integration->type,
            'lead_id' => $event->leadData['id'] ?? null,
        ]);

        // Отправляем уведомление оператору если настроено
        if ($event->conversation->bot->settings['notify_on_lead_create'] ?? false) {
            $operators = $event->conversation->bot->organization->users()
                ->whereHas('roles', function($q) {
                    $q->whereIn('name', ['admin', 'operator']);
                })
                ->get();

            foreach ($operators as $operator) {
                // Mail::to($operator->email)->queue(new LeadCreatedMail($event));
            }
        }

        // Можем также отправить webhook если настроен
        if ($webhookUrl = $event->conversation->bot->settings['lead_webhook_url'] ?? null) {
            $this->sendWebhook($webhookUrl, [
                'event' => 'lead_created',
                'conversation_id' => $event->conversation->id,
                'lead_data' => $event->leadData,
                'crm_type' => $event->integration->type,
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * Send webhook notification
     */
    protected function sendWebhook(string $url, array $data): void
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $client->post($url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send lead webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}