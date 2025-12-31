@extends('layouts.app')

@section('title', 'Добавить пользователя')

@section('content')
<div class="py-12">
    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Добавить пользователя</h2>
            </div>

            @if ($errors->any())
                <div class="p-4 m-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('organization.users.store') }}" class="p-6">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Имя *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Иван Иванов">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="user@example.com">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Пароль *</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           minlength="8">
                    <p class="mt-1 text-xs text-gray-500">Минимум 8 символов</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Подтверждение пароля *</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           minlength="8">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Роль *</label>
                    <select name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Выберите роль</option>
                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Администратор</option>
                        <option value="operator" {{ old('role') == 'operator' ? 'selected' : '' }}>Оператор</option>
                        <option value="viewer" {{ old('role') == 'viewer' ? 'selected' : '' }}>Наблюдатель</option>
                    </select>
                </div>

                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-6">
                    <h3 class="text-sm font-medium text-blue-900 mb-2">Права доступа ролей:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li><strong>Администратор:</strong> Полный доступ к ботам, диалогам и интеграциям</li>
                        <li><strong>Оператор:</strong> Работа с диалогами, просмотр статистики</li>
                        <li><strong>Наблюдатель:</strong> Только просмотр информации</li>
                    </ul>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('organization.users.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Отмена
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Создать пользователя
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection