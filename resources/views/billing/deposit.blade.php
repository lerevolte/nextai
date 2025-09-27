@extends('layouts.app')

@section('title', 'Пополнение баланса')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Пополнение баланса</h2>
            </div>

            @if(session('error'))
                <div class="m-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="p-6">
                <!-- Текущий баланс -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-600">Текущий баланс</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ number_format($organization->balance->balance ?? 0, 2) }} ₽
                            </p>
                        </div>
                        @if(($organization->balance->bonus_balance ?? 0) > 0)
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Бонусный баланс</p>
                            <p class="text-xl font-semibold text-purple-600">
                                +{{ number_format($organization->balance->bonus_balance, 2) }} ₽
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Быстрые суммы -->
                <div class="mb-6">
                    <p class="text-sm font-medium text-gray-700 mb-3">Выберите сумму пополнения</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach($suggestedAmounts as $amount)
                        <button onclick="setAmount({{ $amount }})" 
                                class="amount-btn px-4 py-3 border-2 border-gray-300 rounded-lg hover:border-indigo-600 hover:bg-indigo-50 transition-colors text-center">
                            <span class="text-lg font-semibold">{{ number_format($amount, 0, '', ' ') }} ₽</span>
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Форма пополнения -->
                <form id="depositForm" method="POST" action="{{ route('billing.payment.create') }}">
                    @csrf
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Или введите свою сумму</label>
                        <div class="relative">
                            <input type="number" 
                                   id="amount" 
                                   name="amount" 
                                   min="100" 
                                   max="100000" 
                                   step="1"
                                   required
                                   class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg"
                                   placeholder="0">
                            <span class="absolute right-4 top-3 text-lg text-gray-500">₽</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Минимальная сумма пополнения: 100 ₽</p>
                    </div>

                    <!-- Способы оплаты -->
                    <div class="mb-6">
                        <p class="text-sm font-medium text-gray-700 mb-3">Способ оплаты</p>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="payment_method" value="bank_card" checked class="mr-3">
                                <div class="flex-1">
                                    <span class="font-medium">💳 Банковская карта</span>
                                    <p class="text-xs text-gray-500 mt-1">Visa, Mastercard, МИР</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="payment_method" value="yookassa" class="mr-3">
                                <div class="flex-1">
                                    <span class="font-medium">🏦 ЮKassa</span>
                                    <p class="text-xs text-gray-500 mt-1">Все доступные способы оплаты</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Промокод -->
                    <div class="mb-6">
                        <button type="button" onclick="togglePromocode()" class="text-sm text-indigo-600 hover:text-indigo-700">
                            У меня есть промокод
                        </button>
                        <div id="promocodeBlock" class="hidden mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Промокод</label>
                            <div class="flex gap-3">
                                <input type="text" 
                                       name="promocode" 
                                       id="promocode"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg"
                                       placeholder="Введите промокод">
                                <button type="button" onclick="applyPromocode()" 
                                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    Применить
                                </button>
                            </div>
                            <div id="promocodeResult" class="mt-2 text-sm"></div>
                        </div>
                    </div>

                    <!-- Итоговая сумма -->
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">К оплате:</span>
                            <span class="text-2xl font-bold text-gray-900">
                                <span id="totalAmount">0</span> ₽
                            </span>
                        </div>
                        <div id="bonusInfo" class="hidden mt-2 text-sm text-green-600">
                            <span>+ Бонус: <span id="bonusAmount">0</span> ₽</span>
                        </div>
                    </div>

                    <!-- Соглашение -->
                    <div class="mb-6">
                        <label class="flex items-start">
                            <input type="checkbox" required class="mt-1 mr-3 rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-600">
                                Нажимая кнопку «Пополнить», я соглашаюсь с 
                                <a href="/terms" target="_blank" class="text-indigo-600 hover:text-indigo-700">условиями оплаты</a>
                                и 
                                <a href="/offer" target="_blank" class="text-indigo-600 hover:text-indigo-700">офертой</a>
                            </span>
                        </label>
                    </div>

                    <!-- Кнопки -->
                    <div class="flex gap-3">
                        <a href="{{ route('billing.balance') }}" 
                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-center text-gray-700 hover:bg-gray-50">
                            Отмена
                        </a>
                        <button type="submit" 
                                id="submitBtn"
                                class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                            Пополнить баланс
                        </button>
                    </div>
                </form>

                <!-- Информация -->
                <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Информация об оплате</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Средства поступят на баланс моментально после оплаты</li>
                        <li>• Комиссия за пополнение не взимается</li>
                        <li>• Минимальная сумма пополнения: 100 ₽</li>
                        <li>• Все платежи защищены по стандарту PCI DSS</li>
                    </ul>
                </div>

                <!-- Безопасность -->
                <div class="mt-4 flex items-center justify-center space-x-4 text-xs text-gray-500">
                    <span>🔒 Безопасная оплата</span>
                    <span>•</span>
                    <img src="https://www.yoomoney.ru/transfer/img/ym-logo.svg" alt="ЮKassa" class="h-4">
                    <span>•</span>
                    <img src="https://www.visa.com.ua/dam/VCOM/regional/ap/russia/global-elements/images/logo-visa-blue-gradient-800x450.jpg" alt="Visa" class="h-4">
                    <span>•</span>
                    <img src="https://brand.mastercard.com/content/dam/mccom/brandcenter/brand-history/brandmark-mastercard-brand-mark-1200x630.jpg" alt="Mastercard" class="h-4">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedAmount = 0;
let bonusPercent = 0;

function setAmount(amount) {
    selectedAmount = amount;
    document.getElementById('amount').value = amount;
    
    // Убираем активный класс со всех кнопок
    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.classList.remove('border-indigo-600', 'bg-indigo-50');
        btn.classList.add('border-gray-300');
    });
    
    // Добавляем активный класс на нажатую кнопку
    event.target.closest('.amount-btn').classList.remove('border-gray-300');
    event.target.closest('.amount-btn').classList.add('border-indigo-600', 'bg-indigo-50');
    
    updateTotal();
}

function updateTotal() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const totalElement = document.getElementById('totalAmount');
    const bonusInfo = document.getElementById('bonusInfo');
    const bonusAmountElement = document.getElementById('bonusAmount');
    
    totalElement.textContent = amount.toLocaleString('ru-RU');
    
    // Рассчитываем бонусы
    let bonus = 0;
    if (amount >= 10000) {
        bonus = amount * 0.1; // 10% бонус при пополнении от 10000
        bonusPercent = 10;
    } else if (amount >= 5000) {
        bonus = amount * 0.05; // 5% бонус при пополнении от 5000
        bonusPercent = 5;
    } else if (amount >= 3000) {
        bonus = amount * 0.03; // 3% бонус при пополнении от 3000
        bonusPercent = 3;
    }
    
    if (bonus > 0) {
        bonusInfo.classList.remove('hidden');
        bonusAmountElement.textContent = bonus.toLocaleString('ru-RU');
    } else {
        bonusInfo.classList.add('hidden');
    }
}

function togglePromocode() {
    const block = document.getElementById('promocodeBlock');
    block.classList.toggle('hidden');
}

function applyPromocode() {
    const promocode = document.getElementById('promocode').value;
    const resultElement = document.getElementById('promocodeResult');
    
    if (!promocode) {
        resultElement.textContent = 'Введите промокод';
        resultElement.className = 'mt-2 text-sm text-red-600';
        return;
    }
    
    // Здесь можно добавить AJAX запрос для проверки промокода
    // Для примера просто показываем сообщение
    resultElement.textContent = '✓ Промокод применен! Бонус +10% к пополнению';
    resultElement.className = 'mt-2 text-sm text-green-600';
    bonusPercent += 10;
    updateTotal();
}

// Обновляем сумму при изменении поля ввода
document.getElementById('amount').addEventListener('input', updateTotal);

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    updateTotal();
});
</script>
@endsection