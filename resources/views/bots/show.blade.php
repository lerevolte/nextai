{{-- resources/views/bots/show.blade.php --}}
@extends('layouts.app')

@section('title', $bot->name)

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-sm rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        @if($bot->avatar_url)
                            <img src="{{ $bot->avatar_url }}" alt="{{ $bot->name }}" class="w-12 h-12 rounded-full">
                        @else
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                <span class="text-indigo-600 font-bold text-xl">{{ substr($bot->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <div class="ml-4">
                            <h1 class="text-2xl font-bold text-gray-900">{{ $bot->name }}</h1>
                            <p class="text-gray-500">{{ $bot->description }}</p>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <a href="{{ route('bots.edit', [$organization, $bot]) }}" 
                           class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            Настройки
                        </a>
                        <button onclick="copyToClipboard('{{ route('widget.show', $bot->slug) }}')" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Получить код виджета
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Всего диалогов</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['total_conversations'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Активные диалоги</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['active_conversations'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Сообщений сегодня</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['messages_today'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Статус</div>
                <div class="mt-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $bot->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $bot->is_active ? 'Активен' : 'Неактивен' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white shadow rounded-lg">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <a href="#channels" class="tab-link active border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Каналы
                    </a>
                    <a href="#conversations" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Диалоги
                    </a>
                    <a href="#knowledge" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        База знаний
                    </a>
                    <a href="#crm" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Интеграции
                    </a>
                    <a href="#settings" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Настройки
                    </a>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Channels Tab -->
                <div id="channels-content" class="tab-content">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium">Подключенные каналы</h3>
                        <a href="{{ route('channels.create', [$organization, $bot]) }}" 
                           class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                            Добавить канал
                        </a>
                    </div>
                    <div class="space-y-4">
                        @forelse($bot->channels as $channel)
                            <div class="border rounded-lg p-4 flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                        @if($channel->type == 'telegram')
                                            <svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                                            </svg>
                                        @elseif($channel->type == 'whatsapp')
                                            <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.123-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                            </svg>
                                        @else
                                            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $channel->name }}</p>
                                        <p class="text-sm text-gray-500">{{ ucfirst($channel->type) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $channel->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $channel->is_active ? 'Активен' : 'Неактивен' }}
                                    </span>
                                    <a href="{{ route('channels.edit', [$organization, $bot, $channel]) }}" 
                                       class="text-indigo-600 hover:text-indigo-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">Нет подключенных каналов</p>
                        @endforelse
                    </div>
                </div>

                <!-- Conversations Tab -->
                <div id="conversations-content" class="tab-content hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium">Последние диалоги</h3>
                    </div>
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Канал</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сообщений</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                    <th class="relative px-6 py-3"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($bot->conversations()->latest()->take(10)->get() as $conversation)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $conversation->user_name ?? 'Гость' }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $conversation->user_email }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $conversation->channel->type }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $conversation->messages_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $conversation->status == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ $conversation->status == 'active' ? 'Активен' : 'Закрыт' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $conversation->created_at->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('conversations.show', [$organization, $bot, $conversation]) }}" 
                                               class="text-indigo-600 hover:text-indigo-900">
                                                Просмотр
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Нет диалогов
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Knowledge Base Tab -->
                <div id="knowledge-content" class="tab-content hidden">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium">База знаний</h3>
                        <div>
                            <a href="{{ route('knowledge.create', [$organization, $bot]) }}" 
                               class="mr-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                                Добавить материал
                            </a>
                            <a href="{{ route('knowledge.import', [$organization, $bot]) }}" class="mr-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm"
                               style="background: #6366f1; color: white;">
                                📥 Импорт документов
                            </a>
                            <a href="{{ route('knowledge.sources.index', [$organization, $bot]) }}" 
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm"
                               style="background: #8b5cf6; color: white;">
                                🔄 Источники
                            </a>
                        </div>
                    </div>
                    @if($bot->knowledgeBase && $bot->knowledgeBase->items->count() > 0)
                        <div class="space-y-4">
                            @foreach($bot->knowledgeBase->items as $item)
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">{{ $item->title }}</h4>
                                            <p class="mt-1 text-sm text-gray-500">{{ Str::limit($item->content, 150) }}</p>
                                            <p class="mt-2 text-xs text-gray-400">
                                                Тип: {{ $item->type }} • Добавлено: {{ $item->created_at->format('d.m.Y') }}
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="{{ route('knowledge.edit', [$organization, $bot, $item]) }}" 
                                               class="text-indigo-600 hover:text-indigo-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                            <a href="{{ route('knowledge.versions', [$organization, $bot, $item->id]) }}" 
                                               class="text-indigo-600 hover:text-indigo-500">
                                                <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </a>
                                            <form method="POST" action="{{ route('knowledge.destroy', [$organization, $bot, $item]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Удалить этот материал?')" 
                                                        class="text-red-600 hover:text-red-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">База знаний пуста</p>
                    @endif
                </div>

                <div id="crm-content" class="tab-content hidden">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium">Интеграции</h3>
                        <a href="{{ route('crm.index', $organization) }}" 
                           class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                            Управление интеграциями
                        </a>
                    </div>
                    
                    @php
                        $crmIntegrations = $bot->crmIntegrations;
                    @endphp
                    
                    @if($crmIntegrations->count() > 0)
                        <div class="space-y-4">
                            @foreach($crmIntegrations as $crm)
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <span class="text-2xl mr-3">{{ $crm->getIcon() }}</span>
                                            <div>
                                                <h4 class="font-medium text-gray-900">{{ $crm->name }}</h4>
                                                <p class="text-sm text-gray-500">{{ $crm->getTypeName() }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-4">
                                            @if($crm->pivot->is_active)
                                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                    Активна
                                                </span>
                                            @else
                                                <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                                                    Неактивна
                                                </span>
                                            @endif
                                            <a href="{{ route('crm.show', [$organization, $crm]) }}" 
                                               class="text-indigo-600 hover:text-indigo-900 text-sm">
                                                Подробнее →
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 grid grid-cols-2 gap-4 text-sm text-gray-600">
                                        <div>
                                            <span class="font-medium">Лиды:</span> 
                                            {{ $crm->pivot->create_leads ? '✅ Создаются' : '❌ Не создаются' }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Сделки:</span> 
                                            {{ $crm->pivot->create_deals ? '✅ Создаются' : '❌ Не создаются' }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Контакты:</span> 
                                            {{ $crm->pivot->sync_contacts ? '✅ Синхронизируются' : '❌ Не синхронизируются' }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Диалоги:</span> 
                                            {{ $crm->pivot->sync_conversations ? '✅ Синхронизируются' : '❌ Не синхронизируются' }}
                                        </div>
                                    </div>
                                    
                                    @if($crm->last_sync_at)
                                    <div class="mt-3 pt-3 border-t text-xs text-gray-500">
                                        Последняя синхронизация: {{ $crm->last_sync_at->diffForHumans() }}
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Быстрые действия -->
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Быстрые действия</h4>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="syncAllConversations()" 
                                        class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    🔄 Синхронизировать все диалоги
                                </button>
                                <button onclick="exportToday()" 
                                        class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    📤 Экспорт диалогов за сегодня
                                </button>
                                <a href="{{ route('crm.index', $organization) }}" 
                                   class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    ⚙️ Настройки интеграций
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12 bg-gray-50 rounded-lg">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            <h3 class="text-sm font-medium text-gray-900 mb-2">CRM не подключена</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                Подключите CRM для автоматического создания лидов и синхронизации диалогов
                            </p>
                            <a href="{{ route('crm.index', $organization) }}" 
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                                <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Подключить CRM
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Settings Tab -->
                <div id="settings-content" class="tab-content hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium">Настройки бота</h3>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Код для встраивания виджета</h4>
                            <p class="mt-1 text-sm text-gray-500">Вставьте этот код перед закрывающим тегом &lt;/body&gt; на вашем сайте</p>
                            <div class="mt-2">
                                <pre class="bg-gray-100 rounded-lg p-4 text-xs overflow-x-auto"><code>&lt;script src="{{ url('/widget/script.js') }}"&gt;&lt;/script&gt;
&lt;script&gt;
  ChatBotWidget.init({
    botId: '{{ $bot->slug }}',
    position: 'bottom-right',
    primaryColor: '#4F46E5',
    baseUrl: '{{ url('/') }}'
  });
&lt;/script&gt;</code></pre>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Webhook URL</h4>
                            <p class="mt-1 text-sm text-gray-500">Используйте этот URL для интеграции с внешними сервисами</p>
                            <div class="mt-2">
                                <input type="text" readonly value="{{ url('/api/bots/' . $bot->slug . '/webhook') }}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm">
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-900">API Ключ</h4>
                            <p class="mt-1 text-sm text-gray-500">Используйте этот ключ для API запросов</p>
                            <div class="mt-2">
                                <div class="flex">
                                    <input type="password" id="api-key" readonly value="{{ $bot->api_key ?? 'Нажмите для генерации' }}" 
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg bg-gray-50 text-sm">
                                    <button onclick="toggleApiKey()" class="px-4 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg hover:bg-gray-200">
                                        Показать
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Tab switching
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-link').forEach(tab => {
                tab.classList.remove('border-indigo-500', 'text-indigo-600', 'active');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Add active class to clicked tab
            this.classList.remove('border-transparent', 'text-gray-500');
            this.classList.add('border-indigo-500', 'text-indigo-600', 'active');
            
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show corresponding content
            const targetId = this.getAttribute('href').substring(1) + '-content';
            document.getElementById(targetId).classList.remove('hidden');
        });
    });

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Ссылка скопирована в буфер обмена!');
        });
    }

    function toggleApiKey() {
        const input = document.getElementById('api-key');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    function syncAllConversations() {
        if (confirm('Синхронизировать все диалоги этого бота с CRM?')) {
            // Здесь можно добавить AJAX запрос для синхронизации
            alert('Синхронизация запущена в фоновом режиме');
        }
    }

    function exportToday() {
        if (confirm('Экспортировать все диалоги за сегодня в CRM?')) {
            // Здесь можно добавить AJAX запрос для экспорта
            alert('Экспорт запущен');
        }
    }
</script>
@endpush
@endsection