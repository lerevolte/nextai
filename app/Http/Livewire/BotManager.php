<?php

namespace App\Http\Livewire;

use App\Models\Bot;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;

class BotManager extends Component
{
    use WithPagination;

    public Organization $organization;
    public $search = '';
    public $showCreateModal = false;
    public $editingBot = null;

    // Поля для создания/редактирования
    public $name = '';
    public $description = '';
    public $ai_provider = 'openai';
    public $ai_model = 'gpt-4o-mini';
    public $system_prompt = '';
    public $welcome_message = '';
    public $temperature = 0.7;
    public $max_tokens = 500;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'ai_provider' => 'required|in:openai,gemini,deepseek',
        'ai_model' => 'required|string',
        'system_prompt' => 'required|string',
        'welcome_message' => 'nullable|string',
        'temperature' => 'required|numeric|min:0|max:2',
        'max_tokens' => 'required|integer|min:50|max:4000',
    ];

    public function mount(Organization $organization)
    {
        $this->organization = $organization;
    }

    public function render()
    {
        $bots = $this->organization->bots()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->withCount('conversations')
            ->paginate(10);

        return view('livewire.bot-manager', [
            'bots' => $bots,
            'aiProviders' => config('chatbot.ai_providers'),
        ]);
    }

    public function createBot()
    {
        if (!$this->organization->canCreateBot()) {
            session()->flash('error', 'Достигнут лимит ботов для вашей организации');
            return;
        }

        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function editBot(Bot $bot)
    {
        if ($bot->organization_id !== $this->organization->id) {
            return;
        }

        $this->editingBot = $bot;
        $this->name = $bot->name;
        $this->description = $bot->description;
        $this->ai_provider = $bot->ai_provider;
        $this->ai_model = $bot->ai_model;
        $this->system_prompt = $bot->system_prompt;
        $this->welcome_message = $bot->welcome_message;
        $this->temperature = $bot->temperature;
        $this->max_tokens = $bot->max_tokens;
        
        $this->showCreateModal = true;
    }

    public function saveBot()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'system_prompt' => $this->system_prompt,
            'welcome_message' => $this->welcome_message,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
        ];

        if ($this->editingBot) {
            $this->editingBot->update($data);
            session()->flash('success', 'Бот успешно обновлен');
        } else {
            $data['organization_id'] = $this->organization->id;
            $data['slug'] = \Str::slug($this->name) . '-' . \Str::random(6);
            
            $bot = Bot::create($data);
            
            // Создаем веб-канал по умолчанию
            $bot->channels()->create([
                'type' => 'web',
                'name' => 'Виджет для сайта',
                'settings' => [
                    'position' => 'bottom-right',
                    'color' => '#4F46E5',
                ],
            ]);
            
            session()->flash('success', 'Бот успешно создан');
        }

        $this->closeModal();
    }

    public function deleteBot(Bot $bot)
    {
        if ($bot->organization_id !== $this->organization->id) {
            return;
        }

        $bot->delete();
        session()->flash('success', 'Бот удален');
    }

    public function toggleBotStatus(Bot $bot)
    {
        if ($bot->organization_id !== $this->organization->id) {
            return;
        }

        $bot->update(['is_active' => !$bot->is_active]);
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->editingBot = null;
        $this->name = '';
        $this->description = '';
        $this->ai_provider = 'openai';
        $this->ai_model = 'gpt-4o-mini';
        $this->system_prompt = '';
        $this->welcome_message = '';
        $this->temperature = 0.7;
        $this->max_tokens = 500;
    }
}