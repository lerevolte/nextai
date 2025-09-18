<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Services\AIService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index(Organization $organization, Bot $bot)
    {
        $conversations = $bot->conversations()
            ->with('channel')
            ->withCount('messages')
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        return view('conversations.index', compact('organization', 'bot', 'conversations'));
    }

    public function show(Organization $organization, Bot $bot, Conversation $conversation)
    {
        // Проверяем что диалог принадлежит боту
        if ($conversation->bot_id !== $bot->id) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        return view('conversations.show', compact('organization', 'bot', 'conversation', 'messages'));
    }

    public function takeover(Request $request, Organization $organization, Bot $bot, Conversation $conversation)
    {
        // Проверяем что диалог принадлежит боту
        if ($conversation->bot_id !== $bot->id) {
            abort(403);
        }

        $conversation->update([
            'status' => 'waiting_operator',
            'metadata' => array_merge($conversation->metadata ?? [], [
                'operator_id' => auth()->id(),
                'takeover_at' => now(),
            ]),
        ]);

        // Добавляем системное сообщение
        $conversation->messages()->create([
            'role' => 'system',
            'content' => 'Оператор ' . auth()->user()->name . ' подключился к диалогу',
        ]);

        return redirect()
            ->route('conversations.show', [$organization, $bot, $conversation])
            ->with('success', 'Вы взяли управление диалогом');
    }

    public function close(Request $request, Organization $organization, Bot $bot, Conversation $conversation)
    {
        // Проверяем что диалог принадлежит боту
        if ($conversation->bot_id !== $bot->id) {
            abort(403);
        }

        $conversation->close();

        // Добавляем системное сообщение
        $conversation->messages()->create([
            'role' => 'system',
            'content' => 'Диалог закрыт оператором ' . auth()->user()->name,
        ]);

        return redirect()
            ->route('conversations.index', [$organization, $bot])
            ->with('success', 'Диалог закрыт');
    }

    public function sendMessage(Request $request, Organization $organization, Bot $bot, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'as_operator' => 'boolean',
        ]);

        // Проверяем что диалог принадлежит боту
        if ($conversation->bot_id !== $bot->id) {
            abort(403);
        }

        if ($request->as_operator) {
            // Отправка от имени оператора
            $conversation->messages()->create([
                'role' => 'operator',
                'content' => $request->message,
                'metadata' => [
                    'operator_id' => auth()->id(),
                    'operator_name' => auth()->user()->name,
                ],
            ]);
        } else {
            // Отправка от имени пользователя (для тестирования)
            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $request->message,
            ]);

            // Генерируем ответ бота
            try {
                $response = $this->aiService->generateResponse($bot, $conversation, $request->message);
                
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $response,
                ]);
            } catch (\Exception $e) {
                $conversation->messages()->create([
                    'role' => 'system',
                    'content' => 'Ошибка при генерации ответа: ' . $e->getMessage(),
                ]);
            }
        }

        $conversation->increment('messages_count');
        $conversation->update(['last_message_at' => now()]);

        return redirect()
            ->route('conversations.show', [$organization, $bot, $conversation]);
    }

    // API методы
    public function apiIndex(Request $request, Bot $bot)
    {
        $conversations = $bot->conversations()
            ->with('channel')
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                      ->orWhere('user_email', 'like', "%{$search}%")
                      ->orWhere('user_phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_message_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($conversations);
    }

    public function messages(Request $request, Bot $bot, Conversation $conversation)
    {
        // Проверяем что диалог принадлежит боту
        if ($conversation->bot_id !== $bot->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $messages = $conversation->messages()
            ->when($request->after_id, function ($query, $afterId) {
                $query->where('id', '>', $afterId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function export(Organization $organization, Bot $bot, Conversation $conversation)
    {
        // Проверяем что диалог принадлежит боту
        if ($conversation->bot_id !== $bot->id) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        $content = "Диалог #" . $conversation->id . "\n";
        $content .= "Пользователь: " . $conversation->getUserDisplayName() . "\n";
        $content .= "Канал: " . $conversation->channel->getTypeName() . "\n";
        $content .= "Начат: " . $conversation->created_at->format('d.m.Y H:i') . "\n";
        $content .= str_repeat('-', 50) . "\n\n";

        foreach ($messages as $message) {
            $content .= "[" . $message->created_at->format('H:i:s') . "] ";
            $content .= $message->getRoleName() . ": ";
            $content .= $message->content . "\n\n";
        }

        $filename = 'dialog-' . $conversation->id . '-' . date('Y-m-d') . '.txt';

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}