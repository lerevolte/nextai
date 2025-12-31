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

        // Пытаемся найти существующий АКТИВНЫЙ диалог
        if ($sessionId) {
            $conversation = Conversation::where('external_id', $sessionId)
                ->where('bot_id', $bot->id)
                ->where('channel_id', $channel->id)
                ->where('status', 'active') // Только активные диалоги
                ->first();
                
            // Log::info('Looking for existing conversation', [
            //     'session_id' => $sessionId,
            //     'bot_id' => $bot->id,
            //     'channel_id' => $channel->id,
            //     'found' => $conversation ? true : false
            // ]);
        }

        // Если диалог не найден, создаем новый
        if (!$conversation) {
            // Если session_id не был предоставлен, генерируем новый
            if (!$sessionId) {
                $sessionId = Str::uuid()->toString();
            }
            
            // Log::info('Creating new conversation', [
            //     'session_id' => $sessionId,
            //     'bot_id' => $bot->id,
            //     'user_info' => $userInfo
            // ]);
            
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel_id' => $channel->id,
                'external_id' => $sessionId,
                'status' => 'active',
                'user_name' => $userInfo['name'] ?? null,
                'user_email' => $userInfo['email'] ?? null,
                'user_phone' => $userInfo['phone'] ?? null,
                'user_data' => $userInfo,
            ]);
            
            // Создаем приветственное сообщение если есть
            if ($bot->welcome_message) {
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $bot->welcome_message,
                ]);
                $conversation->increment('messages_count');
            }
            
        } else {
            // Диалог найден. Обновляем контактные данные если нужно
            $needsUpdate = false;
            $updateData = [];
            
            if (!empty($userInfo['name']) && empty($conversation->user_name)) {
                $updateData['user_name'] = $userInfo['name'];
                $needsUpdate = true;
            }
            
            if (!empty($userInfo['email']) && empty($conversation->user_email)) {
                $updateData['user_email'] = $userInfo['email'];
                $needsUpdate = true;
            }
            
            if (!empty($userInfo['phone']) && empty($conversation->user_phone)) {
                $updateData['user_phone'] = $userInfo['phone'];
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $updateData['user_data'] = array_merge($conversation->user_data ?? [], $userInfo);
                $conversation->update($updateData);
                
                // Log::info('Updated conversation user data', [
                //     'conversation_id' => $conversation->id,
                //     'updates' => $updateData
                // ]);
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

        $channel = $bot->channels()->where('type', 'web')->where('is_active', true)->firstOrFail();

        // Находим диалог по session_id
        $conversation = Conversation::where('external_id', $request->session_id)
            ->where('bot_id', $bot->id)
            ->where('channel_id', $channel->id)
            ->first();
            
        if (!$conversation) {
            Log::error('Conversation not found for session during sendMessage. This should not happen.', [
                'session_id' => $request->session_id,
            ]);
            return response()->json(['error' => 'Conversation not found. Please re-initialize.'], 404);
        }

        // Проверяем, был ли этот диалог уже синхронизирован с Битрикс24
        $wasAlreadySynced = isset($conversation->metadata['bitrix24_chat_id']);

        // Обновляем данные пользователя, если они переданы
        $userInfo = $request->input('user_info');
        if ($userInfo) {
            $updateData = [];
            if (!empty($userInfo['name']) && !$conversation->user_name) $updateData['user_name'] = $userInfo['name'];
            if (!empty($userInfo['email']) && !$conversation->user_email) $updateData['user_email'] = $userInfo['email'];
            if (!empty($userInfo['phone']) && !$conversation->user_phone) $updateData['user_phone'] = $userInfo['phone'];
            if (!empty($updateData)) {
                $updateData['user_data'] = array_merge($conversation->user_data ?? [], $userInfo);
                $conversation->update($updateData);
            }
        }

        DB::beginTransaction();
        
        try {
            // 1. Сохраняем сообщение пользователя в нашу базу
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $request->message,
            ]);

            // 2. Запускаем создание чата в Битрикс24 ТОЛЬКО ОДИН РАЗ
            if (!$wasAlreadySynced) {
                Log::info("First user message in unsynced conversation {$conversation->id}. Triggering CRM sync.", [
                    'message_id' => $userMessage->id
                ]);
                (new \App\Observers\ConversationObserver())->created($conversation);
            }

            // 3. Обновляем счетчики и время
            $conversation->increment('messages_count');
            $conversation->update(['last_message_at' => now()]);

            // 4. ПРОВЕРЯЕМ СТАТУС ДИАЛОГА перед генерацией ответа
            if ($conversation->status === 'waiting_operator') {
                Log::info('Skipping AI response - operator is handling conversation', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $userMessage->id,
                    'status' => $conversation->status
                ]);
                
                DB::commit();
                
                // Возвращаем системное сообщение вместо ответа бота
                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversation->id,
                    'status' => 'operator_handling',
                    // Не возвращаем message, чтобы виджет не добавлял ничего в чат
                ]);
            }

            // 5. Генерируем ответ от AI только если диалог активен
            $responseContent = '';
            $responseTime = 0;

            if (!$bot->isWorkingHours()) {
                $responseContent = $bot->settings['offline_message'] ?? 'К сожалению, мы сейчас не в сети. Пожалуйста, напишите нам позже.';
            } else {
                $startTime = microtime(true);
                $responseContent = $this->aiService->generateResponse($bot, $conversation, $request->message);
                $responseTime = microtime(true) - $startTime;
            }

            // 6. Сохраняем ответ бота в нашу базу
            $botMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'response_time' => $responseTime,
            ]);

            $conversation->increment('messages_count');

            DB::commit();

            // 7. Отправляем ответ бота в виджет
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
            
            // Log::info('Conversation ended via widget', [
            //     'conversation_id' => $conversation->id,
            //     'session_id' => $request->session_id
            // ]);
        }

        return response()->json(['success' => true]);
    }

    public function pollMessages(Request $request, Bot $bot)
    {
        $request->validate([
            'session_id' => 'required|string',
            'last_message_id' => 'nullable|integer',
        ]);

        $lastMessageId = $request->input('last_message_id', 0);

        // Log::info('[WidgetController] Poll request received', [
        //     'bot_id' => $bot->id,
        //     'polling_after_message_id' => $lastMessageId
        // ]);
        
        $conversation = Conversation::where('external_id', $request->session_id)
            ->where('bot_id', $bot->id)
            ->first();
            
        if (!$conversation) {
            return response()->json(['messages' => []]);
        }
        
        $messages = $conversation->messages()
            ->where('id', '>', $lastMessageId)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                    // --- ФИНАЛЬНОЕ ИСПРАВЛЕНИЕ: Гарантированно добавляем metadata ---
                    'metadata' => $message->metadata ?? [] 
                ];
            });
        
        // Log::info('[WidgetController] Poll response sending', [
        //     'conversation_id' => $conversation->id,
        //     'messages_found' => $messages->count()
        // ]);
        
        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function confirmDelivery(Request $request, Bot $bot)
    {
        $request->validate([
            'session_id' => 'required|string',
            'b24_message_ids' => 'required|array',
        ]);

        $b24MessageIds = array_filter($request->input('b24_message_ids'));


        Log::channel('bitrix24')->info('[ConfirmDelivery] Received request from widget', [
            'session_id' => $request->session_id,
            'b24_ids_to_confirm' => $b24MessageIds,
        ]);

        if (empty($b24MessageIds)) {
            return response()->json(['success' => true, 'message' => 'No IDs to confirm.']);
        }

        try {
            $conversation = Conversation::where('bot_id', $bot->id)
                ->where('external_id', $request->session_id)
                ->firstOrFail();

            $integration = $bot->crmIntegrations()
                ->where('type', 'bitrix24')
                ->wherePivot('is_active', true)
                ->first();

            if (!$integration) {
                return response()->json(['success' => false, 'error' => 'Active Bitrix24 integration not found.'], 404);
            }
            
            $provider = new \App\Services\CRM\Providers\Bitrix24ConnectorProvider($integration);
            $provider->confirmMessageDeliveryFromWidget($conversation, $b24MessageIds);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Failed to confirm message delivery to Bitrix24', [
                'session_id' => $request->session_id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Обновление информации о пользователе без отправки сообщения
     */
    public function updateUserInfo(Request $request, Bot $bot)
    {
        $request->validate([
            'session_id' => 'required|string',
            'user_info' => 'required|array',
            'user_info.name' => 'required|string|max:255',
            'user_info.email' => 'nullable|email|max:255',
            'user_info.phone' => 'nullable|string|max:50',
        ]);

        $conversation = Conversation::where('external_id', $request->session_id)
            ->where('bot_id', $bot->id)
            ->first();
            
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $userInfo = $request->input('user_info');
        
        // Обновляем только пустые поля
        $updateData = [];
        
        if (!empty($userInfo['name']) && empty($conversation->user_name)) {
            $updateData['user_name'] = $userInfo['name'];
        }
        
        if (!empty($userInfo['email']) && empty($conversation->user_email)) {
            $updateData['user_email'] = $userInfo['email'];
        }
        
        if (!empty($userInfo['phone']) && empty($conversation->user_phone)) {
            $updateData['user_phone'] = $userInfo['phone'];
        }
        
        if (!empty($updateData)) {
            $updateData['user_data'] = array_merge($conversation->user_data ?? [], $userInfo);
            $conversation->update($updateData);
            
            Log::info('User info updated via widget', [
                'conversation_id' => $conversation->id,
                'updates' => array_keys($updateData)
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User info updated successfully'
        ]);
    }
}