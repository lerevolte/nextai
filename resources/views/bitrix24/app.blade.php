<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат-бот коннектор</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .bot-list {
            display: grid;
            gap: 12px;
        }
        
        .bot-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .bot-card:hover {
            background: #f9fafb;
            border-color: #6366f1;
        }
        
        .bot-info {
            flex: 1;
        }
        
        .bot-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .bot-status {
            font-size: 12px;
            color: #666;
        }
        
        .bot-status.registered {
            color: #10b981;
        }
        
        .bot-status.not-registered {
            color: #f59e0b;
        }
        
        .bot-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #6366f1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            opacity: 0.3;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .connector-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px;
            background: #f3f4f6;
            border-radius: 100px;
            font-size: 12px;
        }
        
        .connector-status.active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9ca3af;
        }
        
        .status-dot.active {
            background: #10b981;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Управление чат-ботами</h1>
            <p>Подключите ботов к открытым линиям Битрикс24</p>
        </div>
        
        <div class="section">
            <h2>Доступные боты</h2>
            
            @if($bots->count() > 0)
                <div class="bot-list">
                    @foreach($bots as $bot)
                        @php
                            $isRegistered = $bot->metadata['bitrix24_connector_registered'] ?? false;
                            $connector = collect($connectors)->firstWhere('bot_id', $bot->id);
                        @endphp
                        
                        <div class="bot-card">
                            <div class="bot-info">
                                <div class="bot-name">{{ $bot->name }}</div>
                                @if($isRegistered)
                                    <div class="bot-status registered">
                                        ✅ Коннектор зарегистрирован
                                    </div>
                                    @if($connector && $connector['active'])
                                        <div class="connector-status active" style="margin-top: 8px;">
                                            <span class="status-dot active"></span>
                                            Подключен к линии #{{ $connector['line_id'] }}
                                        </div>
                                    @endif
                                @else
                                    <div class="bot-status not-registered">
                                        ⚠️ Коннектор не зарегистрирован
                                    </div>
                                @endif
                            </div>
                            
                            <div class="bot-actions">
                                @if(!$isRegistered)
                                    <button class="btn btn-primary" onclick="registerConnector('{{ $bot->id }}')">
                                        Зарегистрировать
                                    </button>
                                @else
                                    @if(!$connector || !$connector['active'])
                                        <button class="btn btn-secondary" onclick="showInstructions('{{ $bot->id }}', '{{ $bot->name }}')">
                                            📋 Инструкция
                                        </button>
                                    @endif
                                    <button class="btn btn-danger" onclick="unregisterConnector('{{ $bot->id }}')">
                                        Удалить
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p>Нет доступных ботов</p>
                    <p style="font-size: 14px; margin-top: 8px;">Создайте бота в панели управления</p>
                </div>
            @endif
        </div>
        
        <div class="section">
            <h2>Инструкция по подключению</h2>
            <ol style="padding-left:20px;line-height: 1.8; color: #4b5563;">
                <li>Нажмите "Зарегистрировать" для нужного бота</li>
                <li>После успешной регистрации перейдите в <strong>CRM → Контакт-центр</strong></li>
                <li>Найдите блок с названием вашего бота</li>
                <li>Нажмите "Подключить" и выберите открытую линию</li>
                <li>Готово! Теперь сообщения от бота будут приходить в открытую линию</li>
            </ol>
        </div>
        
        <div id="instructions-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="background: white; margin: 50px auto; max-width: 500px; padding: 24px; border-radius: 12px;">
                <h3 style="margin-bottom: 16px;">Подключение бота "<span id="bot-name"></span>"</h3>
                <div class="alert alert-info">
                    Коннектор зарегистрирован! Теперь нужно подключить его в Битрикс24:
                </div>
                <ol style="padding-left:20px;line-height: 1.8; color: #4b5563;">
                    <li>Перейдите в <strong>CRM → Контакт-центр</strong></li>
                    <li>Найдите блок "<span id="bot-name-2"></span>"</li>
                    <li>Нажмите кнопку "Подключить"</li>
                    <li>Выберите нужную открытую линию</li>
                </ol>
                <div style="margin-top: 20px; text-align: right;">
                    <button class="btn btn-primary" onclick="closeInstructions()">Понятно</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        BX24.init(function(){
            console.log('Bitrix24 JS API initialized');
        });
        
        function registerConnector(botId) {
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '<span class="spinner"></span> Регистрация...';
            
            fetch('/bitrix24/register-connector', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    bot_id: botId,
                    domain: '{{ $domain }}',
                    auth_id: '{{ $authId }}'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    //location.reload();
                    button.innerHTML = '✅ Готово';
                } else {
                    alert('❌ Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                    button.disabled = false;
                    button.innerHTML = 'Зарегистрировать';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Ошибка при регистрации коннектора');
                button.disabled = false;
                button.innerHTML = 'Зарегистрировать';
            });
        }
        
        function unregisterConnector(botId) {
            if (!confirm('Удалить регистрацию коннектора? Все активные подключения будут разорваны.')) {
                return;
            }
            
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '<span class="spinner"></span> Удаление...';

            // Отправляем запрос на сервер для удаления
            fetch('/bitrix24/unregister-connector', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    bot_id: botId,
                    domain: '{{ $domain }}',
                    auth_id: '{{ $authId }}'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload(); // Перезагружаем страницу, чтобы увидеть изменения
                } else {
                    alert('❌ Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                    button.disabled = false;
                    button.innerHTML = 'Удалить';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Произошла ошибка при отправке запроса');
                button.disabled = false;
                button.innerHTML = 'Удалить';
            });
        }
        
        function showInstructions(botId, botName) {
            document.getElementById('bot-name').textContent = botName;
            document.getElementById('bot-name-2').textContent = botName;
            document.getElementById('instructions-modal').style.display = 'block';
        }
        
        function closeInstructions() {
            document.getElementById('instructions-modal').style.display = 'none';
        }
    </script>
</body>
</html>