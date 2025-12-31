@extends('layouts.app')

@section('title', 'Диалоги')

@section('content')
<div style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="font-size: 24px; font-weight: bold;">Диалоги</h2>
            <p style="color: #6b7280; margin-top: 5px;">Бот: {{ $bot->name }}</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <select id="status-filter" onchange="filterConversations()" 
                    style="padding: 10px 15px; border: 1px solid #d1d5db; border-radius: 5px; background: white;">
                <option value="">Все статусы</option>
                <option value="active">Активные</option>
                <option value="waiting_operator">Ждут оператора</option>
                <option value="closed">Закрытые</option>
            </select>
            <a href="{{ route('bots.show', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 5px;">
                ← Назад к боту
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 15px; background: #d1fae5; border: 1px solid #10b981; color: #065f46; border-radius: 5px; margin-bottom: 20px;">
            ✓ {{ session('success') }}
        </div>
    @endif

    <!-- Статистика -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Всего диалогов</div>
            <div style="font-size: 24px; font-weight: bold; color: #111827; margin-top: 5px;">
                {{ $conversations->total() }}
            </div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Активных</div>
            <div style="font-size: 24px; font-weight: bold; color: #10b981; margin-top: 5px;">
                {{ $conversations->where('status', 'active')->count() }}
            </div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Ждут оператора</div>
            <div style="font-size: 24px; font-weight: bold; color: #f59e0b; margin-top: 5px;">
                {{ $conversations->where('status', 'waiting_operator')->count() }}
            </div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Закрытых</div>
            <div style="font-size: 24px; font-weight: bold; color: #6b7280; margin-top: 5px;">
                {{ $conversations->where('status', 'closed')->count() }}
            </div>
        </div>
    </div>

    <!-- Список диалогов -->
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f9fafb;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">
                        Пользователь
                    </th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">
                        Канал
                    </th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; font-size: 14px;">
                        Сообщений
                    </th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; font-size: 14px;">
                        Статус
                    </th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">
                        Последняя активность
                    </th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; font-size: 14px;">
                        Действия
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversations as $conversation)
                <tr style="border-top: 1px solid #e5e7eb; hover: background-color: #f9fafb;" class="conversation-row">
                    <td style="padding: 12px;">
                        <div style="font-weight: 500; color: #111827;">
                            {{ $conversation->getUserDisplayName() }}
                        </div>
                        @if($conversation->user_email)
                            <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                                📧 {{ $conversation->user_email }}
                            </div>
                        @endif
                        @if($conversation->user_phone)
                            <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                                📱 {{ $conversation->user_phone }}
                            </div>
                        @endif
                    </td>
                    <td style="padding: 12px;">
                        <div style="display: flex; align-items: center;">
                            <span style="font-size: 18px; margin-right: 8px;">
                                {{ $conversation->channel->getIcon() }}
                            </span>
                            <span style="color: #374151;">
                                {{ $conversation->channel->getTypeName() }}
                            </span>
                        </div>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="padding: 4px 12px; background: #f3f4f6; border-radius: 20px; font-size: 14px; font-weight: 500;">
                            {{ $conversation->messages_count }}
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        @if($conversation->status == 'active')
                            <span style="padding: 6px 12px; background: #d1fae5; color: #065f46; border-radius: 20px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center;">
                                <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; margin-right: 6px;"></span>
                                Активен
                            </span>
                        @elseif($conversation->status == 'waiting_operator')
                            <span style="padding: 6px 12px; background: #fef3c7; color: #92400e; border-radius: 20px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center;">
                                <span style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%; margin-right: 6px; animation: pulse 2s infinite;"></span>
                                Ждет оператора
                            </span>
                        @else
                            <span style="padding: 6px 12px; background: #f3f4f6; color: #6b7280; border-radius: 20px; font-size: 13px; font-weight: 500;">
                                Закрыт
                            </span>
                        @endif
                    </td>
                    <td style="padding: 12px;">
                        <div style="color: #374151;">
                            {{ $conversation->last_message_at ? $conversation->last_message_at->diffForHumans() : 'Нет сообщений' }}
                        </div>
                        <div style="font-size: 12px; color: #9ca3af; margin-top: 2px;">
                            {{ $conversation->last_message_at ? $conversation->last_message_at->format('d.m.Y H:i') : '' }}
                        </div>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <a href="{{ route('conversations.show', [$organization, $bot, $conversation]) }}" 
                           style="padding: 6px 12px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; font-size: 13px; display: inline-block;">
                            Открыть →
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding: 60px; text-align: center;">
                        <svg style="width: 48px; height: 48px; margin: 0 auto 20px; color: #d1d5db;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p style="color: #6b7280; font-size: 16px;">Пока нет диалогов</p>
                        <p style="color: #9ca3af; font-size: 14px; margin-top: 5px;">Диалоги появятся здесь после первого обращения к боту</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($conversations->hasPages())
        <div style="margin-top: 20px;">
            {{ $conversations->links() }}
        </div>
    @endif
</div>

<style>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.conversation-row:hover {
    background-color: #f9fafb;
}
</style>

<script>
function filterConversations() {
    const status = document.getElementById('status-filter').value;
    if (status) {
        window.location.href = window.location.pathname + '?status=' + status;
    } else {
        window.location.href = window.location.pathname;
    }
}
</script>
@endsection