{{-- resources/views/layouts/navigation.blade.php --}}
<nav style="background: white; border-bottom: 1px solid #e5e7eb;">
    <div style="max-width: 1280px; margin: 0 auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; height: 64px;">
            <div style="display: flex; align-items: center;">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}" style="text-decoration: none; color: #6366f1; font-size: 20px; font-weight: bold; margin-right: 40px;">
                    ChatBot Service
                </a>

                <!-- Navigation Links -->
                <div style="display: flex; gap: 30px;">
                    <a href="{{ route('dashboard') }}" 
                       style="text-decoration: none; color: {{ request()->routeIs('dashboard') ? '#6366f1' : '#6b7280' }}; font-weight: 500;">
                        Дашборд
                    </a>

                    @if(auth()->user() && auth()->user()->organization)
                    <a href="{{ route('bots.index', auth()->user()->organization) }}" 
                       style="text-decoration: none; color: {{ request()->routeIs('bots.*') ? '#6366f1' : '#6b7280' }}; font-weight: 500;">
                        Боты
                    </a>
                    @endif

                    @can('analytics.view')
                    <a href="{{ route('analytics.index', auth()->user()->organization) }}" 
                       style="text-decoration: none; color: {{ request()->routeIs('analytics.*') ? '#6366f1' : '#6b7280' }}; font-weight: 500;">
                        Аналитика
                    </a>
                    @endcan

                    @can('users.view')
                    <a href="{{ route('users.index') }}" 
                       style="text-decoration: none; color: {{ request()->routeIs('users.*') ? '#6366f1' : '#6b7280' }}; font-weight: 500;">
                        Пользователи
                    </a>
                    @endcan

                    @can('organization.update')
                    <a href="{{ route('organization.settings') }}" 
                       style="text-decoration: none; color: {{ request()->routeIs('organization.*') ? '#6366f1' : '#6b7280' }}; font-weight: 500;">
                        Настройки
                    </a>
                    @endcan
                </div>
            </div>

            <!-- User Menu -->
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="{{ route('profile.edit') }}" 
                   style="text-decoration: none; color: #6b7280;">
                    {{ auth()->user()->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" 
                            style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>