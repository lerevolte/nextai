@extends('layouts.app')

@section('title', 'Добавить материал в базу знаний')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 24px; font-weight: bold;">Добавить материал в базу знаний</h2>
        <p style="color: #6b7280; margin-top: 5px;">Бот: {{ $bot->name }}</p>
    </div>

    @if ($errors->any())
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            <p style="font-weight: bold; margin-bottom: 10px;">Исправьте следующие ошибки:</p>
            <ul style="margin-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('knowledge.store', [$organization, $bot]) }}" 
          style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @csrf

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                Тип материала
            </label>
            <select name="type" id="type" required
                    style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
                <option value="manual" {{ old('type') == 'manual' ? 'selected' : '' }}>✏️ Ручной ввод</option>
                <option value="url" {{ old('type') == 'url' ? 'selected' : '' }}>🔗 Веб-страница</option>
                <option value="file" {{ old('type') == 'file' ? 'selected' : '' }}>📄 Файл</option>
            </select>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                Заголовок <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;"
                   placeholder="Например: Информация о доставке и оплате">
            <small style="color: #6b7280; display: block; margin-top: 5px;">
                Краткое описание содержимого для удобной навигации
            </small>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                Содержание <span style="color: #ef4444;">*</span>
            </label>
            <textarea name="content" rows="12" required
                      style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.5;"
                      placeholder="Введите информацию, которую должен знать бот...

Например:
- Доставка осуществляется по всей России
- Стоимость доставки: 300 рублей
- Срок доставки: 3-5 рабочих дней
- При заказе от 3000 рублей доставка бесплатная">{{ old('content') }}</textarea>
            <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                <small style="color: #6b7280;">
                    Эта информация будет использоваться ботом для формирования ответов
                </small>
                <small style="color: #6b7280;">
                    <span id="char-count">0</span> символов
                </small>
            </div>
        </div>

        <div style="margin-bottom: 25px;" id="url-field">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                URL источника <span style="color: #9ca3af;">(опционально)</span>
            </label>
            <input type="url" name="source_url" value="{{ old('source_url') }}"
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;"
                   placeholder="https://example.com/page">
            <small style="color: #6b7280; display: block; margin-top: 5px;">
                Укажите источник информации для отслеживания актуальности
            </small>
        </div>

        <!-- Подсказки -->
        <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #bfdbfe;">
            <h4 style="margin-bottom: 12px; color: #1e40af; display: flex; align-items: center;">
                <span style="margin-right: 8px;">💡</span>
                Советы для эффективной базы знаний:
            </h4>
            <ul style="margin: 0; padding-left: 25px; color: #1e40af; line-height: 1.8;">
                <li><strong>Структурируйте информацию:</strong> Разбивайте большие тексты на логические блоки по темам</li>
                <li><strong>Будьте конкретны:</strong> Включайте точные цифры, даты, условия</li>
                <li><strong>Используйте примеры:</strong> Добавляйте реальные кейсы и ситуации</li>
                <li><strong>Обновляйте регулярно:</strong> Следите за актуальностью информации</li>
                <li><strong>Избегайте дублирования:</strong> Каждый материал должен содержать уникальную информацию</li>
            </ul>
        </div>

        <!-- Примеры -->
        <details style="margin-bottom: 30px;">
            <summary style="cursor: pointer; padding: 15px; background: #f9fafb; border-radius: 6px; font-weight: 500;">
                📝 Показать примеры хороших материалов
            </summary>
            <div style="padding: 20px; background: #f9fafb; border-radius: 0 0 6px 6px; margin-top: -1px;">
                <div style="margin-bottom: 20px;">
                    <strong>Пример 1: Информация о доставке</strong>
                    <pre style="background: white; padding: 15px; border-radius: 4px; margin-top: 10px; white-space: pre-wrap; font-size: 14px;">Мы осуществляем доставку по всей России.

Стоимость доставки:
- По Москве: 300 рублей, срок 1-2 дня
- По России: от 500 рублей, срок 3-7 дней
- При заказе от 5000 рублей - доставка бесплатная

Способы доставки:
- Курьером до двери
- В пункты выдачи СДЭК
- Почтой России

Отслеживание: После отправки вы получите трек-номер на email.</pre>
                </div>
                
                <div>
                    <strong>Пример 2: Политика возврата</strong>
                    <pre style="background: white; padding: 15px; border-radius: 4px; margin-top: 10px; white-space: pre-wrap; font-size: 14px;">Вы можете вернуть товар в течение 14 дней с момента получения.

Условия возврата:
- Товар не был в использовании
- Сохранена упаковка и товарный вид
- Есть чек или подтверждение оплаты

Процесс возврата:
1. Свяжитесь с нами через форму на сайте
2. Отправьте товар обратно
3. Возврат средств в течение 5 рабочих дней</pre>
                </div>
            </div>
        </details>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="{{ route('knowledge.index', [$organization, $bot]) }}" 
               style="padding: 12px 24px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 6px; font-weight: 500;">
                Отмена
            </a>
            <button type="submit" 
                    style="padding: 12px 24px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                Добавить материал
            </button>
        </div>
    </form>
</div>

<script>
// Счетчик символов
const contentTextarea = document.querySelector('textarea[name="content"]');
const charCount = document.getElementById('char-count');

contentTextarea.addEventListener('input', function() {
    charCount.textContent = this.value.length;
});

// Инициализация счетчика при загрузке
charCount.textContent = contentTextarea.value.length;
</script>
@endsection