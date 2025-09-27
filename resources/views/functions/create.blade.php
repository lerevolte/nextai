@extends('layouts.app')

@section('title', 'Создать функцию')

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h2 style="font-size: 24px; margin-bottom: 20px;">Создание функции для бота</h2>

    <form id="functionForm" method="POST" action="{{ route('functions.store', [$organization, $bot]) }}">
        @csrf
        
        <!-- Основная информация -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3>Основная информация</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Код функции (английский)</label>
                    <input type="text" name="name" pattern="[a-z_]+" required
                           placeholder="create_lead">
                </div>
                <div>
                    <label>Название для отображения</label>
                    <input type="text" name="display_name" required
                           placeholder="Создание лида в CRM">
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <label>Описание</label>
                <textarea name="description" rows="3"
                          placeholder="Функция извлекает контактные данные из диалога и создает лид в Битрикс24"></textarea>
            </div>
            
            <div style="margin-top: 20px;">
                <label>Когда запускать функцию</label>
                <select name="trigger_type" onchange="toggleTriggerKeywords(this)">
                    <option value="auto">Автоматически при обнаружении данных</option>
                    <option value="keyword">По ключевым словам</option>
                    <option value="manual">Только вручную</option>
                </select>
            </div>
            
            <div id="triggerKeywords" style="display: none; margin-top: 10px;">
                <label>Ключевые слова (через запятую)</label>
                <input type="text" name="trigger_keywords_text" 
                       placeholder="создать лид, сохранить контакт, добавить в CRM">
            </div>
        </div>

        <!-- Параметры -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3>Параметры функции</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Определите данные, которые бот должен извлекать из диалога
            </p>
            
            <div id="parametersContainer"></div>
            
            <button type="button" onclick="addParameter()" 
                    style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 5px;">
                + Добавить параметр
            </button>
        </div>

        <!-- Действия -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3>Действия</h3>
            
            <div id="actionsContainer"></div>
            
            <button type="button" onclick="addAction()" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 5px;">
                + Добавить действие
            </button>
        </div>

        <!-- Поведение после выполнения -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3>Поведение после выполнения</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>При успешном выполнении</label>
                    <select name="behavior[on_success]">
                        <option value="continue">Продолжить диалог</option>
                        <option value="pause">Поставить на паузу</option>
                        <option value="enhance_prompt">Дополнить промпт</option>
                    </select>
                </div>
                <div>
                    <label>При ошибке</label>
                    <select name="behavior[on_error]">
                        <option value="continue">Продолжить диалог</option>
                        <option value="pause">Поставить на паузу</option>
                        <option value="notify">Уведомить администратора</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <label>Сообщение при успехе</label>
                <input type="text" name="behavior[success_message]" 
                       placeholder="✓ Лид #{lead_id} успешно создан">
            </div>
            
            <div style="margin-top: 20px;">
                <label>Сообщение при ошибке</label>
                <input type="text" name="behavior[error_message]" 
                       placeholder="Не удалось создать лид: {error}">
            </div>
            
            <div style="margin-top: 20px;" id="promptEnhancement" style="display: none;">
                <label>Дополнение к промпту</label>
                <textarea name="behavior[prompt_enhancement]" rows="3"
                          placeholder="Лид создан. Теперь помоги клиенту выбрать подходящий тариф."></textarea>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="padding: 12px 30px; background: #6366f1; color: white; border: none; border-radius: 5px;">
                Создать функцию
            </button>
            <a href="{{ route('functions.index', [$organization, $bot]) }}" 
               style="padding: 12px 30px; background: #e5e7eb; color: #374151; text-decoration: none; border-radius: 5px;">
                Отмена
            </a>
        </div>
    </form>
</div>

<script>
let parameterIndex = 0;
let actionIndex = 0;

function addParameter() {
    const container = document.getElementById('parametersContainer');
    const html = `
        <div class="parameter-item" style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h4>Параметр #${parameterIndex + 1}</h4>
                <button type="button" onclick="removeParameter(this)" style="color: red;">Удалить</button>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div>
                    <label>Код параметра</label>
                    <input type="text" name="parameters[${parameterIndex}][code]" 
                           pattern="[a-z_]+" required placeholder="client_name">
                </div>
                <div>
                    <label>Название</label>
                    <input type="text" name="parameters[${parameterIndex}][name]" 
                           required placeholder="Имя клиента">
                </div>
                <div>
                    <label>Тип</label>
                    <select name="parameters[${parameterIndex}][type]" required>
                        <option value="string">Текстовый</option>
                        <option value="number">Числовой</option>
                        <option value="boolean">Логический</option>
                        <option value="date">Дата</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 15px;">
                <label>Что искать в диалоге (подсказка для AI)</label>
                <input type="text" name="parameters[${parameterIndex}][description]" 
                       placeholder="Имя человека, с которым общается бот">
            </div>
            
            <div style="margin-top: 15px;">
                <label>
                    <input type="checkbox" name="parameters[${parameterIndex}][is_required]" value="1">
                    Обязательный параметр
                </label>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    parameterIndex++;
}

function removeParameter(button) {
    button.closest('.parameter-item').remove();
}

function addAction() {
    const container = document.getElementById('actionsContainer');
    const html = `
        <div class="action-item" style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h4>Действие #${actionIndex + 1}</h4>
                <button type="button" onclick="removeAction(this)" style="color: red;">Удалить</button>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label>Провайдер</label>
                    <select name="actions[${actionIndex}][provider]" onchange="updateActionType(this, ${actionIndex})">
                        <option value="">Выберите провайдера</option>
                        @foreach($crmIntegrations as $crm)
                            <option value="{{ $crm->type }}">{{ $crm->name }}</option>
                        @endforeach
                        <option value="webhook">Webhook</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div>
                    <label>Тип действия</label>
                    <select name="actions[${actionIndex}][type]" id="actionType_${actionIndex}">
                        <option value="">Сначала выберите провайдера</option>
                    </select>
                </div>
            </div>
            
            <div id="actionConfig_${actionIndex}" style="margin-top: 15px;">
                <!-- Здесь будет динамическая конфигурация -->
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    actionIndex++;
}

