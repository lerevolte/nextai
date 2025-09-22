@extends('layouts.app')

@section('title', 'A/B Тестирование')

@section('content')
<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    {{-- Заголовок --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 28px; font-weight: bold; color: #111827;">🧪 A/B Тестирование</h1>
            <p style="color: #6b7280; margin-top: 5px;">Оптимизируйте промпты и настройки ботов</p>
        </div>
        
        <a href="{{ route('ab-tests.create', $organization) }}" 
           style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
            ➕ Создать тест
        </a>
    </div>

    {{-- Список тестов --}}
    <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
        @if($tests->count() > 0)
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280;">НАЗВАНИЕ</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280;">БОТ</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">ТИП</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">УЧАСТНИКИ</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">СТАТУС</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">ДЕЙСТВИЯ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tests as $test)
                    <tr style="border-top: 1px solid #e5e7eb;">
                        <td style="padding: 15px;">
                            <div style="font-weight: 500; color: #111827;">{{ $test->name }}</div>
                            @if($test->description)
                                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">{{ Str::limit($test->description, 50) }}</div>
                            @endif
                        </td>
                        <td style="padding: 15px;">
                            <a href="{{ route('bots.show', [$organization, $test->bot]) }}" 
                               style="color: #6366f1; text-decoration: none;">
                                {{ $test->bot->name }}
                            </a>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <span style="padding: 4px 8px; background: #f3f4f6; border-radius: 4px; font-size: 12px;">
                                {{ ucfirst($test->type) }}
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            {{ $test->variants->sum('participants') }}
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            @if($test->status == 'active')
                                <span style="padding: 4px 8px; background: #d1fae5; color: #065f46; border-radius: 4px; font-size: 12px;">
                                    ● Активен
                                </span>
                            @elseif($test->status == 'draft')
                                <span style="padding: 4px 8px; background: #fef3c7; color: #92400e; border-radius: 4px; font-size: 12px;">
                                    ○ Черновик
                                </span>
                            @elseif($test->status == 'completed')
                                <span style="padding: 4px 8px; background: #e5e7eb; color: #374151; border-radius: 4px; font-size: 12px;">
                                    ✓ Завершен
                                </span>
                            @else
                                <span style="padding: 4px 8px; background: #fee2e2; color: #991b1b; border-radius: 4px; font-size: 12px;">
                                    ⏸ Приостановлен
                                </span>
                            @endif
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                <a href="{{ route('ab-tests.show', [$organization, $test]) }}" 
                                   style="padding: 5px 10px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                    Детали
                                </a>
                                @if($test->status == 'active')
                                    <form method="POST" action="{{ route('ab-tests.pause', [$organization, $test]) }}" style="margin: 0;">
                                        @csrf
                                        <button type="submit" 
                                                style="padding: 5px 10px; background: #f59e0b; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">
                                            Пауза
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 60px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px;">🧪</div>
                <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 10px;">
                    Нет A/B тестов
                </h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Создайте первый тест для оптимизации работы ваших ботов
                </p>
                <a href="{{ route('ab-tests.create', $organization) }}" 
                   style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 8px; display: inline-block;">
                    Создать первый тест
                </a>
            </div>
        @endif
    </div>

    @if($tests->hasPages())
        <div style="margin-top: 20px;">
            {{ $tests->links() }}
        </div>
    @endif
</div>
@endsection