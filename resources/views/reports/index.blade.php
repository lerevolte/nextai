@extends('layouts.app')

@section('title', 'Отчеты')

@section('content')
<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    {{-- Заголовок --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 28px; font-weight: bold; color: #111827;">📊 Отчеты и аналитика</h1>
            <p style="color: #6b7280; margin-top: 5px;">Генерируйте детальные отчеты о работе ботов</p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button onclick="showGenerateModal()" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer;">
                📥 Сгенерировать отчет
            </button>
            <button onclick="showScheduleModal()" 
                    style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">
                ⏰ Запланировать
            </button>
        </div>
    </div>

    {{-- Быстрая генерация --}}
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Быстрая генерация</h3>
        <form method="POST" action="{{ route('reports.generate', $organization) }}" style="display: flex; gap: 10px; align-items: end;">
            @csrf
            <div>
                <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px;">Период</label>
                <select name="period" style="padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="7">7 дней</option>
                    <option value="30" selected>30 дней</option>
                    <option value="90">90 дней</option>
                    <option value="365">Год</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px;">Формат</label>
                <select name="format" style="padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px;">Бот</label>
                <select name="bot_id" style="padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="">Все боты</option>
                    @foreach($organization->bots as $bot)
                        <option value="{{ $bot->id }}">{{ $bot->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <button type="submit" 
                    style="padding: 8px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                Сгенерировать
            </button>
        </form>
    </div>

    {{-- Запланированные отчеты --}}
    @if($scheduledReports->count() > 0)
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">📅 Запланированные отчеты</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
            @foreach($scheduledReports as $report)
            <div style="padding: 15px; border: 1px solid #e5e7eb; border-radius: 6px;">
                <div style="font-weight: 500; color: #111827;">{{ $report->name }}</div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                    {{ ucfirst($report->frequency) }} • {{ strtoupper($report->format) }}
                </div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;">
                    Следующий запуск: {{ $report->next_run_at ? \Carbon\Carbon::parse($report->next_run_at)->format('d.m.Y H:i') : 'Не запланирован' }}
                </div>
                <form method="POST" action="{{ route('reports.scheduled.delete', [$organization, $report]) }}" style="margin-top: 10px;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            style="padding: 4px 8px; background: #fee2e2; color: #991b1b; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">
                        Удалить
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- История отчетов --}}
    <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
            <h3 style="font-size: 16px; font-weight: 600;">📂 История отчетов</h3>
        </div>
        
        @if($generatedReports->count() > 0)
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280;">НАЗВАНИЕ</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">ФОРМАТ</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">РАЗМЕР</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280;">СОЗДАН</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #6b7280;">ДЕЙСТВИЯ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($generatedReports as $report)
                    <tr style="border-top: 1px solid #e5e7eb;">
                        <td style="padding: 15px;">
                            <div style="font-weight: 500; color: #111827;">{{ $report->name }}</div>
                            @if($report->scheduledReport)
                                <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                                    Автоматический
                                </div>
                            @endif
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <span style="padding: 4px 8px; background: 
                                {{ $report->format == 'pdf' ? '#fee2e2' : 
                                   ($report->format == 'excel' ? '#d1fae5' : '#fef3c7') }}; 
                                color: 
                                {{ $report->format == 'pdf' ? '#991b1b' : 
                                   ($report->format == 'excel' ? '#065f46' : '#92400e') }}; 
                                border-radius: 4px; font-size: 12px;">
                                {{ strtoupper($report->format) }}
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            {{ number_format($report->file_size / 1024, 1) }} KB
                        </td>
                        <td style="padding: 15px;">
                            {{ \Carbon\Carbon::parse($report->generated_at)->format('d.m.Y H:i') }}
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <a href="{{ route('reports.download', [$organization, $report]) }}" 
                               style="padding: 5px 10px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                Скачать
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 60px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px;">📊</div>
                <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 10px;">
                    Нет сгенерированных отчетов
                </h3>
                <p style="color: #6b7280;">
                    Сгенерируйте первый отчет для анализа работы ботов
                </p>
            </div>
        @endif
    </div>

    @if($generatedReports->hasPages())
        <div style="margin-top: 20px;">
            {{ $generatedReports->links() }}
        </div>
    @endif
</div>

{{-- Модальное окно генерации --}}
<div id="generateModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin-bottom: 20px;">Генерация отчета</h3>
        <!-- Форма генерации -->
        <button onclick="document.getElementById('generateModal').style.display='none'" 
                style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 20px; cursor: pointer;">
            ✕
        </button>
    </div>
</div>

<script>
function showGenerateModal() {
    document.getElementById('generateModal').style.display = 'block';
}

function showScheduleModal() {
    // Реализация модального окна для планирования
}
</script>
@endsection