<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WidgetController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function show(Bot $bot)
    {
        if (!$bot->is_active) {
            abort(404);
        }

        // Получаем веб-канал
        $channel = $bot->channels()
            ->where('type', 'web')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            abort(404);
        }

        return view('widget.chat', compact('bot', 'channel'));
    }

    public function initialize(Request $request, Bot $bot)
    {
        $request->validate([
            'session_id' => 'nullable|string|max:255',
        ]);

        // Получаем или создаем сессию
        $sessionId = $request->session_id ?? Str::uuid()->toString();

        // Получаем веб-канал
        $channel = $bot->channels()
            ->where('type', 'web')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return response()->json(['error' => 'Bot not available'], 404);
        }

        // Проверяем существующую беседу
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('channel_id', $channel->id)
            ->where('external_id', $sessionId)
            ->where('status', 'active')
            ->first();

        $messages = [];
        if ($conversation) {
            // Загружаем историю сообщений
            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'created_at' => $message->created_at->toIso8601String(),
                    ];
                });
        }

        return response()->json([
            'session_id' => $sessionId,
            'bot' => [
                'name' => $bot->name,
                'avatar_url' => $bot->avatar_url,
                'welcome_message' => $bot->welcome_message,
            ],
            'settings' => $channel->settings ?? [],
            'messages' => $messages,
            'conversation_id' => $conversation?->id,
        ]);
    }

    public function sendMessage(Request $request, Bot $bot)
    {
        info('sendMessage');
        $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'required|string',
            'user_info' => 'nullable|array',
        ]);

        if (!$bot->is_active) {
            info('Bot is not active');
            return response()->json(['error' => 'Bot is not active'], 400);
        }

        // Получаем веб-канал
        $channel = $bot->channels()
            ->where('type', 'web')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            info('Channel not available');
            return response()->json(['error' => 'Channel not available'], 404);
        }

        DB::beginTransaction();
        info('beginTransaction');
        try {
            // Получаем или создаем диалог
            $conversation = Conversation::firstOrCreate(
                [
                    'bot_id' => $bot->id,
                    'channel_id' => $channel->id,
                    'external_id' => $request->session_id,
                    'status' => 'active',
                ],
                [
                    'user_name' => $request->input('user_info.name'),
                    'user_email' => $request->input('user_info.email'),
                    'user_phone' => $request->input('user_info.phone'),
                    'user_data' => $request->input('user_info'),
                ]
            );
            info('Conversation');
            // Сохраняем сообщение пользователя
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $request->message,
            ]);

            // Обновляем счетчики
            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);

            // Проверяем рабочие часы
            if (!$bot->isWorkingHours()) {
                $responseContent = $bot->offline_message ?? 
                    'К сожалению, мы сейчас не в сети. Пожалуйста, напишите нам позже или оставьте свои контактные данные.';
            } else {
                // Генерируем ответ с помощью AI
                $startTime = microtime(true);
                $responseContent = $this->aiService->generateResponse(
                    $bot,
                    $conversation,
                    $request->message
                );
                $responseTime = microtime(true) - $startTime;
            }

            // Сохраняем ответ бота
            $botMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'response_time' => $responseTime ?? null,
            ]);

            // Обновляем счетчики
            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => [
                    'id' => $botMessage->id,
                    'role' => 'assistant',
                    'content' => $responseContent,
                    'created_at' => $botMessage->created_at->toIso8601String(),
                ],
                'conversation_id' => $conversation->id,
            ]);

        } catch (\Exception $e) {
            info('catchTransaction');
            DB::rollback();
            info($e->getMessage());
            \Log::error('Widget message error: ' . $e->getMessage(), [
                'bot_id' => $bot->id,
                'session_id' => $request->session_id,
            ]);

            return response()->json([
                'error' => 'Произошла ошибка при обработке сообщения. Пожалуйста, попробуйте позже.'
            ], 500);
        }
    }

    public function endConversation(Request $request, Bot $bot)
    {
        $request->validate([
            'session_id' => 'required|string',
            'conversation_id' => 'required|integer',
        ]);

        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('bot_id', $bot->id)
            ->where('external_id', $request->session_id)
            ->first();

        if ($conversation) {
            $conversation->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}