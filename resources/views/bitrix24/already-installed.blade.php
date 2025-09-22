<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Приложение уже установлено</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        
        .warning-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .warning-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        h1 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 16px;
        }
        
        .info {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        
        .btn {
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        
        <h1>Приложение уже установлено</h1>
        <p style="color: #6b7280; margin-bottom: 24px;">
            Этот Битрикс24 уже подключен к организации
        </p>
        
        <div class="info">
            <p style="margin: 0; color: #92400e;">
                <strong>Организация:</strong> {{ $organization->name }}<br>
                <strong>Домен:</strong> {{ $domain }}
            </p>
        </div>
        
        <p style="color: #6b7280; margin-bottom: 24px; font-size: 14px;">
            Приложение готово к использованию. Вы можете перейти к управлению ботами.
        </p>
        
        <button onclick="BX24.init(function(){ BX24.installFinish(); });" class="btn">
            Продолжить
        </button>
    </div>
</body>
</html>