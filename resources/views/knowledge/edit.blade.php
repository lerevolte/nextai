@extends('layouts.app')

@section('title', 'Редактировать материал')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 24px; font-weight: bold;">Редактировать материал</h2>
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

    <form method="POST" action="{{ route('knowledge.update', [$organization, $bot, $item->id]) }}" 
          style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @csrf
        @method('PUT')

        <!-- Информация о материале -->
        <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 25px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div>
                    <span style="color: #6b7280; font-size: 14px;">Тип:</span>
                    <div style="font-weight: 500; margin-top: 2px;">{{ $item->getTypeIcon() }} {{ $item->getTypeName() }}</div>
                </div>
                <div>
                    <span style="color: #6b7280; font-size: 14px;">Создан:</span>
                    <div style="font-weight: 500; margin-top: 2px;">{{ $item->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div>
                    <span style="color: #6b7280; font-size: 14px;">Изменен:</span>
                    <div style="font-weight: 500; margin-top: 2px;">{{ $item->updated_at->format('d.m.Y H:i') }}</div>
                </div>
                <div>
                    <span style="color: #6b7280; font-size: 14px;">Размер:</span>
                    <div style="font-weight: 500; margin-top: 2px;">{{ $item->getWordCount() }} слов</div>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                Заголовок <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="title" value="{{ old('title', $item->title) }}" required
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                Содержание <span style="color: #ef4444;">*</span>
            </label>
            <textarea name="content" rows="15" required
                      style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.5;">{{ old('content', $item->content) }}</textarea>
            <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                <small style="color: #6b7280;">
                    Эта информация будет использоваться ботом для формирования ответов
                </small>
                <small style="color: #6b7280;">
                    <span id="char-count">0</span> символов
                </small>
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                URL источника <span style="color: #9ca3af;">(опционально)</span>
            </label>
            <input type="url" name="source_url" value="{{ old('source_url', $item->source_url) }}"
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
        </div>

        <div style="margin-bottom: 25px; padding: 15px; background: #f9fafb; border-radius: 6px;">
            <label style="display: flex; align-items: center; cursor: pointer; font-weight: 500;">
                <input type="checkbox" name="is_active" value="1" 
                       {{ old('is_active', $item->is_active) ? 'checked' : '' }}
                       style="width: 20px; height: 20px; margin-right: 10px;">
                <span>Материал активен</span>
            </label>
            <small style="color: #6b7280; display: block; margin-top: 5px; margin-left: 30px;">
                Только активные материалы используются ботом при генерации ответов
            </small>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                Заметки об изменениях <span style="color: #9ca3af;">(опционально)</span>
            </label>
            <input type="text" name="change_notes" 
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;"
                   placeholder="Например: Добавлена информация о доставке в регионы">
            <small style="color: #6b7280; display: block; margin-top: 5px;">
                Опишите, что изменилось в этой версии
            </small>
        </div>

        <!-- Информация о версиях -->
        <div style="background: #f0f9ff; padding: 15px; border-radius: 6px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="color: #1e40af;">Текущая версия: {{ $item->version }}</strong>
                    <p style="color: #3b82f6; font-size: 14px; margin-top: 5px;">
                        При сохранении будет создана версия {{ $item->version + 1 }}
                    </p>
                </div>
                <a href="{{ route('knowledge.versions', [$organization, $bot, $item->id]) }}" 
                   style="padding: 8px 16px; background: white; color: #1e40af; text-decoration: none; border-radius: 5px; border: 1px solid #93c5fd;">
                    📜 История версий
                </a>
            </div>
        </div>

        @if($item->metadata)
        <details style="margin-bottom: 25px;">
            <summary style="cursor: pointer; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 14px; color: #6b7280;">
                Метаданные
            </summary>
            <div style="padding: 15px; background: #f9fafb; border-radius: 0 0 6px 6px; margin-top: -1px;">
                <pre style="font-size: 12px; color: #6b7280;">{{ json_encode($item->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </details>
        @endif

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="{{ route('knowledge.index', [$organization, $bot]) }}" 
               style="padding: 12px 24px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 6px; font-weight: 500;">
                Отмена
            </a>
            <button type="submit" 
                    style="padding: 12px 24px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                Сохранить изменения
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