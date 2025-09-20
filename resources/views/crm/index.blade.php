@extends('layouts.app')

@section('title', 'Интеграции')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Интеграции</h2>
            <a href="{{ route('crm.create', $organization) }}" 
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                Добавить интеграцию
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($integrations as $integration)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <span class="text-3xl mr-3">{{ $integration->getIcon() }}</span>
                            <div>
                                <h3 class="text-lg font-semibold">{{ $integration->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $integration->getTypeName() }}</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full {{ $integration->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $integration->is_active ? 'Активна' : 'Неактивна' }}
                        </span>
                    </div>

                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span>Подключено ботов:</span>
                            <span class="font-medium">{{ $integration->bots->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Синхронизировано:</span>
                            <span class="font-medium">{{ $integration->sync_entities_count }}</span>
                        </div>
                        @if($integration->last_sync_at)
                        <div class="flex justify-between">
                            <span>Последняя синхронизация:</span>
                            <span class="font-medium">{{ $integration->last_sync_at->diffForHumans() }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex flex-wrap gap-2">
                            @foreach($integration->bots as $bot)
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
                                {{ $bot->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 flex justify-between">
                        <a href="{{ route('crm.show', [$organization, $integration]) }}" 
                           class="text-indigo-600 hover:text-indigo-900">
                            Подробнее →
                        </a>
                        <div class="flex space-x-2">
                            <a href="{{ route('crm.edit', [$organization, $integration]) }}" 
                               class="text-gray-600 hover:text-gray-900">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <button onclick="testConnection({{ $integration->id }})" 
                                    class="text-blue-600 hover:text-blue-900">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            @if($integrations->isEmpty())
            <div class="col-span-full text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Нет интеграций</h3>
                <p class="mt-1 text-sm text-gray-500">Начните с добавления интеграции с вашей системой</p>
                <div class="mt-6">
                    <a href="{{ route('crm.create', $organization) }}" 
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Добавить интеграцию
                    </a>
                </div>
            </div>
            @endif
        </div>

        <!-- Доступные типы интеграций -->
        <div class="mt-12" style="display: none;">
            <h3 class="text-lg font-semibold mb-4">Доступные интеграции</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($availableTypes as $type => $info)
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2" style="display: none">{{ $info['icon'] }}</span>
                        <h4 class="font-medium">{{ $info['name'] }}</h4>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">{{ $info['description'] }}</p>
                    <div class="flex flex-wrap gap-1" style="display: none">
                        @foreach($info['features'] as $feature => $available)
                            @if($available)
                            <span class="px-2 py-1 bg-white text-gray-700 text-xs rounded">
                                {{ ucfirst($feature) }}
                            </span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function testConnection(integrationId) {
    fetch(`{{ url('/o/' . $organization->slug . '/crm') }}/${integrationId}/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('Ошибка при проверке подключения');
        console.error(error);
    });
}
</script>
@endpush
@endsection