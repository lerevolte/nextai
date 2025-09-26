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
        $messageId = $message->id;
        $conversationId = $message->conversation_id;
        $role = $message->role;
        
        Log::info("=== MessageObserver::created ===", [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'role' => $role,
            'content_preview' => substr($message->content, 0, 50) . '...'
        ]);
        
        if ($message->role === 'user') {
            $isFirstUserMessage = $message->conversation->messages()
                ->where('role', 'user')
                ->where('id', '<=', $message->id)
                ->count() === 1;
            
            Log::info("User message check", [
                'is_first' => $isFirstUserMessage,
                'has_initial_sent' => $message->conversation->metadata['bitrix24_initial_message_sent'] ?? false
            ]);
                
            if (!$isFirstUserMessage && 
                !($message->conversation->metadata['bitrix24_initial_message_sent'] ?? false)) {
                Log::info("SKIP: Not first message and open line not created");
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
            Log::info("SKIP: No active Bitrix24 integration");
            return;
        }
        
        // Отправляем только сообщения пользователя и бота
        $shouldSync = in_array($message->role, ['user', 'assistant']) && 
                      !($message->metadata['from_bitrix24'] ?? false);
        
        Log::info("Sync check", [
            'should_sync' => $shouldSync,
            'role' => $message->role,
            'from_bitrix24' => $message->metadata['from_bitrix24'] ?? false
        ]);
                      
        if (!$shouldSync) {
            Log::info("SKIP: Message should not be synced");
            return;
        }
        
        try {
            $provider = new Bitrix24ConnectorProvider($bitrix24Integration);
            
            // Проверяем, что коннектор настроен
            $botIntegration = $bitrix24Integration->bots()
                ->where('bot_id', $conversation->bot_id)
                ->first();
                
            if (!$botIntegration) {
                Log::warning("SKIP: Bot integration not found");
                return;
            }
            
            $connectorSettings = json_decode($botIntegration->pivot->connector_settings, true) ?? [];
            
            Log::info("Connector check", [
                'has_line_id' => !empty($connectorSettings['line_id']),
                'is_active' => !empty($connectorSettings['active']),
                'settings' => $connectorSettings
            ]);
            
            if (empty($connectorSettings['line_id']) || empty($connectorSettings['active'])) {
                Log::info('SKIP: Bitrix24 connector not configured', [
                    'bot_id' => $conversation->bot_id,
                    'message_id' => $message->id
                ]);
                return;
            }
            
            Log::info("SENDING message to Bitrix24 connector");
            $result = $provider->sendUserMessage($conversation, $message);
            
            if ($result['success']) {
                Log::info('SUCCESS: Message sent to Bitrix24 connector', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
            } else {
                Log::warning('FAILED: Could not send to Bitrix24 connector', [
                    'message_id' => $message->id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('EXCEPTION in MessageObserver', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}