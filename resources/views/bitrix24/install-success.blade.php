<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Успешная установка</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .success-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .success-icon svg {
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
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .info-label {
            color: #6b7280;
        }
        
        .info-value {
            color: #1f2937;
            font-weight: 500;
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
        
        .next-steps {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
            text-align: left;
        }
        
        .next-steps h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 12px;
        }
        
        .next-steps ol {
            margin: 0;
            padding-left: 20px;
            color: #3730a3;
            font-size: 13px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        
        <h1>Успешно подключено!</h1>
        <p style="color: #6b7280; margin-bottom: 24px;">
            Битрикс24 успешно привязан к вашей организации
        </p>
        
        <div class="info">
            <div class="info-row">
                <span class="info-label">Организация:</span>
                <span class="info-value">{{ $organization->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Битрикс24:</span>
                <span class="info-value">{{ $domain }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Статус:</span>
                <span class="info-value" style="color: #10b981;">✅ Активна</span>
            </div>
        </div>
        
        <div class="next-steps">
            <h3>🚀 Следующие шаги:</h3>
            <ol>
                <li>Приложение установится в ваш Битрикс24</li>
                <li>Откройте CRM → Инструменты → Чат-бот коннектор</li>
                <li>Зарегистрируйте коннекторы для ваших ботов</li>
                <li>Подключите их в Контакт-центре</li>
            </ol>
        </div>
        
        <button onclick="BX24.init(function(){ BX24.installFinish(); });" class="btn">
            Завершить установку
        </button>
    </div>
</body>
</html>