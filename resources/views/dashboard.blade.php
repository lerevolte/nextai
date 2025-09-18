{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div style="padding: 20px;">
    <h2 style="font-size: 24px; margin-bottom: 20px;">Добро пожаловать, {{ auth()->user()->name }}!</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">Всего ботов</h3>
            <p style="font-size: 32px; font-weight: bold; color: #111827;">{{ $stats['total_bots'] ?? 0 }}</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">Активных ботов</h3>
            <p style="font-size: 32px; font-weight: bold; color: #10b981;">{{ $stats['active_bots'] ?? 0 }}</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">Диалогов (30 дней)</h3>
            <p style="font-size: 32px; font-weight: bold; color: #6366f1;">{{ $stats['total_conversations'] ?? 0 }}</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">Сообщений</h3>
            <p style="font-size: 32px; font-weight: bold; color: #8b5cf6;">{{ $stats['messages_sent'] ?? 0 }}</p>
        </div>
    </div>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="font-size: 18px; margin-bottom: 15px;">Быстрые действия</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="{{ route('bots.index', auth()->user()->organization) }}" 
               style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                Управление ботами
            </a>
            <a href="{{ route('bots.create', auth()->user()->organization) }}" 
               style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                Создать нового бота
            </a>
            @can('users.view')
            <a href="{{ route('users.index') }}" 
               style="padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                Пользователи
            </a>
            @endcan
            <a href="{{ route('organization.settings') }}" 
               style="padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                Настройки
            </a>
        </div>
    </div>

    @if(isset($topBots) && count($topBots) > 0)
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="font-size: 18px; margin-bottom: 15px;">Популярные боты</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <th style="text-align: left; padding: 10px;">Название</th>
                    <th style="text-align: left; padding: 10px;">Диалогов</th>
                    <th style="text-align: left; padding: 10px;">Статус</th>
                    <th style="text-align: left; padding: 10px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topBots as $bot)
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 10px;">{{ $bot->name }}</td>
                    <td style="padding: 10px;">{{ $bot->conversations_count }}</td>
                    <td style="padding: 10px;">
                        <span style="padding: 2px 8px; background: {{ $bot->is_active ? '#10b981' : '#ef4444' }}; color: white; border-radius: 3px; font-size: 12px;">
                            {{ $bot->is_active ? 'Активен' : 'Неактивен' }}
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <a href="{{ route('bots.show', [auth()->user()->organization, $bot]) }}" 
                           style="color: #6366f1; text-decoration: none;">
                            Открыть →
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection