<?

namespace App\Observers;

use App\Models\Conversation;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use Illuminate\Support\Facades\Log;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        $conversation = $message->conversation;
        
        // Проверяем интеграцию с Битрикс24
        $bitrix24Integration = $conversation->bot->crmIntegrations()
            ->where('type', 'bitrix24')
            ->wherePivot('is_active', true)
            ->first();
            
        if (!$bitrix24Integration) {
            return;
        }
        
        // Отправляем только сообщения пользователя
        // Сообщения бота/оператора из Битрикс24 не нужно отправлять обратно
        if ($message->role === 'user' && !($message->metadata['from_bitrix24'] ?? false)) {
            try {
                $provider = new Bitrix24ConnectorProvider($bitrix24Integration);
                $result = $provider->sendUserMessage($conversation, $message);
                
                if ($result['success']) {
                    Log::info('Message sent to Bitrix24 connector', [
                        'message_id' => $message->id,
                        'conversation_id' => $conversation->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send message to Bitrix24 connector', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}