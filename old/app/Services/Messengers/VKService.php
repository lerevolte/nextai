<?php

namespace App\Services\Messengers;

use App\Models\Channel;
use App\Models\Conversation;
use App\Services\AIService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class VKService
{
    protected AIService $aiService;
    protected Client $httpClient;
    protected const API_VERSION = '5.131';
    protected const API_URL = 'https://api.vk.com/method/';
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
        $this->httpClient = new Client();
    }

    public function processWebhook(Channel $channel, array $data)
    {
        try {
            // Проверка подтверждения сервера
            if ($data['type'] === 'confirmation') {
                return response($channel->credentials['confirmation_token'], 200);
            }

            // Обрабатываем только новые сообщения
            if ($data['type'] !== 'message_new') {
                return response('ok', 200);
            }

            $message = $data['object']['message'];
            $userId = $message['from_id'];
            $text = $message['text'];
            $peerId = $message['peer_id'];

            // Получаем информацию о пользователе
            $userInfo = $this->getUserInfo($channel, $userId);

            // Получаем или создаем диалог
            $conversation = $this->getOrCreateConversation($channel, $userId, $userInfo);

            // Сохраняем сообщение пользователя
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $text,
                'metadata' => [
                    'vk_message_id' => $message['id'],
                    'vk_peer_id' => $peerId,
                ],
            ]);

            // Генерируем ответ
            $bot = $channel->bot;
            $responseContent = $this->aiService->generateResponse($bot, $conversation, $text);

            // Отправляем ответ
            $this->sendMessage($channel, $userId, $responseContent);

            // Сохраняем ответ бота
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
            ]);

            // Обновляем счетчики
            $conversation->increment('messages_count', 2);
            $conversation->update(['last_message_at' => now()]);

            return response('ok', 200);

        } catch (\Exception $e) {
            Log::error('VK webhook error: ' . $e->getMessage(), [
                'channel_id' => $channel->id,
                'data' => $data,
            ]);
            
            return response('ok', 200); // VK требует всегда возвращать 'ok'
        }
    }

    protected function getUserInfo(Channel $channel, int $userId)
    {
        $response = $this->httpClient->get(self::API_URL . 'users.get', [
            'query' => [
                'user_ids' => $userId,
                'access_token' => $channel->credentials['access_token'],
                'v' => self::API_VERSION,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        
        if (isset($data['response'][0])) {
            $user = $data['response'][0];
            return [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'full_name' => $user['first_name'] . ' ' . $user['last_name'],
            ];
        }

        return ['full_name' => 'User'];
    }

    protected function getOrCreateConversation(Channel $channel, int $userId, array $userInfo)
    {
        return Conversation::firstOrCreate(
            [
                'bot_id' => $channel->bot_id,
                'channel_id' => $channel->id,
                'external_id' => $userId,
                'status' => 'active',
            ],
            [
                'user_name' => $userInfo['full_name'],
                'user_data' => [
                    'vk_user_id' => $userId,
                    'vk_first_name' => $userInfo['first_name'] ?? null,
                    'vk_last_name' => $userInfo['last_name'] ?? null,
                ],
            ]
        );
    }

    public function sendMessage(Channel $channel, int $userId, string $message, array $keyboard = null)
    {
        $params = [
            'user_id' => $userId,
            'message' => $message,
            'random_id' => random_int(0, PHP_INT_MAX),
            'access_token' => $channel->credentials['access_token'],
            'v' => self::API_VERSION,
        ];

        if ($keyboard) {
            $params['keyboard'] = json_encode($keyboard);
        }

        $response = $this->httpClient->post(self::API_URL . 'messages.send', [
            'form_params' => $params,
        ]);

        return json_decode($response->getBody(), true);
    }

    public function setWebhook(Channel $channel)
    {
        // Для VK webhook настраивается через интерфейс группы
        // Здесь можно сохранить URL для отображения пользователю
        $webhookUrl = route('webhooks.vk', $channel);
        
        $channel->update([
            'settings' => array_merge($channel->settings ?? [], [
                'webhook_url' => $webhookUrl,
            ]),
        ]);
        
        return $webhookUrl;
    }
}