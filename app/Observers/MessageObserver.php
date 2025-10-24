<?php

namespace App\Observers;

use App\Models\Message;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use App\Services\MessageProcessingService;
use App\Services\ChannelMessageService;
use Illuminate\Support\Facades\Log;

class MessageObserver
{
    protected MessageProcessingService $messageProcessingService;
    protected ChannelMessageService $channelMessageService;
    
    public function __construct(
        MessageProcessingService $messageProcessingService,
        ChannelMessageService $channelMessageService
    ) {
        $this->messageProcessingService = $messageProcessingService;
        $this->channelMessageService = $channelMessageService;
    }
    
    public function created(Message $message): void
    {
        $conversation = $message->conversation;
        
        // Не обрабатываем сообщения, которые пришли ИЗ Битрикс24
        if ($message->metadata['from_bitrix24'] ?? false) {
            Log::info('Message from Bitrix24, checking if need to send to channel', [
                'message_id' => $message->id,
                'role' => $message->role,
                'channel_type' => $conversation->channel->type
            ]);
            
            // Отправляем в канал ТОЛЬКО сообщения оператора (НЕ бота)
            if ($message->role === 'operator' && $conversation->channel->type !== 'web') {
                Log::info('Sending operator message to channel', [
                    'message_id' => $message->id,
                    'channel_type' => $conversation->channel->type
                ]);
                
                $this->channelMessageService->sendToChannel($message);
            }
            
            return;
        }

        // Отправляем в Битрикс24 только если чат уже создан
        $hasBitrix24Chat = isset($conversation->metadata['bitrix24_chat_id']);
        
        if (in_array($message->role, ['user', 'assistant']) && $hasBitrix24Chat) {
            $bitrix24Integration = $conversation->bot->crmIntegrations()
               ->where('type', 'bitrix24')
               ->wherePivot('is_active', true)
               ->first();
           
            if ($bitrix24Integration) {
                try {
                    $provider = new Bitrix24ConnectorProvider($bitrix24Integration);
                    $provider->sendUserMessage($conversation, $message);
                } catch (\Exception $e) {
                    Log::error('Failed to send to Bitrix24', [
                        'message_id' => $message->id, 
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Обрабатываем функции только для сообщений пользователя
        if ($message->role === 'user') {
            if ($conversation->status === 'waiting_operator') {
                Log::info('🔴 Bot is DISABLED - operator is handling', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id
                ]);
                return;
            }
            
            if ($conversation->status === 'active') {
                Log::info('🟢 Bot is ACTIVE - processing message', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id
                ]);
                
                $this->messageProcessingService->processMessage($message);
            }
        }
        
        // КРИТИЧНО: НЕ отправляем ответы бота обратно в канал
        // Они уже отправлены в TelegramService/WhatsAppService/VKService
        // Битрикс24 получает их через sendUserMessage выше
    }
}