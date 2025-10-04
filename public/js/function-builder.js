// public/js/function-builder.js
class FunctionBuilder {
    constructor() {
        this.functionData = {
            name: '',
            display_name: '',
            description: '',
            trigger_type: 'keyword',
            trigger_keywords: [],
            parameters: [],
            actions: [],
            behavior: {
                on_success: 'continue',
                on_error: 'continue',
                success_message: '',
                error_message: ''
            }
        };
        
        this.parameterIndex = 0;
        this.actionIndex = 0;
        
        this.init();
    }
    
    init() {
        const container = document.getElementById('function-builder');
        if (!container) return;
        
        this.botId = container.dataset.botId;
        this.organization = container.dataset.organization;
        this.submitUrl = container.dataset.submitUrl;
        this.csrf = container.dataset.csrf;
        
        this.render(container);
        this.attachEventListeners();
    }
    
    render(container) {
        container.innerHTML = `
            <form id="function-form" class="space-y-6">
                <!-- Основная информация -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Основная информация</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Код функции (латиница и _) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="function_name"
                                   pattern="[a-z_]+"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="check_order_status"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Название для отображения <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="display_name"
                                   id="display_name" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="Проверка статуса заказа"
                                   required>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Описание
                        </label>
                        <textarea name="description"
                                  id="description"
                                  rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                  placeholder="Функция проверяет статус заказа в базе данных"></textarea>
                    </div>
                </div>
                
                <!-- Триггеры -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Триггер срабатывания</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Тип триггера
                        </label>
                        <select name="trigger_type" 
                                id="trigger_type"
                                onchange="functionBuilder.toggleTriggerOptions(this.value)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="keyword">Ключевые слова</option>
                            <option value="auto">Автоматический (AI)</option>
                            <option value="manual">Ручной запуск</option>
                        </select>
                    </div>
                    
                    <div id="keyword_options" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Ключевые слова (через запятую)
                        </label>
                        <input type="text" 
                               id="trigger_keywords_input"
                               placeholder="статус заказа, где мой заказ, отследить"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="mt-1 text-sm text-gray-500">
                            Функция сработает при обнаружении этих слов в сообщении
                        </p>
                    </div>
                </div>
                
                <!-- Параметры -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Параметры функции</h3>
                        <button type="button" 
                                onclick="functionBuilder.addParameter()"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm">
                            + Добавить параметр
                        </button>
                    </div>
                    <div id="parameters_container">
                        <!-- Параметры будут добавлены здесь -->
                    </div>
                </div>
                
                <!-- Действия -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Действия</h3>
                        <button type="button" 
                                onclick="functionBuilder.addAction()"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                            + Добавить действие
                        </button>
                    </div>
                    <div id="actions_container">
                        <!-- Действия будут добавлены здесь -->
                    </div>
                </div>
                
                <!-- Поведение после выполнения -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Поведение после выполнения</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                При успехе
                            </label>
                            <select name="behavior[on_success]" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="continue">Продолжить диалог</option>
                                <option value="pause">Поставить на паузу</option>
                                <option value="enhance_prompt">Дополнить промпт</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                При ошибке
                            </label>
                            <select name="behavior[on_error]" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="continue">Продолжить диалог</option>
                                <option value="pause">Поставить на паузу</option>
                                <option value="notify">Уведомить администратора</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Сообщение при успехе
                        </label>
                        <input type="text" 
                               name="behavior[success_message]"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               placeholder="✅ Действие выполнено успешно">
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Сообщение при ошибке
                        </label>
                        <input type="text" 
                               name="behavior[error_message]"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               placeholder="❌ Произошла ошибка при выполнении">
                    </div>
                </div>
                
                <!-- Кнопки отправки -->
                <div class="flex justify-center gap-4">
                    <button type="submit" 
                            class="px-6 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium">
                        Создать функцию
                    </button>
                    <a href="/bots/${this.botId}" 
                       class="px-6 py-3 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 font-medium">
                        Отмена
                    </a>
                </div>
            </form>
        `;
        
        // Добавляем первый параметр и действие по умолчанию
        this.addParameter();
        this.addAction();
    }
    
