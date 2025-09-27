@extends('layouts.app')

@section('title', 'Оплата успешна')

@section('content')
<div class="py-12">
    <div class="max-w-md mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-8 text-center">
            <!-- Иконка успеха -->
            <div class="mx-auto flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Оплата прошла успешно!</h2>
            <p class="text-gray-600 mb-6">
                Средства зачислены на ваш баланс и доступны для использования
            </p>
            
            <!-- Информация о платеже -->
            @if(session('payment_info'))
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Сумма:</span>
                    <span class="font-semibold">{{ session('payment_info.amount') }} ₽</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Номер платежа:</span>
                    <span class="font-mono text-sm">{{ session('payment_info.id') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Дата:</span>
                    <span>{{ session('payment_info.date') }}</span>
                </div>
            </div>
            @endif
            
            <!-- Кнопки действий -->
            <div class="space-y-3">
                <a href="{{ route('billing.balance') }}" 
                   class="block w-full px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Перейти к балансу
                </a>
                <a href="{{ route('billing.tariffs') }}" 
                   class="block w-full px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Выбрать тариф
                </a>
                <a href="{{ route('dashboard') }}" 
                   class="block w-full px-4 py-3 text-gray-600 hover:text-gray-900">
                    Вернуться в личный кабинет
                </a>
            </div>
            
            <!-- Информация о квитанции -->
            <div class="mt-6 pt-6 border-t text-sm text-gray-500">
                <p>Квитанция об оплате отправлена на вашу электронную почту</p>
                <p class="mt-1">
                    Если у вас есть вопросы, обратитесь в 
                    <a href="/support" class="text-indigo-600 hover:text-indigo-700">службу поддержки</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection