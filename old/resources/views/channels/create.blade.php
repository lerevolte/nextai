{{-- resources/views/channels/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Добавить канал')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Добавить новый канал для {{ $bot->name }}</h2>
            </div>

            <form method="POST" action="{{ route('channels.store', [$organization, $bot]) }}" class="p-6">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Тип канала</label>
                    <select name="type" id="channel-type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Выберите тип канала</option>
                        @foreach($availableChannels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Название канала</label>
                    <input type="text" name="name" value="{{ old('name') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Например: Основной Telegram бот">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Telegram настройки -->
                <div id="telegram-settings" class="channel-settings hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки Telegram</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bot Token</label>
                        <input type="text" name="credentials[bot_token]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Получите от @BotFather">
                        <p class="mt-1 text-xs text-gray-500">
                            Создайте бота через @BotFather в Telegram и скопируйте токен
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Secret Token</label>
                        <input type="text" name="credentials[secret_token]" 
                               value="{{ Str::random(32) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">
                            Секретный токен для защиты webhook
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Быстрые ответы (опционально)</label>
                        <div id="telegram-quick-replies">
                            <div class="quick-reply-input mb-2">
                                <input type="text" name="settings[quick_replies][]" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                       placeholder="Например: Узнать цены">
                            </div>
                        </div>
                        <button type="button" onclick="addQuickReply('telegram')" 
                                class="text-sm text-indigo-600 hover:text-indigo-500">
                            + Добавить быстрый ответ
                        </button>
                    </div>
                </div>

                <!-- WhatsApp настройки -->
                <div id="whatsapp-settings" class="channel-settings hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки WhatsApp (Twilio)</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account SID</label>
                        <input type="text" name="credentials[account_sid]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Auth Token</label>
                        <input type="password" name="credentials[auth_token]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp номер</label>
                        <input type="text" name="credentials[phone_number]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="+14155238886">
                        <p class="mt-1 text-xs text-gray-500">
                            Номер должен быть подключен к Twilio WhatsApp
                        </p>
                    </div>
                </div>

                <!-- VK настройки -->
                <div id="vk-settings" class="channel-settings hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки ВКонтакте</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Token</label>
                        <input type="text" name="credentials[access_token]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">
                            Ключ доступа сообщества с правами messages
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmation Token</label>
                        <input type="text" name="credentials[confirmation_token]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">
                            Строка подтверждения из настроек Callback API
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                        <input type="text" name="credentials[secret_key]" 
                               value="{{ Str::random(16) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">
                            Секретный ключ для проверки запросов
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('bots.show', [$organization, $bot]) }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Отмена
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Добавить канал
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('channel-type').addEventListener('change', function() {
        // Скрываем все настройки
        document.querySelectorAll('.channel-settings').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Показываем настройки для выбранного типа
        const settings = document.getElementById(this.value + '-settings');
        if (settings) {
            settings.classList.remove('hidden');
        }
    });

    function addQuickReply(type) {
        const container = document.getElementById(type + '-quick-replies');
        const div = document.createElement('div');
        div.className = 'quick-reply-input mb-2';
        div.innerHTML = `
            <div class="flex">
                <input type="text" name="settings[quick_replies][]" 
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg"
                       placeholder="Текст быстрого ответа">
                <button type="button" onclick="this.parentElement.parentElement.remove()" 
                        class="px-3 py-2 bg-red-500 text-white rounded-r-lg hover:bg-red-600">
                    Удалить
                </button>
            </div>
        `;
        container.appendChild(div);
    }
</script>
@endpush
@endsection