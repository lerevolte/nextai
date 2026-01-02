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

    <form method="POST" action="{{ route('knowledge.sources.store', [$organization, $bot]) }}" id="source-form"
          style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @csrf

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Тип источника</label>
            <select name="type" id="source-type" required onchange="showSourceSettings()"
                    style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                <option value="">Выберите тип источника</option>
                <option value="notion">📝 Notion</option>
                <option value="google_docs">📘 Google Docs</option>
                <option value="url">🌐 Веб-страницы</option>
                <option value="google_drive">📁 Google Drive</option>
                <option value="github">🐙 GitHub</option>
            </select>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Название источника</label>
            <input type="text" name="name" required
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                   placeholder="Например: Документация продукта">
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

        <!-- Настройки для Google Docs -->
        <div id="google_docs-settings" class="source-settings" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #374151;">Настройки Google Docs</h3>
            
            <!-- Тип авторизации -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Тип доступа</label>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                    <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                           id="auth-type-public-label">
                        <input type="radio" name="config[auth_type]" value="public" checked 
                               onchange="toggleGoogleAuthFields()" style="margin-right: 10px;">
                        <div>
                            <div style="font-weight: 500;">🌐 Публичный</div>
                            <div style="font-size: 12px; color: #6b7280;">Документы доступны по ссылке</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                           id="auth-type-service-label">
                        <input type="radio" name="config[auth_type]" value="service_account"
                               onchange="toggleGoogleAuthFields()" style="margin-right: 10px;">
                        <div>
                            <div style="font-weight: 500;">🔑 Service Account</div>
                            <div style="font-size: 12px; color: #6b7280;">Для приватных документов</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                           id="auth-type-oauth-label">
                        <input type="radio" name="config[auth_type]" value="oauth"
                               onchange="toggleGoogleAuthFields()" style="margin-right: 10px;">
                        <div>
                            <div style="font-weight: 500;">👤 OAuth 2.0</div>
                            <div style="font-size: 12px; color: #6b7280;">Ваш аккаунт Google</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Подсказка для публичного доступа -->
            <div id="google-public-info" style="background: #f0fdf4; padding: 15px; border-radius: 6px; border: 1px solid #bbf7d0; margin-bottom: 20px;">
                <h4 style="margin-bottom: 8px; color: #166534;">✅ Простой способ — публичный доступ</h4>
                <p style="color: #166534; font-size: 14px; margin-bottom: 10px;">
                    Для публичных документов авторизация не нужна. Просто убедитесь, что документ доступен по ссылке:
                </p>
                <ol style="margin: 0; padding-left: 20px; color: #166534; font-size: 14px; line-height: 1.8;">
                    <li>Откройте документ в Google Docs</li>
                    <li>Нажмите "Поделиться" → "Изменить доступ"</li>
                    <li>Выберите "Все, у кого есть ссылка" → "Читатель"</li>
                    <li>Вставьте ссылку на документ ниже</li>
                </ol>
            </div>

            <!-- Service Account JSON -->
            <div id="google-service-account-fields" style="display: none;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Service Account JSON</label>
                    <textarea name="config[service_account_json]" rows="6"
                              style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-family: monospace; font-size: 12px;"
                              placeholder='{"type": "service_account", "project_id": "...", ...}'></textarea>
                    <small style="color: #6b7280;">
                        Скачайте JSON-ключ из <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">Google Cloud Console</a>
                    </small>
                </div>

                <div style="background: #fef3c7; padding: 15px; border-radius: 6px; border: 1px solid #fcd34d; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 8px; color: #92400e;">⚠️ Важно для Service Account</h4>
                    <p style="color: #92400e; font-size: 14px;">
                        Предоставьте доступ к документам для email сервисного аккаунта (например: <code>my-service@project.iam.gserviceaccount.com</code>)
                    </p>
                </div>
            </div>

            <!-- OAuth поля -->
            <div id="google-oauth-fields" style="display: none;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Access Token</label>
                    <input type="text" name="config[access_token]"
                           style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Refresh Token (опционально)</label>
                    <input type="text" name="config[refresh_token]"
                           style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>
            </div>

            <!-- Источник документов -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Источник документов</label>
                <select name="config[source_type]" id="google-source-type" onchange="toggleGoogleSourceFields()"
                        style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="urls">По ссылкам на документы</option>
                    <option value="documents">По ID документов</option>
                    <option value="folder">Все документы из папки (требует авторизацию)</option>
                </select>
            </div>

            <!-- URL документов -->
            <div id="google-urls-field">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Ссылки на документы</label>
                    <textarea name="config[document_urls_text]" rows="4" id="google-urls-textarea"
                              style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                              placeholder="https://docs.google.com/document/d/1ABC.../edit&#10;https://docs.google.com/document/d/2DEF.../edit"></textarea>
                    <small style="color: #6b7280;">Вставьте ссылки на Google Docs (по одной на строку)</small>
                </div>
            </div>

            <!-- ID документов -->
            <div id="google-documents-field" style="display: none;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">ID документов</label>
                    <textarea name="config[document_ids_text]" rows="4" id="google-ids-textarea"
                              style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                              placeholder="1ABC123def456...&#10;2DEF789ghi012..."></textarea>
                    <small style="color: #6b7280;">
                        ID документа — часть URL между <code>/d/</code> и <code>/edit</code>
                    </small>
                </div>
            </div>

            <!-- Папка -->
            <div id="google-folder-field" style="display: none;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">ID папки Google Drive</label>
                    <input type="text" name="config[folder_id]"
                           style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px;"
                           placeholder="1ABCdef...">
                    <small style="color: #6b7280;">
                        ID папки — часть URL: <code>drive.google.com/drive/folders/<strong>ID_ПАПКИ</strong></code>
                    </small>
                </div>
                
                <div id="folder-auth-warning" style="background: #fef3c7; padding: 15px; border-radius: 6px; border: 1px solid #fcd34d; margin-bottom: 20px;">
                    <p style="color: #92400e; font-size: 14px; margin: 0;">
                        ⚠️ Для синхронизации папки требуется Service Account или OAuth авторизация
                    </p>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" name="config[delete_removed]" value="1" style="margin-right: 8px;">
                    <span>Удалять элементы, которых больше нет в источнике</span>
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
    
    document.querySelectorAll('.source-settings').forEach(el => {
        el.style.display = 'none';
    });
    
    if (type) {
        const settings = document.getElementById(type + '-settings');
        if (settings) {
            settings.style.display = 'block';
        }
    }
}

function toggleGoogleAuthFields() {
    const authType = document.querySelector('input[name="config[auth_type]"]:checked').value;
    
    document.getElementById('google-public-info').style.display = 'none';
    document.getElementById('google-service-account-fields').style.display = 'none';
    document.getElementById('google-oauth-fields').style.display = 'none';
    
    // Сброс стилей выбора
    document.querySelectorAll('[id^="auth-type-"]').forEach(el => {
        el.style.borderColor = '#e5e7eb';
        el.style.background = 'white';
    });
    
    if (authType === 'public') {
        document.getElementById('google-public-info').style.display = 'block';
        document.getElementById('auth-type-public-label').style.borderColor = '#10b981';
        document.getElementById('auth-type-public-label').style.background = '#f0fdf4';
    } else if (authType === 'service_account') {
        document.getElementById('google-service-account-fields').style.display = 'block';
        document.getElementById('auth-type-service-label').style.borderColor = '#6366f1';
        document.getElementById('auth-type-service-label').style.background = '#eef2ff';
    } else if (authType === 'oauth') {
        document.getElementById('google-oauth-fields').style.display = 'block';
        document.getElementById('auth-type-oauth-label').style.borderColor = '#6366f1';
        document.getElementById('auth-type-oauth-label').style.background = '#eef2ff';
    }
    
    // Проверяем доступность папки
    toggleGoogleSourceFields();
}

function toggleGoogleSourceFields() {
    const sourceType = document.getElementById('google-source-type').value;
    const authType = document.querySelector('input[name="config[auth_type]"]:checked')?.value || 'public';
    
    document.getElementById('google-urls-field').style.display = 'none';
    document.getElementById('google-documents-field').style.display = 'none';
    document.getElementById('google-folder-field').style.display = 'none';
    
    if (sourceType === 'urls') {
        document.getElementById('google-urls-field').style.display = 'block';
    } else if (sourceType === 'documents') {
        document.getElementById('google-documents-field').style.display = 'block';
    } else if (sourceType === 'folder') {
        document.getElementById('google-folder-field').style.display = 'block';
        
        // Показываем предупреждение если публичный доступ
        const warning = document.getElementById('folder-auth-warning');
        warning.style.display = authType === 'public' ? 'block' : 'none';
    }
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    toggleGoogleAuthFields();
    
    const form = document.getElementById('source-form');
    
    form.addEventListener('submit', function(e) {
        const type = document.getElementById('source-type').value;
        
        // Обработка URL для источника url
        if (type === 'url') {
            const urlsText = document.querySelector('textarea[name="config[urls_text]"]');
            if (urlsText && urlsText.value) {
                const urls = urlsText.value.split('\n').filter(url => url.trim());
                urls.forEach((url, index) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `config[urls][${index}]`;
                    input.value = url.trim();
                    form.appendChild(input);
                });
            }
        }
        
        // Обработка Google Docs
        if (type === 'google_docs') {
            const sourceType = document.getElementById('google-source-type').value;
            
            if (sourceType === 'urls') {
                const urlsText = document.getElementById('google-urls-textarea');
                if (urlsText && urlsText.value) {
                    const urls = urlsText.value.split('\n').filter(url => url.trim());
                    urls.forEach((url, index) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `config[document_urls][${index}]`;
                        input.value = url.trim();
                        form.appendChild(input);
                    });
                }
            }
            
            if (sourceType === 'documents') {
                const idsText = document.getElementById('google-ids-textarea');
                if (idsText && idsText.value) {
                    const ids = idsText.value.split('\n').filter(id => id.trim());
                    ids.forEach((id, index) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `config[document_ids][${index}]`;
                        input.value = id.trim();
                        form.appendChild(input);
                    });
                }
            }
        }
    });
});
</script>
@endsection