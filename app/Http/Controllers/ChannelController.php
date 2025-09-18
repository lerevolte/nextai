<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Channel;
use App\Models\Organization;
use App\Services\Messengers\TelegramService;
use App\Services\Messengers\VKService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ChannelController extends Controller
{
    public function index(Organization $organization, Bot $bot)
    {
        $channels = $bot->channels()->get();
        
        return view('channels.index', compact('organization', 'bot', 'channels'));
    }

    public function create(Organization $organization, Bot $bot)
    {
        $availableChannels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'vk' => 'ВКонтакте',
            'instagram' => 'Instagram',
            'web' => 'Веб-виджет',
        ];

        return view('channels.create', compact('organization', 'bot', 'availableChannels'));
    }

    public function store(Request $request, Organization $organization, Bot $bot)
    {
        $validated = $request->validate([
            'type' => 'required|in:telegram,whatsapp,vk,instagram,web',
            'name' => 'required|string|max:255',
        ]);

        // Валидация специфичных для канала полей
        $credentials = $this->validateChannelCredentials($request);

        $channel = $bot->channels()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'credentials' => $credentials ? Crypt::encrypt($credentials) : null,
            'settings' => $request->settings ?? [],
            'is_active' => true,
        ]);

        // Настройка webhook для канала
        $this->setupWebhook($channel);

        return redirect()
            ->route('bots.show', [$organization, $bot])
            ->with('success', 'Канал успешно добавлен');
    }

    public function edit(Organization $organization, Bot $bot, Channel $channel)
    {
        return view('channels.edit', compact('organization', 'bot', 'channel'));
    }

    public function update(Request $request, Organization $organization, Bot $bot, Channel $channel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Если обновляются credentials
        if ($request->has('credentials')) {
            $credentials = $this->validateChannelCredentials($request);
            $validated['credentials'] = Crypt::encrypt($credentials);
            
            // Переустанавливаем webhook при изменении credentials
            $this->setupWebhook($channel);
        }

        if ($request->has('settings')) {
            $validated['settings'] = $request->settings;
        }

        $channel->update($validated);

        return redirect()
            ->route('bots.show', [$organization, $bot])
            ->with('success', 'Канал обновлен');
    }

    public function destroy(Organization $organization, Bot $bot, Channel $channel)
    {
        // Удаляем webhook перед удалением канала
        $this->removeWebhook($channel);
        
        $channel->delete();

        return redirect()
            ->route('bots.show', [$organization, $bot])
            ->with('success', 'Канал удален');
    }

    protected function validateChannelCredentials(Request $request): ?array
    {
        switch ($request->type) {
            case 'telegram':
                return $request->validate([
                    'credentials.bot_token' => 'required|string',
                    'credentials.secret_token' => 'required|string|min:16',
                ]);

            case 'whatsapp':
                return $request->validate([
                    'credentials.account_sid' => 'required|string',
                    'credentials.auth_token' => 'required|string',
                    'credentials.phone_number' => 'required|string',
                ]);

            case 'vk':
                return $request->validate([
                    'credentials.access_token' => 'required|string',
                    'credentials.confirmation_token' => 'required|string',
                    'credentials.secret_key' => 'required|string',
                ]);

            default:
                return null;
        }
    }

    protected function setupWebhook(Channel $channel)
    {
        try {
            switch ($channel->type) {
                case 'telegram':
                    app(TelegramService::class)->setWebhook($channel);
                    break;
                    
                case 'vk':
                    $webhookUrl = app(VKService::class)->setWebhook($channel);
                    // Для VK показываем URL пользователю
                    session()->flash('info', "Webhook URL для VK: $webhookUrl");
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to setup webhook', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function removeWebhook(Channel $channel)
    {
        try {
            if ($channel->type === 'telegram') {
                app(TelegramService::class)->removeWebhook($channel);
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove webhook', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}