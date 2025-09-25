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

        $channel = $bot->channels()
            ->where('type', 'web')
            ->where('is_active', true)
            ->firstOrFail();

        $sessionId = $request->input('session_id');
        $userInfo = $request->input('user_info', []);
        $conversation = null;

        // Пытаемся найти существующий АКТИВНЫЙ диалог
        if ($sessionId) {
            $conversation = Conversation::where('external_id', $sessionId)
                ->where('bot_id', $bot->id)
                ->where('channel_id', $channel->id)
                ->where('status', 'active')
                ->first();
        }

        // Если диалог не найден И переданы данные пользователя - создаем новый
        if (!$conversation && !empty($userInfo['name'])) {
            $sessionId = $sessionId ?: Str::uuid()->toString();
            
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel_id' => $channel->id,
                'external_id' => $sessionId,
                'status' => 'active',
                'user_name' => $userInfo['name'] ?? null,
                'user_email' => $userInfo['email'] ?? null,
                'user_phone' => $userInfo['phone'] ?? null,
                'user_data' => $userInfo,
                'metadata' => [
                    'widget_initialized' => true,
                    'initialization_date' => now()->toIso8601String(),
                ]
            ]);
            
            // Создаем приветственное сообщение локально (НЕ отправляем в Битрикс24)
            if ($bot->welcome_message) {
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $bot->welcome_message,
                    'metadata' => ['is_welcome' => true]
                ]);
                $conversation->increment('messages_count');
            }
        }

        // Если диалог найден или создан - возвращаем данные
        if ($conversation) {
            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'created_at' => $message->created_at->toIso8601String(),
                        'metadata' => [
                            'operator_name' => $message->metadata['operator_name'] ?? null,
                        ]
                    ];
                });

            return response()->json([
                'session_id' => $sessionId,
                'bot' => [
                    'name' => $bot->name,
                    'avatar_url' => $bot->avatar_url,
                    'welcome_message' => null, // НЕ отправляем дублирующее приветствие
                    'collect_contacts' => $bot->collect_contacts,
                ],
                'settings' => $channel->settings ?? [],
                'messages' => $messages,
                'conversation_id' => $conversation->id,
                'user_info' => [
                    'name' => $conversation->user_name,
                    'email' => $conversation->user_email,
                    'phone' => $conversation->user_phone,
                ],
            ]);
        }

        // Если нет диалога и нет данных пользователя - возвращаем настройки для показа формы
        return response()->json([
            'session_id' => $sessionId,
            'bot' => [
                'name' => $bot->name,
                'avatar_url' => $bot->avatar_url,
                'welcome_message' => $bot->welcome_message,
                'collect_contacts' => $bot->collect_contacts,
            ],
            'settings' => $channel->settings ?? [],
            'messages' => [],
            'conversation_id' => null,
            'user_info' => null,
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
        
        // Находим активный диалог
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('channel_id', $channel->id)
            ->where('external_id', $request->session_id)
            ->where('status', 'active')
            ->first();
            
        if (!$conversation) {
            Log::error('Conversation not found for session', [
                'session_id' => $request->session_id,
                'bot_id' => $bot->id,
                'channel_id' => $channel->id
            ]);
            
            // Пытаемся найти любой диалог с этим session_id (возможно закрытый)
            $anyConversation = Conversation::where('bot_id', $bot->id)
                ->where('channel_id', $channel->id)
                ->where('external_id', $request->session_id)
                ->first();
                
            if ($anyConversation) {
                Log::info('Found closed conversation, reopening', [
                    'conversation_id' => $anyConversation->id,
                    'status' => $anyConversation->status
                ]);
                
                // Переоткрываем диалог
                $anyConversation->update(['status' => 'active']);
                $conversation = $anyConversation;
            } else {
                return response()->json(['error' => 'Conversation not found. Please re-initialize.'], 404);
            }
        }

        // Обновляем пользовательские данные если переданы
        $userInfo = $request->input('user_info');
        if ($userInfo && (!$conversation->user_name || !$conversation->user_email)) {
            $updateData = [];
            
            if (!empty($userInfo['name']) && !$conversation->user_name) {
                $updateData['user_name'] = $userInfo['name'];
            }
            
            if (!empty($userInfo['email']) && !$conversation->user_email) {
                $updateData['user_email'] = $userInfo['email'];
            }
            
            if (!empty($userInfo['phone']) && !$conversation->user_phone) {
                $updateData['user_phone'] = $userInfo['phone'];
            }
            
            if (!empty($updateData)) {
                $updateData['user_data'] = array_merge($conversation->user_data ?? [], $userInfo);
                $conversation->update($updateData);
            }
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
            
            Log::info('Conversation ended via widget', [
                'conversation_id' => $conversation->id,
                'session_id' => $request->session_id
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function pollMessages(Request $request, Bot $bot)
    {
        $request->validate([
            'session_id' => 'required|string',
            'last_message_id' => 'nullable|integer',
        ]);
        Log::info('=== Poll request ===', [
            'bot_slug' => $bot->slug,
            'session_id' => $request->session_id,
            'last_message_id' => $request->last_message_id
        ]);
    
        $channel = $bot->channels()->where('type', 'web')->where('is_active', true)->first();
        if (!$channel) {
            return response()->json(['error' => 'Channel not available'], 404);
        }
        
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('channel_id', $channel->id)
            ->where('external_id', $request->session_id)
            ->first();
            
        if (!$conversation) {
            Log::warning('Conversation not found for polling', [
                'bot_id' => $bot->id,
                'session_id' => $request->session_id
            ]);
            return response()->json(['error' => 'Conversation not found'], 404);
        }
        
        $query = $conversation->messages();
        
        if ($request->last_message_id) {
            $query->where('id', '>', $request->last_message_id);
        }
        
        $messages = $query->orderBy('created_at', 'asc')->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                    'metadata' => [
                        'operator_name' => $message->metadata['operator_name'] ?? null,
                        'from_bitrix24' => $message->metadata['from_bitrix24'] ?? false,
                    ]
                ];
            });
        
        // Log::info('Poll response', [
        //     'conversation_id' => $conversation->id,
        //     'messages_count' => $messages->count(),
        //     'last_message_id' => $messages->last()?->get('id')
        // ]);
        
        return response()->json([
            'messages' => $messages,
            'conversation_status' => $conversation->status,
        ]);
    }
}