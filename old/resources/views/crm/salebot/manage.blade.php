@extends('layouts.app')

@section('title', 'Управление Salebot - ' . $integration->name)

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold">Управление воронками Salebot</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $integration->name }}</p>
                </div>
                <a href="{{ route('crm.show', [$organization, $integration]) }}" 
                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    ← Назад к интеграции
                </a>
            </div>

            <div class="p-6">
                <!-- Список воронок -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4">Доступные воронки</h3>
                    <div id="funnels-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="text-center py-8 text-gray-500 col-span-full">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500 mx-auto"></div>
                            <p class="mt-2">Загрузка воронок...</p>
                        </div>
                    </div>
                </div>

                <!-- Управление диалогами -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4">Быстрые действия</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Запуск воронки -->
                        <div class="border rounded-lg p-4">
                            <h4 class="font-medium mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Запустить воронку для диалога
                            </h4>
                            <div class="space-y-3">
                                <select id="conversation-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Выберите диалог</option>
                                    @foreach($conversations ?? [] as $conversation)
                                    <option value="{{ $conversation->id }}">
                                        #{{ $conversation->id }} - {{ $conversation->getUserDisplayName() }}
                                    </option>
                                    @endforeach
                                </select>
                                
                                <select id="funnel-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Выберите воронку</option>
                                </select>
                                
                                <select id="block-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" style="display: none;">
                                    <option value="">С начала воронки (опционально)</option>
                                </select>
                                
                                <button onclick="startFunnel()" 
                                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    Запустить воронку
                                </button>
                            </div>
                        </div>

                        <!-- Передача оператору -->
                        <div class="border rounded-lg p-4">
                            <h4 class="font-medium mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                Передать оператору
                            </h4>
                            <div class="space-y-3">
                                <select id="conversation-operator" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Выберите диалог</option>
                                    @foreach($conversations ?? [] as $conversation)
                                    <option value="{{ $conversation->id }}">
                                        #{{ $conversation->id }} - {{ $conversation->getUserDisplayName() }}
                                    </option>
                                    @endforeach
                                </select>
                                
                                <select id="operator-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Автоматический выбор</option>
                                </select>
                                
                                <button onclick="transferToOperator()" 
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    Передать оператору
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Рассылка -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                        </svg>
                        Массовая рассылка
                    </h3>
                    <div class="border rounded-lg p-4">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Текст сообщения</label>
                                <textarea id="broadcast-message" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                          placeholder="Введите текст рассылки..."></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Фильтр по переменной</label>
                                    <input type="text" id="filter-variable" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                           placeholder="Например: city=Москва">
                                    <p class="mt-1 text-xs text-gray-500">Формат: переменная=значение</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Задержка (сек)</label>
                                    <input type="number" id="broadcast-delay" value="0" min="0" max="86400"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="mt-1 text-xs text-gray-500">Максимум 24 часа (86400 сек)</p>
                                </div>
                            </div>
                            
                            <button onclick="sendBroadcast()" 
                                    class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                Отправить рассылку
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Статистика -->
                <div>
                    <h3 class="text-lg font-medium mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Статистика воронок
                    </h3>
                    <div id="funnel-stats" class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-500 text-center">Выберите воронку для просмотра статистики</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для блоков воронки -->
<div id="blocks-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Блоки воронки</h3>
                <button onclick="closeBlocksModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="blocks-content" class="max-h-96 overflow-y-auto">
                <!-- Блоки будут загружены сюда -->
            </div>
            <div class="mt-4">
                <button onclick="closeBlocksModal()" 
                        class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600 focus:outline-none">
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const integrationId = {{ $integration->id }};
const organizationSlug = '{{ $organization->slug }}';
let funnelsData = [];
let operatorsData = [];

// Загрузка воронок
async function loadFunnels() {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/funnels`);
        const data = await response.json();
        
        if (data.success) {
            funnelsData = data.funnels || [];
            displayFunnels(funnelsData);
            updateFunnelSelect(funnelsData);
        } else {
            showError('Ошибка загрузки воронок: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error loading funnels:', error);
        showError('Ошибка при загрузке воронок');
    }
}

// Отображение воронок
function displayFunnels(funnels) {
    const container = document.getElementById('funnels-list');
    
    if (!funnels || funnels.length === 0) {
        container.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">Воронки не найдены</div>';
        return;
    }
    
    container.innerHTML = funnels.map(funnel => `
        <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="flex justify-between items-start mb-2">
                <h4 class="font-medium text-gray-900">${funnel.name}</h4>
                ${funnel.is_active !== false ? '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">Активна</span>' : '<span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">Неактивна</span>'}
            </div>
            ${funnel.description ? `<p class="text-sm text-gray-600 mb-3">${funnel.description}</p>` : ''}
            <div class="flex justify-between items-center">
                <button onclick="viewFunnelBlocks('${funnel.id}')" class="text-sm text-indigo-600 hover:text-indigo-900">
                    Блоки →
                </button>
                <button onclick="loadFunnelStats('${funnel.id}')" class="text-sm text-gray-600 hover:text-gray-900">
                    Статистика
                </button>
            </div>
        </div>
    `).join('');
}

// Обновление селекта воронок
function updateFunnelSelect(funnels) {
    const select = document.getElementById('funnel-select');
    select.innerHTML = '<option value="">Выберите воронку</option>';
    
    funnels.forEach(funnel => {
        const option = document.createElement('option');
        option.value = funnel.id;
        option.textContent = funnel.name;
        select.appendChild(option);
    });
}

// Загрузка операторов
async function loadOperators() {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/users`);
        const data = await response.json();
        
        if (data.success) {
            operatorsData = data.users || [];
            updateOperatorSelect(operatorsData);
        }
    } catch (error) {
        console.error('Error loading operators:', error);
    }
}

