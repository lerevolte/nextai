<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Conversation;
use App\Services\Messengers\TelegramService;
use App\Services\Messengers\WhatsAppService;
use App\Services\Messengers\VKService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChannelMessageService
{
    protected TelegramService $telegramService;
    protected WhatsAppService $whatsAppService;
    protected VKService $vkService;
    
    public function __construct(
        TelegramService $telegramService,
        WhatsAppService $whatsAppService,
        VKService $vkService
    ) {
        $this->telegramService = $telegramService;
        $this->whatsAppService = $whatsAppService;
        $this->vkService = $vkService;
    }
    
    /**
     * Отправить сообщение в канал пользователя
     */
    public function sendToChannel(Message $message): bool
    {
        // Проверка на дубли
        $cacheKey = "channel_msg_sent_{$message->id}_{$message->conversation->channel->type}";
        if (Cache::has($cacheKey)) {
            Log::info("🚫 Message already sent to channel", [
                'message_id' => $message->id,
                'channel_type' => $message->conversation->channel->type
            ]);
            return true;
        }
        
        $conversation = $message->conversation;
        $channel = $conversation->channel;
        
        // Не отправляем системные сообщения
        if ($message->role === 'system') {
            return false;
        }
        
        // Не отправляем сообщения, которые пришли из этого канала
        if ($message->metadata['from_' . $channel->type] ?? false) {
            return false;
        }
        
        try {
            $result = false;
            
            switch ($channel->type) {
                case 'telegram':
                    $result = $this->sendToTelegram($message, $channel, $conversation);
                    break;
                case 'whatsapp':
                    $result = $this->sendToWhatsApp($message, $channel, $conversation);
                    break;
                case 'vk':
                    $result = $this->sendToVK($message, $channel, $conversation);
                    break;
                case 'web':
                    $result = true;
                    break;
            }
            
            // Кешируем успешную отправку
            if ($result) {
                Cache::put($cacheKey, true, 3600);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Failed to send to channel', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Отправить в Telegram
     */
    protected function sendToTelegram(Message $message, $channel, Conversation $conversation): bool
    {
        try {
            $telegram = new \Telegram\Bot\Api($channel->credentials['bot_token']);
            
            // Получаем chat_id из external_id разговора
            $chatId = $conversation->external_id;
            
            // Формируем текст в зависимости от роли
            $text = $message->content;
            
            if ($message->role === 'operator') {
                $operatorName = $message->metadata['operator_name'] ?? 'Оператор';
                $text = "👤 *{$operatorName}:*\n\n{$text}";
            } elseif ($message->role === 'assistant') {
                $text = "🤖 *Бот:*\n\n{$text}";
            }
            
            $sentMessage = $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
            
            // Сохраняем ID отправленного сообщения
            $message->update([
                'metadata' => array_merge($message->metadata ?? [], [
                    'telegram_message_id' => $sentMessage->getMessageId(),
                    'sent_to_telegram_at' => now()->toIso8601String(),
                ])
            ]);
            
            Log::info('Message sent to Telegram', [
                'message_id' => $message->id,
                'telegram_message_id' => $sentMessage->getMessageId(),
                'chat_id' => $chatId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send to Telegram', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Отправить в WhatsApp
     */
    protected function sendToWhatsApp(Message $message, $channel, Conversation $conversation): bool
    {
        try {
            // Формируем номер телефона
            $to = str_replace('whatsapp:', '', $conversation->external_id);
            
            // Формируем текст
            $text = $message->content;
            
            if ($message->role === 'operator') {
                $operatorName = $message->metadata['operator_name'] ?? 'Оператор';
                $text = "👤 {$operatorName}:\n\n{$text}";
            }
            
            $result = $this->whatsAppService->sendMessage($channel, $to, $text);
            
            // Сохраняем ID сообщения
            if ($result) {
                $message->update([
                    'metadata' => array_merge($message->metadata ?? [], [
                        'whatsapp_message_id' => $result->sid,
                        'sent_to_whatsapp_at' => now()->toIso8601String(),
                    ])
                ]);
            }
            
            Log::info('Message sent to WhatsApp', [
                'message_id' => $message->id,
                'to' => $to
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send to WhatsApp', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Отправить в VK
     */
    protected function sendToVK(Message $message, $channel, Conversation $conversation): bool
    {
        try {
            // Получаем user_id из external_id
            $userId = (int)$conversation->external_id;
            
            // Формируем текст
            $text = $message->content;
            
            if ($message->role === 'operator') {
                $operatorName = $message->metadata['operator_name'] ?? 'Оператор';
                $text = "👤 {$operatorName}:\n\n{$text}";
            }
            
            $result = $this->vkService->sendMessage($channel, $userId, $text);
            
            Log::info('Message sent to VK', [
                'message_id' => $message->id,
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send to VK', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}