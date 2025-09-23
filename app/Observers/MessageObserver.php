<?php

namespace App\Observers;

use App\Models\Message;
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
        if ($message->role === 'user') {
            $isFirstUserMessage = $message->conversation->messages()
                ->where('role', 'user')
                ->where('id', '<=', $message->id)
                ->count() === 1;
                
            if (!$isFirstUserMessage && 
                !($message->conversation->metadata['bitrix24_initial_message_sent'] ?? false)) {
                // Не первое сообщение и открытая линия еще не создана - не отправляем
                return;
            }
        }
        $conversation = $message->conversation;
        
        // Проверяем интеграцию с Битрикс24
        $bitrix24Integration = $conversation->bot->crmIntegrations()
            ->where('type', 'bitrix24')
            ->wherePivot('is_active', true)
            ->first();
            
        if (!$bitrix24Integration) {
            return;
        }
        
        // Отправляем только сообщения пользователя и бота
        // НЕ отправляем сообщения от оператора или системы из Битрикс24
        $shouldSync = in_array($message->role, ['user', 'assistant']) && 
                      !($message->metadata['from_bitrix24'] ?? false);
                      
        if (!$shouldSync) {
            return;
        }
        
        try {
            $provider = new Bitrix24ConnectorProvider($bitrix24Integration);
            
            // Проверяем, что коннектор настроен
            $botIntegration = $bitrix24Integration->bots()
                ->where('bot_id', $conversation->bot_id)
                ->first();
                
            if (!$botIntegration) {
                return;
            }
            
            $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
            
            if (empty($connectorSettings['line_id']) || empty($connectorSettings['active'])) {
                Log::info('Bitrix24 connector not configured for message sync', [
                    'bot_id' => $conversation->bot_id,
                    'message_id' => $message->id
                ]);
                return;
            }
            
            $result = $provider->sendUserMessage($conversation, $message);
            
            if ($result['success']) {
                Log::info('Message sent to Bitrix24 connector', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
            } else {
                Log::warning('Failed to send message to Bitrix24 connector', [
                    'message_id' => $message->id,
                    'error' => $result['error'] ?? 'Unknown error'
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