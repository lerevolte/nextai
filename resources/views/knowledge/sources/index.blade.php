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

    <div style="display: grid; gap: 20px;">
        @forelse($sources as $source)
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 24px; margin-right: 10px;">
                                @switch($source->type)
                                    @case('notion') 📝 @break
                                    @case('url') 🌐 @break
                                    @case('google_drive') 📁 @break
                                    @case('github') 🐙 @break
                                    @default 📊
                                @endswitch
                            </span>
                            <div>
                                <h3 style="font-size: 18px; font-weight: 600;">{{ $source->name }}</h3>
                                <span style="color: #6b7280; font-size: 14px;">{{ ucfirst($source->type) }}</span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">
                            <div>
                                <span style="color: #6b7280; font-size: 13px;">Элементов</span>
                                <div style="font-size: 20px; font-weight: bold;">{{ $source->items_count }}</div>
                            </div>
                            <div>
                                <span style="color: #6b7280; font-size: 13px;">Интервал</span>
                                <div style="font-size: 14px;">{{ ucfirst($source->sync_settings['interval'] ?? 'manual') }}</div>
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
                                            @default
                                                <span style="color: #6b7280;">В процессе</span>
                                        @endswitch
                                    @else
                                        <span style="color: #6b7280;">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($source->next_sync_at)
                            <div style="font-size: 13px; color: #6b7280;">
                                Следующая синхронизация: {{ $source->next_sync_at->format('d.m.Y H:i') }}
                            </div>
                        @endif
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <form method="POST" action="{{ route('knowledge.sources.sync', [$organization, $bot, $source]) }}" style="margin: 0;">
                            @csrf
                            <button type="submit" 
                                    style="padding: 8px 16px; background: #6366f1; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                🔄 Синхронизировать
                            </button>
                        </form>
                        
                        <form method="POST" action="{{ route('knowledge.sources.destroy', [$organization, $bot, $source]) }}" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    onclick="return confirm('Удалить этот источник?')"
                                    style="padding: 8px 16px; background: #fee2e2; color: #991b1b; border: none; border-radius: 5px; cursor: pointer;">
                                Удалить
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div style="background: white; border-radius: 8px; padding: 40px; text-align: center;">
                <p style="color: #6b7280; font-size: 16px;">Нет подключенных источников</p>
                <p style="color: #9ca3af; margin-top: 5px;">Добавьте источник для автоматической синхронизации знаний</p>
            </div>
        @endforelse
    </div>
</div>
@endsection