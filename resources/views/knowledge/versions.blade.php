@extends('layouts.app')

@section('title', 'История версий')

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 24px; font-weight: bold;">История версий: {{ $item->title }}</h2>
        <p style="color: #6b7280; margin-top: 5px;">
            Текущая версия: {{ $item->version }} • Последнее изменение: {{ $item->updated_at->format('d.m.Y H:i') }}
        </p>
    </div>

    <div style="display: flex; gap: 20px;">
        <!-- Список версий -->
        <div style="width: 350px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px;">Версии документа</h3>
            
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <!-- Текущая версия -->
                <div style="padding: 15px; border-bottom: 1px solid #e5e7eb; background: #f0f4ff; cursor: pointer;"
                     onclick="showVersion('current')">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: #4f46e5;">
                                Версия {{ $item->version }} (текущая)
                            </div>
                            <div style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                                {{ $item->updated_at->format('d.m.Y H:i') }}
                            </div>
                            @if($item->metadata['updated_by'] ?? null)
                                <div style="font-size: 12px; color: #9ca3af; margin-top: 2px;">
                                    Изменил: {{ \App\Models\User::find($item->metadata['updated_by'])->name ?? 'Система' }}
                                </div>
                            @endif
                        </div>
                        <span style="padding: 4px 8px; background: #4f46e5; color: white; border-radius: 4px; font-size: 11px;">
                            АКТИВНА
                        </span>
                    </div>
                </div>

                <!-- Предыдущие версии -->
                @foreach($versions as $version)
                    <div style="padding: 15px; border-bottom: 1px solid #e5e7eb; cursor: pointer; hover: background: #f9fafb;"
                         onclick="showVersion({{ $version->id }})">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 500;">
                                    Версия {{ $version->version }}
                                </div>
                                <div style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                                    {{ $version->created_at->format('d.m.Y H:i') }}
                                </div>
                                @if($version->created_by)
                                    <div style="font-size: 12px; color: #9ca3af; margin-top: 2px;">
                                        Изменил: {{ $version->creator->name ?? 'Система' }}
                                    </div>
                                @endif
                                @if($version->change_notes)
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 5px; font-style: italic;">
                                        {{ $version->change_notes }}
                                    </div>
                                @endif
                            </div>
                            <button onclick="event.stopPropagation(); restoreVersion({{ $version->id }})" 
                                    style="margin-right:5px;padding: 4px 8px; background: #f3f4f6; color: #374151; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">
                                Восстановить
                            </button>
                            @if($versions->count() > 1)
                            <button onclick="event.stopPropagation(); deleteVersion({{ $version->id }})" 
                                    style="padding: 4px 8px; background: #fee2e2; color: #991b1b; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">
                                Удалить
                            </button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($versions->isEmpty())
                    <div style="padding: 20px; text-align: center; color: #9ca3af;">
                        Нет предыдущих версий
                    </div>
                @endif
            </div>
        </div>

        <!-- Предпросмотр версии -->
        <div style="flex: 1;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px;">Содержимое версии</h3>
            
            <div id="version-preview" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; min-height: 500px;">
                <div id="version-content">
                    <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">{{ $item->title }}</h4>
                    <div style="white-space: pre-wrap; line-height: 1.6; color: #374151;">{{ $item->content }}</div>
                </div>
            </div>

            <!-- Сравнение версий -->
            <div style="margin-top: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Сравнение версий</h4>
                
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <select id="compare-from" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="">Выберите версию</option>
                        @foreach($versions as $version)
                            <option value="{{ $version->id }}">Версия {{ $version->version }}</option>
                        @endforeach
                    </select>
                    
                    <select id="compare-to" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="current" selected>Текущая версия</option>
                        @foreach($versions as $version)
                            <option value="{{ $version->id }}">Версия {{ $version->version }}</option>
                        @endforeach
                    </select>
                    
                    <button onclick="compareVersions()" 
                            style="padding: 8px 16px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        Сравнить
                    </button>
                </div>
                
                <div id="diff-result" style="display: none;">
                    <div style="background: #f9fafb; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px;">
                        <!-- Результаты сравнения будут здесь -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const versions = @json($versions);
const currentItem = @json($item);

function showVersion(versionId) {
    const contentDiv = document.getElementById('version-content');
    
    if (versionId === 'current') {
        contentDiv.innerHTML = `
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">${currentItem.title}</h4>
            <div style="white-space: pre-wrap; line-height: 1.6; color: #374151;">${currentItem.content}</div>
        `;
    } else {
        const version = versions.find(v => v.id === versionId);
        if (version) {
            contentDiv.innerHTML = `
                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">${version.title}</h4>
                <div style="white-space: pre-wrap; line-height: 1.6; color: #374151;">${version.content}</div>
            `;
        }
    }
}

function restoreVersion(versionId) {
    if (!confirm('Восстановить эту версию? Текущая версия будет сохранена в истории.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("knowledge.versions.restore", [$organization, $bot, $item->id]) }}';
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '{{ csrf_token() }}';
    form.appendChild(csrfToken);
    
    const versionInput = document.createElement('input');
    versionInput.type = 'hidden';
    versionInput.name = 'version_id';
    versionInput.value = versionId;
    form.appendChild(versionInput);
    
    document.body.appendChild(form);
    form.submit();
}

function compareVersions() {
    const fromId = document.getElementById('compare-from').value;
    const toId = document.getElementById('compare-to').value;
    
    if (!fromId || !toId) {
        alert('Выберите обе версии для сравнения');
        return;
    }
    
    if (fromId === toId) {
        alert('Выберите разные версии для сравнения');
        return;
    }
    
    document.getElementById('diff-result').style.display = 'block';
    document.getElementById('diff-result').querySelector('div').innerHTML = 'Загрузка сравнения...';
    
    fetch('{{ route("knowledge.versions.compare", [$organization, $bot, $item->id]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            from_id: fromId,
            to_id: toId
        })
    })
    .then(response => response.json())
    .then(data => {
        let html = `
            <div style="background: #f9fafb; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                <strong>Сравнение:</strong> ${data.fromVersion} → ${data.toVersion}
            </div>
        `;
        
        // Статистика изменений
        if (data.stats) {
            html += `
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                    <div style="background: white; padding: 10px; border-radius: 4px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 12px; color: #6b7280;">Строки</div>
                        <div style="font-size: 14px; font-weight: 600;">
                            <span style="color: #16a34a;">+${data.stats.lines.added}</span> / 
                            <span style="color: #dc2626;">-${data.stats.lines.removed}</span>
                        </div>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 4px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 12px; color: #6b7280;">Слова</div>
                        <div style="font-size: 14px; font-weight: 600;">
                            <span style="color: #16a34a;">+${data.stats.words.added}</span> / 
                            <span style="color: #dc2626;">-${data.stats.words.removed}</span>
                        </div>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 4px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 12px; color: #6b7280;">Символы</div>
                        <div style="font-size: 14px; font-weight: 600;">
                            <span style="color: #16a34a;">+${data.stats.chars.added}</span> / 
                            <span style="color: #dc2626;">-${data.stats.chars.removed}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        html += '<h5 style="margin-bottom: 10px; font-weight: 600;">Изменения в заголовке:</h5>';
        html += '<div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 20px; line-height: 1.6;">' + data.titleDiff + '</div>';
        
        html += '<h5 style="margin-bottom: 10px; font-weight: 600;">Изменения в контенте:</h5>';
        html += '<div style="background: white; padding: 15px; border-radius: 6px; max-height: 400px; overflow-y: auto; line-height: 1.6; white-space: pre-wrap;">' + data.contentDiff + '</div>';
        
        // Легенда
        html += `
            <div style="margin-top: 15px; padding: 10px; background: #f9fafb; border-radius: 6px; font-size: 12px;">
                <strong>Легенда:</strong>
                <span style="background: #dcfce7; color: #166534; padding: 2px 5px; margin: 0 5px;">Добавлено</span>
                <span style="background: #fee2e2; color: #991b1b; text-decoration: line-through; padding: 2px 5px; margin: 0 5px;">Удалено</span>
            </div>
        `;
        
        document.getElementById('diff-result').querySelector('div').innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('diff-result').querySelector('div').innerHTML = 
            '<div style="color: #dc2626;">Ошибка при сравнении версий</div>';
    });
}


function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function deleteVersion(versionId) {
    if (!confirm('Удалить эту версию? Это действие необратимо.')) {
        return;
    }
    
    // Создаем форму для отправки DELETE запроса через POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/o/{{ $organization->slug }}/bots/{{ $bot->id }}/knowledge/{{ $item->id }}/versions/${versionId}/delete`;
    form.style.display = 'none';
    
    // CSRF токен
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    form.appendChild(csrfInput);
    
    // Метод DELETE через _method
    const methodInput = document.createElement('input');
    methodInput.type = 'hidden';
    methodInput.name = '_method';
    methodInput.value = 'DELETE';
    form.appendChild(methodInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection