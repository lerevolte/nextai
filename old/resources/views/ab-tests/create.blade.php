@extends('layouts.app')

@section('title', 'Создать A/B тест')

@section('content')
<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    <h1 style="font-size: 28px; font-weight: bold; color: #111827; margin-bottom: 30px;">
        Создать A/B тест
    </h1>

    @if ($errors->any())
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('ab-tests.store', $organization) }}">
        @csrf

        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Основные настройки</h2>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Бот *</label>
                    <select name="bot_id" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                        <option value="">Выберите бота</option>
                        @foreach($bots as $bot)
                            <option value="{{ $bot->id }}" {{ old('bot_id') == $bot->id ? 'selected' : '' }}>
                                {{ $bot->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Тип теста *</label>
                    <select name="type" id="test_type" required onchange="updateVariantFields()"
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                        <option value="">Выберите тип</option>
                        <option value="prompt" {{ old('type') == 'prompt' ? 'selected' : '' }}>Системный промпт</option>
                        <option value="temperature" {{ old('type') == 'temperature' ? 'selected' : '' }}>Temperature (креативность)</option>
                        <option value="model" {{ old('type') == 'model' ? 'selected' : '' }}>Модель AI</option>
                        <option value="welcome_message" {{ old('type') == 'welcome_message' ? 'selected' : '' }}>Приветственное сообщение</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название теста *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       placeholder="Например: Тест дружелюбного vs формального тона"
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
            </div>

            <div style="margin-top: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Описание</label>
                <textarea name="description" rows="2"
                          placeholder="Краткое описание цели теста"
                          style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">{{ old('description') }}</textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Трафик теста (%) *</label>
                    <input type="number" name="traffic_percentage" value="{{ old('traffic_percentage', 100) }}" 
                           min="1" max="100" required
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <small style="color: #6b7280;">% пользователей для теста</small>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Мин. выборка *</label>
                    <input type="number" name="min_sample_size" value="{{ old('min_sample_size', 100) }}" 
                           min="10" required
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <small style="color: #6b7280;">Мин. участников на вариант</small>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Уровень доверия (%) *</label>
                    <input type="number" name="confidence_level" value="{{ old('confidence_level', 95) }}" 
                           min="80" max="99" required
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <small style="color: #6b7280;">Стат. значимость</small>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Дата начала</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Дата окончания</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="auto_apply_winner" value="1" 
                           {{ old('auto_apply_winner') ? 'checked' : '' }}
                           style="margin-right: 8px;">
                    <span style="font-weight: 500;">Автоматически применить настройки победителя после завершения</span>
                </label>
            </div>
        </div>

        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Варианты теста</h2>
            
            <div id="variants-container">
                {{-- Контрольный вариант --}}
                <div class="variant-block" style="border: 2px solid #dbeafe; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <h3 style="font-size: 16px; font-weight: 600;">Вариант A (Контроль)</h3>
                        <span style="padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 12px; font-size: 12px;">
                            Контрольный
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название *</label>
                            <input type="text" name="variants[0][name]" value="{{ old('variants.0.name', 'Текущие настройки') }}" required
                                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Трафик (%) *</label>
                            <input type="number" name="variants[0][traffic_allocation]" value="{{ old('variants.0.traffic_allocation', 50) }}" 
                                   min="0" max="100" step="0.1" required class="traffic-allocation"
                                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Описание</label>
                        <input type="text" name="variants[0][description]" value="{{ old('variants.0.description') }}"
                               placeholder="Краткое описание варианта"
                               style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                    </div>
                    
                    <div class="variant-config" style="margin-top: 15px;">
                        {{-- Здесь будут динамические поля в зависимости от типа теста --}}
                    </div>
                </div>

                {{-- Тестовый вариант --}}
                <div class="variant-block" style="border: 2px solid #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Вариант B</h3>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название *</label>
                            <input type="text" name="variants[1][name]" value="{{ old('variants.1.name', 'Новый вариант') }}" required
                                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Трафик (%) *</label>
                            <input type="number" name="variants[1][traffic_allocation]" value="{{ old('variants.1.traffic_allocation', 50) }}" 
                                   min="0" max="100" step="0.1" required class="traffic-allocation"
                                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Описание</label>
                        <input type="text" name="variants[1][description]" value="{{ old('variants.1.description') }}"
                               placeholder="Краткое описание варианта"
                               style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                    </div>
                    
                    <div class="variant-config" style="margin-top: 15px;">
                        {{-- Здесь будут динамические поля в зависимости от типа теста --}}
                    </div>
                </div>
            </div>

            <button type="button" onclick="addVariant()" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer;">
                + Добавить вариант
            </button>
            
            <div style="margin-top: 15px; padding: 15px; background: #f3f4f6; border-radius: 6px;">
                <span style="font-weight: 500;">Сумма трафика:</span>
                <span id="traffic-sum" style="margin-left: 10px; font-weight: bold;">100%</span>
                <span id="traffic-warning" style="margin-left: 10px; color: #ef4444; display: none;">
                    ⚠️ Сумма должна быть 100%
                </span>
            </div>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <a href="{{ route('ab-tests.index', $organization) }}" 
               style="padding: 10px 20px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 5px;">
                Отмена
            </a>
            <button type="submit" 
                    style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Создать тест
            </button>
        </div>
    </form>
</div>

<script>
let variantCount = 2;

function addVariant() {
    if (variantCount >= 5) {
        alert('Максимум 5 вариантов');
        return;
    }
    
    const container = document.getElementById('variants-container');
    const variantHtml = `
        <div class="variant-block" style="border: 2px solid #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="font-size: 16px; font-weight: 600;">Вариант ${String.fromCharCode(65 + variantCount)}</h3>
                <button type="button" onclick="removeVariant(this)" 
                        style="padding: 4px 8px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                    Удалить
                </button>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название *</label>
                    <input type="text" name="variants[${variantCount}][name]" required
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Трафик (%) *</label>
                    <input type="number" name="variants[${variantCount}][traffic_allocation]" value="0" 
                           min="0" max="100" step="0.1" required class="traffic-allocation" onchange="updateTrafficSum()"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                </div>
            </div>
            
            <div style="margin-top: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Описание</label>
                <input type="text" name="variants[${variantCount}][description]"
                       placeholder="Краткое описание варианта"
                       style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
            </div>
            
            <div class="variant-config" style="margin-top: 15px;">
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', variantHtml);
    variantCount++;
    updateVariantFields();
    updateTrafficSum();
}

function removeVariant(button) {
    button.closest('.variant-block').remove();
    variantCount--;
    updateTrafficSum();
}

function updateVariantFields() {
    const testType = document.getElementById('test_type').value;
    const configs = document.querySelectorAll('.variant-config');
    
    configs.forEach((config, index) => {
        let fieldHtml = '';
        
        switch(testType) {
            case 'prompt':
                fieldHtml = `
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Системный промпт *</label>
                    <textarea name="variants[${index}][config][prompt]" rows="4" required
                              style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;"
                              placeholder="Введите системный промпт для этого варианта"></textarea>
                `;
                break;
            case 'temperature':
                fieldHtml = `
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Temperature *</label>
                    <input type="number" name="variants[${index}][config][temperature]" 
                           min="0" max="2" step="0.1" required value="0.7"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <small style="color: #6b7280;">0 - точные ответы, 2 - максимально креативные</small>
                `;
                break;
            case 'model':
                fieldHtml = `
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Модель AI *</label>
                    <select name="variants[${index}][config][model]" required
                            style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                        <option value="gpt-4o">GPT-4o</option>
                        <option value="gpt-4o-mini">GPT-4o Mini</option>
                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                    </select>
                `;
                break;
            case 'welcome_message':
                fieldHtml = `
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Приветственное сообщение *</label>
                    <textarea name="variants[${index}][config][welcome_message]" rows="2" required
                              style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;"
                              placeholder="Введите приветственное сообщение"></textarea>
                `;
                break;
        }
        
        config.innerHTML = fieldHtml;
    });
}

function updateTrafficSum() {
    const inputs = document.querySelectorAll('.traffic-allocation');
    let sum = 0;
    
    inputs.forEach(input => {
        sum += parseFloat(input.value) || 0;
    });
    
    document.getElementById('traffic-sum').textContent = sum + '%';
    document.getElementById('traffic-warning').style.display = Math.abs(sum - 100) > 0.01 ? 'inline' : 'none';
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.traffic-allocation').forEach(input => {
        input.addEventListener('change', updateTrafficSum);
    });
    updateTrafficSum();
});
</script>
@endsection