@extends('layouts.app')

@section('title', $integration->name)

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-sm rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <span class="text-3xl mr-3">{!! $integration->getIcon() !!}</span>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">{{ $integration->name }}</h1>
                            <p class="text-gray-500">{{ $integration->getTypeName() }}</p>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="testConnection()" 
                                class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                            🔌 Тест подключения
                        </button>
                        <a href="{{ route('crm.edit', [$organization, $integration]) }}" 
                           class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            ⚙️ Настройки
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Статус</div>
                <div class="mt-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $integration->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $integration->is_active ? '✅ Активна' : '❌ Неактивна' }}
                    </span>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Всего синхронизаций</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['total_syncs'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Успешных</div>
                <div class="mt-1 text-3xl font-semibold text-green-600">{{ $stats['successful_syncs'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Ошибок</div>
                <div class="mt-1 text-3xl font-semibold text-red-600">{{ $stats['failed_syncs'] ?? 0 }}</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white shadow rounded-lg">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <a href="#bots" class="tab-link active border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        🤖 Подключенные боты
                    </a>
                    <a href="#entities" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📊 Синхронизированные объекты
                    </a>
                    <a href="#logs" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📝 Журнал синхронизации
                    </a>
                    <a href="#settings" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        ⚙️ Настройки
                    </a>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Bots Tab -->
                <div id="bots-content" class="tab-content">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium">Подключенные боты</h3>
                        <button onclick="showAddBotModal()" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                            + Добавить бота
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        @forelse($integration->bots as $bot)
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ $bot->name }}</h4>
                                        <div class="mt-2 text-sm text-gray-500 space-y-1">
                                            <div>
                                                ✅ Синхронизация контактов: 
                                                <span class="font-medium">{{ $bot->pivot->sync_contacts ? 'Да' : 'Нет' }}</span>
                                            </div>
                                            <div>
                                                💬 Синхронизация диалогов: 
                                                <span class="font-medium">{{ $bot->pivot->sync_conversations ? 'Да' : 'Нет' }}</span>
                                            </div>
                                            <div>
                                                📋 Создание лидов: 
                                                <span class="font-medium">{{ $bot->pivot->create_leads ? 'Да' : 'Нет' }}</span>
                                            </div>
                                            <div>
                                                💰 Создание сделок: 
                                                <span class="font-medium">{{ $bot->pivot->create_deals ? 'Да' : 'Нет' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="{{ route('crm.bot-settings', [$organization, $integration, $bot]) }}" 
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Настроить
                                        </a>
                                        <button onclick="removeBotConnection({{ $bot->id }})" 
                                                class="text-red-600 hover:text-red-900">
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">Нет подключенных ботов</p>
                        @endforelse
                    </div>
                </div>

                <!-- Entities Tab -->
                <div id="entities-content" class="tab-content hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium">Синхронизированные объекты</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Локальный ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID в CRM</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Последняя синхронизация</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($integration->syncEntities as $entity)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100">
                                                {{ ucfirst($entity->entity_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            #{{ $entity->local_id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($entity->getRemoteUrl())
                                                <a href="{{ $entity->getRemoteUrl() }}" target="_blank" 
                                                   class="text-indigo-600 hover:text-indigo-900">
                                                    {{ $entity->remote_id }} ↗
                                                </a>
                                            @else
                                                {{ $entity->remote_id }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $entity->last_synced_at ? $entity->last_synced_at->diffForHumans() : 'Никогда' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button onclick="syncEntity('{{ $entity->entity_type }}', '{{ $entity->local_id }}')"
                                                    class="text-indigo-600 hover:text-indigo-900">
                                                Синхронизировать
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            Нет синхронизированных объектов
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div id="logs-content" class="tab-content hidden">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium">Журнал синхронизации</h3>
                        <button onclick="refreshLogs()" class="text-indigo-600 hover:text-indigo-900">
                            🔄 Обновить
                        </button>
                    </div>
                    
                    <div class="space-y-2">
                        @forelse($integration->syncLogs as $log)
                            <div class="flex items-start space-x-3 p-3 {{ $log->status == 'error' ? 'bg-red-50' : 'bg-gray-50' }} rounded-lg">
                                <div class="flex-shrink-0">
                                    @if($log->status == 'success')
                                        <span class="text-green-500">✓</span>
                                    @else
                                        <span class="text-red-500">✗</span>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm">
                                        <span class="font-medium">{{ $log->getActionName() }}</span>
                                        <span class="text-gray-500">{{ $log->getEntityTypeName() }}</span>
                                        <span class="text-gray-400">{{ $log->getDirectionIcon() }}</span>
                                    </div>
                                    @if($log->error_message)
                                        <div class="text-xs text-red-600 mt-1">{{ $log->error_message }}</div>
                                    @endif
                                    <div class="text-xs text-gray-400 mt-1">
                                        {{ $log->created_at->format('d.m.Y H:i:s') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">Журнал пуст</p>
                        @endforelse
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="settings-content" class="tab-content hidden">
                    <div class="space-y-6">
                        @if($integration->type == 'bitrix24')
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Настройки Битрикс24</h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm text-gray-700">Webhook URL</label>
                                        <div class="mt-1">
                                            <input type="text" readonly 
                                                   value="{{ substr($integration->credentials['webhook_url'] ?? '', 0, 50) }}..." 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                        </div>
                                    </div>
                                    
                                    @if($integration->settings['openline_config_id'] ?? null)
                                    <div>
                                        <label class="block text-sm text-gray-700">ID открытой линии</label>
                                        <div class="mt-1">
                                            <input type="text" readonly 
                                                   value="{{ $integration->settings['openline_config_id'] }}" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                        </div>
                                    </div>
                                    @endif
                                    
                                    <div class="p-4 bg-blue-50 rounded-lg">
                                        <h5 class="font-medium text-blue-900 mb-2">Webhook для Битрикс24</h5>
                                        <p class="text-sm text-blue-700 mb-2">
                                            Добавьте этот URL в обработчики событий Битрикс24:
                                        </p>
                                        <code class="block p-2 bg-white rounded text-xs">
                                            {{ url('/webhooks/crm/bitrix24') }}
                                        </code>
                                    </div>
                                </div>
                            </div>
                        @elseif($integration->type == 'salebot')
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Настройки Salebot</h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm text-gray-700">API ключ</label>
                                        <div class="mt-1">
                                            <input type="password" readonly 
                                                   value="••••••••••••" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm text-gray-700">ID бота</label>
                                        <div class="mt-1">
                                            <input type="text" readonly 
                                                   value="{{ $integration->credentials['bot_id'] ?? '' }}" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                        </div>
                                    </div>
                                    
                                    <div class="p-4 bg-purple-50 rounded-lg">
                                        <h5 class="font-medium text-purple-900 mb-2">Webhook для Salebot</h5>
                                        <p class="text-sm text-purple-700 mb-2">
                                            Используйте этот URL в настройках webhook вашего бота:
                                        </p>
                                        <code class="block p-2 bg-white rounded text-xs">
                                            {{ url('/webhooks/crm/salebot') }}
                                        </code>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="pt-6 border-t">
                            <!-- <h4 class="text-sm font-medium text-gray-900 mb-4">Опасная зона</h4> -->
                            <button onclick="deleteIntegration()" 
                                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-700">
                                Удалить интеграцию
                            </button>
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
        
        document.querySelectorAll('.tab-link').forEach(tab => {
            tab.classList.remove('border-indigo-500', 'text-indigo-600');
            tab.classList.add('border-transparent', 'text-gray-500');
        });
        
        this.classList.remove('border-transparent', 'text-gray-500');
        this.classList.add('border-indigo-500', 'text-indigo-600');
        
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        const targetId = this.getAttribute('href').substring(1) + '-content';
        document.getElementById(targetId).classList.remove('hidden');
    });
});

function testConnection() {
    fetch('{{ route("crm.test-connection", [$organization, $integration]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.success ? '✅ Подключение успешно!' : '❌ Ошибка подключения: ' + data.message);
    });
}

function syncEntity(entityType, localId) {
    fetch('{{ route("crm.sync-conversation", [$organization, $integration]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            conversation_id: localId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Синхронизация выполнена');
            location.reload();
        } else {
            alert('❌ Ошибка: ' + data.error);
        }
    });
}

function deleteIntegration() {
    if (confirm('Вы уверены? Это действие нельзя отменить!')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("crm.destroy", [$organization, $integration]) }}';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function refreshLogs() {
    location.reload();
}
</script>
@endpush
@endsection