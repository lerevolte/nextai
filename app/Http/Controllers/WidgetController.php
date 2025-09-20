<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'user_info' => 'nullable|array',
        ]);

        if (!$bot->is_active) {
            return response()->json(['error' => 'Bot is not active'], 400);
        }

        // Получаем веб-канал
        $channel = $bot->channels()
            ->where('type', 'web')
            ->where('is_active', true)
            ->firstOrFail();

        $sessionId = $request->input('session_id');
        $conversation = null;
        $userInfo = $request->input('user_info', []);

        // Пытаемся найти существующий диалог, только если session_id был предоставлен
        if ($sessionId) {
            $conversation = Conversation::where('external_id', $sessionId)
                ->where('bot_id', $bot->id)
                ->where('channel_id', $channel->id)
                ->where('status', 'active')
                ->first();
        }

        // Если диалог не найден, это означает, что нам нужен полный сброс.
        // Создаем новый диалог с новым ID и сбрасываем информацию о пользователе.
        if (!$conversation) {
            $sessionId = Str::uuid()->toString(); // Генерируем новый ID
            
            // При сбросе мы принудительно создаем диалог без данных пользователя,
            // чтобы форма сбора контактов появилась снова.
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel_id' => $channel->id,
                'external_id' => $sessionId,
                'status' => 'active',
                'user_name' => null,
                'user_email' => null,
                'user_phone' => null,
                'user_data' => [],
            ]);
        } else {
            // Диалог найден. Проверяем, нужно ли обновить контактные данные.
            // Это происходит, когда пользователь сначала общается анонимно, а потом заполняет форму.
            if (!empty($userInfo['name']) && empty($conversation->user_name)) {
                $conversation->update([
                    'user_name' => $userInfo['name'],
                    'user_email' => $userInfo['email'] ?? $conversation->user_email,
                    'user_phone' => $userInfo['phone'] ?? $conversation->user_phone,
                    'user_data' => array_merge($conversation->user_data ?? [], $userInfo),
                ]);
            }
        }

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

        return response()->json([
            'session_id' => $sessionId,
            'bot' => [
                'name' => $bot->name,
                'avatar_url' => $bot->avatar_url,
                'welcome_message' => $bot->welcome_message,
                'collect_contacts' => $bot->collect_contacts,
            ],
            'settings' => $channel->settings ?? [],
            'messages' => $messages,
            'conversation_id' => $conversation->id,
            'user_info' => $conversation->user_name ? [
                'name' => $conversation->user_name,
                'email' => $conversation->user_email,
                'phone' => $conversation->user_phone,
            ] : null,
        ]);
    }

    public function sendMessage(Request $request, Bot $bot)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'required|string',
            'user_info' => 'nullable|array',
        ]);

        if (!$bot->is_active) {
            Log::warning('Attempt to send message to inactive bot', ['bot_id' => $bot->id]);
            return response()->json(['error' => 'Bot is not active'], 400);
        }

        $channel = $bot->channels()->where('type', 'web')->where('is_active', true)->first();

        if (!$channel) {
            Log::warning('Web channel not available for bot', ['bot_id' => $bot->id]);
            return response()->json(['error' => 'Channel not available'], 404);
        }
        
        // Находим активный диалог. Он должен был быть создан на этапе initialize().
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('channel_id', $channel->id)
            ->where('external_id', $request->session_id)
            ->where('status', 'active')
            ->first();
            
        if (!$conversation) {
            Log::error('Conversation not found for session', ['session_id' => $request->session_id]);
            return response()->json(['error' => 'Conversation not found. Please re-initialize.'], 404);
        }

        DB::beginTransaction();
        
        try {
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $request->message,
            ]);

            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);

            $responseContent = '';
            $responseTime = 0;

            if (!$bot->isWorkingHours()) {
                $responseContent = $bot->settings['offline_message'] ?? 'К сожалению, мы сейчас не в сети. Пожалуйста, напишите нам позже или оставьте свои контактные данные.';
            } else {
                $startTime = microtime(true);
                $responseContent = $this->aiService->generateResponse($bot, $conversation, $request->message);
                $responseTime = microtime(true) - $startTime;
            }

            $botMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'response_time' => $responseTime,
            ]);

            $conversation->increment('messages_count');

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
            DB::rollback();
            Log::error('Widget message error', [
                'bot_id' => $bot->id,
                'session_id' => $request->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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