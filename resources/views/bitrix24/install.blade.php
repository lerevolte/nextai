<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка приложения</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
</head>
<body style="font-family: Arial, sans-serif; padding: 40px; text-align: center;">
    <div style="max-width: 500px; margin: 0 auto;">
        <h1 style="color: #10b981;">✅ Приложение успешно установлено!</h1>
        <p style="color: #666; margin: 20px 0;">
            Чат-бот коннектор готов к работе
        </p>
        <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 30px 0;">
            <p style="margin: 0; color: #374151;">
                Домен: <strong>{{ $domain }}</strong>
            </p>
        </div>
        <button onclick="BX24.init(function(){ BX24.installFinish(); });" 
                style="background: #6366f1; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px;">
            Завершить установку
        </button>
    </div>
</body>
</html>