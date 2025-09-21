@extends('layouts.app')

@section('title', 'A/B тест: ' . $test->name)

@section('content')
<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    {{-- Заголовок --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 28px; font-weight: bold; color: #111827; margin-bottom: 10px;">
                {{ $test->name }}
            </h1>
            <p style="color: #6b7280;">
                {{ $test->bot->name }} • {{ ucfirst($test->type) }} тест
                @if($test->description)
                    • {{ $test->description }}
                @endif
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <span style="padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500;
                background: {{ $test->status == 'active' ? '#d1fae5' : 
                            ($test->status == 'completed' ? '#dbeafe' : 
                            ($test->status == 'paused' ? '#fef3c7' : '#f3f4f6')) }}; 
                color: {{ $test->status == 'active' ? '#065f46' : 
                        ($test->status == 'completed' ? '#1e40af' : 
                        ($test->status == 'paused' ? '#92400e' : '#6b7280')) }};">
                {{ ucfirst($test->status) }}
            </span>
            
            @if($test->status == 'active')
                <button onclick="pauseTest()" 
                        style="padding: 8px 16px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    ⏸ Приостановить
                </button>
                <button onclick="completeTest()" 
                        style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    ✓ Завершить тест
                </button>
            @endif
        </div>
    </div>

    {{-- Основная информация --}}
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Начало теста</div>
                <div style="font-size: 16px; font-weight: 500;">{{ $test->starts_at->format('d.m.Y H:i') }}</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Окончание</div>
                <div style="font-size: 16px; font-weight: 500;">
                    {{ $test->ends_at ? $test->ends_at->format('d.m.Y H:i') : 'Не установлено' }}
                </div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Трафик теста</div>
                <div style="font-size: 16px; font-weight: 500;">{{ $test->traffic_percentage }}%</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Мин. выборка</div>
                <div style="font-size: 16px; font-weight: 500;">{{ $test->min_sample_size }}</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Уровень доверия</div>
                <div style="font-size: 16px; font-weight: 500;">{{ $test->confidence_level }}%</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;">Всего участников</div>
                <div style="font-size: 16px; font-weight: 500;">{{ $analysis['total_participants'] }}</div>
            </div>
        </div>
    </div>

    {{-- Варианты теста --}}
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Варианты теста</h2>
        
        <div style="display: grid; gap: 20px;">
            @foreach($test->variants as $variant)
                <div style="border: 2px solid {{ $variant->is_control ? '#dbeafe' : '#f3f4f6' }}; 
                            border-radius: 8px; padding: 20px; position: relative;
                            {{ $analysis['winner'] && $analysis['winner']['id'] == $variant->id ? 'background: #fef3c7;' : '' }}">
                    
                    @if($variant->is_control)
                        <span style="position: absolute; top: 10px; right: 10px; padding: 4px 12px; 
                                   background: #dbeafe; color: #1e40af; border-radius: 12px; font-size: 12px;">
                            Контроль
                        </span>
                    @endif
                    
                    @if($analysis['winner'] && $analysis['winner']['id'] == $variant->id)
                        <span style="position: absolute; top: 10px; right: 10px; padding: 4px 12px; 
                                   background: #fbbf24; color: #78350f; border-radius: 12px; font-size: 12px;">
                            🏆 Лидер
                        </span>
                    @endif
                    
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">
                        {{ $variant->name }}
                    </h3>
                    
                    @if($variant->description)
                        <p style="color: #6b7280; margin-bottom: 15px;">{{ $variant->description }}</p>
                    @endif
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <div>
                            <div style="color: #6b7280; font-size: 13px;">Участники</div>
                            <div style="font-size: 20px; font-weight: bold;">{{ $variant->participants }}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 13px;">Конверсии</div>
                            <div style="font-size: 20px; font-weight: bold;">{{ $variant->conversions }}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 13px;">Конверсия</div>
                            <div style="font-size: 20px; font-weight: bold; color: #10b981;">
                                {{ number_format($variant->conversion_rate, 2) }}%
                            </div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 13px;">Трафик</div>
                            <div style="font-size: 20px; font-weight: bold;">
                                {{ $variant->traffic_allocation }}%
                            </div>
                        </div>
                    </div>
                    
                    @if($variant->metrics)
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                                @if(isset($variant->metrics['avg_messages']))
                                    <div>
                                        <span style="color: #6b7280; font-size: 12px;">Ср. сообщений:</span>
                                        <strong>{{ $variant->metrics['avg_messages'] }}</strong>
                                    </div>
                                @endif
                                @if(isset($variant->metrics['avg_response_time']))
                                    <div>
                                        <span style="color: #6b7280; font-size: 12px;">Ср. время ответа:</span>
                                        <strong>{{ $variant->metrics['avg_response_time'] }}с</strong>
                                    </div>
                                @endif
                                @if(isset($variant->metrics['transfer_rate']))
                                    <div>
                                        <span style="color: #6b7280; font-size: 12px;">Передача оператору:</span>
                                        <strong>{{ $variant->metrics['transfer_rate'] }}%</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    @php
                        $variantAnalysis = collect($analysis['variants'])->firstWhere('id', $variant->id);
                    @endphp
                    
                    @if($variantAnalysis && !$variant->is_control)
                        <div style="margin-top: 15px; padding: 10px; background: #f9fafb; border-radius: 6px;">
                            @if($variantAnalysis['statistical_significance'] ?? null)
                                <div style="margin-bottom: 5px;">
                                    <span style="color: #6b7280; font-size: 12px;">Стат. значимость:</span>
                                    <strong style="color: {{ $variantAnalysis['statistical_significance'] >= $test->confidence_level ? '#10b981' : '#f59e0b' }};">
                                        {{ $variantAnalysis['statistical_significance'] }}%
                                    </strong>
                                </div>
                            @endif
                            @if($variantAnalysis['improvement'] ?? null)
                                <div>
                                    <span style="color: #6b7280; font-size: 12px;">Улучшение:</span>
                                    <strong style="color: {{ $variantAnalysis['improvement'] > 0 ? '#10b981' : '#ef4444' }};">
                                        {{ $variantAnalysis['improvement'] > 0 ? '+' : '' }}{{ $variantAnalysis['improvement'] }}%
                                    </strong>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Рекомендации --}}
    @if(!empty($analysis['recommendations']))
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Рекомендации</h2>
            
            @foreach($analysis['recommendations'] as $recommendation)
                <div style="padding: 15px; margin-bottom: 10px; border-radius: 6px;
                            background: {{ $recommendation['type'] == 'success' ? '#d1fae5' : 
                                         ($recommendation['type'] == 'warning' ? '#fef3c7' : 
                                         ($recommendation['type'] == 'action' ? '#dbeafe' : '#f3f4f6')) }};">
                    <p style="margin: 0; color: {{ $recommendation['type'] == 'success' ? '#065f46' : 
                                                  ($recommendation['type'] == 'warning' ? '#92400e' : 
                                                  ($recommendation['type'] == 'action' ? '#1e40af' : '#374151')) }};">
                        {{ $recommendation['message'] }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Действия --}}
    <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <a href="{{ route('ab-tests.analysis', [$organization, $test]) }}" 
           style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px;">
            📊 Детальный анализ
        </a>
        <a href="{{ route('ab-tests.index', $organization) }}" 
           style="padding: 10px 20px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 6px;">
            ← К списку тестов
        </a>
    </div>
</div>

<script>
function pauseTest() {
    if (confirm('Приостановить этот тест?')) {
        fetch('{{ route('ab-tests.pause', [$organization, $test]) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        }).then(response => {
            if (response.ok) {
                window.location.reload();
            }
        });
    }
}

function completeTest() {
    if (confirm('Завершить этот тест? После завершения тест нельзя будет возобновить.')) {
        // Здесь можно добавить модальное окно для выбора победителя
        fetch('{{ route('ab-tests.complete', [$organization, $test]) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                winner_variant_id: {{ $analysis['winner']['id'] ?? 'null' }},
                apply_winner: false
            })
        }).then(response => {
            if (response.ok) {
                window.location.reload();
            }
        });
    }
}
</script>
@endsection