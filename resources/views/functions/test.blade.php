@extends('layouts.app')

@section('title', 'Тест функции: ' . $function->display_name)

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-4">Тестирование функции: {{ $function->display_name }}</h1>
        
        <!-- Информация о функции -->
        <div class="bg-gray-50 rounded p-4 mb-6">
            <p><strong>Тип триггера:</strong> {{ $function->trigger_type }}</p>
            @if($function->trigger_keywords)
                <p><strong>Ключевые слова:</strong> {{ implode(', ', $function->trigger_keywords) }}</p>
            @endif
            <p><strong>Параметры:</strong> {{ $function->parameters->pluck('code')->implode(', ') }}</p>
        </div>

        <!-- Тестовый чат -->
        <div class="grid grid-cols-2 gap-6">
            <!-- Левая колонка - Чат -->
            <div>
                <h3 class="font-semibold mb-3">Тестовый чат</h3>
                <div id="testChat" class="border rounded-lg h-96 overflow-y-auto p-4 bg-gray-50 mb-4"></div>
                
                <div class="flex gap-2">
                    <input type="text" id="testMessage" class="flex-1 border rounded px-3 py-2" 
                           placeholder="Введите сообщение для теста...">
                    <button onclick="sendTestMessage()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Отправить
                    </button>
                </div>
                
           
            </div>
            
            <!-- Правая колонка - Результаты -->
            <div>
                <h3 class="font-semibold mb-3">Результат выполнения</h3>
                <div id="executionResult" class="border rounded-lg h-96 overflow-y-auto p-4 bg-gray-50">
                    <div class="text-gray-500">Результаты появятся здесь...</div>
                </div>
                
                <!-- Статус -->
                <div id="executionStatus" class="mt-4 p-3 rounded hidden"></div>
            </div>
        </div>

        <!-- Автоматические тесты -->
        <!-- Статус -->
        <div id="testStatus" class="hidden mb-4"></div>

        <!-- Быстрые тесты -->
        <div class="mt-4">
            <h4 class="font-semibold mb-2">Быстрые тесты:</h4>
            <div class="space-y-2">
                @foreach($function->parameters as $param)
                    <button onclick="sendQuickTest('{{ $param->code }}')" 
                            class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                        Тест: {{ $param->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Автоматические тесты -->
        <div class="mt-6 border-t pt-4">
            <h4 class="font-semibold mb-3">Автоматические тесты:</h4>
            <div class="grid grid-cols-2 gap-2">
                <button onclick="testParameterExtraction()" 
                        class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    🧪 Тест извлечения параметров
                </button>
                <button onclick="testTriggers()" 
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    🎯 Тест триггеров
                </button>
                <button onclick="runFullTest()" 
                        class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600 col-span-2">
                    🚀 Полный тест сценария
                </button>
            </div>
        </div>

        <div class="mt-4">
            <button onclick="clearTestChat()" 
                    class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
                🗑️ Очистить чат
            </button>
        </div>
        <!-- Панель настроек тестирования -->
        <div class="bg-gray-50 p-4 rounded mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" id="realExecutionMode" class="mr-2">
                        <span class="text-sm font-medium">Реальное выполнение в CRM</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">
                        ⚠️ Будут создаваться настоящие записи в Битрикс24
                    </p>
                </div>
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" id="debugMode" class="mr-2" checked>
                        <span class="text-sm font-medium">Режим отладки</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">
                        Показывать подробные логи
                    </p>
                </div>
            </div>
        </div>

        <!-- Модальное окно с результатами тестов -->
        <div id="testResults" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold">Результаты тестов</h3>
                        <button onclick="closeTestResults()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="testResultsContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// CSRF токен
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;


// Заголовки для запросов
const apiHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrfToken
};

let testConversationId = null;
let testMessages = [];
let realExecutionMode = false;

// Инициализация тестового диалога
async function initTestConversation() {
    try {
        const response = await fetch('/api/functions/test-conversation', {
            method: 'POST',
            headers: apiHeaders,
            credentials: 'same-origin',
            body: JSON.stringify({
                bot_id: {{ $bot->id }},
                function_id: {{ $function->id }}
            })
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('HTTP Error:', response.status, errorText);
            showStatus('❌ Ошибка инициализации: ' + response.status, 'error');
            return;
        }
        
        const data = await response.json();
        testConversationId = data.conversation_id;
        console.log('Test conversation created:', testConversationId);
    } catch (error) {
        console.error('Failed to init test conversation:', error);
        showStatus('❌ Ошибка соединения: ' + error.message, 'error');
    }
}

// Обновление отображения текущего режима
function updateExecutionModeDisplay() {
    const statusText = document.getElementById('executionModeStatus');
    if (statusText) {
        if (realExecutionMode) {
            statusText.innerHTML = '<span class="text-red-600 font-semibold">🔴 РЕАЛЬНОЕ ВЫПОЛНЕНИЕ</span>';
        } else {
            statusText.innerHTML = '<span class="text-green-600 font-semibold">🟢 СИМУЛЯЦИЯ</span>';
        }
    }
}

// Отправка тестового сообщения
async function sendTestMessage() {
    const input = document.getElementById('testMessage');
    const message = input.value.trim();
    
    if (!message) return;
    
    addMessageToTestChat('user', message);
    testMessages.push({ role: 'user', content: message });
    input.value = '';
    
    document.getElementById('executionResult').innerHTML = '<div class="text-gray-500">⏳ Обработка...</div>';
    
    try {
        // 1. Тестируем триггеры
        const triggerResponse = await fetch('/api/functions/test-triggers', {
            method: 'POST',
            headers: apiHeaders,
            credentials: 'same-origin',
            body: JSON.stringify({
                function: {
                    id: {{ $function->id }},
                    trigger_type: '{{ $function->trigger_type }}',
                    trigger_keywords: @json($function->trigger_keywords)
                },
                message: message,
                conversation_history: testMessages.slice(0, -1) // Без текущего сообщения
            })
        });
        
        if (!triggerResponse.ok) {
            const errorText = await triggerResponse.text();
            throw new Error(`Trigger test failed: ${triggerResponse.status} - ${errorText}`);
        }
        
        const triggerResult = await triggerResponse.json();
        console.log('Trigger result:', triggerResult);
        
        if (triggerResult.matched) {
            showStatus('✅ Триггер сработал!', 'success');
            
            // 2. Выполняем функцию
            const executeResponse = await fetch('/api/functions/test-execute', {
                method: 'POST',
                headers: apiHeaders,
                credentials: 'same-origin',
                body: JSON.stringify({
                    function: { id: {{ $function->id }} },
                    message: message,
                    conversation_history: testMessages.slice(0, -1),
                    extractOnly: false,
                    realExecution: realExecutionMode // Измените на true для реального выполнения в CRM
                })
            });
            
            if (!executeResponse.ok) {
                const errorData = await executeResponse.json();
                throw new Error(`Execute test failed: ${errorData.error || executeResponse.statusText}`);
            }
            
            const executeResult = await executeResponse.json();
            console.log('Execute result:', executeResult);
            
            displayExecutionResult(executeResult);
            
            // Добавляем ответ бота
            const botMessage = executeResult.status === 'success' 
                ? ('{{ $function->behavior->success_message ?? "Готово!" }}').replace('{lead_id}', executeResult.executedActions?.[0]?.data?.lead_id || 'TEST')
                : '{{ $function->behavior->error_message ?? "Произошла ошибка" }}';
            
            addMessageToTestChat('assistant', botMessage);
            testMessages.push({ role: 'assistant', content: botMessage });
        } else {
            showStatus('ℹ️ Триггер не сработал для этого сообщения', 'info');
            document.getElementById('executionResult').innerHTML = 
                `<div class="text-yellow-600">
                    ⚠️ Триггер не сработал<br>
                    <small>Тип: ${triggerResult.debug?.trigger_type}<br>
                    Ключевые слова: ${JSON.stringify(triggerResult.debug?.keywords)}</small>
                </div>`;
        }
        
    } catch (error) {
        console.error('Test error:', error);
        showStatus('❌ Ошибка: ' + error.message, 'error');
        document.getElementById('executionResult').innerHTML = 
            `<div class="text-red-500">
                <strong>Ошибка:</strong> ${error.message}<br>
                <small class="text-xs">Проверьте консоль браузера для деталей</small>
            </div>`;
    }
}
// Отображение результатов выполнения
function displayExecutionResult(result) {
    let html = '<div class="space-y-4">';
    
    // Если статус "waiting_for_parameters"
    if (result.status === 'waiting_for_parameters') {
        html += '<div class="bg-yellow-50 p-4 rounded border border-yellow-200">';
        html += '<h4 class="font-semibold text-yellow-900 mb-2">⚠️ Недостаточно данных</h4>';
        html += '<p class="text-sm text-yellow-800 mb-3">Для выполнения функции необходимы следующие параметры:</p>';
        
        if (result.missingParams && result.missingParams.length > 0) {
            html += '<ul class="list-disc list-inside text-sm text-yellow-700">';
            result.missingParams.forEach(param => {
                html += `<li><strong>${param.name}</strong> (${param.code})`;
                if (param.description) {
                    html += ` - ${param.description}`;
                }
                html += '</li>';
            });
            html += '</ul>';
        }
        
        if (result.extractedParams && Object.keys(result.extractedParams).length > 0) {
            html += '<div class="mt-3 pt-3 border-t border-yellow-300">';
            html += '<p class="text-xs text-yellow-700 mb-1">Уже извлечено:</p>';
            for (const [key, value] of Object.entries(result.extractedParams)) {
                html += `<div class="text-xs text-yellow-600">✓ ${key}: ${value}</div>`;
            }
            html += '</div>';
        }
        
        html += '</div>';
    } else {
        // Извлеченные параметры
        if (result.extractedParams && Object.keys(result.extractedParams).length > 0) {
            html += '<div class="bg-blue-50 p-4 rounded">';
            html += '<h4 class="font-semibold text-blue-900 mb-2">📋 Извлеченные параметры:</h4>';
            for (const [key, value] of Object.entries(result.extractedParams)) {
                html += `<div class="text-sm"><strong>${key}:</strong> ${value}</div>`;
            }
            html += '</div>';
        }
        
        // Выполненные действия
        if (result.executedActions && result.executedActions.length > 0) {
            html += '<div class="bg-green-50 p-4 rounded">';
            html += '<h4 class="font-semibold text-green-900 mb-2">⚙️ Выполненные действия:</h4>';
            result.executedActions.forEach(action => {
                const icon = action.status === 'success' ? '✓' : '✗';
                const colorClass = action.status === 'success' ? 'text-green-700' : 'text-red-700';
                html += `<div class="${colorClass} text-sm">${icon} ${action.name}: ${action.result}</div>`;
                if (action.data) {
                    html += `<div class="text-xs text-gray-600 ml-4 mt-1">Данные: ${JSON.stringify(action.data)}</div>`;
                }
            });
            html += '</div>';
        }
    }
    
    // Лог выполнения
    if (result.executionLog && result.executionLog.length > 0) {
        html += '<div class="bg-gray-50 p-4 rounded">';
        html += '<h4 class="font-semibold text-gray-900 mb-2">📝 Лог выполнения:</h4>';
        result.executionLog.forEach(log => {
            const colorClass = log.level === 'error' ? 'text-red-600' : 
                              log.level === 'warning' ? 'text-yellow-600' : 'text-gray-600';
            html += `<div class="text-xs ${colorClass}">[${log.time}] ${log.message}</div>`;
        });
        html += '</div>';
    }
    
    html += '</div>';
    
    document.getElementById('executionResult').innerHTML = html;
}

// Вспомогательные функции
function addMessageToTestChat(role, content) {
    const chat = document.getElementById('testChat');
    const messageDiv = document.createElement('div');
    messageDiv.className = `mb-3 ${role === 'user' ? 'text-right' : 'text-left'}`;
    messageDiv.innerHTML = `
        <div class="inline-block px-4 py-2 rounded-lg ${
            role === 'user' 
                ? 'bg-blue-500 text-white' 
                : 'bg-gray-200 text-gray-800'
        }">
            ${content}
        </div>
    `;
    chat.appendChild(messageDiv);
    chat.scrollTop = chat.scrollHeight;
}

function showStatus(message, type = 'info') {
    const statusDiv = document.getElementById('testStatus');
    if (statusDiv) {
        const colors = {
            success: 'bg-green-50 text-green-800 border-green-200',
            error: 'bg-red-50 text-red-800 border-red-200',
            info: 'bg-blue-50 text-blue-800 border-blue-200'
        };
        statusDiv.className = `p-3 rounded border ${colors[type]} mb-4`;
        statusDiv.textContent = message;
    }
}
// Быстрый тест с заранее заданным сообщением
function sendQuickTest(parameterCode) {
    const testMessages = {
        'name': 'Меня зовут Александр Петров',
        'phone': 'Мой телефон +7 999 123-45-67',
        'email': 'Email для связи: test@example.com',
        'order_number': 'Проверьте заказ номер 12345',
        'date': 'Хочу записаться на завтра в 15:00',
        'client_name': 'Меня зовут Иван Иванов',
        'client_phone': 'Телефон +7 999 888-77-66',
        'client_email': 'ivan@test.com'
    };
    
    const message = testMessages[parameterCode] || `Тест параметра ${parameterCode}`;
    document.getElementById('testMessage').value = message;
    sendTestMessage();
}

// Тест извлечения параметров
async function testParameterExtraction() {
    const testCases = [
        { message: 'Меня зовут Александр Петров, мой телефон +7 999 123-45-67', expected: ['name', 'phone'] },
        { message: 'Email для связи: test@example.com', expected: ['email'] },
        { message: 'Проверьте заказ номер 12345', expected: ['order_number'] },
        { message: 'Хочу записаться на завтра в 15:00', expected: ['date'] }
    ];
    
    showStatus('🧪 Запуск тестов извлечения параметров...', 'info');
    let results = '';
    
    for (const testCase of testCases) {
        try {
            const response = await fetch('/api/functions/test-execute', {
                method: 'POST',
                headers: apiHeaders,
                credentials: 'same-origin',
                body: JSON.stringify({
                    function: { id: {{ $function->id }} },
                    message: testCase.message,
                    conversation_history: [],
                    extractOnly: true
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            const extracted = result.extractedParams || {};
            const extractedKeys = Object.keys(extracted);
            
            results += `
                <div class="mb-3 p-3 border rounded ${extractedKeys.length > 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}">
                    <div class="font-medium">"${testCase.message}"</div>
                    <div class="text-sm mt-1">
                        ${extractedKeys.length > 0 
                            ? `✓ Извлечено: ${JSON.stringify(extracted)}` 
                            : '✗ Ничего не извлечено'}
                    </div>
                </div>
            `;
        } catch (error) {
            results += `
                <div class="mb-3 p-3 border rounded bg-red-50 border-red-200">
                    <div class="font-medium">"${testCase.message}"</div>
                    <div class="text-sm text-red-600">Ошибка: ${error.message}</div>
                </div>
            `;
        }
    }
    
    showTestResults(`
        <h3 class="font-bold text-lg mb-3">Тест извлечения параметров</h3>
        ${results}
    `);
}

// Тест триггеров
async function testTriggers() {
    const testCases = [
        { message: 'создать лид', shouldMatch: true, label: '✅ Позитивный тест' },
        { message: 'хочу оставить заявку', shouldMatch: true, label: '✅ Синоним триггера' },
        { message: 'какая погода сегодня?', shouldMatch: false, label: '❌ Негативный тест' }
    ];
    
    showStatus('🧪 Запуск тестов триггеров...', 'info');
    let results = '';
    
    for (const testCase of testCases) {
        try {
            const response = await fetch('/api/functions/test-triggers', {
                method: 'POST',
                headers: apiHeaders,
                credentials: 'same-origin',
                body: JSON.stringify({
                    function: {
                        id: {{ $function->id }},
                        trigger_type: '{{ $function->trigger_type }}',
                        trigger_keywords: @json($function->trigger_keywords)
                    },
                    message: testCase.message,
                    conversation_history: []
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            const passed = result.matched === testCase.shouldMatch;
            
            results += `
                <div class="mb-3 p-3 border rounded ${passed ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}">
                    <div class="font-medium">${testCase.label}</div>
                    <div class="text-sm mt-1">
                        Сообщение: "${testCase.message}"<br>
                        Ожидалось: ${testCase.shouldMatch ? 'срабатывание' : 'не срабатывание'}<br>
                        Получено: ${result.matched ? 'сработал' : 'не сработал'}
                        ${result.trigger ? ` (триггер: ${result.trigger})` : ''}
                        ${passed ? ' ✓' : ' ✗'}
                    </div>
                </div>
            `;
        } catch (error) {
            results += `
                <div class="mb-3 p-3 border rounded bg-red-50 border-red-200">
                    <div class="font-medium">${testCase.label}</div>
                    <div class="text-sm text-red-600">Ошибка: ${error.message}</div>
                </div>
            `;
        }
    }
    
    showTestResults(`
        <h3 class="font-bold text-lg mb-3">Тест триггеров</h3>
        ${results}
    `);
}

// Полный тест сценария
async function runFullTest() {
    const scenario = [
        'Здравствуйте, хочу создать лид',
        'Меня зовут Иван Иванов',
        'Мой телефон +7 999 888-77-66',
        'Email: ivan@test.com'
    ];
    
    showStatus('🧪 Запуск полного сценария...', 'info');
    let results = '<h3 class="font-bold text-lg mb-3">Полный сценарий создания лида</h3>';
    let conversationHistory = [];
    
    for (const message of scenario) {
        try {
            // Проверяем триггер
            const triggerResponse = await fetch('/api/functions/test-triggers', {
                method: 'POST',
                headers: apiHeaders,
                credentials: 'same-origin',
                body: JSON.stringify({
                    function: {
                        id: {{ $function->id }},
                        trigger_type: '{{ $function->trigger_type }}',
                        trigger_keywords: @json($function->trigger_keywords)
                    },
                    message: message,
                    conversation_history: conversationHistory
                })
            });
            
            const triggerResult = await triggerResponse.json();
            
            results += `
                <div class="mb-2 p-2 border-l-4 ${triggerResult.matched ? 'border-green-500 bg-green-50' : 'border-gray-300 bg-gray-50'}">
                    <div class="text-sm">→ ${message} ${triggerResult.matched ? '✓' : '○'}</div>
                </div>
            `;
            
            conversationHistory.push({ role: 'user', content: message });
            
            // Небольшая задержка между запросами
            await new Promise(resolve => setTimeout(resolve, 500));
        } catch (error) {
            results += `
                <div class="mb-2 p-2 border-l-4 border-red-500 bg-red-50">
                    <div class="text-sm text-red-600">→ ${message} ✗ (${error.message})</div>
                </div>
            `;
        }
    }
    
    // Финальное выполнение
    try {
        const executeResponse = await fetch('/api/functions/test-execute', {
            method: 'POST',
            headers: apiHeaders,
            credentials: 'same-origin',
            body: JSON.stringify({
                function: { id: {{ $function->id }} },
                message: scenario[scenario.length - 1],
                conversation_history: conversationHistory.slice(0, -1),
                extractOnly: false,
                realExecution: realExecutionMode
            })
        });
        
        const executeResult = await executeResponse.json();
        
        results += `
            <div class="mt-4 p-4 border rounded ${executeResult.status === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}">
                <div class="font-bold mb-2">Результат выполнения:</div>
                <div class="text-sm">
                    Статус: ${executeResult.status === 'success' ? '✓ Успешно' : '✗ Ошибка'}<br>
                    Параметры: ${JSON.stringify(executeResult.extractedParams || {})}<br>
                    Действия: ${executeResult.executedActions?.length || 0} выполнено
                </div>
            </div>
        `;
    } catch (error) {
        results += `
            <div class="mt-4 p-4 border rounded bg-red-50 border-red-200">
                <div class="text-sm text-red-600">Ошибка выполнения: ${error.message}</div>
            </div>
        `;
    }
    
    showTestResults(results);
}

// Показать результаты тестов
function showTestResults(html) {
    const resultsDiv = document.getElementById('testResults');
    const contentDiv = document.getElementById('testResultsContent');
    if (resultsDiv && contentDiv) {
        contentDiv.innerHTML = html;
        resultsDiv.classList.remove('hidden');
    }
}

// Закрыть результаты тестов
function closeTestResults() {
    const resultsDiv = document.getElementById('testResults');
    if (resultsDiv) {
        resultsDiv.classList.add('hidden');
    }
}

// Очистить чат
function clearTestChat() {
    document.getElementById('testChat').innerHTML = '';
    testMessages = [];
    document.getElementById('executionResult').innerHTML = '<div class="text-gray-500">Отправьте сообщение для тестирования</div>';
    showStatus('Чат очищен', 'info');
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
    initTestConversation();

    const realExecutionCheckbox = document.getElementById('realExecutionMode');
    if (realExecutionCheckbox) {
        realExecutionCheckbox.addEventListener('change', function() {
            realExecutionMode = this.checked;
            updateExecutionModeDisplay();
            
            // Показываем предупреждение при включении
            if (realExecutionMode) {
                showStatus('⚠️ ВНИМАНИЕ: Включен режим реального выполнения! Будут создаваться настоящие записи в CRM.', 'warning');
            } else {
                showStatus('ℹ️ Режим симуляции. Реальные действия выполняться не будут.', 'info');
            }
        });
    }

    // Обработчик чекбокса отладки
    const debugModeCheckbox = document.getElementById('debugMode');
    if (debugModeCheckbox) {
        debugModeCheckbox.addEventListener('change', function() {
            console.log('Debug mode:', this.checked ? 'enabled' : 'disabled');
        });
    }
});
</script>
@endsection