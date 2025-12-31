@extends('layouts.app')

@section('title', 'Мониторинг производительности')

@section('content')
<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    {{-- Заголовок --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: bold; color: #111827;">
            Мониторинг производительности
        </h1>
        <div style="display: flex; gap: 10px;">
            <button onclick="refreshMetrics()" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer;">
                🔄 Обновить
            </button>
            <button onclick="showOptimizationModal()" 
                    style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                ⚡ Оптимизация
            </button>
        </div>
    </div>

    {{-- Основные метрики --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        {{-- Кэш --}}
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Попадание в кэш</div>
                    <div style="font-size: 28px; font-weight: bold; color: {{ $metrics['cache']['hit_rate'] > 70 ? '#10b981' : '#f59e0b' }};">
                        {{ $metrics['cache']['hit_rate'] }}%
                    </div>
                </div>
                <div style="width: 40px; height: 40px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    💾
                </div>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                Память: {{ number_format($metrics['cache']['memory_usage'] ?? 0) }} MB<br>
                Ключей: {{ number_format($metrics['cache']['keys_count'] ?? 0) }}
            </div>
        </div>

        {{-- База данных --}}
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Соединения БД</div>
                    <div style="font-size: 28px; font-weight: bold; color: #111827;">
                        {{ $metrics['database']['connections'] }}
                    </div>
                </div>
                <div style="width: 40px; height: 40px; background: #f0fdf4; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    🗄️
                </div>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                Медленных запросов: {{ $metrics['database']['slow_queries'] ?? 0 }}<br>
                Cache hit: {{ $metrics['database']['query_cache_hit_rate'] ?? 0 }}%
            </div>
        </div>

        {{-- API --}}
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Время ответа API</div>
                    <div style="font-size: 28px; font-weight: bold; color: {{ $metrics['api']['avg_response_time'] < 2 ? '#10b981' : '#f59e0b' }};">
                        {{ $metrics['api']['avg_response_time'] }}с
                    </div>
                </div>
                <div style="width: 40px; height: 40px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    ⚡
                </div>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                Запросов/мин: {{ $metrics['api']['requests_per_minute'] }}<br>
                Ошибок: {{ $metrics['api']['error_rate'] }}%
            </div>
        </div>

        {{-- Ресурсы --}}
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">CPU / RAM</div>
                    <div style="font-size: 28px; font-weight: bold; 
                                color: {{ $metrics['resources']['cpu_usage'] > 80 || $metrics['resources']['memory_usage'] > 80 ? '#ef4444' : '#10b981' }};">
                        {{ $metrics['resources']['cpu_usage'] }}% / {{ $metrics['resources']['memory_usage'] }}%
                    </div>
                </div>
                <div style="width: 40px; height: 40px; background: #fee2e2; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    💻
                </div>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                Диск: {{ $metrics['resources']['disk_usage'] ?? 0 }}%
            </div>
        </div>
    </div>

    {{-- Производительность ботов --}}
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Производительность ботов</h2>
        
        <table style="width: 100%;">
            <thead>
                <tr style="border-bottom: 2px solid #e5e7eb;">
                    <th style="text-align: left; padding: 12px; font-size: 14px; color: #6b7280;">Бот</th>
                    <th style="text-align: center; padding: 12px; font-size: 14px; color: #6b7280;">Статус</th>
                    <th style="text-align: center; padding: 12px; font-size: 14px; color: #6b7280;">Активные диалоги</th>
                    <th style="text-align: center; padding: 12px; font-size: 14px; color: #6b7280;">Ср. время ответа</th>
                    <th style="text-align: center; padding: 12px; font-size: 14px; color: #6b7280;">Кэш хиты</th>
                    <th style="text-align: center; padding: 12px; font-size: 14px; color: #6b7280;">Токены</th>
                    <th style="text-align: center; padding: 12px; font-size: 14px; color: #6b7280;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($botsPerformance as $bot)
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 12px;">
                            <a href="{{ route('bots.show', [$organization, $bot['id']]) }}" 
                               style="color: #111827; text-decoration: none; font-weight: 500;">
                                {{ $bot['name'] }}
                            </a>
                        </td>
                        <td style="text-align: center; padding: 12px;">
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%;
                                       background: {{ $bot['status'] == 'healthy' ? '#10b981' : 
                                                    ($bot['status'] == 'slow' ? '#f59e0b' : 
                                                    ($bot['status'] == 'overloaded' ? '#ef4444' : '#6b7280')) }};">
                            </span>
                        </td>
                        <td style="text-align: center; padding: 12px;">
                            {{ $bot['active_conversations'] }}
                        </td>
                        <td style="text-align: center; padding: 12px;">
                            <span style="color: {{ $bot['avg_response_time'] > 3 ? '#ef4444' : '#10b981' }};">
                                {{ $bot['avg_response_time'] }}с
                            </span>
                        </td>
                        <td style="text-align: center; padding: 12px;">
                            <span style="color: {{ $bot['cache_hit_rate'] > 50 ? '#10b981' : '#f59e0b' }};">
                                {{ $bot['cache_hit_rate'] }}%
                            </span>
                        </td>
                        <td style="text-align: center; padding: 12px;">
                            {{ number_format($bot['tokens_used']) }}
                        </td>
                        <td style="text-align: center; padding: 12px;">
                            <button onclick="optimizeBot({{ $bot['id'] }})" 
                                    style="padding: 4px 12px; background: #6366f1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                Оптимизировать
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Рекомендации --}}
    @if(!empty($recommendations))
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Рекомендации по оптимизации</h2>
            
            @foreach($recommendations as $rec)
                <div style="padding: 15px; margin-bottom: 10px; border-left: 4px solid 
                            {{ $rec['priority'] == 'high' ? '#ef4444' : 
                               ($rec['priority'] == 'medium' ? '#f59e0b' : '#6366f1') }}; 
                            background: #f9fafb; border-radius: 4px;">
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 5px;">
                        {{ $rec['title'] }}
                    </h3>
                    <p style="color: #6b7280; margin-bottom: 10px;">
                        {{ $rec['description'] }}
                    </p>
                    <button onclick="applyRecommendation('{{ $rec['type'] }}')" 
                            style="padding: 6px 12px; background: #6366f1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                        {{ $rec['action'] }}
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Модальное окно оптимизации --}}
<div id="optimizationModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Запуск оптимизации</h2>
        
        <form action="{{ route('performance.optimize', $organization) }}" method="POST">
            @csrf
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 10px;">
                    <input type="radio" name="type" value="cache" checked>
                    <span style="margin-left: 8px;">Оптимизация кэша</span>
                </label>
                <label style="display: block; margin-bottom: 10px;">
                    <input type="radio" name="type" value="database">
                    <span style="margin-left: 8px;">Оптимизация базы данных</span>
                </label>
                <label style="display: block; margin-bottom: 10px;">
                    <input type="radio" name="type" value="cleanup">
                    <span style="margin-left: 8px;">Очистка старых данных</span>
                </label>
                <label style="display: block; margin-bottom: 10px;">
                    <input type="radio" name="type" value="all">
                    <span style="margin-left: 8px;">Полная оптимизация</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeOptimizationModal()" 
                        style="padding: 10px 20px; background: #f3f4f6; color: #111827; border: none; border-radius: 6px; cursor: pointer;">
                    Отмена
                </button>
                <button type="submit" 
                        style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Запустить
                </button>
            </div>
        </form>
    </div>
</div>
@endsection