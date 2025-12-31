@extends('layouts.app')

@section('title', 'Создать функцию')

@section('content')
<style type="text/css">
    .field-mapping-container {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-top: 15px;
    }

    .field-mapping-item {
        background: white;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 12px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s;
    }

    .field-mapping-item:hover {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .field-mapping-item label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
        font-weight: 500;
    }

    .field-mapping-item select,
    .field-mapping-item input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .field-mapping-item select:focus,
    .field-mapping-item input:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .btn-add-field {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-add-field:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .loading {
        text-align: center;
        padding: 40px;
        color: #6b7280;
        font-size: 16px;
    }

    /* Анимация загрузки */
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    .loading {
        animation: pulse 1.5s infinite;
    }

    /* Подсказки для полей */
    .field-hint {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 2px;
    }

    .required-field::after {
        content: " *";
        color: #ef4444;
        font-weight: bold;
    }
</style>
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
                <!-- В форме создания/редактирования функции -->
                <div class="mt-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="behavior[accumulate_parameters]" value="1"
                               {{ old('behavior.accumulate_parameters', $function->behavior->accumulate_parameters ?? false) ? 'checked' : '' }}
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <span class="ml-2 text-sm text-gray-700">Накапливать параметры из нескольких сообщений</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">
                        Если включено, бот будет собирать параметры из нескольких сообщений пользователя до выполнения действия
                    </p>
                </div>
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
    
    // Определяем тип сущности для каждого действия
    const entityTypeMap = {
        'create_lead': 'lead',
        'create_deal': 'deal',
        'create_contact': 'contact',
        'create_task': 'task'
    };
    
    const entityType = entityTypeMap[type];
    
    // Если это действие Битрикс24 с поддерживаемым типом
    if (provider === 'bitrix24' && entityType) {
        // Показываем загрузку
        configDiv.innerHTML = '<div class="loading">⏳ Загрузка полей CRM...</div>';
        
        // Загружаем поля из CRM
        loadCRMFields(provider, entityType).then(fields => {
            const entityLabels = {
                'lead': 'лида',
                'deal': 'сделки',
                'contact': 'контакта',
                'task': 'задачи'
            };
            
            configDiv.innerHTML = `
                <h5 style="margin-bottom: 15px; font-weight: 600;">Маппинг полей ${entityLabels[entityType] || entityType}</h5>
                
                <div class="field-mapping-container" id="fieldMapping_${index}">
                    <div class="field-mapping-list" id="fieldList_${index}">
                        <!-- Здесь будут динамические поля -->
                    </div>
                    
                    <button type="button" onclick="addFieldMapping(${index})" 
                            class="btn-add-field" style="margin-top: 15px; padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 5px;">
                        + Добавить поле
                    </button>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <h6 style="margin-bottom: 10px;">Дополнительные настройки</h6>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        ${getAdditionalSettingsHTML(entityType, index)}
                    </div>
                </div>
            `;
            
            // Сохраняем поля для использования
            window.crmFields = window.crmFields || {};
            window.crmFields[index] = fields;
            
            // Загружаем дополнительные данные
            loadAdditionalData(provider, entityType, index);
            
            // Добавляем первое поле по умолчанию
            addFieldMapping(index);
        });
    } else if (provider === 'webhook') {
        showWebhookConfig(configDiv, type, index);
    } else if (provider === 'email') {
        showEmailConfig(configDiv, index);
    }
}

