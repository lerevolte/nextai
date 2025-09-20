@extends('layouts.app')

@section('title', 'Управление Salebot')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-semibold">Управление воронками Salebot</h2>
                <a href="{{ route('crm.show', [$organization, $integration]) }}" 
                   class="text-gray-600 hover:text-gray-900">← Назад</a>
            </div>

            <div class="p-6">
                <!-- Список воронок -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4">Доступные воронки</h3>
                    <div id="funnels-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="text-center py-8 text-gray-500">
                            Загрузка воронок...
                        </div>
                    </div>
                </div>

                <!-- Управление диалогами -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4">Быстрые действия</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Запуск воронки -->
                        <div class="border rounded-lg p-4">
                            <h4 class="font-medium mb-3">Запустить воронку для диалога</h4>
                            <div class="space-y-3">
                                <select id="conversation-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Выберите диалог</option>
                                    @foreach($conversations ?? [] as $conversation)
                                    <option value="{{ $conversation->id }}">
                                        #{{ $conversation->id }} - {{ $conversation->getUserDisplayName() }}
                                    </option>
                                    @endforeach
                                </select>
                                
                                <select id="funnel-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Выберите воронку</option>
                                </select>
                                
                                <button onclick="startFunnel()" 
                                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                    Запустить воронку
                                </button>
                            </div>
                        </div>

                        <!-- Передача оператору -->
                        <div class="border rounded-lg p-4">
                            <h4 class="font-medium mb-3">Передать оператору</h4>
                            <div class="space-y-3">
                                <select id="conversation-operator" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Выберите диалог</option>
                                    @foreach($conversations ?? [] as $conversation)
                                    <option value="{{ $conversation->id }}">
                                        #{{ $conversation->id }} - {{ $conversation->getUserDisplayName() }}
                                    </option>
                                    @endforeach
                                </select>
                                
                                <select id="operator-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Автоматический выбор</option>
                                </select>
                                
                                <button onclick="transferToOperator()" 
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Передать оператору
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Рассылка -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4">Массовая рассылка</h3>
                    <div class="border rounded-lg p-4">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Текст сообщения</label>
                                <textarea id="broadcast-message" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                          placeholder="Введите текст рассылки..."></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Фильтр по переменной</label>
                                    <input type="text" id="filter-variable" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                           placeholder="Например: city=Москва">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Задержка (сек)</label>
                                    <input type="number" id="broadcast-delay" value="0" min="0" max="86400"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                            </div>
                            
                            <button onclick="sendBroadcast()" 
                                    class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Отправить рассылку
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Статистика -->
                <div>
                    <h3 class="text-lg font-medium mb-4">Статистика воронок</h3>
                    <div id="funnel-stats" class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-500 text-center">Выберите воронку для просмотра статистики</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const integrationId = {{ $integration->id }};
const organizationSlug = '{{ $organization->slug }}';

// Загрузка воронок
async function loadFunnels() {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/funnels`);
        const data = await response.json();
        
        if (data.success) {
            alert('Диалог передан оператору!');
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error transferring to operator:', error);
        alert('Ошибка при передаче оператору');
    }
}

// Отправка рассылки
async function sendBroadcast() {
    const message = document.getElementById('broadcast-message').value;
    const filterVariable = document.getElementById('filter-variable').value;
    const delay = document.getElementById('broadcast-delay').value;
    
    if (!message) {
        alert('Введите текст сообщения');
        return;
    }
    
    if (!confirm(`Отправить рассылку? Сообщение: "${message}"`)) {
        return;
    }
    
    const filters = {};
    if (filterVariable && filterVariable.includes('=')) {
        const [key, value] = filterVariable.split('=');
        filters[key.trim()] = value.trim();
    }
    
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/broadcast`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message: message,
                filters: filters,
                delay: parseInt(delay) || 0
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Рассылка отправлена! Получателей: ${data.recipients_count || 0}`);
            document.getElementById('broadcast-message').value = '';
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error sending broadcast:', error);
        alert('Ошибка при отправке рассылки');
    }
}

// Загрузка статистики воронки
async function loadFunnelStats(funnelId) {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/funnel-stats?funnel_id=${funnelId}`);
        const data = await response.json();
        
        if (data.success) {
            displayFunnelStats(data.stats);
        }
    } catch (error) {
        console.error('Error loading funnel stats:', error);
    }
}

// Отображение статистики
function displayFunnelStats(stats) {
    const container = document.getElementById('funnel-stats');
    
    if (!stats || Object.keys(stats).length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">Статистика недоступна</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            ${Object.entries(stats).map(([key, value]) => `
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">${value}</p>
                    <p class="text-sm text-gray-600">${formatStatKey(key)}</p>
                </div>
            `).join('')}
        </div>
    `;
}

// Форматирование ключей статистики
function formatStatKey(key) {
    const labels = {
        'total_clients': 'Всего клиентов',
        'active_funnels': 'Активных воронок',
        'completed': 'Завершено',
        'conversion': 'Конверсия',
        'average_time': 'Среднее время'
    };
    return labels[key] || key;
}

// Просмотр блоков воронки
async function viewFunnelBlocks(funnelId) {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/funnel-blocks?funnel_id=${funnelId}`);
        const data = await response.json();
        
        if (data.success) {
            showBlocksModal(funnelId, data.blocks);
        }
    } catch (error) {
        console.error('Error loading funnel blocks:', error);
    }
}

// Модальное окно с блоками
function showBlocksModal(funnelId, blocks) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Блоки воронки</h3>
                <div class="max-h-96 overflow-y-auto">
                    ${blocks.length > 0 ? blocks.map(block => `
                        <div class="border-b py-2">
                            <p class="font-medium">${block.name}</p>
                            <p class="text-sm text-gray-600">Тип: ${block.type}</p>
                            ${block.position !== undefined ? `<p class="text-sm text-gray-500">Позиция: ${block.position}</p>` : ''}
                        </div>
                    `).join('') : '<p class="text-gray-500">Блоки не найдены</p>'}
                </div>
                <div class="mt-4">
                    <button onclick="this.closest('.fixed').remove()" 
                            class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600 focus:outline-none">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadFunnels();
    loadOperators();
});
</script>
@endpush
@endsection