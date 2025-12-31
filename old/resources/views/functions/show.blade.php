@extends('layouts.app')

@section('title', $function->display_name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 24px; margin-bottom: 5px;">{{ $function->display_name }}</h2>
            <p style="color: #6b7280;">{{ $function->description }}</p>
        </div>
        <div style="display: flex; gap: 10px;">
            @if($function->is_active)
                <span style="padding: 8px 16px; background: #d1fae5; color: #065f46; border-radius: 20px;">
                    ✓ Активна
                </span>
            @else
                <span style="padding: 8px 16px; background: #fee2e2; color: #991b1b; border-radius: 20px;">
                    ✗ Неактивна
                </span>
            @endif
            <a href="{{ route('functions.edit', [$organization, $bot, $function]) }}" 
               style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                Редактировать
            </a>
            <a href="{{ route('functions.test', [$organization, $bot, $function]) }}" 
               style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">
                🧪 Тестировать
            </a>
            <a href="{{ route('functions.index', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #e5e7eb; color: #374151; text-decoration: none; border-radius: 5px;">
                К списку
            </a>
        </div>
    </div>

    <!-- Статистика выполнения -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; color: #6b7280;">Всего выполнений</div>
            <div style="font-size: 28px; font-weight: bold; margin-top: 5px;">
                {{ $function->executions()->count() }}
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; color: #6b7280;">Успешных</div>
            <div style="font-size: 28px; font-weight: bold; margin-top: 5px; color: #10b981;">
                {{ $function->executions()->where('status', 'success')->count() }}
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; color: #6b7280;">С ошибками</div>
            <div style="font-size: 28px; font-weight: bold; margin-top: 5px; color: #ef4444;">
                {{ $function->executions()->where('status', 'failed')->count() }}
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; color: #6b7280;">Последнее выполнение</div>
            <div style="font-size: 14px; margin-top: 5px;">
                @if($lastExecution = $function->executions()->latest()->first())
                    {{ $lastExecution->executed_at->diffForHumans() }}
                @else
                    Не выполнялась
                @endif
            </div>
        </div>
    </div>

    <!-- Детали функции -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        <!-- Левая колонка -->
        <div>
            <!-- Действия -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">Действия</h3>
                @foreach($function->actions as $action)
                    <div style="padding: 15px; background: #f9fafb; border-radius: 6px; margin-bottom: 10px;">
                        <div style="font-weight: 500;">{{ ucfirst($action->type) }} ({{ $action->provider }})</div>
                        @if($action->field_mapping)
                            <div style="margin-top: 10px;">
                                <strong>Маппинг полей:</strong>
                                @foreach($action->field_mapping as $mapping)
                                    <div style="margin-left: 20px; margin-top: 5px; font-size: 14px;">
                                        • {{ $mapping['crm_field'] }} ← 
                                        @if($mapping['source_type'] === 'parameter')
                                            Параметр: {{ $mapping['value'] }}
                                        @elseif($mapping['source_type'] === 'static')
                                            Значение: {{ $mapping['value'] }}
                                        @else
                                            {{ $mapping['value'] }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- История выполнения -->
            <div style="background: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin-bottom: 15px;">История выполнения</h3>
                @forelse($function->executions()->latest()->take(5)->get() as $execution)
                    <div style="padding: 10px; border-bottom: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                @if($execution->status === 'success')
                                    <span style="color: #10b981;">✓ Успешно</span>
                                @else
                                    <span style="color: #ef4444;">✗ Ошибка</span>
                                @endif
                                <span style="margin-left: 10px; color: #6b7280; font-size: 14px;">
                                    {{ $execution->executed_at->format('d.m.Y H:i:s') }}
                                </span>
                            </div>
                            <a href="#" style="color: #6366f1; font-size: 14px;">Подробнее →</a>
                        </div>
                        @if($execution->error_message)
                            <div style="margin-top: 5px; color: #ef4444; font-size: 14px;">
                                {{ $execution->error_message }}
                            </div>
                        @endif
                    </div>
                @empty
                    <p style="color: #9ca3af;">Функция еще не выполнялась</p>
                @endforelse
            </div>
        </div>

        <!-- Правая колонка -->
        <div>
            <!-- Параметры -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">Параметры</h3>
                @forelse($function->parameters as $param)
                    <div style="margin-bottom: 15px;">
                        <div style="font-weight: 500;">{{ $param->name }}</div>
                        <div style="font-size: 14px; color: #6b7280;">
                            Код: {{ $param->code }} | 
                            Тип: {{ $param->type }}
                            @if($param->is_required)
                                | <span style="color: #ef4444;">Обязательный</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p style="color: #9ca3af;">Параметры не заданы</p>
                @endforelse
            </div>

            <!-- Триггер -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">Триггер</h3>
                <div style="padding: 10px; background: #f9fafb; border-radius: 6px;">
                    <div style="font-weight: 500;">{{ ucfirst($function->trigger_type) }}</div>
                    @if($function->trigger_keywords)
                        <div style="margin-top: 10px;">
                            @foreach($function->trigger_keywords as $keyword)
                                <span style="display: inline-block; padding: 4px 8px; background: white; border: 1px solid #d1d5db; border-radius: 4px; margin: 2px; font-size: 14px;">
                                    {{ $keyword }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Поведение -->
            <div style="background: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin-bottom: 15px;">Поведение</h3>
                @if($function->behavior)
                    <div style="font-size: 14px;">
                        <p style="margin-bottom: 10px;">
                            <strong>При успехе:</strong> {{ ucfirst($function->behavior->on_success) }}
                        </p>
                        <p style="margin-bottom: 10px;">
                            <strong>При ошибке:</strong> {{ ucfirst($function->behavior->on_error) }}
                        </p>
                        @if($function->behavior->success_message)
                            <p style="margin-bottom: 10px;">
                                <strong>Сообщение успеха:</strong><br>
                                <span style="color: #6b7280;">{{ $function->behavior->success_message }}</span>
                            </p>
                        @endif
                        @if($function->behavior->error_message)
                            <p>
                                <strong>Сообщение ошибки:</strong><br>
                                <span style="color: #6b7280;">{{ $function->behavior->error_message }}</span>
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection