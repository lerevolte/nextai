@extends('layouts.app')

@section('title', 'Добавить источник знаний')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 24px; font-weight: bold;">Добавить источник знаний</h2>
        <p style="color: #6b7280; margin-top: 5px;">Автоматическая синхронизация из внешних источников</p>
    </div>

    @if ($errors->any())
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('knowledge.sources.store', [$organization, $bot]) }}" 
          style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @csrf

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Тип источника</label>
            <select name="type" id="source-type" required onchange="showSourceSettings()"
                    style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                <option value="">Выберите тип источника</option>
                <option value="notion">📝 Notion</option>
                <option value="url">🌐 Веб-страницы</option>
                <option value="google_drive">📁 Google Drive</option>
                <option value="github">🐙 GitHub</option>
            </select>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Название источника</label>
            <input type="text" name="name" required
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                   placeholder="Например: База знаний Notion">
        </div>

        <!-- Настройки для Notion -->
        <div id="notion-settings" class="source-settings" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #374151;">Настройки Notion</h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">API Token</label>
                <input type="password" name="config[api_token]"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                       placeholder="secret_xxx...">
                <small style="color: #6b7280;">Получите токен на <a href="https://www.notion.so/my-integrations" target="_blank">notion.so/my-integrations</a></small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Database ID</label>
                <input type="text" name="config[database_id]"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                <small style="color: #6b7280;">ID базы данных из URL страницы в Notion</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" name="config[delete_removed]" value="1" style="margin-right: 8px;">
                    <span>Удалять элементы, которых больше нет в Notion</span>
                </label>
            </div>
        </div>

        <!-- Настройки для URL -->
        <div id="url-settings" class="source-settings" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #374151;">Настройки веб-страниц</h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">URL-адреса (по одному на строку)</label>
                <textarea name="config[urls_text]" rows="5"
                          style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                          placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
                <small style="color: #6b7280;">Добавьте URL-адреса страниц для отслеживания</small>
            </div>
        </div>

        <!-- Настройки для Google Drive -->
        <div id="google_drive-settings" class="source-settings" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #374151;">Настройки Google Drive</h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Folder ID</label>
                <input type="text" name="config[folder_id]"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                <small style="color: #6b7280;">ID папки из URL в Google Drive</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Service Account Credentials (JSON)</label>
                <textarea name="config[credentials]" rows="5"
                          style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-family: monospace;"
                          placeholder='{"type": "service_account", ...}'></textarea>
            </div>
        </div>

        <!-- Настройки для GitHub -->
        <div id="github-settings" class="source-settings" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #374151;">Настройки GitHub</h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Repository</label>
                <input type="text" name="config[repository]"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                       placeholder="owner/repository">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Branch</label>
                <input type="text" name="config[branch]" value="main"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Path (опционально)</label>
                <input type="text" name="config[path]"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                       placeholder="/docs">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Access Token (для приватных репозиториев)</label>
                <input type="password" name="config[token]"
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
        </div>

        <!-- Настройки синхронизации -->
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="margin-bottom: 15px; color: #374151;">Настройки синхронизации</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Интервал обновления</label>
                    <select name="sync_settings[interval]" required
                            style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="manual">Вручную</option>
                        <option value="hourly">Каждый час</option>
                        <option value="daily" selected>Ежедневно</option>
                        <option value="weekly">Еженедельно</option>
                        <option value="monthly">Ежемесячно</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Автоматическая синхронизация</label>
                    <label style="display: flex; align-items: center; margin-top: 12px;">
                        <input type="checkbox" name="sync_settings[auto_sync]" value="1" checked style="margin-right: 8px;">
                        <span>Включить автоматическую синхронизацию</span>
                    </label>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: flex; align-items: center;">
                <input type="checkbox" name="sync_now" value="1" checked style="margin-right: 8px;">
                <span style="font-weight: 500;">Запустить синхронизацию сразу после создания</span>
            </label>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="{{ route('knowledge.sources.index', [$organization, $bot]) }}" 
               style="padding: 12px 24px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 6px;">
                Отмена
            </a>
            <button type="submit" 
                    style="padding: 12px 24px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                Создать источник
            </button>
        </div>
    </form>
</div>

<script>
function showSourceSettings() {
    const type = document.getElementById('source-type').value;
    
    // Скрываем все настройки
    document.querySelectorAll('.source-settings').forEach(el => {
        el.style.display = 'none';
    });
    
    // Показываем настройки для выбранного типа
    if (type) {
        const settings = document.getElementById(type + '-settings');
        if (settings) {
            settings.style.display = 'block';
        }
    }
}

// Обработка URL для источника url
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const urlsText = document.querySelector('textarea[name="config[urls_text]"]');
        if (urlsText && urlsText.value) {
            const urls = urlsText.value.split('\n').filter(url => url.trim());
            
            // Создаем скрытые поля для каждого URL
            urls.forEach((url, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `config[urls][${index}]`;
                input.value = url.trim();
                form.appendChild(input);
            });
        }
    });
});
</script>
@endsection