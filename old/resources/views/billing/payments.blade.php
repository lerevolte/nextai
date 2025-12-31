@extends('layouts.app')

@section('title', 'История платежей')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">История платежей</h2>
            <a href="{{ route('billing.deposit') }}" 
               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Пополнить баланс
            </a>
        </div>

        <!-- Фильтры -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('billing.payments') }}" class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Период</label>
                    <select name="period" onchange="this.form.submit()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="all" {{ request('period') == 'all' ? 'selected' : '' }}>Все время</option>
                        <option value="today" {{ request('period') == 'today' ? 'selected' : '' }}>Сегодня</option>
                        <option value="week" {{ request('period') == 'week' ? 'selected' : '' }}>Последняя неделя</option>
                        <option value="month" {{ request('period', 'month') == 'month' ? 'selected' : '' }}>Последний месяц</option>
                        <option value="year" {{ request('period') == 'year' ? 'selected' : '' }}>Последний год</option>
                    </select>
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                    <select name="status" onchange="this.form.submit()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="" {{ !request('status') ? 'selected' : '' }}>Все статусы</option>
                        <option value="succeeded" {{ request('status') == 'succeeded' ? 'selected' : '' }}>Успешные</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Ожидают оплаты</option>
                        <option value="canceled" {{ request('status') == 'canceled' ? 'selected' : '' }}>Отменённые</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Неудачные</option>
                    </select>
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тип</label>
                    <select name="type" onchange="this.form.submit()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="" {{ !request('type') ? 'selected' : '' }}>Все типы</option>
                        <option value="deposit" {{ request('type') == 'deposit' ? 'selected' : '' }}>Пополнения</option>
                        <option value="subscription" {{ request('type') == 'subscription' ? 'selected' : '' }}>Подписки</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Таблица платежей -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            @if($payments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID платежа
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дата
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Тип
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Описание
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Статус
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Сумма
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($payments as $payment)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    #{{ $payment->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $payment->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($payment->type === 'deposit')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Пополнение
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                            Подписка
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $payment->description }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($payment->status === 'succeeded')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Оплачено
                                        </span>
                                    @elseif($payment->status === 'pending')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Ожидает оплаты
                                        </span>
                                    @elseif($payment->status === 'canceled')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Отменён
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Неудачно
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $payment->status === 'succeeded' ? 'text-gray-900' : 'text-gray-400' }}">
                                    {{ number_format($payment->amount, 2) }} ₽
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <div class="flex justify-center space-x-2">
                                        @if($payment->status === 'pending' && $payment->confirmation_url)
                                        <a href="{{ $payment->confirmation_url }}" 
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Оплатить
                                        </a>
                                        @endif
                                        
                                        @if($payment->status === 'succeeded')
                                        <button onclick="downloadReceipt('{{ $payment->id }}')" 
                                                class="text-gray-600 hover:text-gray-900">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Пагинация -->
                <div class="px-6 py-3 bg-gray-50 border-t">
                    {{ $payments->withQueryString()->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Нет платежей</h3>
                    <p class="mt-1 text-sm text-gray-500">Начните с пополнения баланса для активации тарифа</p>
                    <div class="mt-6">
                        <a href="{{ route('billing.deposit') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Пополнить баланс
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function downloadReceipt(paymentId) {
    // Здесь будет логика скачивания квитанции
    window.location.href = '/billing/payment/' + paymentId + '/receipt';
}
</script>
@endsection