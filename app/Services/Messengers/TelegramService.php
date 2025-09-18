<?php

namespace App\Services\Messengers;

use App\Models\Bot;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected AIService $aiService;
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function processWebhook(Channel $channel, array $data)
    {
        try {
            $telegram = new Api($channel->credentials['bot_token']);
            $update = new Update($data);
            
            // Обрабатываем только текстовые сообщения
            if (!$update->getMessage() || !$update->getMessage()->has('text')) {
                return;
            }

            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $text = $message->getText();
            $userId = $message->getFrom()->getId();
            $userName = $message->getFrom()->getFirstName() . ' ' . $message->getFrom()->getLastName();

            // Обработка команд
            if (str_starts_with($text, '/')) {
                $this->handleCommand($telegram, $chatId, $text, $channel);
                return;
            }

            // Получаем или создаем диалог
            $conversation = $this->getOrCreateConversation($channel, $chatId, $userId, $userName);

            // Сохраняем сообщение пользователя
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $text,
                'metadata' => [
                    'telegram_message_id' => $message->getMessageId(),
                ],
            ]);

            // Отправляем индикатор "печатает..."
            $telegram->sendChatAction([
                'chat_id' => $chatId,
                'action' => 'typing',
            ]);

            // Генерируем ответ
            $bot = $channel->bot;
            $responseContent = $this->aiService->generateResponse($bot, $conversation, $text);

            // Отправляем ответ
            $sentMessage = $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $responseContent,
                'parse_mode' => 'Markdown',
            ]);

            // Сохраняем ответ бота
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'metadata' => [
                    'telegram_message_id' => $sentMessage->getMessageId(),
                ],
            ]);

            // Обновляем счетчики
            $conversation->increment('messages_count', 2);
            $conversation->update(['last_message_at' => now()]);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage(), [
                'channel_id' => $channel->id,
                'data' => $data,
            ]);
        }
    }

    protected function handleCommand(Api $telegram, $chatId, string $command, Channel $channel)
    {
        $bot = $channel->bot;
        
        switch ($command) {
            case '/start':
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $bot->welcome_message ?? "Здравствуйте! Я {$bot->name}. Чем могу помочь?",
                    'reply_markup' => $this->getMainKeyboard($channel),
                ]);
                break;
                
            case '/help':
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Доступные команды:\n/start - Начать диалог\n/help - Помощь\n/reset - Начать новый диалог\n/contact - Связаться с оператором",
                ]);
                break;
                
            case '/reset':
                // Закрываем текущий диалог
                Conversation::where('channel_id', $channel->id)
                    ->where('external_id', $chatId)
                    ->where('status', 'active')
                    ->update(['status' => 'closed', 'closed_at' => now()]);
                    
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Диалог сброшен. Начните новую беседу.",
                ]);
                break;
                
            case '/contact':
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Переключаю на оператора. Пожалуйста, подождите...",
                ]);
                
                // Здесь можно добавить логику уведомления операторов
                $this->notifyOperators($channel, $chatId);
                break;
        }
    }

    protected function getMainKeyboard(Channel $channel)
    {
        $settings = $channel->settings ?? [];
        $buttons = $settings['quick_replies'] ?? [];
        
        if (empty($buttons)) {
            return null;
        }

        $keyboard = [];
        foreach (array_chunk($buttons, 2) as $row) {
            $keyboard[] = $row;
        }

        return json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);
    }

    protected function getOrCreateConversation(Channel $channel, $chatId, $userId, $userName)
    {
        return Conversation::firstOrCreate(
            [
                'bot_id' => $channel->bot_id,
                'channel_id' => $channel->id,
                'external_id' => $chatId,
                'status' => 'active',
            ],
            [
                'user_name' => $userName,
                'user_data' => [
                    'telegram_user_id' => $userId,
                    'telegram_chat_id' => $chatId,
                ],
            ]
        );
    }

    protected function notifyOperators(Channel $channel, $chatId)
    {
        // Отправка уведомлений операторам
        // Можно использовать события Laravel
        event(new \App\Events\OperatorNeeded($channel, $chatId));
    }

    public function setWebhook(Channel $channel)
    {
        $telegram = new Api($channel->credentials['bot_token']);
        
        $webhookUrl = route('webhooks.telegram', $channel);
        
        $telegram->setWebhook([
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'callback_query'],
        ]);
        
        return true;
    }

    public function removeWebhook(Channel $channel)
    {
        $telegram = new Api($channel->credentials['bot_token']);
        $telegram->removeWebhook();
        
        return true;
    }
}