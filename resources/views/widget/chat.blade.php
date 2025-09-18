{{-- resources/views/widget/chat.blade.php --}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $bot->name }} - Chat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: transparent;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: #ffffff;
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .bot-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
            font-size: 18px;
        }

        .bot-info {
            flex: 1;
        }

        .bot-name {
            font-weight: 600;
            font-size: 16px;
        }

        .bot-status {
            font-size: 12px;
            opacity: 0.9;
            display: flex;
            align-items: center;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            margin-right: 6px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f9fafb;
        }

        .message {
            margin-bottom: 16px;
            display: flex;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
            position: relative;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.assistant .message-content {
            background: white;
            color: #1f2937;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 4px;
        }

        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 4px;
            max-width: 70px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .typing-indicator.active {
            display: inline-block;
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9ca3af;
            margin: 0 2px;
            animation: typing 1.4s infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-10px);
            }
        }

        .chat-input {
            padding: 16px;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .input-field {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            outline: none;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .input-field:focus {
            border-color: #667eea;
        }

        .send-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .send-button:hover {
            transform: scale(1.05);
        }

        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .welcome-message {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
            color: #4b5563;
            font-size: 14px;
        }

        .quick-replies {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .quick-reply {
            padding: 8px 16px;
            background: white;
            border: 1px solid #667eea;
            color: #667eea;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .quick-reply:hover {
            background: #667eea;
            color: white;
        }

        /* Scrollbar styles */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="bot-avatar">
                @if($bot->avatar_url)
                    <img src="{{ $bot->avatar_url }}" alt="{{ $bot->name }}" style="width: 100%; height: 100%; border-radius: 50%;">
                @else
                    {{ substr($bot->name, 0, 1) }}
                @endif
            </div>
            <div class="bot-info">
                <div class="bot-name">{{ $bot->name }}</div>
                <div class="bot-status">
                    <span class="status-dot"></span>
                    <span>Онлайн</span>
                </div>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            @if($bot->welcome_message)
                <div class="welcome-message">
                    {{ $bot->welcome_message }}
                </div>
            @endif

            <!-- Quick replies (опционально) -->
            @if($channel->settings['quick_replies'] ?? false)
                <div class="quick-replies">
                    @foreach($channel->settings['quick_replies'] as $reply)
                        <button class="quick-reply" onclick="sendQuickReply('{{ $reply }}')">{{ $reply }}</button>
                    @endforeach
                </div>
            @endif

            <div class="typing-indicator" id="typingIndicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <div class="chat-input">
            <input type="text" 
                   id="messageInput" 
                   class="input-field" 
                   placeholder="Введите сообщение..." 
                   onkeypress="handleKeyPress(event)">
            <button id="sendButton" class="send-button" onclick="sendMessage()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>

    <script>
        const botSlug = '{{ $bot->slug }}';
        const apiUrl = '{{ url("/widget") }}/' + botSlug;
        let sessionId = localStorage.getItem('chat_session_' + botSlug);
        let conversationId = null;

        // Инициализация чата
        async function initChat() {
            try {
                const response = await fetch(apiUrl + '/initialize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        session_id: sessionId
                    })
                });

                const data = await response.json();
                sessionId = data.session_id;
                conversationId = data.conversation_id;
                localStorage.setItem('chat_session_' + botSlug, sessionId);

                // Загружаем историю сообщений
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        addMessageToChat(msg.role, msg.content, new Date(msg.created_at));
                    });
                }
            } catch (error) {
                console.error('Failed to initialize chat:', error);
            }
        }

        // Отправка сообщения
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message) return;

            // Отключаем кнопку отправки
            document.getElementById('sendButton').disabled = true;

            // Добавляем сообщение пользователя в чат
            addMessageToChat('user', message);

            // Очищаем поле ввода
            input.value = '';

            // Показываем индикатор набора
            showTypingIndicator();

            try {
                const response = await fetch(apiUrl + '/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: sessionId
                    })
                });

                const data = await response.json();

                if (data.error) {
                    addMessageToChat('assistant', data.error);
                } else {
                    conversationId = data.conversation_id;
                    addMessageToChat('assistant', data.message.content);
                }
            } catch (error) {
                console.error('Failed to send message:', error);
                addMessageToChat('assistant', 'Произошла ошибка. Пожалуйста, попробуйте позже.');
            } finally {
                hideTypingIndicator();
                document.getElementById('sendButton').disabled = false;
            }
        }

        // Добавление сообщения в чат
        function addMessageToChat(role, content, timestamp = new Date()) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;

            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            
            // Преобразуем переносы строк в <br>
            contentDiv.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');

            const timeDiv = document.createElement('div');
            timeDiv.className = 'message-time';
            timeDiv.textContent = formatTime(timestamp);
            contentDiv.appendChild(timeDiv);

            messageDiv.appendChild(contentDiv);
            
            // Вставляем перед индикатором набора
            const typingIndicator = document.getElementById('typingIndicator');
            messagesContainer.insertBefore(messageDiv, typingIndicator);

            // Прокручиваем вниз
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Быстрые ответы
        function sendQuickReply(text) {
            document.getElementById('messageInput').value = text;
            sendMessage();
        }

        // Обработка нажатия Enter
        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        // Показать индикатор набора
        function showTypingIndicator() {
            document.getElementById('typingIndicator').classList.add('active');
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Скрыть индикатор набора
        function hideTypingIndicator() {
            document.getElementById('typingIndicator').classList.remove('active');
        }

        // Форматирование времени
        function formatTime(date) {
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
        }

        // Экранирование HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            initChat();
        });

        // Закрытие диалога при уходе со страницы
        window.addEventListener('beforeunload', function() {
            if (conversationId) {
                navigator.sendBeacon(apiUrl + '/end', JSON.stringify({
                    session_id: sessionId,
                    conversation_id: conversationId
                }));
            }
        });
    </script>
</body>
</html>