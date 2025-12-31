<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Интеграция не найдена</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .error-icon svg {
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
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
            color: #991b1b;
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
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>
        
        <h1>Интеграция не настроена</h1>
        <p style="color: #6b7280; margin-bottom: 24px;">
            {{ $message ?? 'Не удалось найти настройки интеграции для вашего Битрикс24' }}
        </p>
        
        <div class="info">
            <p style="margin: 0;">
                <strong>Домен:</strong> {{ $domain }}<br>
                Пожалуйста, переустановите приложение для корректной настройки
            </p>
        </div>
        
        <p style="color: #6b7280; margin-bottom: 24px; font-size: 14px;">
            Для решения проблемы:
        </p>
        
        <ol style="text-align: left; max-width: 300px; margin: 0 auto 24px; color: #6b7280; font-size: 14px; line-height: 1.8;">
            <li>Удалите приложение из Битрикс24</li>
            <li>Установите его заново из маркета</li>
            <li>Следуйте инструкциям по настройке</li>
        </ol>
        
        <button onclick="BX24.init(function(){ 
            BX24.callMethod('app.info', {}, function(res){
                if(res.data()){
                    window.location.href = '/bitrix24/install?' + new URLSearchParams(res.data()).toString();
                }
            });
        });" class="btn">
            Попробовать снова
        </button>
    </div>
</body>
</html>