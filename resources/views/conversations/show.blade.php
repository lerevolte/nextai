@extends('layouts.app')

@section('title', 'Диалог #' . $conversation->id)

@section('content')
<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="font-size: 24px; font-weight: bold;">Диалог #{{ $conversation->id }}</h2>
            <p style="color: #6b7280; margin-top: 5px;">
                {{ $conversation->getUserDisplayName() }} • {{ $conversation->channel->getTypeName() }}
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            @if($conversation->status == 'active')
                <form method="POST" action="{{ route('conversations.takeover', [$organization, $bot, $conversation]) }}" style="margin: 0;">
                    @csrf
                    <button type="submit" 
                            style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 500;">
                        👤 Взять управление
                    </button>
                </form>
            @endif
            
            @if($conversation->status != 'closed')
                <form method="POST" action="{{ route('conversations.close', [$organization, $bot, $conversation]) }}" style="margin: 0;">
                    @csrf
                    <button type="submit" onclick="return confirm('Закрыть этот диалог?')"
                            style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 500;">
                        ✕ Закрыть диалог
                    </button>
                </form>
            @endif
            
            <a href="{{ route('conversations.index', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 5px; font-weight: 500;">
                ← Назад к списку
            </a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
        <!-- Сообщения -->
        <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; flex-direction: column; height: 700px;">
            <div style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-size: 18px; font-weight: 600;">История сообщений</h3>
            </div>
            
            <div style="flex: 1; overflow-y: auto; padding: 20px;" id="messages-container">
                @foreach($messages as $message)
                    <div style="margin-bottom: 20px; animation: fadeIn 0.3s;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <!-- Аватар -->
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: {{ $message->role == 'user' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : ($message->role == 'assistant' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : ($message->role == 'operator' ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : '#e5e7eb')) }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                @if($message->role == 'user')
                                    <svg style="width: 20px; height: 20px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                @elseif($message->role == 'assistant')
                                    <svg style="width: 20px; height: 20px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                @elseif($message->role == 'operator')
                                    <svg style="width: 20px; height: 20px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @else
                                    <svg style="width: 20px; height: 20px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
                            </div>
                            
                            <!-- Содержимое -->
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="font-weight: 600; color: {{ $message->role == 'user' ? '#4c1d95' : ($message->role == 'assistant' ? '#059669' : ($message->role == 'operator' ? '#d97706' : '#6b7280')) }};">
                                        {{ $message->getRoleName() }}
                                        @if($message->role == 'operator' && isset($message->metadata['operator_name']))
                                            ({{ $message->metadata['operator_name'] }})
                                        @endif
                                    </span>
                                    <span style="font-size: 12px; color: #9ca3af;">
                                        {{ $message->created_at->format('d.m.Y H:i:s') }}
                                    </span>
                                    @if($message->response_time)
                                        <span style="font-size: 12px; color: #9ca3af; padding: 2px 8px; background: #f3f4f6; border-radius: 10px;">
                                            ⚡ {{ round($message->response_time, 2) }}с
                                        </span>
                                    @endif
                                </div>
                                <div style="padding: 12px 16px; background: {{ $message->role == 'user' ? '#f0f4ff' : '#f9fafb' }}; border-radius: 12px; white-space: pre-wrap; line-height: 1.5; color: #111827;">{{ $message->content }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($conversation->status != 'closed')
            <!-- Форма отправки сообщения (для тестирования) -->
            <div style="padding: 20px; border-top: 1px solid #e5e7eb; background: #f9fafb;">
                <form method="POST" action="{{ route('conversations.show', [$organization, $bot, $conversation]) }}" style="display: flex; gap: 10px;">
                    @csrf
                    <input type="text" name="message" placeholder="Введите тестовое сообщение..." required
                           style="flex: 1; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    <button type="submit" name="as_operator" value="1"
                            style="padding: 12px 20px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                        Отправить как оператор
                    </button>
                </form>
            </div>
            @endif
        </div>

        <!-- Информационная панель -->
        <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; height: fit-content;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 20px;">Информация о диалоге</h3>
            
            <div style="space-y: 15px;">
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Статус</label>
                    <div style="margin-top: 5px;">
                        @if($conversation->status == 'active')
                            <span style="padding: 8px 16px; background: #d1fae5; color: #065f46; border-radius: 6px; font-weight: 500; display: inline-block;">
                                ● Активен
                            </span>
                        @elseif($conversation->status == 'waiting_operator')
                            <span style="padding: 8px 16px; background: #fef3c7; color: #92400e; border-radius: 6px; font-weight: 500; display: inline-block;">
                                ● Ждет оператора
                            </span>
                        @else
                            <span style="padding: 8px 16px; background: #f3f4f6; color: #6b7280; border-radius: 6px; font-weight: 500; display: inline-block;">
                                ○ Закрыт
                            </span>
                        @endif
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Канал</label>
                    <div class="flex" style="margin-top: 5px; font-weight: 500; color: #111827;">
                        {!! $conversation->channel->getIcon() !!} {{ $conversation->channel->getTypeName() }}
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Пользователь</label>
                    <div style="margin-top: 5px; font-weight: 500; color: #111827;">
                        {{ $conversation->getUserDisplayName() }}
                    </div>
                    @if($conversation->user_email)
                        <div style="font-size: 14px; color: #6b7280; margin-top: 2px;">
                            📧 {{ $conversation->user_email }}
                        </div>
                    @endif
                    @if($conversation->user_phone)
                        <div style="font-size: 14px; color: #6b7280; margin-top: 2px;">
                            📱 {{ $conversation->user_phone }}
                        </div>
                    @endif
                </div>

                <div style="padding: 15px; background: #f9fafb; border-radius: 6px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="font-size: 12px; color: #6b7280;">Сообщений</label>
                            <div style="font-size: 20px; font-weight: bold; color: #111827; margin-top: 2px;">
                                {{ $conversation->messages_count }}
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6b7280;">Длительность</label>
                            <div style="font-size: 20px; font-weight: bold; color: #111827; margin-top: 2px;">
                                {{ $conversation->getDuration() }}
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Начат</label>
                    <div style="margin-top: 5px; color: #374151;">
                        {{ $conversation->created_at->format('d.m.Y в H:i') }}
                    </div>
                </div>

                @if($conversation->closed_at)
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Закрыт</label>
                    <div style="margin-top: 5px; color: #374151;">
                        {{ $conversation->closed_at->format('d.m.Y в H:i') }}
                    </div>
                </div>
                @endif

                @if($conversation->ai_tokens_used)
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Токенов использовано</label>
                    <div style="margin-top: 5px; font-weight: 500; color: #111827;">
                        {{ number_format($conversation->ai_tokens_used) }}
                    </div>
                </div>
                @endif

                @if($conversation->getAverageResponseTime() > 0)
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Среднее время ответа</label>
                    <div style="margin-top: 5px; font-weight: 500; color: #111827;">
                        {{ $conversation->getAverageResponseTime() }} сек
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Автоматическая прокрутка к последнему сообщению
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
});
</script>
@endsection