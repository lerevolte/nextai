<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\Messengers\TelegramService;
use App\Services\Messengers\WhatsAppService;
use App\Services\Messengers\VKService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
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

    public function telegram(Request $request, Channel $channel)
    {
        if ($channel->type !== 'telegram' || !$channel->is_active) {
            return response('Channel not found or inactive', 404);
        }

        // Проверка токена безопасности
        if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== $channel->credentials['secret_token']) {
            return response('Unauthorized', 401);
        }

        $this->telegramService->processWebhook($channel, $request->all());

        return response('ok', 200);
    }

    public function whatsapp(Request $request, Channel $channel)
    {
        if ($channel->type !== 'whatsapp' || !$channel->is_active) {
            return response('Channel not found or inactive', 404);
        }

        // Twilio проверка подписи
        if (!$this->validateTwilioSignature($request, $channel)) {
            return response('Unauthorized', 401);
        }

        return $this->whatsAppService->processWebhook($channel, $request->all());
    }

    public function vk(Request $request, Channel $channel)
    {
        if ($channel->type !== 'vk' || !$channel->is_active) {
            return response('Channel not found or inactive', 404);
        }

        // VK проверка секретного ключа
        $data = $request->all();
        if (($data['secret'] ?? '') !== $channel->credentials['secret_key']) {
            return response('Unauthorized', 401);
        }

        return $this->vkService->processWebhook($channel, $data);
    }

    protected function validateTwilioSignature(Request $request, Channel $channel): bool
    {
        $signature = $request->header('X-Twilio-Signature');
        if (!$signature) {
            return false;
        }

        $authToken = $channel->credentials['auth_token'];
        $url = $request->fullUrl();
        $data = $request->all();

        // Сортируем параметры по ключу
        ksort($data);

        // Формируем строку для подписи
        $string = $url;
        foreach ($data as $key => $value) {
            $string .= $key . $value;
        }

        // Вычисляем подпись
        $computedSignature = base64_encode(hash_hmac('sha1', $string, $authToken, true));

        return hash_equals($signature, $computedSignature);
    }
}