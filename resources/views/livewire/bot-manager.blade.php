{{-- resources/views/livewire/bot-manager.blade.php --}}
<div>
    <!-- Header -->
    <div class="mb-6 flex justify-between items-center">
        <div class="flex-1 max-w-lg">
            <input wire:model.debounce.300ms="search" 
                   type="text" 
                   placeholder="Поиск ботов..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <button wire:click="createBot" 
                class="ml-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            Создать бота
        </button>
    </div>

    <!-- Bots Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($bots as $bot)
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center">
                            @if($bot->avatar_url)
                                <img src="{{ $bot->avatar_url }}" alt="{{ $bot->name }}" class="w-12 h-12 rounded-full">
                            @else
                                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-indigo-600 font-bold text-lg">{{ substr($bot->name, 0, 1) }}</span>
                                </div>
                            @endif
                            <div class="ml-3">
                                <h3 class="text-lg font-semibold text-gray-900">{{ $bot->name }}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $bot->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $bot->is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </div>
                        </div>
                        <button wire:click="toggleBotStatus({{ $bot->id }})" 
                                class="p-1 text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                    </div>

                    <p class="text-gray-600 text-sm mb-4">{{ $bot->description ?? 'Без описания' }}</p>

                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Провайдер:</span>
                            <span class="font-medium">{{ ucfirst($bot->ai_provider) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Модель:</span>
                            <span class="font-medium">{{ $bot->ai_model }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Диалогов:</span>
                            <span class="font-medium">{{ $bot->conversations_count }}</span>
                        </div>
                    </div>

                    <div class="flex space-x-2">
                        <a href="{{ route('bots.show', [$organization, $bot]) }}" 
                           class="flex-1 text-center px-3 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
                            Открыть
                        </a>
                        <button wire:click="editBot({{ $bot->id }})" 
                                class="flex-1 text-center px-3 py-2 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition">
                            Изменить
                        </button>
                        <button wire:click="deleteBot({{ $bot->id }})" 
                                onclick="return confirm('Вы уверены?')"
                                class="px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $bots->links() }}
    </div>

    <!-- Create/Edit Modal -->
    @if($showCreateModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold">
                    {{ $editingBot ? 'Редактировать бота' : 'Создать нового бота' }}
                </h3>
            </div>

            <form wire:submit.prevent="saveBot" class="p-6 space-y-4">
                <!-- Основная информация -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название</label>
                    <input wire:model="name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                    <textarea wire:model="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- AI настройки -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">AI Провайдер</label>
                        <select wire:model="ai_provider" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($aiProviders as $key => $provider)
                                <option value="{{ $key }}">{{ ucfirst($key) }}</option>
                            @endforeach
                        </select>
                        @error('ai_provider') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Модель</label>
                        <select wire:model="ai_model" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            @if(isset($aiProviders[$ai_provider]['models']))
                                @foreach($aiProviders[$ai_provider]['models'] as $model => $label)
                                    <option value="{{ $model }}">{{ $label }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('ai_model') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Системный промпт</label>
                    <textarea wire:model="system_prompt" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Опишите роль и поведение бота..."></textarea>
                    @error('system_prompt') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Приветственное сообщение</label>
                    <textarea wire:model="welcome_message" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Сообщение, которое увидит пользователь при начале диалога"></textarea>
                    @error('welcome_message') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Temperature (0-2)</label>
                        <input wire:model="temperature" type="number" step="0.1" min="0" max="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        @error('temperature') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        <small class="text-gray-500">Креативность ответов (0.7 рекомендуется)</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Tokens</label>
                        <input wire:model="max_tokens" type="number" min="50" max="4000" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        @error('max_tokens') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        <small class="text-gray-500">Максимальная длина ответа</small>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Отмена
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        {{ $editingBot ? 'Сохранить' : 'Создать' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>