function removeAction(button) {
    button.closest('.action-item').remove();
}

function updateActionType(select, index) {
    const provider = select.value;
    const typeSelect = document.getElementById(`actionType_${index}`);
    const configDiv = document.getElementById(`actionConfig_${index}`);
    
    // Очищаем типы действий
    typeSelect.innerHTML = '<option value="">Выберите действие</option>';
    
    // Добавляем действия в зависимости от провайдера
    const actions = {
        'bitrix24': [
            {value: 'create_lead', text: 'Создать лид'},
            {value: 'create_deal', text: 'Создать сделку'},
            {value: 'create_contact', text: 'Создать контакт'},
            {value: 'create_task', text: 'Создать задачу'},
        ],
        'webhook': [
            {value: 'post', text: 'POST запрос'},
            {value: 'get', text: 'GET запрос'},
        ],
        'email': [
            {value: 'send', text: 'Отправить письмо'},
        ]
    };
    
    if (actions[provider]) {
        actions[provider].forEach(action => {
            const option = document.createElement('option');
            option.value = action.value;
            option.textContent = action.text;
            typeSelect.appendChild(option);
        });
    }
    
    // Обновляем конфигурацию при изменении типа
    typeSelect.onchange = function() {
        showActionConfig(provider, this.value, index);
    };
}

function showActionConfig(provider, type, index) {
    const configDiv = document.getElementById(`actionConfig_${index}`);
    
    if (provider === 'bitrix24' && type === 'create_lead') {
        configDiv.innerHTML = `
            <h5>Маппинг полей лида</h5>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <label>Название лида</label>
                    <select name="actions[${index}][config][title_source]">
                        <option value="static">Статичное значение</option>
                        <option value="parameter">Из параметра</option>
                    </select>
                    <input type="text" name="actions[${index}][config][title]" placeholder="Лид из чат-бота">
                </div>
                <div>
                    <label>Имя клиента</label>
                    <select name="actions[${index}][config][name_source]">
                        <option value="parameter">Из параметра</option>
                        <option value="static">Статичное значение</option>
                    </select>
                    <select name="actions[${index}][field_mapping][name]" class="parameter-select">
                        <option value="">Выберите параметр</option>
                    </select>
                </div>
                <div>
                    <label>Email</label>
                    <select name="actions[${index}][config][email_source]">
                        <option value="parameter">Из параметра</option>
                        <option value="static">Статичное значение</option>
                    </select>
                    <select name="actions[${index}][field_mapping][email]" class="parameter-select">
                        <option value="">Выберите параметр</option>
                    </select>
                </div>
                <div>
                    <label>Телефон</label>
                    <select name="actions[${index}][config][phone_source]">
                        <option value="parameter">Из параметра</option>
                        <option value="static">Статичное значение</option>
                    </select>
                    <select name="actions[${index}][field_mapping][phone]" class="parameter-select">
                        <option value="">Выберите параметр</option>
                    </select>
                </div>
                <div>
                    <label>Стадия лида</label>
                    <select name="actions[${index}][config][status_id]">
                        <option value="NEW">Новый</option>
                        <option value="IN_PROCESS">В работе</option>
                        <option value="PROCESSED">Обработан</option>
                    </select>
                </div>
                <div>
                    <label>Ответственный</label>
                    <select name="actions[${index}][config][assigned_by_id]">
                        <option value="1">По умолчанию</option>
                        <!-- Загрузить пользователей из CRM -->
                    </select>
                </div>
            </div>
        `;
        updateParameterSelects();
    }
}

function updateParameterSelects() {
    // Обновляем все селекты параметров
    const selects = document.querySelectorAll('.parameter-select');
    const parameters = document.querySelectorAll('[name^="parameters["][name*="[code]"]');
    
    selects.forEach(select => {
        // Сохраняем текущее значение
        const currentValue = select.value;
        
        // Очищаем и заполняем заново
        select.innerHTML = '<option value="">Выберите параметр</option>';
        
        parameters.forEach(param => {
            if (param.value) {
                const option = document.createElement('option');
                option.value = param.value;
                option.textContent = param.value;
                select.appendChild(option);
            }
        });
        
        // Восстанавливаем значение
        select.value = currentValue;
    });
}

function toggleTriggerKeywords(select) {
    const keywordsDiv = document.getElementById('triggerKeywords');
    keywordsDiv.style.display = select.value === 'keyword' ? 'block' : 'none';
}

// Преобразование ключевых слов перед отправкой формы
document.getElementById('functionForm').onsubmit = function(e) {
    const keywordsText = document.querySelector('[name="trigger_keywords_text"]');
    if (keywordsText && keywordsText.value) {
        const keywords = keywordsText.value.split(',').map(k => k.trim()).filter(k => k);
        
        // Создаем скрытые поля для массива
        keywords.forEach((keyword, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `trigger_keywords[${index}]`;
            input.value = keyword;
            this.appendChild(input);
        });
    }
};

// Добавляем первый параметр при загрузке
document.addEventListener('DOMContentLoaded', function() {
    addParameter();
});
</script>
@endsection