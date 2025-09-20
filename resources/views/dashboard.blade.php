@extends('layouts.app')

@section('title', 'Дашборд')

@section('content')
<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    {{-- Заголовок --}}
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: bold; color: #111827; margin-bottom: 10px;">
            Дашборд
        </h1>
        <p style="color: #6b7280;">
            {{ $organization->name }} • Период: {{ $period }} дней
        </p>
    </div>

    {{-- Фильтр периода --}}
    <div style="margin-bottom: 30px;">
        <form method="GET" action="{{ route('dashboard') }}" style="display: inline-flex; gap: 10px;">
            <select name="period" onchange="this.form.submit()" 
                    style="padding: 8px 15px; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                <option value="7" {{ $period == 7 ? 'selected' : '' }}>Последние 7 дней</option>
                <option value="30" {{ $period == 30 ? 'selected' : '' }}>Последние 30 дней</option>
                <option value="90" {{ $period == 90 ? 'selected' : '' }}>Последние 90 дней</option>
            </select>
            
            <button type="button" onclick="refreshDashboard()" 
                    style="padding: 8px 15px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer;">
                🔄 Обновить
            </button>
        </form>
    </div>

    {{-- Основные метрики (без анимаций) --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px; margin-bottom: 8px;">Диалоги</div>
            <div style="font-size: 28px; font-weight: bold; color: #111827;" id="metric-conversations">
                {{ $metrics['summary']['total_conversations']['value'] ?? 0 }}
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px; margin-bottom: 8px;">Пользователи</div>
            <div style="font-size: 28px; font-weight: bold; color: #111827;" id="metric-users">
                {{ $metrics['summary']['unique_users']['value'] ?? 0 }}
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px; margin-bottom: 8px;">Сообщения</div>
            <div style="font-size: 28px; font-weight: bold; color: #111827;" id="metric-messages">
                {{ $metrics['summary']['total_messages']['value'] ?? 0 }}
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px; margin-bottom: 8px;">Активные</div>
            <div style="font-size: 28px; font-weight: bold; color: #10b981;" id="metric-active">
                {{ $metrics['summary']['active_conversations']['value'] ?? 0 }}
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px; margin-bottom: 8px;">Ср. время ответа</div>
            <div style="font-size: 28px; font-weight: bold; color: #111827;" id="metric-response">
                {{ $metrics['summary']['avg_response_time']['value'] ?? 0 }}с
            </div>
        </div>

        <!-- <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px; margin-bottom: 8px;"></div>
            <div style="font-size: 28px; font-weight: bold; color: #111827;" id="metric-success">
                {{ $metrics['summary']['success_rate']['value'] ?? 0 }}%
            </div>
        </div> -->
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        {{-- Топ боты --}}
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px;">Активные боты</h3>
            
            @if(count($topBots) > 0)
                <table style="width: 100%;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 8px 0; font-size: 14px; color: #6b7280;">Бот</th>
                            <th style="text-align: right; padding: 8px 0; font-size: 14px; color: #6b7280;">Диалоги</th>
                            <th style="text-align: center; padding: 8px 0; font-size: 14px; color: #6b7280;">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topBots as $bot)
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 10px 0;">
                                <a href="{{ route('bots.show', [$organization, $bot->id]) }}" 
                                   style="color: #111827; text-decoration: none; font-weight: 500;">
                                    {{ $bot->name }}
                                </a>
                            </td>
                            <td style="text-align: right; padding: 10px 0;">
                                {{ $bot->conversation_count }}
                            </td>
                            <td style="text-align: center; padding: 10px 0;">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; 
                                           background: {{ $bot->is_active ? '#10b981' : '#ef4444' }};">
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="color: #6b7280;">Нет активных ботов</p>
            @endif
        </div>

        {{-- Недавняя активность --}}
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px;">Недавняя активность</h3>
            
            @if(count($recentActivity) > 0)
                <div style="max-height: 300px; overflow-y: auto;">
                    @foreach($recentActivity as $activity)
                    <div style="padding: 10px 0; border-bottom: 1px solid #f3f4f6;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <div style="font-weight: 500; color: #111827;">
                                    {{ $activity->user_name ?? 'Гость #' . $activity->id }}
                                </div>
                                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                                    {{ $activity->bot_name }} • {{ ucfirst($activity->channel_type) }}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span style="padding: 2px 8px; background: 
                                    {{ $activity->status == 'active' ? '#d1fae5' : 
                                       ($activity->status == 'waiting_operator' ? '#fef3c7' : '#f3f4f6') }}; 
                                    color: 
                                    {{ $activity->status == 'active' ? '#065f46' : 
                                       ($activity->status == 'waiting_operator' ? '#92400e' : '#6b7280') }};
                                    border-radius: 10px; font-size: 12px;">
                                    {{ $activity->status == 'active' ? 'Активен' : 
                                       ($activity->status == 'waiting_operator' ? 'Ждет' : 'Закрыт') }}
                                </span>
                                <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">
                                    {{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <p style="color: #6b7280;">Нет недавней активности</p>
            @endif
        </div>
    </div>

    {{-- Быстрые действия --}}
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px;">Быстрые действия</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="{{ route('bots.index', $organization) }}" 
               style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px;">
                🤖 Управление ботами
            </a>
            <a href="{{ route('bots.create', $organization) }}" 
               style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 6px;">
                ➕ Создать бота
            </a>
            @if(count($topBots) > 0)
                <a href="{{ route('conversations.index', [$organization, $topBots->first()->id]) }}" 
                   style="padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 6px;">
                    💬 Диалоги
                </a>
            @endif
            <a href="{{ route('organization.settings') }}" 
               style="padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 6px;">
                ⚙️ Настройки
            </a>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    // Показываем индикатор загрузки
    document.querySelectorAll('[id^="metric-"]').forEach(el => {
        el.style.opacity = '0.5';
    });
    
    fetch('{{ route("analytics.refresh", $organization) }}?period={{ $period }}', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем метрики
            document.getElementById('metric-conversations').textContent = data.metrics.total_conversations.value;
            document.getElementById('metric-users').textContent = data.metrics.unique_users.value;
            document.getElementById('metric-messages').textContent = data.metrics.total_messages.value;
            document.getElementById('metric-active').textContent = data.metrics.active_conversations.value;
            document.getElementById('metric-response').textContent = data.metrics.avg_response_time.value + 'с';
            document.getElementById('metric-success').textContent = data.metrics.success_rate.value + '%';
            
            // Восстанавливаем opacity
            document.querySelectorAll('[id^="metric-"]').forEach(el => {
                el.style.opacity = '1';
            });
        }
    })
    .catch(error => {
        console.error('Error refreshing dashboard:', error);
        document.querySelectorAll('[id^="metric-"]').forEach(el => {
            el.style.opacity = '1';
        });
    });
}

// Автообновление каждые 60 секунд (опционально)
// setInterval(refreshDashboard, 60000);
</script>
@endsection