{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.guest')

@section('content')
    <div class="logo">
        <h1>ChatBot Service</h1>
        <p style="color: #6b7280; margin-top: 5px;">Регистрация</p>
    </div>

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name">Имя</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        </div>

        <div class="form-group">
            <label for="organization_name">Название организации</label>
            <input id="organization_name" type="text" name="organization_name" value="{{ old('organization_name') }}" required>
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Подтверждение пароля</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>
        </div>

        <button type="submit" class="btn">
            Зарегистрироваться
        </button>

        <div class="links">
            <a href="{{ route('login') }}">Уже есть аккаунт? Войти</a>
        </div>
    </form>
@endsection