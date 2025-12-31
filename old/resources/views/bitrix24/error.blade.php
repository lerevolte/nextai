<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        
        .error-icon {
            width: 64px;
            height: 64px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .error-icon svg {
            width: 32px;
            height: 32px;
            color: #ef4444;
        }
        
        h1 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 12px;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            color: #991b1b;
            font-size: 14px;
            text-align: left;
        }
        
        .btn {
            padding: 12px 24px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }
        
        .support-info {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
        }
        
        .support-info a {
            color: #6366f1;
            text-decoration: none;
        }
        
        .support-info a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        
        <h1>Произошла ошибка</h1>
        <p style="color: #6b7280; margin-bottom: 20px;">
            К сожалению, что-то пошло не так
        </p>
        
        @if(isset($message) && $message)
        <div class="error-message">
            {{ $message }}
        </div>
        @endif
        
        <button onclick="window.location.reload();" class="btn">
            Попробовать снова
        </button>
        
        <div class="support-info">
            <p>Если проблема повторяется, обратитесь в поддержку:</p>
            <p style="margin-top: 8px;">
                📧 <a href="mailto:support@yourdomain.com">support@yourdomain.com</a>
            </p>
        </div>
    </div>
</body>
</html>