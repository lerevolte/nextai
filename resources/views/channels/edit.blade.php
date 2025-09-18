{{-- resources/views/channels/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Редактировать канал')

@section('content')
<div style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h2 style="font-size: 24px; margin-bottom: 20px;">Редактировать канал: {{ $channel->name }}</h2>

    @if ($errors->any())
        <div style="padding: 15px; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 5px; margin-bottom: 20px;">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('channels.update', [$organization, $bot, $channel]) }}" 
          style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        @csrf
        @method('PUT')

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Название канала</label>
            <input type="text" name="name" value="{{ old('name', $channel->name) }}" required
                   style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Тип канала</label>
            <input type="text" value="{{ ucfirst($channel->type) }}" disabled
                   style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; background: #f3f4f6;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" 
                       {{ old('is_active', $channel->is_active) ? 'checked' : '' }}
                       style="margin-right: 8px;">
                <span style="font-weight: 500;">Канал активен</span>
            </label>
        </div>

        @if($channel->type == 'web')
        <div style="background: #f3f4f6; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 10px;">Настройки виджета</h4>
            
            <div style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 5px;">Позиция на странице</label>
                <select name="settings[position]" 
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;">
                    <option value="bottom-right" {{ ($channel->settings['position'] ?? '') == 'bottom-right' ? 'selected' : '' }}>
                        Внизу справа
                    </option>
                    <option value="bottom-left" {{ ($channel->settings['position'] ?? '') == 'bottom-left' ? 'selected' : '' }}>
                        Внизу слева
                    </option>
                </select>
            </div>

            <div style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 5px;">Цвет виджета</label>
                <input type="color" name="settings[color]" 
                       value="{{ $channel->settings['color'] ?? '#4F46E5' }}"
                       style="width: 100%; height: 40px; border: 1px solid #d1d5db; border-radius: 5px;">
            </div>
        </div>
        @endif

        @if($channel->type == 'telegram')
        <div style="background: #f3f4f6; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 10px;">Настройки Telegram</h4>
            <p style="color: #6b7280; font-size: 14px;">
                Webhook URL: <code>{{ route('webhooks.telegram', $channel) }}</code>
            </p>
            <p style="color: #6b7280; font-size: 14px; margin-top: 10px;">
                Для изменения токена бота удалите этот канал и создайте новый
            </p>
        </div>
        @endif

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="{{ route('bots.show', [$organization, $bot]) }}" 
               style="padding: 10px 20px; background: #f3f4f6; color: #111827; text-decoration: none; border-radius: 5px;">
                Отмена
            </a>
            <button type="submit" 
                    style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Сохранить изменения
            </button>
        </div>
    </form>
</div>
@endsection