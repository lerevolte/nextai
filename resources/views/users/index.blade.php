{{-- resources/views/users/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Пользователи')

@section('content')
<div style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-size: 24px;">Пользователи организации</h2>
        @can('users.create')
        <a href="{{ route('users.create') }}" 
           style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">
            + Добавить пользователя
        </a>
        @endcan
    </div>

    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="padding: 12px; text-align: left;">Имя</th>
                    <th style="padding: 12px; text-align: left;">Email</th>
                    <th style="padding: 12px; text-align: left;">Роль</th>
                    <th style="padding: 12px; text-align: left;">Статус</th>
                    <th style="padding: 12px; text-align: left;">Последний вход</th>
                    <th style="padding: 12px; text-align: left;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr style="border-top: 1px solid #e5e7eb;">
                    <td style="padding: 12px;">{{ $user->name }}</td>
                    <td style="padding: 12px;">{{ $user->email }}</td>
                    <td style="padding: 12px;">
                        @foreach($user->roles as $role)
                            <span style="padding: 2px 8px; background: #e0e7ff; color: #3730a3; border-radius: 4px; font-size: 12px;">
                                {{ $role->name }}
                            </span>
                        @endforeach
                    </td>
                    <td style="padding: 12px;">
                        <span style="padding: 2px 8px; background: {{ $user->is_active ? '#d1fae5' : '#fee2e2' }}; color: {{ $user->is_active ? '#065f46' : '#991b1b' }}; border-radius: 4px; font-size: 12px;">
                            {{ $user->is_active ? 'Активен' : 'Неактивен' }}
                        </span>
                    </td>
                    <td style="padding: 12px;">
                        {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'Никогда' }}
                    </td>
                    <td style="padding: 12px;">
                        @can('users.update')
                        <a href="{{ route('users.edit', $user) }}" style="color: #6366f1; text-decoration: none; margin-right: 10px;">
                            Изменить
                        </a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding: 20px; text-align: center; color: #6b7280;">
                        Пользователи не найдены
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div style="margin-top: 20px;">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection