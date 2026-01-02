@extends('layouts.app')

@section('title', 'Источники знаний')

@section('content')
<div style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="font-size: 24px; font-weight: bold;">Источники знаний</h2>
            <p style="color: #6b7280; margin-top: 5px;">Автоматическая синхронизация из внешних источников</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('knowledge.sources.create', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">
                + Добавить источник
            </a>
            <a href="{{ route('knowledge.import', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                📥 Импорт файла
            </a>
            <a href="{{ route('knowledge.index', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 5px;">
                ← К базе знаний
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 15px; background: #d1fae5; border: 1px solid #10b981; color: #065f46; border-radius: 5px; margin-bottom: 20px;">
            ✓ {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            ✗ {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; gap: 20px;">
        @forelse($sources as $source)
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 24px; margin-right: 10px;">
                                @switch($source->type)
                                    @case('notion') 📝 @break
                                    @case('google_docs') 📘 @break
                                    @case('url') 🌐 @break
                                    @case('google_drive') 📁 @break
                                    @case('github') 🐙 @break
                                    @default 📊
                                @endswitch
                            </span>
                            <div>
                                <h3 style="font-size: 18px; font-weight: 600;">{{ $source->name }}</h3>
                                <div style="display: flex; align-items: center; gap: 10px; margin-top: 2px;">
                                    <span style="color: #6b7280; font-size: 14px;">
                                        {{ $source->getTypeName() }}
                                    </span>
                                    @if($source->type === 'google_docs')
                                        @php
                                            $authType = $source->config['auth_type'] ?? 'public';
                                        @endphp
                                        <span style="padding: 2px 8px; font-size: 11px; border-radius: 10px; 
                                            @if($authType === 'public')
                                                background: #d1fae5; color: #065f46;
                                            @else
                                                background: #e0e7ff; color: #3730a3;
                                            @endif
                                        ">
                                            @if($authType === 'public')
                                                🌐 Публичный
                                            @elseif($authType === 'service_account')
                                                🔑 Service Account
                                            @else
                                                👤 OAuth
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">
                            <div>
                                <span style="color: #6b7280; font-size: 13px;">Элементов</span>
                                <div style="font-size: 20px; font-weight: bold;">{{ $source->items_count }}</div>
                            </div>
                            <div>
                                <span style="color: #6b7280; font-size: 13px;">Интервал</span>
                                <div style="font-size: 14px;">
                                    @php
                                        $intervals = [
                                            'manual' => 'Вручную',
                                            'hourly' => 'Каждый час',
                                            'daily' => 'Ежедневно',
                                            'weekly' => 'Еженедельно',
                                            'monthly' => 'Ежемесячно',
                                        ];
                                    @endphp
                                    {{ $intervals[$source->sync_settings['interval'] ?? 'manual'] ?? 'Не задано' }}
                                </div>
                            </div>
                            <div>
                                <span style="color: #6b7280; font-size: 13px;">Последняя синхр.</span>
                                <div style="font-size: 14px;">
                                    {{ $source->last_sync_at ? $source->last_sync_at->diffForHumans() : 'Никогда' }}
                                </div>
                            </div>
                            <div>
                                <span style="color: #6b7280; font-size: 13px;">Статус</span>
                                <div>
                                    @if($source->syncLogs->first())
                                        @switch($source->syncLogs->first()->status)
                                            @case('success')
                                                <span style="color: #10b981;">✓ Успешно</span>
                                                @break
                                            @case('partial')
                                                <span style="color: #f59e0b;">⚠ Частично</span>
                                                @break
                                            @case('failed')
                                                <span style="color: #ef4444;">✗ Ошибка</span>
                                                @break
                                            @case('in_progress')
                                                <span style="color: #3b82f6;">⏳ Синхронизация...</span>
                                                @break
                                            @default
                                                <span style="color: #6b7280;">{{ $source->syncLogs->first()->status }}</span>
                                        @endswitch
                                    @else
                                        <span style="color: #6b7280;">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Детали источника -->
                        @if($source->type === 'google_docs')
                            <div style="font-size: 13px; color: #6b7280; margin-bottom: 10px;">
                                @php
                                    $sourceType = $source->config['source_type'] ?? 'urls';
                                    $docCount = 0;
                                    
                                    if ($sourceType === 'urls') {
                                        $docCount = count($source->config['document_urls'] ?? []);
                                    } elseif ($sourceType === 'documents') {
                                        $docCount = count($source->config['document_ids'] ?? []);
                                    }
                                @endphp
                                
                                @if($sourceType === 'folder')
                                    📁 Папка: {{ $source->config['folder_id'] ?? '—' }}
                                @else
                                    📄 Документов в источнике: {{ $docCount }}
                                @endif
                            </div>
                        @endif

                        <!-- Последняя ошибка -->
                        @if(isset($source->sync_status['last_error']) && $source->sync_status['last_error'])
                            <div style="background: #fef2f2; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                                <span style="color: #991b1b; font-size: 13px;">
                                    ⚠️ {{ \Str::limit($source->sync_status['last_error'], 100) }}
                                </span>
                            </div>
                        @endif

                        @if($source->next_sync_at && ($source->sync_settings['interval'] ?? 'manual') !== 'manual')
                            <div style="font-size: 13px; color: #6b7280;">
                                Следующая синхронизация: {{ $source->next_sync_at->format('d.m.Y H:i') }}
                                ({{ $source->next_sync_at->diffForHumans() }})
                            </div>
                        @endif
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <form method="POST" action="{{ route('knowledge.sources.sync', [$organization, $bot, $source]) }}" style="margin: 0;">
                            @csrf
                            <button type="submit" 
                                    style="padding: 8px 16px; background: #6366f1; color: white; border: none; border-radius: 5px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                🔄 Синхронизировать
                            </button>
                        </form>
                        
                        <a href="{{ route('knowledge.sources.logs', [$organization, $bot, $source]) }}"
                           style="padding: 8px 16px; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 5px; display: flex; align-items: center; gap: 5px;">
                            📋 Логи
                        </a>
                        
                        <form method="POST" action="{{ route('knowledge.sources.destroy', [$organization, $bot, $source]) }}" 
                              style="margin: 0;"
                              onsubmit="return confirmDelete(this)">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="delete_items" value="0" id="delete-items-{{ $source->id }}">
                            <button type="submit" 
                                    style="padding: 8px 16px; background: #fee2e2; color: #991b1b; border: none; border-radius: 5px; cursor: pointer;">
                                🗑 Удалить
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div style="background: white; border-radius: 8px; padding: 60px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px;">📚</div>
                <p style="color: #374151; font-size: 18px; margin-bottom: 10px;">Нет подключенных источников</p>
                <p style="color: #9ca3af; margin-bottom: 20px;">Добавьте источник для автоматической синхронизации знаний</p>
                <a href="{{ route('knowledge.sources.create', [$organization, $bot]) }}" 
                   style="padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px; display: inline-block;">
                    + Добавить первый источник
                </a>
            </div>
        @endforelse
    </div>
</div>

<script>
function confirmDelete(form) {
    const sourceId = form.querySelector('input[name="delete_items"]').id.replace('delete-items-', '');
    
    const result = confirm('Удалить этот источник?\n\nНажмите OK чтобы удалить только источник.\nЭлементы базы знаний останутся.');
    
    if (result) {
        const deleteItems = confirm('Также удалить все элементы базы знаний, импортированные из этого источника?');
        form.querySelector('input[name="delete_items"]').value = deleteItems ? '1' : '0';
        return true;
    }
    
    return false;
}
</script>
@endsection