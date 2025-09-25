@extends('layouts.app')

@section('title', 'Редактировать интеграцию')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Редактировать интеграцию: {{ $integration->name }}</h2>
            </div>

            @if ($errors->any())
                <div class="p-4 m-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('crm.update', [$organization, $integration]) }}" class="p-6">
                @csrf
                @method('PUT')

                <!-- Название интеграции -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Название интеграции</label>
                    <input type="text" name="name" value="{{ old('name', $integration->name) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Например: Основная CRM">
                </div>

                <!-- Тип CRM (только для чтения) -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Тип CRM</label>
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">{!! $integration->getIcon() !!}</span>
                        <input type="text" value="{{ $integration->getTypeName() }}" disabled
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                    </div>
                </div>

                <!-- Статус -->
                <div class="mb-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" 
                               {{ old('is_active', $integration->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm font-medium text-gray-700">Интеграция активна</span>
                    </label>
                </div>

                <!-- Настройки в зависимости от типа -->
                @if($integration->type == 'bitrix24')
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium mb-4">Настройки Битрикс24</h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                            <input type="text" name="credentials[webhook_url]" 
                                   value="{{ old('credentials.webhook_url', $integration->credentials['webhook_url'] ?? '') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="https://your-domain.bitrix24.ru/rest/1/xxxxx/">
                            <p class="mt-1 text-xs text-gray-500">
                                Оставьте пустым, чтобы не менять текущий URL
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">ID ответственного по умолчанию</label>
                            <input type="number" name="settings[default_responsible_id]" 
                                   value="{{ old('settings.default_responsible_id', $integration->settings['default_responsible_id'] ?? 1) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>

                @elseif($integration->type == 'amocrm')
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium mb-4">Настройки AmoCRM</h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Поддомен</label>
                            <input type="text" name="credentials[subdomain]" 
                                   value="{{ old('credentials.subdomain', $integration->credentials['subdomain'] ?? '') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="yourcompany">
                        </div>
                        
                        <p class="text-sm text-gray-500">
                            Для обновления токенов обратитесь к администратору
                        </p>
                    </div>

                @elseif($integration->type == 'salebot')
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium mb-4">Настройки Salebot</h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">ID бота</label>
                            <input type="text" name="credentials[bot_id]" 
                                   value="{{ old('credentials.bot_id', $integration->credentials['bot_id'] ?? '') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">ID воронки по умолчанию</label>
                            <input type="text" name="settings[default_funnel_id]" 
                                   value="{{ old('settings.default_funnel_id', $integration->settings['default_funnel_id'] ?? '') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="settings[auto_start_funnel]" value="1" 
                                       {{ old('settings.auto_start_funnel', $integration->settings['auto_start_funnel'] ?? false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Автоматически запускать воронку</span>
                            </label>
                        </div>
                    </div>
                @endif

                <!-- Подключенные боты -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-4">Подключенные боты</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3">
                        @foreach($bots as $bot)
                        <label class="flex items-center">
                            <input type="checkbox" name="bot_ids[]" value="{{ $bot->id }}" 
                                   {{ in_array($bot->id, $connectedBotIds) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">{{ $bot->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="flex justify-between">
                    <div>
                        <button type="button" onclick="if(confirm('Удалить эту интеграцию?')) document.getElementById('delete-form').submit();" 
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            Удалить интеграцию
                        </button>
                    </div>
                    <div class="flex space-x-3">
                        <a href="{{ route('crm.show', [$organization, $integration]) }}" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Отмена
                        </a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Сохранить изменения
                        </button>
                    </div>
                </div>
            </form>

            <!-- Скрытая форма для удаления -->
            <form id="delete-form" action="{{ route('crm.destroy', [$organization, $integration]) }}" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
@endsection