@extends('layouts.app')

@section('title', 'Создать функцию')

@section('content')
<div class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Создание функции для бота</h1>
            <p class="text-gray-500 mt-1">Настройте параметры, действия и поведение для новой функции.</p>
        </div>

        <form id="functionForm" method="POST" action="{{ route('functions.store', [$organization, $bot]) }}" class="space-y-6">
            @csrf
            
            <!-- Основная информация -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3 mb-4">Основная информация</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Код функции (английский)</label>
                        <input type="text" id="name" name="name" pattern="[a-z_]+" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               placeholder="create_lead">
                    </div>
                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700">Название для отображения</label>
                        <input type="text" id="display_name" name="display_name" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               placeholder="Создание лида в CRM">
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="description" class="block text-sm font-medium text-gray-700">Описание</label>
                    <textarea id="description" name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Функция извлекает контактные данные из диалога и создает лид в Битрикс24"></textarea>
                </div>

                <div class="mt-4">
                    <label for="trigger_type" class="block text-sm font-medium text-gray-700">Когда запускать функцию</label>
                    <select id="trigger_type" name="trigger_type" onchange="toggleTriggerKeywords(this)"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="auto">Автоматически при обнаружении данных</option>
                        <option value="keyword">По ключевым словам</option>
                        <option value="manual">Только вручную</option>
                    </select>
                </div>
                
                <div id="triggerKeywords" class="hidden mt-4">
                    <label for="trigger_keywords_text" class="block text-sm font-medium text-gray-700">Ключевые слова (через запятую)</label>
                    <input type="text" id="trigger_keywords_text" name="trigger_keywords_text"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="создать лид, сохранить контакт, добавить в CRM">
                </div>
            </div>

            <!-- Параметры -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Параметры функции</h3>
                <p class="text-sm text-gray-500 mt-1 mb-4">Определите данные, которые бот должен извлекать из диалога.</p>
                <div id="parametersContainer" class="space-y-4"></div>
                <button type="button" onclick="addParameter()"
                        class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    + Добавить параметр
                </button>
            </div>

            <!-- Действия -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                 <h3 class="text-lg font-semibold text-gray-900">Действия</h3>
                 <p class="text-sm text-gray-500 mt-1 mb-4">Что должна сделать функция после извлечения параметров.</p>
                <div id="actionsContainer" class="space-y-4"></div>
                <button type="button" onclick="addAction()"
                        class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    + Добавить действие
                </button>
            </div>

            <!-- Поведение после выполнения -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3 mb-4">Поведение после выполнения</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="on_success" class="block text-sm font-medium text-gray-700">При успешном выполнении</label>
                        <select id="on_success" name="behavior[on_success]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="continue">Продолжить диалог</option>
                            <option value="pause">Поставить на паузу</option>
                            <option value="enhance_prompt">Дополнить промпт</option>
                        </select>
                    </div>
                    <div>
                        <label for="on_error" class="block text-sm font-medium text-gray-700">При ошибке</label>
                        <select id="on_error" name="behavior[on_error]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="continue">Продолжить диалог</option>
                            <option value="pause">Поставить на паузу</option>
                            <option value="notify">Уведомить администратора</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="success_message" class="block text-sm font-medium text-gray-700">Сообщение при успехе</label>
                    <input type="text" id="success_message" name="behavior[success_message]"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="✓ Лид #{lead_id} успешно создан">
                </div>
                <div class="mt-4">
                    <label for="error_message" class="block text-sm font-medium text-gray-700">Сообщение при ошибке</label>
                    <input type="text" id="error_message" name="behavior[error_message]"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Не удалось создать лид: {error}">
                </div>
                 <div class="mt-4 hidden" id="promptEnhancement">
                    <label for="prompt_enhancement" class="block text-sm font-medium text-gray-700">Дополнение к промпту</glabel>
                    <textarea id="prompt_enhancement" name="behavior[prompt_enhancement]" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Лид создан. Теперь помоги клиенту выбрать подходящий тариф."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4">
                <a href="{{ route('functions.index', [$organization, $bot]) }}"
                   class="inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Отмена
                </a>
                <button type="submit"
                        class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Создать функцию
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// CSS классы для единообразного стиля
const inputClasses = "block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm";
const labelClasses = "block text-sm font-medium text-gray-700";
const selectClasses = "mt-1 " + inputClasses;

