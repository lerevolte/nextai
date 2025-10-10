<?php

namespace App\Observers;

use App\Models\Message;
use App\Models\Conversation;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use App\Services\FunctionExecutionService;
use Illuminate\Support\Facades\Log;

class MessageObserver
{
    protected FunctionExecutionService $functionService;
    
    public function __construct(FunctionExecutionService $functionService)
    {
        $this->functionService = $functionService;
    }
    
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        // Не обрабатываем сообщения, которые пришли ИЗ Битрикс24
        if ($message->metadata['from_bitrix24'] ?? false) {
            return;
        }

        // Отправляем в Битрикс только сообщения от пользователя или ассистента
        // И только если чат в Битриксе уже был создан (есть metadata)
        if (in_array($message->role, ['user', 'assistant']) && isset($message->conversation->metadata['bitrix24_chat_id'])) {
            $conversation = $message->conversation;
            $bitrix24Integration = $conversation->bot->crmIntegrations()
               ->where('type', 'bitrix24')->wherePivot('is_active', true)->first();
           
            if ($bitrix24Integration) {
                try {
                    $provider = new \App\Services\CRM\Providers\Bitrix24ConnectorProvider($bitrix24Integration);
                    $provider->sendUserMessage($conversation, $message);
                } catch (\Exception $e) {
                    Log::error('EXCEPTION in MessageObserver', ['message_id' => $message->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // ВАЖНО: Обрабатываем функции только для сообщений пользователя
        // И ТОЛЬКО если диалог в статусе 'active' (НЕ waiting_operator)
        if ($message->role === 'user') {
            $conversation = $message->conversation;
            
            // Проверяем статус диалога
            if ($conversation->status === 'waiting_operator') {
                Log::info('Skipping function processing - operator is handling conversation', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'status' => $conversation->status
                ]);
                return;
            }
            
            // Только для активных диалогов обрабатываем функции
            if ($conversation->status === 'active') {
                $this->functionService->processMessage($message);
            }
        }
    }
}