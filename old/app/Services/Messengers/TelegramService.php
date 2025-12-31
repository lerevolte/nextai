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
            
            if (!$update->getMessage() || !$update->getMessage()->has('text')) {
                return;
            }

            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $text = $message->getText();
            $userId = $message->getFrom()->getId();
            $userName = $message->getFrom()->getFirstName() . ' ' . $message->getFrom()->getLastName();
            $bot = $channel->bot;

            // Обработка команды /start
            if ($text === '/start') {
                $this->handleCommand($telegram, $chatId, $text, $channel);
                return; // Выходим после отправки приветствия
            }
            
            // Обработка других команд
            if (str_starts_with($text, '/')) {
                $this->handleCommand($telegram, $chatId, $text, $channel);
                return;
            }

            // Получаем или создаем диалог
            $conversation = $this->getOrCreateConversation($channel, $chatId, $userId, $userName);
            
            // УБИРАЕМ автоматическую отправку приветствия при первом сообщении
            // Приветствие теперь отправляется только по команде /start

            // Сохраняем сообщение пользователя
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $text,
                'metadata' => [
                    'telegram_message_id' => $message->getMessageId(),
                    'from_telegram' => true,
                ],
            ]);

            // Проверяем статус диалога перед генерацией ответа
            if ($conversation->status === 'waiting_operator') {
                Log::info('Operator is handling conversation, skipping AI response', [
                    'conversation_id' => $conversation->id,
                    'chat_id' => $chatId
                ]);
                
                // $telegram->sendMessage([
                //     'chat_id' => $chatId,
                //     'text' => '👤 С вами сейчас работает оператор.',
                // ]);
                
                return;
            }

            // Отправляем индикатор "печатает..."
            $telegram->sendChatAction([
                'chat_id' => $chatId,
                'action' => 'typing',
            ]);

            // Генерируем ответ
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
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function handleCommand(Api $telegram, $chatId, string $command, Channel $channel)
    {
        $bot = $channel->bot;
        
        switch ($command) {
            case '/start':
                $replyMarkup = $this->getMainKeyboard($channel);
                
                $params = [
                    'chat_id' => $chatId,
                    'text' => $bot->welcome_message ?? "Здравствуйте! Я {$bot->name}. Чем могу помочь?",
                ];
                
                if ($replyMarkup) {
                    $params['reply_markup'] = json_encode($replyMarkup);
                }
                info('TELEGRAM HANDLE');
                $telegram->sendMessage($params);
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

        // Правильный формат для Telegram клавиатуры
        $keyboard = [];
        foreach (array_chunk($buttons, 2) as $row) {
            $keyboardRow = [];
            foreach ($row as $button) {
                // Проверяем тип кнопки и форматируем правильно
                if (is_string($button)) {
                    // Если это строка, создаем объект с текстом
                    $keyboardRow[] = ['text' => $button];
                } elseif (is_array($button)) {
                    // Если это массив, проверяем наличие поля text
                    if (isset($button['text']) && is_string($button['text'])) {
                        // Уже правильный формат
                        $keyboardRow[] = $button;
                    } elseif (isset($button['label'])) {
                        // Возможно, текст в поле label
                        $keyboardRow[] = ['text' => (string)$button['label']];
                    } elseif (isset($button['title'])) {
                        // Или в поле title
                        $keyboardRow[] = ['text' => (string)$button['title']];
                    } elseif (isset($button[0]) && is_string($button[0])) {
                        // Или первый элемент массива
                        $keyboardRow[] = ['text' => $button[0]];
                    } else {
                        // Пытаемся получить любое строковое значение
                        $text = $this->extractButtonText($button);
                        if ($text) {
                            $keyboardRow[] = ['text' => $text];
                        }
                    }
                } elseif (is_object($button)) {
                    // Если это объект, конвертируем в массив и обрабатываем
                    $buttonArray = (array)$button;
                    if (isset($buttonArray['text']) && is_string($buttonArray['text'])) {
                        $keyboardRow[] = ['text' => $buttonArray['text']];
                    } else {
                        $text = $this->extractButtonText($buttonArray);
                        if ($text) {
                            $keyboardRow[] = ['text' => $text];
                        }
                    }
                }
            }
            
            // Добавляем строку только если есть кнопки
            if (!empty($keyboardRow)) {
                $keyboard[] = $keyboardRow;
            }
        }

        // Возвращаем null если клавиатура пустая
        if (empty($keyboard)) {
            return null;
        }

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Вспомогательный метод для извлечения текста кнопки
     */
    protected function extractButtonText($button): ?string
    {
        if (is_string($button)) {
            return $button;
        }
        
        if (is_array($button) || is_object($button)) {
            $button = (array)$button;
            
            // Приоритет полей для поиска текста
            $fields = ['text', 'label', 'title', 'name', 'value', 'caption'];
            
            foreach ($fields as $field) {
                if (isset($button[$field]) && is_string($button[$field]) && !empty($button[$field])) {
                    return $button[$field];
                }
            }
            
            // Если не нашли в известных полях, берем первое строковое значение
            foreach ($button as $value) {
                if (is_string($value) && !empty($value)) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    protected function getOrCreateConversation(Channel $channel, $chatId, $userId, $userName)
    {
        // Сначала ищем активный диалог
        $activeConversation = Conversation::where('bot_id', $channel->bot_id)
            ->where('channel_id', $channel->id)
            ->where('external_id', $chatId)
            ->whereIn('status', ['active', 'waiting_operator'])
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($activeConversation) {
            return $activeConversation;
        }
        
        // Если нет активного, создаем новый
        return Conversation::create([
            'bot_id' => $channel->bot_id,
            'channel_id' => $channel->id,
            'external_id' => $chatId,
            'status' => 'active',
            'user_name' => $userName,
            'user_data' => [
                'telegram_user_id' => $userId,
                'telegram_chat_id' => $chatId,
            ],
        ]);
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
            'secret_token' => $channel->credentials['secret_token'],
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