let parameterIndex = 0;
let actionIndex = 0;

function addParameter() {
    const container = document.getElementById('parametersContainer');
    const html = `
        <div class="parameter-item border border-gray-200 p-4 rounded-md bg-gray-50/50">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-gray-800">Параметр #${parameterIndex + 1}</h4>
                <button type="button" onclick="removeDynamicItem(this, '.parameter-item')" class="text-sm font-medium text-red-600 hover:text-red-800 transition-colors">Удалить</button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="${labelClasses}">Код параметра</label>
                    <input type="text" name="parameters[${parameterIndex}][code]" pattern="[a-z_]+" required placeholder="client_name" class="${selectClasses}">
                </div>
                <div>
                    <label class="${labelClasses}">Название</label>
                    <input type="text" name="parameters[${parameterIndex}][name]" required placeholder="Имя клиента" class="${selectClasses}">
                </div>
                <div>
                    <label class="${labelClasses}">Тип</label>
                    <select name="parameters[${parameterIndex}][type]" required class="${selectClasses}">
                        <option value="string">Текстовый</option>
                        <option value="number">Числовой</option>
                        <option value="boolean">Логический</option>
                        <option value="date">Дата</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="${labelClasses}">Что искать в диалоге (подсказка для AI)</label>
                <input type="text" name="parameters[${parameterIndex}][description]" placeholder="Имя человека, с которым общается бот" class="${selectClasses}">
            </div>
            
            <div class="mt-4">
                <label class="flex items-center text-sm text-gray-700">
                    <input type="checkbox" name="parameters[${parameterIndex}][is_required]" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2">Обязательный параметр</span>
                </label>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    parameterIndex++;
    updateParameterSelects();
}

function addAction() {
    const container = document.getElementById('actionsContainer');
    const html = `
        <div class="action-item border border-gray-200 p-4 rounded-md bg-gray-50/50">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-gray-800">Действие #${actionIndex + 1}</h4>
                <button type="button" onclick="removeDynamicItem(this, '.action-item')" class="text-sm font-medium text-red-600 hover:text-red-800 transition-colors">Удалить</button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="${labelClasses}">Провайдер</label>
                    <select name="actions[${actionIndex}][provider]" onchange="updateActionType(this, ${actionIndex})" class="${selectClasses}">
                        <option value="">Выберите провайдера</option>
                        @foreach($crmIntegrations as $crm)
                            <option value="{{ $crm->type }}">{{ $crm->name }}</option>
                        @endforeach
                        <option value="webhook">Webhook</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div>
                    <label class="${labelClasses}">Тип действия</label>
                    <select name="actions[${actionIndex}][type]" id="actionType_${actionIndex}" class="${selectClasses}">
                        <option value="">Сначала выберите провайдера</option>
                    </select>
                </div>
            </div>
            
            <div id="actionConfig_${actionIndex}" class="mt-4 pt-4 border-t border-gray-200">
                <!-- Динамическая конфигурация -->
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    actionIndex++;
}

function removeDynamicItem(button, selector) {
    button.closest(selector).remove();
    updateParameterSelects();
}


