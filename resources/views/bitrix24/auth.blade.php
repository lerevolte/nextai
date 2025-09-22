<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение Битрикс24 к чат-боту</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 900px;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .auth-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 16px;
            font-weight: 500;
            color: #6b7280;
        }
        
        .tab.active {
            background: white;
            color: #6366f1;
            box-shadow: inset 0 -3px 0 #6366f1;
        }
        
        .tab:hover {
            background: white;
        }
        
        .tab-content {
            padding: 40px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .error {
            color: #ef4444;
            font-size: 13px;
            margin-top: 4px;
        }
        
        .btn {
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e5e7eb;
        }
        
        .divider span {
            background: white;
            padding: 0 16px;
            position: relative;
            color: #9ca3af;
            font-size: 14px;
        }
        
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .info-box h3 {
            color: #0369a1;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .info-box p {
            color: #0c4a6e;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .bitrix-info {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .bitrix-info .domain {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .bitrix-info .label {
            font-size: 13px;
            color: #6b7280;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Подключение чат-ботов</h1>
            <p>Привяжите Битрикс24 к вашей системе управления ботами</p>
        </div>
        
        <div class="auth-container">
            <div class="bitrix-info">
                <div class="domain">{{ $domain }}</div>
                <div class="label">Ваш Битрикс24</div>
            </div>
      
            
            <div class="tabs">
                <button class="tab active" onclick="switchTab('login')">
                    Вход в систему
                </button>
                <button class="tab" onclick="switchTab('register')">
                    Новая организация
                </button>
                <button class="tab" onclick="switchTab('api')">
                    По API ключу
                </button>
            </div>
            
            <!-- Вход для существующих пользователей -->
            <div id="login-tab" class="tab-content active">
                <div class="info-box">
                    <h3>👤 Для существующих пользователей</h3>
                    <p>Войдите в свой аккаунт, чтобы подключить Битрикс24 к вашей организации</p>
                </div>
                
                <form method="POST" action="{{ route('bitrix24.login') }}">
                    @csrf
                    <input type="hidden" name="install_data" value="{{ $install_data }}">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Войти и подключить Битрикс24
                    </button>
                </form>
            </div>
            
            <!-- Регистрация новой организации -->
            <div id="register-tab" class="tab-content">
                <div class="info-box">
                    <h3>🏢 Создание новой организации</h3>
                    <p>Зарегистрируйте новую организацию и сразу подключите к ней Битрикс24</p>
                </div>
                
                <form method="POST" action="{{ route('bitrix24.register') }}">
                    @csrf
                    <input type="hidden" name="install_data" value="{{ $install_data }}">
                    <div class="form-group">
                        <label for="organization_name">Название организации</label>
                        <input type="text" id="organization_name" name="organization_name" 
                               value="{{ old('organization_name') }}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_name">Ваше имя</label>
                        <input type="text" id="reg_name" name="name" 
                               value="{{ old('name', $user_info['NAME'] ?? '') }}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_email">Email</label>
                        <input type="email" id="reg_email" name="email" 
                               value="{{ old('email', $user_info['EMAIL'] ?? '') }}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password">Пароль</label>
                        <input type="password" id="reg_password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password_confirmation">Подтвердите пароль</label>
                        <input type="password" id="reg_password_confirmation" 
                               name="password_confirmation" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Создать организацию и подключить
                    </button>
                </form>
            </div>
            
            <!-- Привязка по API ключу -->
            <div id="api-tab" class="tab-content">
                <div class="info-box">
                    <h3>🔑 Подключение по API ключу</h3>
                    <p>Используйте API ключ вашей организации для быстрого подключения</p>
                </div>
                
                <form method="POST" action="{{ route('bitrix24.link-api') }}">
                    @csrf
                    <input type="hidden" name="install_data" value="{{ $install_data }}">
                    <div class="form-group">
                        <label for="api_key">API ключ организации</label>
                        <input type="text" id="api_key" name="api_key" 
                               placeholder="Например: org_xxxxxxxxxxxxx" required>
                        <div style="margin-top: 8px; font-size: 13px; color: #6b7280;">
                            API ключ можно найти в настройках организации в панели управления
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Подключить по API ключу
                    </button>
                </form>
                
                <div class="divider">
                    <span>Где найти API ключ?</span>
                </div>
                
                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.8;">
                    <ol style="margin: 0; padding-left: 20px;">
                        <li>Войдите в панель управления ботами</li>
                        <li>Перейдите в настройки организации</li>
                        <li>Найдите раздел "API интеграции"</li>
                        <li>Скопируйте API ключ</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Скрываем все табы
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Убираем активность со всех кнопок
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Показываем нужный таб
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Делаем кнопку активной
            event.target.classList.add('active');
        }
        
        // Инициализация Битрикс24 JS API
        BX24.init(function(){
            console.log('Bitrix24 JS API initialized');
        });
    </script>
</body>
</html>