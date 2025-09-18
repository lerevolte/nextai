@extends('layouts.app')

@section('title', 'База знаний')

@section('content')
<div style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="font-size: 24px; font-weight: bold;">База знаний</h2>
            <p style="color: #6b7280; margin-top: 5px;">Бот: {{ $bot->name }}</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('knowledge.create', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center;">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Добавить материал
            </a>
            <a href="{{ route('knowledge.import', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center;">
                📥 Импорт документов
            </a>
            <a href="{{ route('knowledge.sources.index', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #8b5cf6; color: white; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center;">
                🔄 Источники
            </a>
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

    @if(session('error'))
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            ✗ {{ session('error') }}
        </div>
    @endif

    <!-- Статистика -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Всего материалов</div>
            <div style="font-size: 32px; font-weight: bold; color: #111827; margin-top: 5px;">
                {{ $knowledgeBase ? $knowledgeBase->getItemsCount() : 0 }}
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Активных</div>
            <div style="font-size: 32px; font-weight: bold; color: #10b981; margin-top: 5px;">
                {{ $knowledgeBase ? $knowledgeBase->getActiveItemsCount() : 0 }}
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: #6b7280; font-size: 14px;">Общий объем</div>
            <div style="font-size: 32px; font-weight: bold; color: #6366f1; margin-top: 5px;">
                {{ $knowledgeBase ? number_format($knowledgeBase->getTotalCharacters()) : 0 }}
            </div>
            <div style="color: #6b7280; font-size: 12px;">символов</div>
        </div>
    </div>

    <!-- Список материалов -->
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @forelse($items as $item)
            <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; hover: background: #f9fafb;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1; margin-right: 20px;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 20px; margin-right: 10px;">{{ $item->getTypeIcon() }}</span>
                            <h3 style="font-size: 18px; font-weight: 600; color: #111827;">
                                {{ $item->title }}
                            </h3>
                        </div>
                        
                        <p style="color: #6b7280; margin-bottom: 15px; line-height: 1.5;">
                            {{ Str::limit($item->content, 300) }}
                        </p>
                        
                        <div style="display: flex; gap: 20px; font-size: 14px; color: #9ca3af; align-items: center;">
                            <span style="display: flex; align-items: center;">
                                <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                {{ $item->getTypeName() }}
                            </span>
                            <span style="display: flex; align-items: center;">
                                <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ $item->getWordCount() }} слов
                            </span>
                            <span style="display: flex; align-items: center;">
                                <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                {{ $item->created_at->format('d.m.Y') }}
                            </span>
                            <span style="padding: 4px 12px; background: {{ $item->is_active ? '#d1fae5' : '#fee2e2' }}; color: {{ $item->is_active ? '#065f46' : '#991b1b' }}; border-radius: 20px; font-size: 12px; font-weight: 500;">
                                {{ $item->is_active ? '● Активно' : '○ Неактивно' }}
                            </span>
                        </div>
                        
                        @if($item->source_url)
                            <div style="margin-top: 10px;">
                                <a href="{{ $item->source_url }}" target="_blank" style="color: #6366f1; font-size: 14px; text-decoration: none;">
                                    🔗 {{ parse_url($item->source_url, PHP_URL_HOST) }}
                                </a>
                            </div>
                        @endif
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <a href="{{ route('knowledge.edit', [$organization, $bot, $item->id]) }}" 
                           style="padding: 8px 16px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center;">
                            <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Изменить
                        </a>
                        <form method="POST" action="{{ route('knowledge.destroy', [$organization, $bot, $item->id]) }}" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Удалить этот материал из базы знаний?')" 
                                    style="padding: 8px 16px; background: #fee2e2; color: #991b1b; border: none; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center;">
                                <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Удалить
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div style="padding: 60px; text-align: center;">
                <svg style="width: 64px; height: 64px; margin: 0 auto 20px; color: #d1d5db;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p style="color: #6b7280; font-size: 18px; margin-bottom: 10px;">База знаний пуста</p>
                <p style="color: #9ca3af; margin-bottom: 20px;">Добавьте материалы, чтобы бот мог использовать их для ответов</p>
                <a href="{{ route('knowledge.create', [$organization, $bot]) }}" 
                   style="padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                    Добавить первый материал
                </a>
            </div>
        @endforelse
    </div>

    @if($items->hasPages())
        <div style="margin-top: 20px;">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection