<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BotController extends Controller
{
    public function index(Organization $organization)
    {
        $bots = $organization->bots()->with('channels')->paginate(10);
        
        return view('bots.index', compact('organization', 'bots'));
    }

    public function create(Organization $organization)
    {
        if (!$organization->canCreateBot()) {
            return redirect()
                ->route('bots.index', $organization)
                ->with('error', 'Достигнут лимит ботов для вашей организации');
        }

        return view('bots.create', compact('organization'));
    }

    public function store(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_provider' => 'required|in:openai,gemini,deepseek',
            'ai_model' => 'required|string',
            'system_prompt' => 'required|string',
            'welcome_message' => 'nullable|string',
            'temperature' => 'required|numeric|min:0|max:2',
            'max_tokens' => 'required|integer|min:50|max:4000',
        ]);

        $validated['organization_id'] = $organization->id;
        $validated['slug'] = \Str::slug($validated['name']) . '-' . \Str::random(6);

        $bot = Bot::create($validated);

        // Автоматически создаем веб-канал для каждого бота
        $bot->channels()->create([
            'type' => 'web',
            'name' => 'Веб-виджет',
            'is_active' => true,
            'settings' => [
                'position' => 'bottom-right',
                'color' => '#4F46E5',
                'show_avatar' => true,
            ],
        ]);

        return redirect()
            ->route('bots.show', [$organization, $bot])
            ->with('success', 'Бот успешно создан. Веб-виджет готов к использованию!');
    }

    public function show(Organization $organization, Bot $bot)
    {
        $bot->load(['channels', 'conversations' => function ($query) {
            $query->latest()->take(10);
        }]);

        $stats = [
            'total_conversations' => $bot->conversations()->count(),
            'active_conversations' => $bot->conversations()->where('status', 'active')->count(),
            'messages_today' => $bot->conversations()
                ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
                ->whereDate('messages.created_at', today())
                ->count(),
        ];

        return view('bots.show', compact('organization', 'bot', 'stats'));
    }

    public function edit(Organization $organization, Bot $bot)
    {
        // Проверяем доступ
        if ($bot->organization_id !== $organization->id) {
            abort(403);
        }
        
        return view('bots.edit', compact('organization', 'bot'));
    }

    public function update(Request $request, Organization $organization, Bot $bot)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_provider' => 'required|in:openai,gemini,deepseek',
            'ai_model' => 'required|string',
            'system_prompt' => 'required|string',
            'welcome_message' => 'nullable|string',
            'temperature' => 'required|numeric|min:0|max:2',
            'max_tokens' => 'required|integer|min:50|max:4000',
            'is_active' => 'boolean',
            'knowledge_base_enabled' => 'boolean', // Добавили
            'collect_contacts' => 'boolean',
            'human_handoff_enabled' => 'boolean',
        ]);

        // Обработка checkbox - если не отмечен, значит false
        $validated['knowledge_base_enabled'] = $request->has('knowledge_base_enabled');
        $validated['is_active'] = $request->has('is_active');
        $validated['collect_contacts'] = $request->has('collect_contacts');
        $validated['human_handoff_enabled'] = $request->has('human_handoff_enabled');

        $bot->update($validated);

        // Если включили базу знаний, создаем её если нет
        if ($validated['knowledge_base_enabled'] && !$bot->knowledgeBase) {
            $bot->knowledgeBase()->create([
                'name' => 'База знаний ' . $bot->name,
                'description' => 'Основная база знаний бота',
                'is_active' => true,
            ]);
        }

        return redirect()
            ->route('bots.show', [$organization, $bot])
            ->with('success', 'Настройки бота обновлены');
    }

    public function destroy(Organization $organization, Bot $bot)
    {
        $bot->delete();

        return redirect()
            ->route('bots.index', $organization)
            ->with('success', 'Бот удален');
    }
}