    addParameter() {
        const container = document.getElementById('parameters_container');
        const index = this.parameterIndex++;
        
        const paramHtml = `
            <div class="parameter-item border border-gray-200 rounded-md p-4 mb-4" id="param_${index}">
                <div class="flex justify-between items-start mb-3">
                    <h4 class="text-sm font-medium text-gray-700">Параметр #${index + 1}</h4>
                    <button type="button" 
                            onclick="functionBuilder.removeParameter(${index})"
                            class="text-red-500 hover:text-red-700 text-sm">
                        Удалить
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Код</label>
                        <input type="text" 
                               name="parameters[${index}][code]"
                               pattern="[a-z_]+"
                               class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                               placeholder="order_id"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Название</label>
                        <input type="text" 
                               name="parameters[${index}][name]"
                               class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                               placeholder="Номер заказа"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Тип</label>
                        <select name="parameters[${index}][type]" 
                                class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="string">Строка</option>
                            <option value="number">Число</option>
                            <option value="boolean">Да/Нет</option>
                            <option value="date">Дата</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Описание</label>
                    <input type="text" 
                           name="parameters[${index}][description]"
                           class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                           placeholder="Уникальный номер заказа">
                </div>
                <div class="mt-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" 
                               name="parameters[${index}][is_required]"
                               value="1"
                               class="rounded border-gray-300 text-indigo-600">
                        <span class="ml-2 text-sm text-gray-600">Обязательный параметр</span>
                    </label>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', paramHtml);
    }
    
    removeParameter(index) {
        const element = document.getElementById(`param_${index}`);
        if (element) {
            element.remove();
        }
    }
    
    addAction() {
        const container = document.getElementById('actions_container');
        const index = this.actionIndex++;
        
        const actionHtml = `
            <div class="action-item border border-gray-200 rounded-md p-4 mb-4" id="action_${index}">
                <div class="flex justify-between items-start mb-3">
                    <h4 class="text-sm font-medium text-gray-700">Действие #${index + 1}</h4>
                    <button type="button" 
                            onclick="functionBuilder.removeAction(${index})"
                            class="text-red-500 hover:text-red-700 text-sm">
                        Удалить
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Тип действия</label>
                        <select name="actions[${index}][type]" 
                                onchange="functionBuilder.updateActionConfig(${index}, this.value)"
                                class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="">Выберите действие</option>
                            <option value="create_lead">Создать лид в CRM</option>
                            <option value="send_email">Отправить Email</option>
                            <option value="webhook">Webhook запрос</option>
                            <option value="database">Запрос к БД</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Провайдер</label>
                        <select name="actions[${index}][provider]" 
                                class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="bitrix24">Bitrix24</option>
                            <option value="amocrm">amoCRM</option>
                            <option value="custom">Другой</option>
                        </select>
                    </div>
                </div>
                <div id="action_config_${index}" class="mt-3">
                    <!-- Конфигурация действия -->
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', actionHtml);
    }
    
    removeAction(index) {
        const element = document.getElementById(`action_${index}`);
        if (element) {
            element.remove();
        }
    }
    
    updateActionConfig(index, type) {
        const configContainer = document.getElementById(`action_config_${index}`);
        
        let configHtml = '';
        
        switch(type) {
            case 'create_lead':
                configHtml = `
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Название лида</label>
                            <input type="text" 
                                   name="actions[${index}][config][title]"
                                   class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                   placeholder="Новый лид от бота">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Статус</label>
                            <select name="actions[${index}][config][status_id]" 
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                <option value="NEW">Новый</option>
                                <option value="IN_PROCESS">В работе</option>
                                <option value="PROCESSED">Обработан</option>
                            </select>
                        </div>
                    </div>
                `;
                break;
                
            case 'webhook':
                configHtml = `
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">URL</label>
                            <input type="url" 
                                   name="actions[${index}][config][url]"
                                   class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                   placeholder="https://example.com/webhook">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Метод</label>
                            <select name="actions[${index}][config][method]" 
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                            </select>
                        </div>
                    </div>
                `;
                break;
        }
        
        configContainer.innerHTML = configHtml;
    }
    
    toggleTriggerOptions(type) {
        const keywordOptions = document.getElementById('keyword_options');
        keywordOptions.style.display = type === 'keyword' ? 'block' : 'none';
    }
    
    attachEventListeners() {
        const form = document.getElementById('function-form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm();
        });
    }
    
    submitForm() {
        const form = document.getElementById('function-form');
        const formData = new FormData(form);
        
        // Добавляем ключевые слова
        const keywordsInput = document.getElementById('trigger_keywords_input');
        if (keywordsInput && keywordsInput.value) {
            const keywords = keywordsInput.value.split(',').map(k => k.trim());
            keywords.forEach((keyword, i) => {
                formData.append(`trigger_keywords[${i}]`, keyword);
            });
        }
        
        // Отправка формы
        fetch(this.submitUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrf,
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => {
            if (response.ok) {
                return response.json();
            }
            throw new Error('Ошибка при создании функции');
        })
        .then(data => {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                alert('Функция создана успешно!');
                window.location.href = `/organizations/${this.organization}/bots/${this.botId}`;
            }
        })
        .catch(error => {
            alert('Ошибка: ' + error.message);
        });
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.functionBuilder = new FunctionBuilder();
});