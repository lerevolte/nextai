{{-- resources/views/bots/create.blade.php --}}
@extends('layouts.app')

@section('title', isset($bot) ? 'Редактировать бота' : 'Создать бота')

@section('content')
<div style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h2 style="font-size: 24px; margin-bottom: 20px;">
        {{ isset($bot) ? 'Редактировать бота' : 'Создать нового бота' }}
    </h2>

    @if ($errors->any())
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" 
          action="{{ isset($bot) ? route('bots.update', [$organization, $bot]) : route('bots.store', $organization) }}" 
          style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @csrf
        @if(isset($bot))
            @method('PUT')
        @endif

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название бота *</label>
            <input type="text" name="name" value="{{ old('name', $bot->name ?? '') }}" required
                   style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
            <small style="color: #6b7280;">Название для идентификации бота в системе</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Описание</label>
            <textarea name="description" rows="3"
                      style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">{{ old('description', $bot->description ?? '') }}</textarea>
            <small style="color: #6b7280;">Краткое описание назначения бота</small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">AI Провайдер *</label>
                <select name="ai_provider" id="ai_provider" required onchange="updateModels()"
                        style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <option value="">Выберите провайдера</option>
                    <option value="openai" {{ old('ai_provider', $bot->ai_provider ?? '') == 'openai' ? 'selected' : '' }}>
                        OpenAI (ChatGPT)
                    </option>
                    <option value="gemini" {{ old('ai_provider', $bot->ai_provider ?? '') == 'gemini' ? 'selected' : '' }}>
                        Google Gemini
                    </option>
                    <option value="deepseek" {{ old('ai_provider', $bot->ai_provider ?? '') == 'deepseek' ? 'selected' : '' }}>
                        DeepSeek
                    </option>
                </select>
                <small style="color: #6b7280;">Выберите AI провайдера для бота</small>
            </div>

            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Модель *</label>
                <select name="ai_model" id="ai_model" required
                        style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <option value="">Сначала выберите провайдера</option>
                </select>
                <small style="color: #6b7280;">Конкретная модель AI</small>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Системный промпт *</label>
            <textarea name="system_prompt" rows="5" required
                      style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;"
                      placeholder="Например: Ты дружелюбный консультант интернет-магазина. Помогаешь клиентам с выбором товаров, отвечаешь на вопросы о доставке и оплате. Всегда вежлив и стараешься решить проблему клиента.">{{ old('system_prompt', $bot->system_prompt ?? 'Ты дружелюбный помощник. Отвечай кратко и по существу на русском языке.') }}</textarea>
            <small style="color: #6b7280;">Это определяет характер и поведение бота. Опишите, кто он и как должен отвечать</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Приветственное сообщение</label>
            <textarea name="welcome_message" rows="2"
                      style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;"
                      placeholder="Например: Здравствуйте! Я виртуальный консультант магазина TechStore. Чем могу помочь?">{{ old('welcome_message', $bot->welcome_message ?? 'Здравствуйте! Я виртуальный помощник. Чем могу помочь?') }}</textarea>
            <small style="color: #6b7280;">Первое сообщение, которое увидит пользователь</small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Temperature (креативность)</label>
                <input type="range" name="temperature" id="temperature" 
                       value="{{ old('temperature', $bot->temperature ?? 0.7) }}" 
                       min="0" max="2" step="0.1"
                       oninput="updateTemperatureValue(this.value)"
                       style="width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                    <small style="color: #6b7280;">Точные ответы</small>
                    <span id="temperature-value" style="font-weight: bold;">{{ old('temperature', $bot->temperature ?? 0.7) }}</span>
                    <small style="color: #6b7280;">Креативные ответы</small>
                </div>
                <small style="color: #6b7280;">0 - максимально точные и однообразные ответы<br>2 - максимально креативные и разнообразные</small>
            </div>

            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Max Tokens (длина ответа)</label>
                <select name="max_tokens" id="max_tokens"
                        style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <option value="150" {{ old('max_tokens', $bot->max_tokens ?? 500) == 150 ? 'selected' : '' }}>
                        Очень короткие (~50 слов)
                    </option>
                    <option value="300" {{ old('max_tokens', $bot->max_tokens ?? 500) == 300 ? 'selected' : '' }}>
                        Короткие (~100 слов)
                    </option>
                    <option value="500" {{ old('max_tokens', $bot->max_tokens ?? 500) == 500 ? 'selected' : '' }}>
                        Средние (~170 слов) - Рекомендуется
                    </option>
                    <option value="1000" {{ old('max_tokens', $bot->max_tokens ?? 500) == 1000 ? 'selected' : '' }}>
                        Длинные (~350 слов)
                    </option>
                    <option value="2000" {{ old('max_tokens', $bot->max_tokens ?? 500) == 2000 ? 'selected' : '' }}>
                        Очень длинные (~700 слов)
                    </option>
                    <option value="4000" {{ old('max_tokens', $bot->max_tokens ?? 500) == 4000 ? 'selected' : '' }}>
                        Максимальные (~1400 слов)
                    </option>
                </select>
                <small style="color: #6b7280;">Токены ≈ количество слов × 3. Влияет на стоимость</small>
            </div>
        </div>

        @if(isset($bot))
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" 
                       {{ old('is_active', $bot->is_active ?? true) ? 'checked' : '' }}
                       style="margin-right: 8px;">
                <span style="font-weight: 500;">Бот активен</span>
            </label>
        </div>
        @endif

        <div style="background: #f3f4f6; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 10px;">💡 Советы по настройке:</h4>
            <ul style="margin: 0; padding-left: 20px; color: #4b5563; font-size: 14px;">
                <li><strong>Системный промпт</strong> - самая важная настройка. Чем подробнее опишете роль и правила, тем лучше будет работать бот</li>
                <li><strong>Temperature</strong> - для техподдержки используйте 0.3-0.5, для творческих задач 0.7-1.0</li>
                <li><strong>Max Tokens</strong> - чем больше значение, тем дороже каждый ответ. Для чат-ботов обычно достаточно 500 токенов</li>
            </ul>
        </div>

        <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bfdbfe;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #1e40af;">
                📚 База знаний
            </h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer; font-weight: 500;">
                    <input type="checkbox" name="knowledge_base_enabled" value="1" 
                           {{ old('knowledge_base_enabled', $bot->knowledge_base_enabled ?? false) ? 'checked' : '' }}
                           onchange="toggleKnowledgeBaseInfo(this)"
                           style="width: 20px; height: 20px; margin-right: 10px;">
                    <span>Использовать базу знаний</span>
                </label>
                <small style="color: #6b7280; display: block; margin-top: 5px; margin-left: 30px;">
                    Бот будет использовать информацию из базы знаний для более точных ответов
                </small>
            </div>

            <div id="knowledge-base-info" style="display: {{ old('knowledge_base_enabled', $bot->knowledge_base_enabled ?? false) ? 'block' : 'none' }}; margin-top: 15px; padding: 15px; background: white; border-radius: 6px;">
                @if(isset($bot) && $bot->knowledgeBase)
                    <p style="margin-bottom: 10px;">
                        <strong>Статус базы знаний:</strong>
                        <span style="color: #10b981;">✓ Активна</span>
                    </p>
                    <p style="margin-bottom: 10px;">
                        <strong>Материалов:</strong> {{ $bot->knowledgeBase->getItemsCount() }}
                        (активных: {{ $bot->knowledgeBase->getActiveItemsCount() }})
                    </p>
                    <p style="margin-bottom: 15px;">
                        <strong>Объем:</strong> {{ number_format($bot->knowledgeBase->getTotalCharacters()) }} символов
                    </p>
                    
                    <a href="{{ route('knowledge.index', [$organization, $bot]) }}" 
                       style="display: inline-block; padding: 8px 16px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; font-size: 14px;">
                        Управление базой знаний →
                    </a>
                @else
                    <p style="color: #6b7280;">
                        После создания бота вы сможете добавить материалы в базу знаний
                    </p>
                @endif
            </div>

            <!-- <div style="margin-top: 15px; padding: 15px; background: #fef3c7; border-radius: 6px;">
                <p style="margin: 0; color: #92400e; font-size: 14px;">
                    <strong>⚠️ Важно:</strong> Для работы базы знаний с векторным поиском требуется API ключ OpenAI 
                    (даже если бот использует другого провайдера). Эмбеддинги генерируются через модель text-embedding-ada-002.
                </p>
            </div> -->
        </div>

        <script>
        function toggleKnowledgeBaseInfo(checkbox) {
            const infoBlock = document.getElementById('knowledge-base-info');
            if (checkbox.checked) {
                infoBlock.style.display = 'block';
            } else {
                infoBlock.style.display = 'none';
            }
        }
        </script>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="{{ route('bots.index', $organization) }}" 
               style="padding: 10px 20px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 5px;">
                Отмена
            </a>
            <button type="submit" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 5px; cursor: pointer;">
                {{ isset($bot) ? 'Сохранить изменения' : 'Создать бота' }}
            </button>
        </div>
    </form>
