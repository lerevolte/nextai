@extends('layouts.app')

@section('title', 'Редактировать пользователя')

@section('content')
<div class="py-12">
    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Редактировать пользователя</h2>
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

            <form method="POST" action="{{ route('organization.users.update', $user) }}" class="p-6">
                @csrf
                @method('PUT')

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Имя *</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Новый пароль</label>
                    <input type="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           minlength="8">
                    <p class="mt-1 text-xs text-gray-500">Оставьте пустым, чтобы не менять пароль</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Подтверждение пароля</label>
                    <input type="password" name="password_confirmation"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           minlength="8">
                </div>

                @if(!$user->hasRole('owner'))
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Роль *</label>
                    <select name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="admin" {{ $user->hasRole('admin') ? 'selected' : '' }}>Администратор</option>
                        <option value="operator" {{ $user->hasRole('operator') ? 'selected' : '' }}>Оператор</option>
                        <option value="viewer" {{ $user->hasRole('viewer') ? 'selected' : '' }}>Наблюдатель</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" 
                               {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm font-medium text-gray-700">Активен</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 ml-6">Неактивные пользователи не могут войти в систему</p>
                </div>
                @else
                <input type="hidden" name="role" value="owner">
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg mb-6">
                    <p class="text-sm text-yellow-800">
                        Владелец организации не может изменить свою роль или статус активности
                    </p>
                </div>
                @endif

                <div class="flex justify-between">
                    <div>
                        @if(!$user->hasRole('owner') && $user->id !== auth()->id())
                        <button type="button" onclick="if(confirm('Удалить пользователя?')) document.getElementById('delete-form').submit();"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            Удалить пользователя
                        </button>
                        @endif
                    </div>
                    <div class="flex space-x-3">
                        <a href="{{ route('organization.users.index') }}" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Отмена
                        </a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Сохранить изменения
                        </button>
                    </div>
                </div>
            </form>

            @if(!$user->hasRole('owner') && $user->id !== auth()->id())
            <form id="delete-form" action="{{ route('organization.users.destroy', $user) }}" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            @endif
        </div>
    </div>
</div>
@endsection