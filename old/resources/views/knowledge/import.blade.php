@extends('layouts.app')

@section('title', 'Импорт документов')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 24px; font-weight: bold;">Импорт документов в базу знаний</h2>
        <p style="color: #6b7280; margin-top: 5px;">Бот: {{ $bot->name }}</p>
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

    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form method="POST" action="{{ route('knowledge.import.process', [$organization, $bot]) }}" 
              enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                    Выберите файл для импорта
                </label>
                <input type="file" name="file" required accept=".pdf,.doc,.docx,.txt,.md,.html,.csv,.xls,.xlsx"
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                <small style="color: #6b7280; display: block; margin-top: 5px;">
                    Поддерживаемые форматы: PDF, Word, Excel, HTML, CSV, TXT, Markdown (до 20MB)
                </small>
            </div>

            <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">Что будет сделано:</h4>
                <ul style="margin-left: 20px; color: #6b7280;">
                    <li>Документ будет проанализирован и разбит на логические части</li>
                    <li>Для каждой части будут созданы эмбеддинги для семантического поиска</li>
                    <li>Контент будет индексирован для полнотекстового поиска</li>
                    <li>Большие документы автоматически разбиваются на оптимальные фрагменты</li>
                </ul>
            </div>

            <button type="submit" 
                    style="padding: 12px 24px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                📥 Импортировать документ
            </button>
        </form>
    </div>

    <!-- Источники знаний -->
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 18px; font-weight: 600;">Автоматические источники</h3>
            <a href="{{ route('knowledge.sources.index', [$organization, $bot]) }}" 
               style="padding: 8px 16px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                Управление источниками →
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; cursor: pointer; hover: border-color: #6366f1;">
                <div style="font-size: 32px; margin-bottom: 10px;">📝</div>
                <h4 style="font-weight: 600; margin-bottom: 5px;">Notion</h4>
                <p style="color: #6b7280; font-size: 14px;">Синхронизация с базами данных Notion</p>
            </div>

            <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; cursor: pointer;">
                <div style="font-size: 32px; margin-bottom: 10px;">📁</div>
                <h4 style="font-weight: 600; margin-bottom: 5px;">Google Drive</h4>
                <p style="color: #6b7280; font-size: 14px;">Импорт документов из Google Drive</p>
            </div>

            <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; cursor: pointer;">
                <div style="font-size: 32px; margin-bottom: 10px;">🌐</div>
                <h4 style="font-weight: 600; margin-bottom: 5px;">Веб-страницы</h4>
                <p style="color: #6b7280; font-size: 14px;">Парсинг и обновление веб-контента</p>
            </div>

            <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; cursor: pointer;">
                <div style="font-size: 32px; margin-bottom: 10px;">🐙</div>
                <h4 style="font-weight: 600; margin-bottom: 5px;">GitHub</h4>
                <p style="color: #6b7280; font-size: 14px;">Синхронизация документации из репозиториев</p>
            </div>
        </div>
    </div>
</div>
@endsection