</div>

<script>
// Конфигурация моделей для каждого провайдера
const models = {
    openai: [
        { value: 'gpt-4o', name: 'GPT-4o (Самая умная, дорогая)', description: 'Лучшее качество ответов' },
        { value: 'gpt-4o-mini', name: 'GPT-4o Mini (Оптимальная)', description: 'Баланс цены и качества' },
        { value: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Предыдущее поколение' },
        { value: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo (Быстрая, дешевая)', description: 'Для простых задач' },
    ],
    gemini: [
        { value: 'gemini-2.5-flash-lite', name: 'Gemini 2.5 Flash Lite', description: 'Быстрая и недорогаяСамая мощная' },
        { value: 'gemini-2.5-pro', name: 'Gemini 2.5 Pro', description: 'Самая мощная модель' },
        { value: 'gemini-2.5-flash', name: 'Gemini 2.5 Flash', description: 'Стандартная модель' },
    ],
    deepseek: [
        { value: 'deepseek-chat', name: 'DeepSeek Chat', description: 'Основная модель' },
        { value: 'deepseek-coder', name: 'DeepSeek Coder', description: 'Для программирования' },
    ]
};

function updateModels() {
    const provider = document.getElementById('ai_provider').value;
    const modelSelect = document.getElementById('ai_model');
    const currentValue = modelSelect.value || '{{ old("ai_model", $bot->ai_model ?? "") }}';
    
    // Очищаем список моделей
    modelSelect.innerHTML = '';
    
    if (!provider) {
        modelSelect.innerHTML = '<option value="">Сначала выберите провайдера</option>';
        return;
    }
    
    // Добавляем модели для выбранного провайдера
    const providerModels = models[provider] || [];
    
    if (providerModels.length === 0) {
        modelSelect.innerHTML = '<option value="">Нет доступных моделей</option>';
        return;
    }
    
    providerModels.forEach(model => {
        const option = document.createElement('option');
        option.value = model.value;
        option.textContent = model.name;
        option.title = model.description;
        
        // Восстанавливаем выбранное значение
        if (model.value === currentValue) {
            option.selected = true;
        }
        
        modelSelect.appendChild(option);
    });
    
    // Если ничего не выбрано, выбираем первую модель
    if (!modelSelect.value && providerModels.length > 0) {
        modelSelect.value = providerModels[0].value;
    }
}

function updateTemperatureValue(value) {
    document.getElementById('temperature-value').textContent = value;
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    updateModels();
});
</script>
@endsection