@extends('layouts.app')

@section('title', 'Настройки бота для ' . $integration->name)

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Настройки бота "{{ $bot->name }}" для {{ $integration->getTypeName() }}</h2>
            </div>

            <form method="POST" action="{{ route('crm.bot-settings.update', [$organization, $integration, $bot]) }}" class="p-6">
                @csrf
                @method('PUT')

                <!-- Основные настройки -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4">Основные настройки</h3>
                    
                    <div class="space-y-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" value="1" 
                                   {{ old('is_active', $settings->pivot->is_active ?? true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 font-medium">Интеграция активна</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="sync_contacts" value="1" 
                                   {{ old('sync_contacts', $settings->pivot->sync_contacts ?? true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2">Синхронизировать контакты</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="sync_conversations" value="1" 
                                   {{ old('sync_conversations', $settings->pivot->sync_conversations ?? true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2">Синхронизировать диалоги</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="create_leads" value="1" 
                                   {{ old('create_leads', $settings->pivot->create_leads ?? true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2">Создавать лиды</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="create_deals" value="1" 
                                   {{ old('create_deals', $settings->pivot->create_deals ?? false) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2">Создавать сделки</span>
                        </label>
                    </div>
                </div>

                <!-- Источник лидов -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Источник лидов</label>
                    <input type="text" name="lead_source" 
                           value="{{ old('lead_source', $settings->pivot->lead_source ?? 'Чат-бот') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                           placeholder="Например: Сайт, Чат-бот, WhatsApp">
                </div>

                @if(isset($users) && count($users) > 0)
                <!-- Ответственный -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ответственный по умолчанию</label>
                    <select name="responsible_user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Автоматически</option>
                        @foreach($users as $user)
                        <option value="{{ $user['id'] }}" 
                                {{ old('responsible_user_id', $settings->pivot->responsible_user_id ?? '') == $user['id'] ? 'selected' : '' }}>
                            {{ $user['name'] ?? $user['title'] ?? 'Пользователь #' . $user['id'] }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif

                @if(isset($pipelines) && count($pipelines) > 0)
                <!-- Воронка -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Воронка продаж</label>
                    <select name="pipeline_settings[pipeline_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">По умолчанию</option>
                        @foreach($pipelines as $pipeline)
                        <option value="{{ $pipeline['id'] }}" 
                                {{ old('pipeline_settings.pipeline_id', $settings->pivot->pipeline_settings['pipeline_id'] ?? '') == $pipeline['id'] ? 'selected' : '' }}>
                            {{ $pipeline['name'] ?? 'Воронка #' . $pipeline['id'] }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Этап воронки -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Начальный этап</label>
                    <select name="pipeline_settings[status_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Первый этап воронки</option>
                        @if(isset($pipelines) && count($pipelines) > 0)
                            @foreach($pipelines as $pipeline)
                                @if(isset($pipeline['statuses']))
                                    <optgroup label="{{ $pipeline['name'] }}">
                                        @foreach($pipeline['statuses'] as $status)
                                        <option value="{{ $status['id'] }}"
                                                {{ old('pipeline_settings.status_id', $settings->pivot->pipeline_settings['status_id'] ?? '') == $status['id'] ? 'selected' : '' }}>
                                            {{ $status['name'] }}
                                        </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        @endif
                    </select>
                </div>
                @endif

                <!-- Специфичные настройки для типов CRM -->
                @if($integration->type == 'bitrix24')
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-medium text-blue-900 mb-3">Настройки Битрикс24</h4>
                    
                    <label class="flex items-center mb-3">
                        <input type="checkbox" name="pipeline_settings[use_openlines]" value="1" 
                               {{ old('pipeline_settings.use_openlines', $settings->pivot->pipeline_settings['use_openlines'] ?? false) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm">Использовать открытые линии</span>
                    </label>

                    <label class="flex items-center">
                        <input type="checkbox" name="pipeline_settings[auto_close_deal]" value="1" 
                               {{ old('pipeline_settings.auto_close_deal', $settings->pivot->pipeline_settings['auto_close_deal'] ?? false) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm">Автоматически закрывать сделки при закрытии диалога</span>
                    </label>
                </div>
                @endif

                @if($integration->type == 'salebot')
                <div class="mb-6 p-4 bg-purple-50 rounded-lg">
                    <h4 class="font-medium text-purple-900 mb-3">Настройки Salebot</h4>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID воронки для запуска</label>
                        <input type="text" name="pipeline_settings[funnel_id]" 
                               value="{{ old('pipeline_settings.funnel_id', $settings->pivot->pipeline_settings['funnel_id'] ?? '') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                               placeholder="Оставьте пустым для использования настроек по умолчанию">
                    </div>

                    <label class="flex items-center">
                        <input type="checkbox" name="pipeline_settings[sync_variables]" value="1" 
                               {{ old('pipeline_settings.sync_variables', $settings->pivot->pipeline_settings['sync_variables'] ?? true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm">Синхронизировать переменные клиента</span>
                    </label>
                </div>
                @endif

                <!-- Кнопки действий -->
                <div class="flex justify-end space-x-3 pt-6 border-t">
                    <a href="{{ route('crm.show', [$organization, $integration]) }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Отмена
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Сохранить настройки
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection