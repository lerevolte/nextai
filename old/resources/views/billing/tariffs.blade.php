@extends('layouts.app')

@section('title', 'Тарифы')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Выберите тарифный план</h1>
            <p class="text-xl text-gray-600">Масштабируйте свой бизнес с нашими гибкими тарифами</p>
            
            <!-- Переключатель периода -->
            <div class="mt-8 flex justify-center">
                <div class="bg-gray-100 p-1 rounded-lg inline-flex">
                    <button onclick="setPeriod('monthly')" id="monthly-btn" 
                            class="px-6 py-2 rounded-md text-sm font-medium transition-all duration-200 bg-white text-gray-900">
                        Ежемесячно
                    </button>
                    <button onclick="setPeriod('yearly')" id="yearly-btn"
                            class="px-6 py-2 rounded-md text-sm font-medium transition-all duration-200 text-gray-500">
                        Ежегодно <span class="text-green-600">(-20%)</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Текущий тариф -->
        @if(isset($currentSubscription) && $currentSubscription)
        <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-blue-600">Ваш текущий тариф</p>
                    <p class="text-lg font-semibold text-blue-900">{{ $currentSubscription->tariff->name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-blue-600">Действует до</p>
                    <p class="text-lg font-semibold text-blue-900">{{ $currentSubscription->ends_at->format('d.m.Y') }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Тарифные планы -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($tariffs as $tariff)
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden {{ $tariff->is_popular ? 'ring-2 ring-indigo-600' : '' }} relative">
                @if($tariff->is_popular)
                <div class="absolute top-0 right-0 bg-indigo-600 text-white px-3 py-1 rounded-bl-lg text-sm font-medium">
                    Популярный
                </div>
                @endif
                
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $tariff->name }}</h3>
                    <p class="text-gray-600 text-sm mb-6">{{ $tariff->description }}</p>
                    
                    <!-- Цена -->
                    <div class="mb-6">
                        <div class="flex items-baseline">
                            <span class="text-4xl font-bold text-gray-900 monthly-price">
                                {{ number_format($tariff->price, 0, '', ' ') }}
                            </span>
                            <span class="text-4xl font-bold text-gray-900 yearly-price" style="display: none;">
                                @if($tariff->price_yearly)
                                    {{ number_format($tariff->price_yearly / 12, 0, '', ' ') }}
                                @else
                                    {{ number_format($tariff->price, 0, '', ' ') }}
                                @endif
                            </span>
                            <span class="text-gray-600 ml-2">₽/мес</span>
                        </div>
                        @if($tariff->price_yearly)
                        <p class="text-sm text-gray-500 mt-1 yearly-price" style="display: none;">
                            При оплате за год: {{ number_format($tariff->price_yearly, 0, '', ' ') }} ₽
                        </p>
                        @endif
                    </div>
                    
                    <!-- Основные лимиты -->
                    <ul class="space-y-3 mb-6">
                        <li class="flex items-center text-sm">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>
                                @if($tariff->bots_limit == -1)
                                    Неограниченно ботов
                                @else
                                    До {{ $tariff->bots_limit }} бот{{ $tariff->bots_limit > 1 ? 'ов' : '' }}
                                @endif
                            </span>
                        </li>
                        <li class="flex items-center text-sm">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>
                                @if($tariff->messages_limit == -1)
                                    Неограниченно сообщений
                                @else
                                    {{ number_format($tariff->messages_limit) }} сообщений/мес
                                @endif
                            </span>
                        </li>
                        <li class="flex items-center text-sm">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>
                                @if($tariff->users_limit == -1)
                                    Неограниченно пользователей
                                @else
                                    До {{ $tariff->users_limit }} пользовател{{ $tariff->users_limit > 1 ? 'ей' : 'я' }}
                                @endif
                            </span>
                        </li>
                    </ul>
                    
                    <!-- Дополнительные фичи -->
                    @if($tariff->features && count($tariff->features) > 0)
                    <ul class="space-y-2 mb-6 border-t pt-4">
                        @foreach($tariff->features as $feature)
                        <li class="flex items-start text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            {{ $feature }}
                        </li>
                        @endforeach
                    </ul>
                    @endif
                    
                    <!-- Кнопка действия -->
                    @if(isset($currentSubscription) && $currentSubscription && $currentSubscription->tariff_id == $tariff->id)
                        <button class="w-full py-2 px-4 bg-gray-100 text-gray-500 rounded-lg font-medium" disabled>
                            Текущий тариф
                        </button>
                    @else
                        <button onclick="selectTariff({{ $tariff->id }}, '{{ $tariff->slug }}')" 
                                class="w-full py-2 px-4 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                            @if($tariff->price == 0)
                                Начать бесплатно
                            @else
                                Выбрать тариф
                            @endif
                        </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- FAQ -->
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">Часто задаваемые вопросы</h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg">
                    <h3 class="font-semibold text-gray-900 mb-2">Можно ли сменить тариф?</h3>
                    <p class="text-gray-600 text-sm">Да, вы можете изменить тариф в любое время. При переходе на более дорогой тариф изменения вступают в силу сразу.</p>
                </div>
                <div class="bg-white p-6 rounded-lg">
                    <h3 class="font-semibold text-gray-900 mb-2">Как работает оплата?</h3>
                    <p class="text-gray-600 text-sm">Оплата производится через баланс организации. Вы можете пополнить баланс картой через ЮКассу.</p>
                </div>
                <div class="bg-white p-6 rounded-lg">
                    <h3 class="font-semibold text-gray-900 mb-2">Есть ли пробный период?</h3>
                    <p class="text-gray-600 text-sm">Да, мы предоставляем 14 дней бесплатного пробного периода для всех новых пользователей.</p>
                </div>
                <div class="bg-white p-6 rounded-lg">
                    <h3 class="font-semibold text-gray-900 mb-2">Можно ли получить счет для оплаты?</h3>
                    <p class="text-gray-600 text-sm">Да, для юридических лиц мы выставляем счета и предоставляем все закрывающие документы.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения -->
<div id="tariffModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        
    <!-- Фон-затемнение -->
    <div class="fixed inset-0 bg-black bg-opacity-60" onclick="closeTariffModal()"></div>
    
    <!-- Панель модального окна -->
    <!-- Изначально смещено и уменьшено для анимации (`opacity-0 scale-95`) -->
    <div id="modalPanel" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 transform scale-95">
        
        <!-- Кнопка закрытия (крестик) -->
        <button onclick="closeTariffModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        
        <h3 class="text-xl font-bold text-gray-800 mb-2">Подтвердите выбор тарифа</h3>
        <p class="text-gray-500 mb-6">Вы выбрали тариф <span id="selectedTariffName" class="font-semibold text-gray-700">Базовый</span>.</p>
        
        <form id="subscribeFrm" method="POST" action="#"> <!-- Замените # на ваш action -->
            <input type="hidden" name="_token" value="w9AclAnqm0P1c1PQvLiUrkSujArxLwsnvXqVOOGX" autocomplete="off">
            <input type="hidden" name="period" id="billingPeriod" value="monthly">
            
            <div class="mb-6">
                <p class="text-sm font-medium text-gray-700 mb-3">Период оплаты:</p>
                <div class="space-y-3">
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-500 transition">
                        <input type="radio" name="period" value="monthly" checked onchange="updatePrice()" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                        <span class="ml-3 text-sm font-medium text-gray-700">Ежемесячно - <span id="monthlyPrice">1</span> ₽/мес</span>
                    </label>
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-500 transition">
                        <input type="radio" name="period" value="yearly" onchange="updatePrice()" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-700">Ежегодно - <span id="yearlyPrice">10</span> ₽/год</span>
                            <span class="block text-xs text-green-600 font-semibold">(экономия <span id="savings">2</span> ₽)</span>
                        </div>
                    </label>
                </div>
            </div>
            
            @php
                $balance = $organization->balance->balance ?? 0;
            @endphp
            <div class="p-4 bg-blue-50 rounded-lg mb-6">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600">Баланс организации:</span>
                    <span class="font-medium text-gray-800">{{ number_format($balance, 2) }} ₽</span>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-gray-800 font-bold">К оплате:</span>
                    <span id="totalPrice" class="text-xl font-bold text-indigo-600">1</span> <span class="font-bold text-indigo-600">₽</span>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" onclick="closeTariffModal()" class="w-full px-4 py-3 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-75 transition">
                    Отмена
                </button>
                <button type="submit" class="w-full px-4 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-75 transition">
                    Подтвердить
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentPeriod = 'monthly';
let selectedTariff = null;

const tariffs = @json($tariffs);

function setPeriod(period) {
    currentPeriod = period;
    
    // Обновляем кнопки
    document.getElementById('monthly-btn').classList.toggle('bg-white', period === 'monthly');
    document.getElementById('monthly-btn').classList.toggle('text-gray-900', period === 'monthly');
    document.getElementById('monthly-btn').classList.toggle('text-gray-500', period !== 'monthly');
    
    document.getElementById('yearly-btn').classList.toggle('bg-white', period === 'yearly');
    document.getElementById('yearly-btn').classList.toggle('text-gray-900', period === 'yearly');
    document.getElementById('yearly-btn').classList.toggle('text-gray-500', period !== 'yearly');
    
    // Показываем/скрываем цены
    document.querySelectorAll('.monthly-price').forEach(el => {
        el.style.display = period === 'monthly' ? 'inline' : 'none';
    });
    document.querySelectorAll('.yearly-price').forEach(el => {
        el.style.display = period === 'yearly' ? 'inline' : 'none';
    });
}

function selectTariff(id, slug) {
    selectedTariff = tariffs.find(t => t.id === id);
    
    if (!selectedTariff) return;
    
    // Если бесплатный тариф - сразу подписываем
    if (selectedTariff.price == 0) {
        document.getElementById('subscribeFrm').action = '/billing/subscribe/' + id;
        document.getElementById('subscribeFrm').submit();
        return;
    }
    
    // Обновляем модальное окно
    document.getElementById('selectedTariffName').textContent = selectedTariff.name;
    document.getElementById('monthlyPrice').textContent = Number(selectedTariff.price).toLocaleString('ru-RU');
    
    const yearlyPrice = selectedTariff.price_yearly || (selectedTariff.price * 12);
    document.getElementById('yearlyPrice').textContent = Number(yearlyPrice).toLocaleString('ru-RU');
    
    const savings = (selectedTariff.price * 12) - yearlyPrice;
    document.getElementById('savings').textContent = Number(savings).toLocaleString('ru-RU');
    
    document.getElementById('subscribeFrm').action = '/billing/subscribe/' + id;
    
    updatePrice();
    
    // Показываем модальное окно
    document.getElementById('tariffModal').classList.remove('hidden');

}

function updatePrice() {
    if (!selectedTariff) return;
    
    const period = document.querySelector('input[name="period"]:checked').value;
    const price = period === 'yearly' 
        ? (selectedTariff.price_yearly || selectedTariff.price * 12) 
        : selectedTariff.price;
    
    document.getElementById('totalPrice').textContent = Number(price).toLocaleString('ru-RU');
    document.getElementById('billingPeriod').value = period;
}

function closeTariffModal() {
    document.getElementById('tariffModal').classList.add('hidden');
}
</script>
@endsection