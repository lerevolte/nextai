{{-- resources/views/organization/settings.blade.php --}}
@extends('layouts.app')

@section('title', 'Настройки организации')

@section('content')
<div style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h2 style="font-size: 24px; margin-bottom: 20px;">Настройки организации</h2>

    @if(session('success'))
        <div style="padding: 15px; background: #d1fae5; border: 1px solid #10b981; color: #065f46; border-radius: 5px; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form method="POST" action="{{ route('organization.update') }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название организации</label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Идентификатор (slug)</label>
                <input type="text" value="{{ $organization->slug }}" disabled
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; background: #f3f4f6;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Лимит ботов</label>
                    <input type="text" value="{{ $organization->bots_limit }}" disabled
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; background: #f3f4f6;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Лимит сообщений</label>
                    <input type="text" value="{{ number_format($organization->messages_limit) }}" disabled
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; background: #f3f4f6;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Статус</label>
                <span style="padding: 4px 12px; background: {{ $organization->is_active ? '#d1fae5' : '#fee2e2' }}; color: {{ $organization->is_active ? '#065f46' : '#991b1b' }}; border-radius: 4px;">
                    {{ $organization->is_active ? 'Активна' : 'Неактивна' }}
                </span>
            </div>


            <button type="submit" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Сохранить изменения
            </button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">API интеграции</h3>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">API ключ организации</label>
            <div class="flex items-center space-x-3">
                <input type="text" 
                       id="api-key-input"
                       value="{{ $organization->api_key }}" 
                       readonly
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-white font-mono text-sm">
                
                <button type="button" 
                        onclick="copyApiKey()"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Копировать
                </button>
                
                <button type="button"
                        onclick="regenerateApiKey()"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    Обновить
                </button>
            </div>
            
            <div class="mt-3 text-sm text-gray-600">
                <p>Используйте этот ключ для интеграции с внешними системами, например Битрикс24.</p>
                <p class="mt-1">⚠️ <strong>Важно:</strong> Не делитесь этим ключом публично!</p>
            </div>
        </div>
    </div>

    <script>
    function copyApiKey() {
        const input = document.getElementById('api-key-input');
        input.select();
        document.execCommand('copy');
        
        // Показываем уведомление
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✓ Скопировано';
        button.classList.add('bg-green-600', 'hover:bg-green-700');
        button.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('bg-green-600', 'hover:bg-green-700');
            button.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
        }, 2000);
    }

    function regenerateApiKey() {
        if (!confirm('Вы уверены? Все существующие интеграции с этим ключом перестанут работать!')) {
            return;
        }
        
        fetch('{{ route("organization.regenerate-api-key") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('api-key-input').value = data.api_key;
                alert('API ключ успешно обновлен');
            }
        });
    }
    </script>

    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="font-size: 18px; margin-bottom: 15px;">Статистика использования</h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <p style="color: #6b7280; font-size: 14px;">Ботов создано</p>
                <p style="font-size: 24px; font-weight: bold;">{{ $organization->bots()->count() }} / {{ $organization->bots_limit }}</p>
            </div>
            
            <div>
                <p style="color: #6b7280; font-size: 14px;">Сообщений в этом месяце</p>
                <p style="font-size: 24px; font-weight: bold;">{{ number_format($organization->getMessagesUsedThisMonth()) }} / {{ number_format($organization->messages_limit) }}</p>
            </div>
        </div>
    </div>
</div>
@endsection