// Обновление селекта операторов
function updateOperatorSelect(operators) {
    const select = document.getElementById('operator-select');
    select.innerHTML = '<option value="">Автоматический выбор</option>';
    
    operators.forEach(operator => {
        const option = document.createElement('option');
        option.value = operator.id;
        option.textContent = operator.name || `Оператор #${operator.id}`;
        select.appendChild(option);
    });
}

// Запуск воронки
async function startFunnel() {
    const conversationId = document.getElementById('conversation-select').value;
    const funnelId = document.getElementById('funnel-select').value;
    const blockId = document.getElementById('block-select').value;
    
    if (!conversationId) {
        alert('Выберите диалог');
        return;
    }
    
    if (!funnelId) {
        alert('Выберите воронку');
        return;
    }
    
    if (!confirm('Запустить воронку для выбранного диалога?')) {
        return;
    }
    
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/start-funnel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                funnel_id: funnelId,
                block_id: blockId || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Воронка успешно запущена!');
            document.getElementById('conversation-select').value = '';
            document.getElementById('funnel-select').value = '';
            document.getElementById('block-select').value = '';
        } else {
            showError('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error starting funnel:', error);
        showError('Ошибка при запуске воронки');
    }
}

// Передача оператору
async function transferToOperator() {
    const conversationId = document.getElementById('conversation-operator').value;
    const operatorId = document.getElementById('operator-select').value;
    
    if (!conversationId) {
        alert('Выберите диалог');
        return;
    }
    
    if (!confirm('Передать диалог оператору?')) {
        return;
    }
    
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/transfer-operator`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                operator_id: operatorId || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Диалог передан оператору!');
            document.getElementById('conversation-operator').value = '';
            document.getElementById('operator-select').value = '';
        } else {
            showError('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error transferring to operator:', error);
        showError('Ошибка при передаче оператору');
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
    
    if (!confirm(`Отправить рассылку?\n\nСообщение: "${message}"\n${filterVariable ? 'Фильтр: ' + filterVariable : 'Без фильтра'}`)) {
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
            showSuccess(`Рассылка отправлена! Получателей: ${data.recipients_count || 0}`);
            document.getElementById('broadcast-message').value = '';
            document.getElementById('filter-variable').value = '';
            document.getElementById('broadcast-delay').value = '0';
        } else {
            showError('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error sending broadcast:', error);
        showError('Ошибка при отправке рассылки');
    }
}

// Загрузка статистики воронки
async function loadFunnelStats(funnelId) {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/funnel-stats${funnelId ? '?funnel_id=' + funnelId : ''}`);
        const data = await response.json();
        
        if (data.success) {
            displayFunnelStats(data.stats, funnelId);
        } else {
            showError('Ошибка загрузки статистики');
        }
    } catch (error) {
        console.error('Error loading funnel stats:', error);
        showError('Ошибка при загрузке статистики');
    }
}

// Отображение статистики
function displayFunnelStats(stats, funnelId) {
    const container = document.getElementById('funnel-stats');
    const funnel = funnelsData.find(f => f.id === funnelId);
    
    if (!stats || Object.keys(stats).length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">Статистика недоступна</p>';
        return;
    }
    
    container.innerHTML = `
        ${funnel ? `<h4 class="font-medium text-gray-900 mb-4">Воронка: ${funnel.name}</h4>` : ''}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            ${Object.entries(stats).map(([key, value]) => `
                <div class="bg-white rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">${formatStatValue(value)}</p>
                    <p class="text-sm text-gray-600 mt-1">${formatStatKey(key)}</p>
                </div>
            `).join('')}
        </div>
    `;
}

// Форматирование значений статистики
function formatStatValue(value) {
    if (typeof value === 'number') {
        if (value % 1 !== 0) {
            return value.toFixed(2);
        }
        return value.toLocaleString('ru-RU');
    }
    return value;
}

// Форматирование ключей статистики
function formatStatKey(key) {
    const labels = {
        'total_clients': 'Всего клиентов',
        'active_funnels': 'Активных воронок',
        'completed': 'Завершено',
        'conversion': 'Конверсия',
        'average_time': 'Среднее время',
        'success_rate': 'Успешность',
        'total_messages': 'Сообщений',
        'active_clients': 'Активных клиентов'
    };
    return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Просмотр блоков воронки
async function viewFunnelBlocks(funnelId) {
    try {
        const response = await fetch(`/o/${organizationSlug}/crm/${integrationId}/salebot/funnel-blocks?funnel_id=${funnelId}`);
        const data = await response.json();
        
        if (data.success) {
            showBlocksModal(funnelId, data.blocks);
        } else {
            showError('Ошибка загрузки блоков');
        }
    } catch (error) {
        console.error('Error loading funnel blocks:', error);
        showError('Ошибка при загрузке блоков воронки');
    }
}

// Показ модального окна с блоками
function showBlocksModal(funnelId, blocks) {
    const modal = document.getElementById('blocks-modal');
    const content = document.getElementById('blocks-content');
    const funnel = funnelsData.find(f => f.id === funnelId);
    
    content.innerHTML = `
        ${funnel ? `<h4 class="font-medium text-gray-700 mb-3">${funnel.name}</h4>` : ''}
        ${blocks.length > 0 ? blocks.map((block, index) => `
            <div class="border-b py-3 ${index === blocks.length - 1 ? 'border-b-0' : ''}">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-medium text-gray-900">${block.name || 'Блок #' + (index + 1)}</p>
                        <p class="text-sm text-gray-600 mt-1">Тип: ${formatBlockType(block.type)}</p>
                        ${block.position !== undefined ? `<p class="text-xs text-gray-500 mt-1">Позиция: ${block.position}</p>` : ''}
                    </div>
                    ${block.id ? `
                        <button onclick="selectBlock('${block.id}', '${block.name}')" 
                                class="text-xs text-indigo-600 hover:text-indigo-900">
                            Выбрать
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('') : '<p class="text-gray-500">Блоки не найдены</p>'}
    `;
    
    modal.classList.remove('hidden');
}

// Выбор блока для запуска
function selectBlock(blockId, blockName) {
    const blockSelect = document.getElementById('block-select');
    blockSelect.style.display = 'block';
    
    // Добавляем выбранный блок в селект, если его там нет
    let option = blockSelect.querySelector(`option[value="${blockId}"]`);
    if (!option) {
        option = document.createElement('option');
        option.value = blockId;
        option.textContent = blockName || `Блок ${blockId}`;
        blockSelect.appendChild(option);
    }
    
    blockSelect.value = blockId;
    closeBlocksModal();
    showSuccess('Блок выбран для запуска воронки');
}

// Форматирование типа блока
function formatBlockType(type) {
    const types = {
        'message': 'Сообщение',
        'delay': 'Задержка',
        'condition': 'Условие',
        'action': 'Действие',
        'input': 'Ввод',
        'menu': 'Меню',
        'api': 'API запрос',
        'operator': 'Передача оператору'
    };
    return types[type] || type;
}

// Закрытие модального окна
function closeBlocksModal() {
    document.getElementById('blocks-modal').classList.add('hidden');
}

// Показ успешного сообщения
function showSuccess(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50 transition-opacity';
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Показ сообщения об ошибке
function showError(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50 transition-opacity';
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Обработка изменения селекта воронок
document.getElementById('funnel-select').addEventListener('change', function() {
    const funnelId = this.value;
    if (funnelId) {
        // При выборе воронки можно загрузить её блоки
        document.getElementById('block-select').style.display = 'none';
        document.getElementById('block-select').value = '';
    }
});

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadFunnels();
    loadOperators();
    
    // Загружаем общую статистику
    loadFunnelStats();
    
    // Автообновление статистики каждые 30 секунд
    setInterval(() => {
        const currentFunnelId = document.querySelector('#funnel-stats h4')?.dataset?.funnelId;
        if (currentFunnelId) {
            loadFunnelStats(currentFunnelId);
        }
    }, 30000);
});

// Обработка нажатия Escape для закрытия модального окна
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBlocksModal();
    }
});

// Закрытие модального окна при клике вне его
document.getElementById('blocks-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBlocksModal();
    }
});
</script>
@endpush

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.transition-opacity {
    transition: opacity 0.3s ease-in-out;
}

.transition-colors {
    transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
}

.transition-shadow {
    transition: box-shadow 0.2s ease-in-out;
}

/* Улучшенные стили для селектов и инпутов */
select:focus, input:focus, textarea:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Анимация появления модального окна */
#blocks-modal {
    animation: fadeIn 0.2s ease-in-out;
}

#blocks-modal > div {
    animation: slideIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Стили для уведомлений */
.fixed {
    animation: slideInRight 0.3s ease-in-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

/* Hover эффекты для кнопок */
button:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

button:active {
    transform: translateY(0);
}

/* Стили для карточек воронок */
#funnels-list > div:hover {
    transform: translateY(-2px);
}

/* Кастомный скроллбар для модального окна */
#blocks-content::-webkit-scrollbar {
    width: 6px;
}

#blocks-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

#blocks-content::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

#blocks-content::-webkit-scrollbar-thumb:hover {
    background: #666;
}
</style>
@endsection