// Вспомогательная функция для получения HTML дополнительных настроек
function getAdditionalSettingsHTML(entityType, index) {
    switch(entityType) {
        case 'lead':
            return `
                <div>
                    <label>Стадия лида</label>
                    <select name="actions[${index}][config][status_id]" id="leadStatus_${index}">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
                <div>
                    <label>Ответственный</label>
                    <select name="actions[${index}][config][assigned_by_id]" id="assignedUser_${index}">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
            `;
        case 'deal':
            return `
                <div>
                    <label>Воронка</label>
                    <select name="actions[${index}][config][category_id]" id="dealCategory_${index}" onchange="loadDealStages(${index})">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
                <div>
                    <label>Стадия</label>
                    <select name="actions[${index}][config][stage_id]" id="dealStage_${index}">
                        <option value="">Сначала выберите воронку</option>
                    </select>
                </div>
                <div>
                    <label>Ответственный</label>
                    <select name="actions[${index}][config][assigned_by_id]" id="assignedUser_${index}">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
            `;
        case 'contact':
            return `
                <div>
                    <label>Тип контакта</label>
                    <select name="actions[${index}][config][type_id]" id="contactType_${index}">
                        <option value="CLIENT">Клиент</option>
                        <option value="SUPPLIER">Поставщик</option>
                        <option value="PARTNER">Партнер</option>
                    </select>
                </div>
                <div>
                    <label>Ответственный</label>
                    <select name="actions[${index}][config][assigned_by_id]" id="assignedUser_${index}">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
            `;
        case 'task':
            return `
                <div>
                    <label>Заголовок задачи</label>
                    <input type="text" name="actions[${index}][config][title]" 
                           placeholder="Обработать обращение из чат-бота"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
                <div>
                    <label>Приоритет</label>
                    <select name="actions[${index}][config][priority]" id="taskPriority_${index}">
                        <option value="0">Низкий</option>
                        <option value="1" selected>Средний</option>
                        <option value="2">Высокий</option>
                    </select>
                </div>
                <div>
                    <label>Ответственный</label>
                    <select name="actions[${index}][config][responsible_id]" id="assignedUser_${index}">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
                <div>
                    <label>Дедлайн (дней)</label>
                    <input type="number" name="actions[${index}][config][deadline_days]" 
                           value="1" min="1"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
            `;
        default:
            return '';
    }
}

// Загрузка дополнительных данных
async function loadAdditionalData(provider, entityType, index) {
    switch(entityType) {
        case 'lead':
            await loadLeadStatuses(provider, index);
            await loadCRMUsers(provider, index);
            break;
        case 'deal':
            await loadDealCategories(provider, index);
            await loadCRMUsers(provider, index);
            break;
        case 'contact':
        case 'task':
            await loadCRMUsers(provider, index);
            break;
    }
}