function updateActionType(select, index) {
    const provider = select.value;
    const typeSelect = document.getElementById(`actionType_${index}`);
    
    typeSelect.innerHTML = '<option value="">Выберите действие</option>';
    typeSelect.disabled = true;

    const actions = {
        'bitrix24': [
            {value: 'create_lead', text: 'Создать лид'},
            {value: 'create_deal', text: 'Создать сделку'},
            {value: 'create_contact', text: 'Создать контакт'},
            {value: 'create_task', text: 'Создать задачу'},
        ],
        'webhook': [{value: 'post', text: 'POST запрос'}, {value: 'get', text: 'GET запрос'}],
        'email': [{value: 'send', text: 'Отправить письмо'}]
    };
    
    if (actions[provider]) {
        typeSelect.disabled = false;
        actions[provider].forEach(action => {
            const option = document.createElement('option');
            option.value = action.value;
            option.textContent = action.text;
            typeSelect.appendChild(option);
        });
    }
    
    typeSelect.onchange = function() {
        showActionConfig(provider, this.value, index);
    };
    // Trigger change to load config for the first item
    typeSelect.dispatchEvent(new Event('change'));
}

function showActionConfig(provider, type, index) {
    const configDiv = document.getElementById(`actionConfig_${index}`);
    configDiv.innerHTML = ''; // Clear previous config

    if (provider === 'bitrix24' && type === 'create_lead') {
        configDiv.innerHTML = `
            <h5 class="text-md font-semibold text-gray-800 mb-2">Настройка полей лида</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="${labelClasses}">Название лида</label>
                    <input type="text" name="actions[${index}][config][title]" placeholder="Лид из чат-бота" class="${selectClasses}">
                </div>
                 <div>
                    <label class="${labelClasses}">Ответственный</label>
                    <select name="actions[${index}][config][assigned_by_id]" class="${selectClasses}">
                        <option value="1">Пользователь по умолчанию</option>
                        <!-- TODO: Load users from CRM -->
                    </select>
                </div>
                <div>
                    <label class="${labelClasses}">Имя клиента</label>
                    <select name="actions[${index}][field_mapping][name]" class="parameter-select ${selectClasses}"></select>
                </div>
                <div>
                    <label class="${labelClasses}">Email</label>
                    <select name="actions[${index}][field_mapping][email]" class="parameter-select ${selectClasses}"></select>
                </div>
                <div>
                    <label class="${labelClasses}">Телефон</label>
                    <select name="actions[${index}][field_mapping][phone]" class="parameter-select ${selectClasses}"></select>
                </div>
            </div>`;
        updateParameterSelects();
    }
    // TODO: Add other configs for webhook, email etc.
}

function updateParameterSelects() {
    const selects = document.querySelectorAll('.parameter-select');
    const parameterInputs = document.querySelectorAll('input[name^="parameters["][name*="[code]"]');
    
    const options = Array.from(parameterInputs).map(input => {
        if (input.value) {
            const nameInput = input.closest('.parameter-item').querySelector('input[name*="[name]"]');
            const displayName = nameInput.value ? `${nameInput.value} (${input.value})` : input.value;
            return `<option value="${input.value}">${displayName}</option>`;
        }
        return '';
    }).join('');

    selects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = '<option value="">- Не выбрано -</option>' + options;
        select.value = currentValue;
    });
}


document.querySelectorAll('input[name^="parameters["]').forEach(input => {
    input.addEventListener('keyup', updateParameterSelects);
});


function toggleTriggerKeywords(select) {
    const keywordsDiv = document.getElementById('triggerKeywords');
    keywordsDiv.style.display = select.value === 'keyword' ? 'block' : 'none';
    if(select.value !== 'keyword'){
        document.getElementById('trigger_keywords_text').value = '';
    }
}

document.getElementById('functionForm').addEventListener('submit', function(e) {
    const keywordsText = document.querySelector('[name="trigger_keywords_text"]');
    if (keywordsText && keywordsText.value) {
        // Clear previous hidden inputs to avoid duplicates
        this.querySelectorAll('input[name^="trigger_keywords["]').forEach(el => el.remove());

        const keywords = keywordsText.value.split(',').map(k => k.trim()).filter(k => k);
        keywords.forEach((keyword, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `trigger_keywords[${index}]`;
            input.value = keyword;
            this.appendChild(input);
        });
    }
});

// Initial setup
document.addEventListener('DOMContentLoaded', function() {
    addParameter();
    // In case of validation error and old() data, update selects
    updateParameterSelects(); 
});

</script>
@endsection
