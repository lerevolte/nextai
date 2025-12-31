{{-- resources/views/bots/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Боты')

@section('content')
<div style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-size: 24px;">Управление ботами</h2>
        <a href="{{ route('bots.create', $organization) }}" 
           style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">
            + Создать бота
        </a>
    </div>

    @if(session('success'))
        <div style="padding: 15px; background: #d1fae5; border: 1px solid #10b981; color: #065f46; border-radius: 5px; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
        @forelse($bots as $bot)
            <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <h3 style="font-size: 18px; font-weight: 600;">{{ $bot->name }}</h3>
                    <span style="padding: 4px 8px; background: {{ $bot->is_active ? '#d1fae5' : '#fee2e2' }}; color: {{ $bot->is_active ? '#065f46' : '#991b1b' }}; border-radius: 4px; font-size: 12px;">
                        {{ $bot->is_active ? 'Активен' : 'Неактивен' }}
                    </span>
                </div>
                
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">
                    {{ $bot->description ?: 'Без описания' }}
                </p>
                
                <div style="border-top: 1px solid #e5e7eb; padding-top: 15px;">
                    <p style="font-size: 14px; margin-bottom: 5px;">
                        <strong>Провайдер:</strong> {{ ucfirst($bot->ai_provider) }}
                    </p>
                    <p style="font-size: 14px; margin-bottom: 15px;">
                        <strong>Модель:</strong> {{ $bot->ai_model }}
                    </p>
                    
                    <div style="display: flex; gap: 10px;">
                        <a href="{{ route('bots.show', [$organization, $bot]) }}" 
                           style="flex: 1; padding: 8px; background: #6366f1; color: white; text-align: center; text-decoration: none; border-radius: 4px;">
                            Открыть
                        </a>
                        <a href="{{ route('bots.edit', [$organization, $bot]) }}" 
                           style="flex: 1; padding: 8px; background: #f3f4f6; color: #111827; text-align: center; text-decoration: none; border-radius: 4px;">
                            Изменить
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 8px;">
                <p style="color: #6b7280; margin-bottom: 20px;">У вас пока нет ботов</p>
                <a href="{{ route('bots.create', $organization) }}" 
                   style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                    Создать первого бота
                </a>
            </div>
        @endforelse
    </div>

    @if($bots->hasPages())
        <div style="margin-top: 20px;">
            {{ $bots->links() }}
        </div>
    @endif
</div>
@endsection