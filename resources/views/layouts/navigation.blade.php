{{-- resources/views/layouts/navigation.blade.php --}}
<nav style="background: white; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; height: 64px;">
            <div style="display: flex; align-items: center;">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}" style="text-decoration: none; display: flex; align-items: center; margin-right: 40px;">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                        <span style="color: white; font-weight: bold; font-size: 20px;">B</span>
                    </div>
                    <span style="color: #111827; font-size: 18px; font-weight: 600;">BotManager</span>
                </a>

                <!-- Main Navigation -->
                @if(auth()->user() && auth()->user()->organization)
                <div style="display: flex; gap: 5px;">
                    <a href="{{ route('dashboard') }}" 
                       style="padding: 8px 16px; text-decoration: none; color: {{ request()->routeIs('dashboard') ? '#6366f1' : '#4b5563' }}; font-weight: 500; border-radius: 6px; background: {{ request()->routeIs('dashboard') ? '#eef2ff' : 'transparent' }}; transition: all 0.2s;">
                        📊 Дашборд
                    </a>

                    <a href="{{ route('bots.index', auth()->user()->organization) }}" 
                       style="padding: 8px 16px; text-decoration: none; color: {{ request()->routeIs('bots.*') ? '#6366f1' : '#4b5563' }}; font-weight: 500; border-radius: 6px; background: {{ request()->routeIs('bots.*') ? '#eef2ff' : 'transparent' }}; transition: all 0.2s;">
                        🤖 Боты
                    </a>

                    @if(auth()->user()->organization->bots()->count() > 0)
                    <div style="position: relative; display: inline-block;">
                        <button onclick="toggleDropdown('conversations-dropdown')" 
                                style="padding: 8px 16px; text-decoration: none; color: {{ request()->routeIs('conversations.*') ? '#6366f1' : '#4b5563' }}; font-weight: 500; border-radius: 6px; background: {{ request()->routeIs('conversations.*') ? '#eef2ff' : 'transparent' }}; border: none; cursor: pointer; transition: all 0.2s;">
                            💬 Диалоги ▼
                        </button>
                        <div id="conversations-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 200px; z-index: 1000; margin-top: 5px;">
                            @foreach(auth()->user()->organization->bots()->limit(5)->get() as $bot)
                            <a href="{{ route('conversations.index', [auth()->user()->organization, $bot]) }}" 
                               style="display: block; padding: 10px 16px; color: #374151; text-decoration: none; hover: background: #f9fafb;">
                                {{ $bot->name }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <a href="{{ route('crm.index', auth()->user()->organization) }}" 
                       style="padding: 8px 16px; text-decoration: none; color: {{ request()->routeIs('crm.*') ? '#6366f1' : '#4b5563' }}; font-weight: 500; border-radius: 6px; background: {{ request()->routeIs('crm.*') ? '#eef2ff' : 'transparent' }}; transition: all 0.2s;">
                        🔗 Интеграции
                    </a>

                    @if(auth()->user()->hasRole(['owner', 'admin']))
                    <div style="position: relative; display: inline-block;">
                        <button onclick="toggleDropdown('admin-dropdown')" 
                                style="padding: 8px 16px; text-decoration: none; color: #4b5563; font-weight: 500; border-radius: 6px; background: transparent; border: none; cursor: pointer; transition: all 0.2s;">
                            ⚙️ Управление ▼
                        </button>
                        <div id="admin-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 200px; z-index: 1000; margin-top: 5px;">
                            <a href="{{ route('organization.settings') }}" 
                               style="display: block; padding: 10px 16px; color: #374151; text-decoration: none;">
                                🏢 Организация
                            </a>
                            @if(auth()->user()->hasPermissionTo('users.view'))
                            <a href="{{ route('organization.users.index') }}" 
                               style="display: block; padding: 10px 16px; color: #374151; text-decoration: none;">
                                👥 Пользователи
                            </a>
                            @endif
                            <hr style="margin: 5px 0; border: none; border-top: 1px solid #e5e7eb;">
                            <a href="{{ route('reports.index', auth()->user()->organization) }}" 
                               style="display: block; padding: 10px 16px; color: #374151; text-decoration: none;">
                                📈 Отчеты
                            </a>
                            <a href="{{ route('performance.index', auth()->user()->organization) }}" 
                               style="display: block; padding: 10px 16px; color: #374151; text-decoration: none;">
                                ⚡ Производительность
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            <!-- User Menu -->
            <div style="display: flex; align-items: center; gap: 20px;">
                @if(auth()->user() && auth()->user()->organization)
                <div style="padding: 6px 12px; background: #f3f4f6; border-radius: 20px; font-size: 13px; color: #6b7280;">
                    {{ auth()->user()->organization->name }}
                </div>
                @endif

                <div style="position: relative; display: inline-block;">
                    <button onclick="toggleDropdown('user-dropdown')" 
                            style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <span style="color: white; font-weight: bold;">{{ substr(auth()->user()->name, 0, 1) }}</span>
                        </div>
                        <span style="color: #374151; font-weight: 500;">{{ auth()->user()->name }}</span>
                        <span style="color: #9ca3af;">▼</span>
                    </button>
                    
                    <div id="user-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 200px; z-index: 1000; margin-top: 5px;">
                        <div style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                            <div style="font-weight: 600; color: #111827;">{{ auth()->user()->name }}</div>
                            <div style="font-size: 13px; color: #6b7280;">{{ auth()->user()->email }}</div>
                        </div>
                        
                        <a href="{{ route('profile.edit') }}" 
                           style="display: block; padding: 10px 16px; color: #374151; text-decoration: none;">
                            👤 Профиль
                        </a>
                        
                        <hr style="margin: 5px 0; border: none; border-top: 1px solid #e5e7eb;">
                        
                        <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                            @csrf
                            <button type="submit" 
                                    style="width: 100%; text-align: left; padding: 10px 16px; color: #dc2626; background: none; border: none; cursor: pointer;">
                                🚪 Выйти
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const allDropdowns = document.querySelectorAll('[id$="-dropdown"]');
    
    // Закрываем все остальные dropdown
    allDropdowns.forEach(d => {
        if (d.id !== id) {
            d.style.display = 'none';
        }
    });
    
    // Переключаем текущий
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Закрываем dropdown при клике вне его
document.addEventListener('click', function(event) {
    const isDropdownButton = event.target.closest('button[onclick*="toggleDropdown"]');
    const isDropdownContent = event.target.closest('[id$="-dropdown"]');
    
    if (!isDropdownButton && !isDropdownContent) {
        const allDropdowns = document.querySelectorAll('[id$="-dropdown"]');
        allDropdowns.forEach(d => d.style.display = 'none');
    }
});
</script>