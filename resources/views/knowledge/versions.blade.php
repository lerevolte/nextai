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
                                    style="padding: 4px 8px; background: #f3f4f6; color: #374151; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">
                                Восстановить
                            </button>
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
    
    // Здесь можно добавить AJAX запрос для получения diff
    document.getElementById('diff-result').style.display = 'block';
    document.getElementById('diff-result').querySelector('div').innerHTML = 'Загрузка сравнения...';
    
    // Имитация сравнения
    setTimeout(() => {
        document.getElementById('diff-result').querySelector('div').innerHTML = `
            <div style="color: #dc2626;">- Удалено: старый текст</div>
            <div style="color: #16a34a;">+ Добавлено: новый текст</div>
        `;
    }, 500);
}
</script>
@endsection