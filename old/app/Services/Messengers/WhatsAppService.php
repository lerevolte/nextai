<?php

namespace App\Services\Messengers;

use App\Models\Channel;
use App\Models\Conversation;
use App\Services\AIService;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected AIService $aiService;
    protected Client $twilio;
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function processWebhook(Channel $channel, array $data)
    {
        try {
            // Инициализируем Twilio клиент
            $this->twilio = new Client(
                $channel->credentials['account_sid'],
                $channel->credentials['auth_token']
            );

            $from = $data['From']; // WhatsApp номер пользователя
            $to = $data['To'];     // Наш WhatsApp номер
            $body = $data['Body']; // Текст сообщения
            $messageId = $data['MessageSid'];

            // Извлекаем номер телефона
            $phoneNumber = str_replace('whatsapp:', '', $from);

            // Получаем или создаем диалог
            $conversation = $this->getOrCreateConversation($channel, $phoneNumber);

            // Сохраняем сообщение пользователя
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $body,
                'metadata' => [
                    'whatsapp_message_id' => $messageId,
                    'whatsapp_from' => $from,
                ],
            ]);

            // Генерируем ответ
            $bot = $channel->bot;
            $responseContent = $this->aiService->generateResponse($bot, $conversation, $body);

            // Отправляем ответ через WhatsApp
            $message = $this->twilio->messages->create(
                $from, // Кому отправляем
                [
                    'from' => $to, // От нашего номера
                    'body' => $responseContent,
                ]
            );

            // Сохраняем ответ бота
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'metadata' => [
                    'whatsapp_message_id' => $message->sid,
                ],
            ]);

            // Обновляем счетчики
            $conversation->increment('messages_count', 2);
            $conversation->update(['last_message_at' => now()]);

            // Возвращаем успешный ответ Twilio
            return response('', 200);

        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage(), [
                'channel_id' => $channel->id,
                'data' => $data,
            ]);
            
            return response('Error', 500);
        }
    }

    protected function getOrCreateConversation(Channel $channel, string $phoneNumber)
    {
        return Conversation::firstOrCreate(
            [
                'bot_id' => $channel->bot_id,
                'channel_id' => $channel->id,
                'external_id' => $phoneNumber,
                'status' => 'active',
            ],
            [
                'user_phone' => $phoneNumber,
                'user_data' => [
                    'whatsapp_phone' => $phoneNumber,
                ],
            ]
        );
    }

    public function sendMessage(Channel $channel, string $to, string $message)
    {
        $this->twilio = new Client(
            $channel->credentials['account_sid'],
            $channel->credentials['auth_token']
        );

        return $this->twilio->messages->create(
            "whatsapp:$to",
            [
                'from' => "whatsapp:" . $channel->credentials['phone_number'],
                'body' => $message,
            ]
        );
    }

    public function sendMediaMessage(Channel $channel, string $to, string $message, string $mediaUrl)
    {
        $this->twilio = new Client(
            $channel->credentials['account_sid'],
            $channel->credentials['auth_token']
        );

        return $this->twilio->messages->create(
            "whatsapp:$to",
            [
                'from' => "whatsapp:" . $channel->credentials['phone_number'],
                'body' => $message,
                'mediaUrl' => [$mediaUrl],
            ]
        );
    }
}