// Новая функция для загрузки воронок сделок
async function loadDealCategories(provider, index) {
    try {
        const response = await fetch(`/api/crm/${provider}/pipelines`, {
            headers: {
                'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        const select = document.getElementById(`dealCategory_${index}`);
        
        if (select) {
            select.innerHTML = '<option value="">По умолчанию</option>';
            (data.pipelines || []).forEach(pipeline => {
                select.innerHTML += `<option value="${pipeline.ID || pipeline.id}">${pipeline.NAME || pipeline.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading deal categories:', error);
    }
}

// Новая функция для загрузки стадий сделки
async function loadDealStages(index) {
    const categorySelect = document.getElementById(`dealCategory_${index}`);
    const stageSelect = document.getElementById(`dealStage_${index}`);
    
    if (!categorySelect || !stageSelect) return;
    
    const categoryId = categorySelect.value;
    
    if (!categoryId) {
        stageSelect.innerHTML = '<option value="">Сначала выберите воронку</option>';
        return;
    }
    
    stageSelect.innerHTML = '<option value="">Загрузка...</option>';
    
    try {
        const response = await fetch(`/api/crm/bitrix24/pipeline-stages?pipeline_id=${categoryId}`, {
            headers: {
                'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        stageSelect.innerHTML = '<option value="">По умолчанию</option>';
        
        (data.stages || []).forEach(stage => {
            stageSelect.innerHTML += `<option value="${stage.STATUS_ID || stage.id}">${stage.NAME || stage.name}</option>`;
        });
    } catch (error) {
        console.error('Error loading deal stages:', error);
        stageSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
    }
}

async function loadCRMFields(provider, entityType) {
    try {
        const response = await fetch(`/api/crm/fields/${provider}/${entityType}`, {
            headers: {
                'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) throw new Error('Failed to load fields');
        
        const data = await response.json();
        return data.fields;
    } catch (error) {
        console.error('Error loading CRM fields:', error);
        return getDefaultFields(entityType);
    }
}

// Конфигурация для webhook
function showWebhookConfig(configDiv, type, index) {
    configDiv.innerHTML = `
        <div style="margin-top: 15px;">
            <label style="display: block; margin-bottom: 5px;">URL для ${type === 'post' ? 'POST' : 'GET'} запроса</label>
            <input type="url" name="actions[${index}][config][url]" required
                   placeholder="https://example.com/api/endpoint"
                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
        </div>
        <div style="margin-top: 15px;">
            <label style="display: block; margin-bottom: 5px;">Данные для отправки (JSON)</label>
            <textarea name="actions[${index}][config][data]" rows="4"
                      placeholder='{"param1": "{parameter_code}", "param2": "static_value"}'
                      style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace;"></textarea>
            <small style="color: #6b7280;">Используйте {parameter_code} для подстановки значений параметров</small>
        </div>
    `;
}

// Конфигурация для email
function showEmailConfig(configDiv, index) {
    configDiv.innerHTML = `
        <div style="margin-top: 15px;">
            <label style="display: block; margin-bottom: 5px;">Email получателя</label>
            <input type="email" name="actions[${index}][config][to]" required
                   placeholder="user@example.com"
                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
        </div>
        <div style="margin-top: 15px;">
            <label style="display: block; margin-bottom: 5px;">Тема письма</label>
            <input type="text" name="actions[${index}][config][subject]" required
                   placeholder="Новое обращение из чат-бота"
                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
        </div>
        <div style="margin-top: 15px;">
            <label style="display: block; margin-bottom: 5px;">Текст письма</label>
            <textarea name="actions[${index}][config][body]" rows="6" required
                      placeholder="Клиент {client_name} оставил обращение..."
                      style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></textarea>
            <small style="color: #6b7280;">Используйте {parameter_code} для подстановки значений</small>
        </div>
    `;
}

// Функция добавления маппинга поля
function addFieldMapping(actionIndex) {
    const container = document.getElementById(`fieldList_${actionIndex}`);
    const fields = window.crmFields[actionIndex] || getDefaultFields('lead');
    const parameters = getAvailableParameters();
    const mappingIndex = container.children.length;
    
    const mappingHtml = `
        <div class="field-mapping-item" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9fafb; border-radius: 5px;">
            <div style="flex: 1;">
                <label style="font-size: 12px; color: #6b7280;">Поле CRM</label>
                <select name="actions[${actionIndex}][field_mapping][${mappingIndex}][crm_field]" 
                        onchange="updateFieldType(${actionIndex}, ${mappingIndex}, this.value)"
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    <option value="">Выберите поле</option>
                    ${fields.map(field => `
                        <option value="${field.code}" 
                                data-type="${field.type}"
                                data-required="${field.isRequired}">
                            ${field.title} ${field.isRequired ? '*' : ''}
                        </option>
                    `).join('')}
                </select>
            </div>
            
            <div style="flex: 1;">
                <label style="font-size: 12px; color: #6b7280;">Источник данных</label>
                <select name="actions[${actionIndex}][field_mapping][${mappingIndex}][source_type]" 
                        onchange="toggleValueInput(${actionIndex}, ${mappingIndex}, this.value)"
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    <option value="parameter">Из параметра функции</option>
                    <option value="static">Статичное значение</option>
                    <option value="dynamic">Динамическое значение</option>
                    <option value="conversation">Из диалога</option>
                </select>
            </div>
            
            <div style="flex: 1;" id="valueInput_${actionIndex}_${mappingIndex}">
                <label style="font-size: 12px; color: #6b7280;">Значение</label>
                <select name="actions[${actionIndex}][field_mapping][${mappingIndex}][value]" 
                        class="parameter-select"
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    <option value="">Выберите параметр</option>
                    ${parameters.map(param => `
                        <option value="{${param.code}}">${param.name} (${param.code})</option>
                    `).join('')}
                </select>
            </div>
            
            <div style="padding-top: 20px;">
                <button type="button" onclick="removeFieldMapping(this)" 
                        style="padding: 8px; color: #ef4444; background: white; border: 1px solid #ef4444; border-radius: 4px;">
                    ✕
                </button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', mappingHtml);
}

// Функция переключения типа значения
function toggleValueInput(actionIndex, mappingIndex, sourceType) {
    const valueDiv = document.getElementById(`valueInput_${actionIndex}_${mappingIndex}`);
    const parameters = getAvailableParameters();
    
    switch(sourceType) {
        case 'static':
            valueDiv.innerHTML = `
                <label style="font-size: 12px; color: #6b7280;">Значение</label>
                <input type="text" 
                       name="actions[${actionIndex}][field_mapping][${mappingIndex}][value]"
                       placeholder="Введите значение"
                       style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
            `;
            break;
            
        case 'parameter':
            valueDiv.innerHTML = `
                <label style="font-size: 12px; color: #6b7280;">Параметр</label>
                <select name="actions[${actionIndex}][field_mapping][${mappingIndex}][value]"
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    <option value="">Выберите параметр</option>
                    ${parameters.map(param => `
                        <option value="{${param.code}}">${param.name} (${param.code})</option>
                    `).join('')}
                </select>
            `;
            break;
            
        case 'dynamic':
            valueDiv.innerHTML = `
                <label style="font-size: 12px; color: #6b7280;">Выражение</label>
                <input type="text" 
                       name="actions[${actionIndex}][field_mapping][${mappingIndex}][value]"
                       placeholder="Например: Заказ от {current_date}"
                       style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
            `;
            break;
            
        case 'conversation':
            valueDiv.innerHTML = `
                <label style="font-size: 12px; color: #6b7280;">Данные из диалога</label>
                <select name="actions[${actionIndex}][field_mapping][${mappingIndex}][value]"
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    <option value="{conversation.id}">ID диалога</option>
                    <option value="{conversation.user_name}">Имя пользователя</option>
                    <option value="{conversation.user_email}">Email пользователя</option>
                    <option value="{conversation.user_phone}">Телефон пользователя</option>
                    <option value="{conversation.messages_count}">Количество сообщений</option>
                    <option value="{conversation.channel}">Канал</option>
                    <option value="{conversation.created_at}">Дата создания</option>
                </select>
            `;
            break;
    }
}
// Удаление маппинга поля
function removeFieldMapping(button) {
    button.closest('.field-mapping-item').remove();
}

// Получение доступных параметров
function getAvailableParameters() {
    const parameters = [];
    document.querySelectorAll('[name^="parameters["][name*="[code]"]').forEach(input => {
        if (input.value) {
            const index = input.name.match(/\[(\d+)\]/)[1];
            const nameInput = document.querySelector(`[name="parameters[${index}][name]"]`);
            parameters.push({
                code: input.value,
                name: nameInput ? nameInput.value : input.value
            });
        }
    });
    return parameters;
}

// Загрузка статусов лидов
async function loadLeadStatuses(provider, index) {
    try {
        const response = await fetch(`/api/crm/${provider}/lead-statuses`, {
            headers: {
                'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        const select = document.getElementById(`leadStatus_${index}`);
        
        select.innerHTML = '<option value="">По умолчанию</option>';
        data.statuses.forEach(status => {
            select.innerHTML += `<option value="${status.STATUS_ID}">${status.NAME}</option>`;
        });
    } catch (error) {
        console.error('Error loading statuses:', error);
    }
}

// Загрузка пользователей CRM
async function loadCRMUsers(provider, index) {
    try {
        const response = await fetch(`/api/crm/${provider}/users`, {
            headers: {
                'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        const select = document.getElementById(`assignedUser_${index}`);
        
        select.innerHTML = '<option value="">По умолчанию</option>';
        data.users.forEach(user => {
            select.innerHTML += `<option value="${user.ID}">${user.NAME} ${user.LAST_NAME}</option>`;
        });
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Поля по умолчанию если не удалось загрузить
function getDefaultFields(entityType) {
    const commonFields = {
        lead: [
            { code: 'TITLE', title: 'Название', type: 'string', isRequired: true },
            { code: 'NAME', title: 'Имя', type: 'string', isRequired: false },
            { code: 'LAST_NAME', title: 'Фамилия', type: 'string', isRequired: false },
            { code: 'PHONE', title: 'Телефон', type: 'phone', isRequired: false, isMultiple: true },
            { code: 'EMAIL', title: 'Email', type: 'email', isRequired: false, isMultiple: true },
            { code: 'COMMENTS', title: 'Комментарий', type: 'text', isRequired: false },
            { code: 'SOURCE_ID', title: 'Источник', type: 'select', isRequired: false },
            { code: 'STATUS_ID', title: 'Стадия', type: 'select', isRequired: false },
            { code: 'ASSIGNED_BY_ID', title: 'Ответственный', type: 'user', isRequired: false }
        ],
        deal: [
            { code: 'TITLE', title: 'Название', type: 'string', isRequired: true },
            { code: 'STAGE_ID', title: 'Стадия', type: 'select', isRequired: false },
            { code: 'CATEGORY_ID', title: 'Воронка', type: 'select', isRequired: false },
            { code: 'OPPORTUNITY', title: 'Сумма', type: 'money', isRequired: false },
            { code: 'CURRENCY_ID', title: 'Валюта', type: 'select', isRequired: false },
            { code: 'ASSIGNED_BY_ID', title: 'Ответственный', type: 'user', isRequired: false },
            { code: 'CONTACT_ID', title: 'Контакт', type: 'relation', isRequired: false },
            { code: 'COMPANY_ID', title: 'Компания', type: 'relation', isRequired: false },
            { code: 'COMMENTS', title: 'Комментарий', type: 'text', isRequired: false }
        ],
        contact: [
            { code: 'NAME', title: 'Имя', type: 'string', isRequired: false },
            { code: 'LAST_NAME', title: 'Фамилия', type: 'string', isRequired: false },
            { code: 'SECOND_NAME', title: 'Отчество', type: 'string', isRequired: false },
            { code: 'PHONE', title: 'Телефон', type: 'phone', isRequired: false, isMultiple: true },
            { code: 'EMAIL', title: 'Email', type: 'email', isRequired: false, isMultiple: true },
            { code: 'POST', title: 'Должность', type: 'string', isRequired: false },
            { code: 'COMPANY_TITLE', title: 'Компания', type: 'string', isRequired: false },
            { code: 'TYPE_ID', title: 'Тип контакта', type: 'select', isRequired: false },
            { code: 'ASSIGNED_BY_ID', title: 'Ответственный', type: 'user', isRequired: false }
        ],
        task: [
            { code: 'TITLE', title: 'Название', type: 'string', isRequired: true },
            { code: 'DESCRIPTION', title: 'Описание', type: 'text', isRequired: false },
            { code: 'RESPONSIBLE_ID', title: 'Ответственный', type: 'user', isRequired: true },
            { code: 'PRIORITY', title: 'Приоритет', type: 'select', isRequired: false },
            { code: 'DEADLINE', title: 'Крайний срок', type: 'datetime', isRequired: false }
        ]
    };
    
    return commonFields[entityType] || [];
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
