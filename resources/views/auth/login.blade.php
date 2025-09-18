{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.guest')

@section('content')
    <div class="logo">
        <h1>ChatBot Service</h1>
        <p style="color: #6b7280; margin-top: 5px;">Вход в систему</p>
    </div>

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
            <label for="remember" style="margin-bottom: 0;">Запомнить меня</label>
        </div>

        <button type="submit" class="btn">
            Войти
        </button>

        <div class="links">
            <a href="{{ route('register') }}">Нет аккаунта? Зарегистрироваться</a>
        </div>
    </form>
@endsection