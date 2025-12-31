@extends('layouts.app')

@section('title', 'Добавить CRM интеграцию')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Добавить CRM интеграцию</h2>
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

            <form method="POST" action="{{ route('crm.store', $organization) }}" class="p-6">
                @csrf

                <!-- Выбор типа CRM -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Тип CRM</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($availableTypes as $type => $info)
                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none">
                            <input type="radio" name="type" value="{{ $type }}" class="sr-only" 
                                   onchange="showCredentialsForm('{{ $type }}')" 
                                   {{ old('type') == $type ? 'checked' : '' }}>
                            <div class="flex flex-1">
                                <div class="flex flex-col">
                                    <span class="block text-sm font-medium text-gray-900">{{ $info['name'] }}</span>
                                    <span class="mt-1 flex items-center text-xs text-gray-500">
                                        {{ $info['description'] }}
                                    </span>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-indigo-600 hidden selected-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Название интеграции -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Название интеграции</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Например: Основная CRM">
                </div>

                <!-- Форма для Битрикс24 -->
                <div id="bitrix24-form" class="credentials-form hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки Битрикс24</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                        <input type="text" name="credentials[webhook_url]" 
                               value="{{ old('credentials.webhook_url') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               placeholder="https://your-domain.bitrix24.ru/rest/1/xxxxx/">
                        <p class="mt-1 text-xs text-gray-500">
                            Создайте входящий вебхук в Битрикс24 с правами на CRM
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID конфигурации открытой линии (опционально)</label>
                        <input type="text" name="settings[openline_config_id]" 
                               value="{{ old('settings.openline_config_id') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID ответственного по умолчанию</label>
                        <input type="number" name="settings[default_responsible_id]" 
                               value="{{ old('settings.default_responsible_id', 1) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>

                <!-- Форма для AmoCRM -->
                <div id="amocrm-form" class="credentials-form hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки AmoCRM</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Поддомен</label>
                        <div class="flex">
                            <input type="text" name="credentials[subdomain]" 
                                   value="{{ old('credentials.subdomain') }}"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg"
                                   placeholder="yourcompany">
                            <span class="px-3 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg">
                                .amocrm.ru
                            </span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                        <input type="text" name="credentials[client_id]" 
                               value="{{ old('credentials.client_id') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                        <input type="password" name="credentials[client_secret]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Token</label>
                        <input type="password" name="credentials[access_token]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <p class="mt-1 text-xs text-gray-500">
                            Получите токен через OAuth2 авторизацию в AmoCRM
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Refresh Token</label>
                        <input type="password" name="credentials[refresh_token]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Redirect URI</label>
                        <input type="text" name="credentials[redirect_uri]" 
                               value="{{ old('credentials.redirect_uri', url('/webhooks/crm/amocrm')) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               readonly>
                    </div>

                    <div class="border-t border-gray-200 my-6 pt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Настройки воронки и этапов</h4>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                ID воронки (Pipeline ID) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="settings[default_pipeline_id]" 
                                   value="{{ old('settings.default_pipeline_id') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="7654321"
                                   required>
                            <p class="mt-1 text-xs text-gray-500">
                                Обязательное поле. ID воронки, в которую будут попадать лиды
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                ID начального этапа (Status ID) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="settings[default_status_id]" 
                                   value="{{ old('settings.default_status_id') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="142"
                                   required>
                            <p class="mt-1 text-xs text-gray-500">
                                Обязательное поле. ID этапа, на котором создаются новые лиды
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                ID ответственного (User ID)
                            </label>
                            <input type="number" name="settings[default_responsible_id]" 
                                   value="{{ old('settings.default_responsible_id', 1) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="1">
                            <p class="mt-1 text-xs text-gray-500">
                                ID пользователя, который будет ответственным за новые лиды
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                ID этапа "Завершено"
                            </label>
                            <input type="number" name="settings[completed_status_id]" 
                                   value="{{ old('settings.completed_status_id') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="143">
                            <p class="mt-1 text-xs text-gray-500">
                                ID этапа для завершенных диалогов (опционально)
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                ID этапа "В работе"
                            </label>
                            <input type="number" name="settings[active_status_id]" 
                                   value="{{ old('settings.active_status_id') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                   placeholder="142">
                            <p class="mt-1 text-xs text-gray-500">
                                ID этапа для активных диалогов (опционально)
                            </p>
                        </div>
                    </div>

                    <div class="p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm font-medium text-blue-900 mb-2">💡 Как получить ID воронки и этапов?</p>
                        <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                            <li>Зайдите в AmoCRM → Настройки → Воронки и статусы</li>
                            <li>Откройте нужную воронку</li>
                            <li>ID воронки будет в URL: <code>/leads/pipelines/<b>7654321</b></code></li>
                            <li>ID этапов можно получить через API или консоль разработчика</li>
                        </ol>
                        <p class="mt-2 text-xs text-blue-700">
                            Или используйте команду: <code class="bg-white px-1 py-0.5 rounded">php artisan crm:get-pipelines {integration_id}</code>
                        </p>
                    </div>
                </div>

                <!-- Форма для Avito -->
                <div id="avito-form" class="credentials-form hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки Avito</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                        <input type="text" name="credentials[client_id]" 
                               value="{{ old('credentials.client_id') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               placeholder="ID приложения из личного кабинета Avito">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                        <input type="password" name="credentials[client_secret]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Приветственное сообщение (опционально)</label>
                        <textarea name="settings[welcome_message]" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ old('settings.welcome_message') }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="settings[auto_reply]" value="1" 
                                   {{ old('settings.auto_reply', true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Автоматические ответы</span>
                        </label>
                    </div>
                </div>

                <!-- Форма для Salebot -->
                <div id="salebot-form" class="credentials-form hidden">
                    <h3 class="text-lg font-medium mb-4">Настройки Salebot</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">API ключ</label>
                        <input type="password" name="credentials[api_key]" 
                               value="{{ old('credentials.api_key') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               placeholder="Ваш API ключ из Salebot">
                        <p class="mt-1 text-xs text-gray-500">
                            Получите API ключ в настройках вашего бота в Salebot
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID бота</label>
                        <input type="text" name="credentials[bot_id]" 
                               value="{{ old('credentials.bot_id') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               placeholder="Идентификатор вашего бота">
                        <!-- <p class="mt-1 text-xs text-gray-500">
                            ID бота можно найти в настройках Salebot
                        </p> -->
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID воронки по умолчанию (опционально)</label>
                        <input type="text" name="settings[default_funnel_id]" 
                               value="{{ old('settings.default_funnel_id') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               placeholder="ID воронки для автозапуска">
                        <p class="mt-1 text-xs text-gray-500">
                            Эта воронка будет автоматически запускаться для новых клиентов
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="settings[auto_start_funnel]" value="1" 
                                   {{ old('settings.auto_start_funnel', true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Автоматически запускать воронку</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="settings[sync_variables]" value="1" 
                                   {{ old('settings.sync_variables', true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Синхронизировать переменные клиента</span>
                        </label>
                    </div>

                    <div class="p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm font-medium text-blue-900 mb-2">Webhook URL для Salebot:</p>
                        <code class="text-xs bg-white px-2 py-1 rounded">{{ url('/webhooks/crm/salebot') }}</code>
                        <p class="mt-2 text-xs text-blue-700">
                            Добавьте этот URL в настройках Webhook вашего бота в Salebot для получения событий
                        </p>
                    </div>
                </div>

                <!-- Выбор ботов -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Подключить к ботам</label>
                    <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3">
                        @foreach($bots as $bot)
                        <label class="flex items-center">
                            <input type="checkbox" name="bot_ids[]" value="{{ $bot->id }}" 
                                   {{ in_array($bot->id, old('bot_ids', [])) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">{{ $bot->name }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Выберите боты, которые будут использовать эту CRM интеграцию
                    </p>
                </div>

                <!-- Кнопки действий -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('crm.index', $organization) }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Отмена
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Добавить интеграцию
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Показ/скрытие форм для разных типов CRM
function showCredentialsForm(type) {
    // Скрываем все формы
    document.querySelectorAll('.credentials-form').forEach(form => {
        form.classList.add('hidden');
    });
    
    // Показываем нужную форму
    const form = document.getElementById(type + '-form');
    if (form) {
        form.classList.remove('hidden');
    }
    
    // Обновляем визуальное выделение
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        const label = radio.closest('label');
        const icon = label.querySelector('.selected-icon');
        if (radio.value === type) {
            label.classList.add('border-indigo-600', 'ring-2', 'ring-indigo-600');
            icon.classList.remove('hidden');
        } else {
            label.classList.remove('border-indigo-600', 'ring-2', 'ring-indigo-600');
            icon.classList.add('hidden');
        }
    });
}
function loadPipelines(integrationId) {
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Загрузка...';
    
    fetch(`/o/{{ $organization->slug }}/crm/${integrationId}/load-pipelines`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="mt-3 space-y-2">';
                data.pipelines.forEach(pipeline => {
                    html += `<div class="p-3 bg-white rounded border">`;
                    html += `<p class="font-medium">Воронка: ${pipeline.name} (ID: ${pipeline.id})</p>`;
                    html += `<div class="mt-2 text-xs space-y-1">`;
                    pipeline.stages.forEach(stage => {
                        html += `<div>• ${stage.name} (ID: ${stage.id})</div>`;
                    });
                    html += `</div></div>`;
                });
                html += '</div>';
                
                button.insertAdjacentHTML('afterend', html);
                button.remove();
            } else {
                alert('Ошибка: ' + data.error);
                button.disabled = false;
                button.textContent = 'Загрузить из AmoCRM';
            }
        })
        .catch(error => {
            alert('Ошибка загрузки: ' + error);
            button.disabled = false;
            button.textContent = 'Загрузить из AmoCRM';
        });
}

// При загрузке страницы показываем форму если тип уже выбран
document.addEventListener('DOMContentLoaded', function() {
    const selectedType = document.querySelector('input[name="type"]:checked');
    if (selectedType) {
        showCredentialsForm(selectedType.value);
    }
});
</script>
@endpush
@endsection