<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Widget</title>
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

        /* Форма контактов */
        .contact-form {
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease;
        }

        .contact-form h3 {
            margin-bottom: 16px;
            color: #1f2937;
            font-size: 18px;
        }

        .contact-form p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input.error {
            border-color: #ef4444;
        }

        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
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

        .user-info-badge {
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 8px;
            margin: 0 20px 10px 20px;
            font-size: 13px;
            color: #6b7280;
            display: none;
        }

        .user-info-badge.show {
            display: block;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="bot-avatar" id="botAvatar">
                B
            </div>
            <div class="bot-info">
                <div class="bot-name" id="botName">Чат-бот</div>
                <div class="bot-status">
                    <span class="status-dot"></span>
                    <span>Онлайн</span>
                </div>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <!-- Форма сбора контактов -->
            <div class="contact-form" id="contactForm">
                <h3>Давайте знакомиться! 👋</h3>
                <p>Пожалуйста, представьтесь, чтобы мы могли лучше вам помочь</p>
                
                <div class="form-group">
                    <label for="userName">Ваше имя *</label>
                    <input type="text" id="userName" placeholder="Иван Иванов" required>
                    <div class="error-message" id="userNameError">Пожалуйста, введите ваше имя</div>
                </div>

                <div class="form-group">
                    <label for="userEmail">Email</label>
                    <input type="email" id="userEmail" placeholder="ivan@example.com">
                    <div class="error-message" id="userEmailError">Введите корректный email</div>
                </div>

                <div class="form-group">
                    <label for="userPhone">Телефон</label>
                    <input type="tel" id="userPhone" placeholder="+7 900 123-45-67">
                    <div class="error-message" id="userPhoneError">Введите корректный телефон</div>
                </div>

                <div class="form-buttons">
                    <button class="btn btn-primary" onclick="submitContactForm()">Начать чат</button>
                    <button class="btn btn-secondary" onclick="skipContactForm()">Пропустить</button>
                </div>
            </div>

            <!-- Информация о пользователе -->
            <div class="user-info-badge" id="userInfoBadge">
                Вы общаетесь как: <strong id="userDisplayName">Гость</strong>
            </div>

            <div class="typing-indicator" id="typingIndicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <div class="chat-input hidden" id="chatInputContainer">
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
        // Конфигурация (будет заменена при интеграции)
        const config = {
            botSlug: 'test-bot',
            apiUrl: '/widget/test-bot',
            csrfToken: 'test-token',
            collectContacts: true, // Можно взять из настроек бота
            welcomeMessage: null,
            botName: 'Помощник',
            botAvatar: null
        };

        let sessionId = localStorage.getItem('chat_session_' + config.botSlug);
        let conversationId = null;
        let userInfo = {
            name: null,
            email: null,
            phone: null
        };

        // Инициализация чата
        async function initChat() {
            // Устанавливаем имя и аватар бота
            if (config.botName) {
                document.getElementById('botName').textContent = config.botName;
                document.getElementById('botAvatar').textContent = config.botName.charAt(0).toUpperCase();
            }

            // Проверяем, нужно ли собирать контакты
            const savedUserInfo = localStorage.getItem('chat_user_info_' + config.botSlug);
            if (savedUserInfo) {
                userInfo = JSON.parse(savedUserInfo);
                startChatWithUser();
            } else if (!config.collectContacts) {
                // Если сбор контактов отключен, сразу начинаем чат
                skipContactForm();
            }
            // Иначе показываем форму контактов (уже видна по умолчанию)
        }

        // Валидация формы контактов
        function validateContactForm() {
            let isValid = true;
            
            const userName = document.getElementById('userName');
            const userEmail = document.getElementById('userEmail');
            const userPhone = document.getElementById('userPhone');
            
            // Сброс ошибок
            document.querySelectorAll('.error-message').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('input').forEach(el => el.classList.remove('error'));
            
            // Проверка имени (обязательное поле)
            if (!userName.value.trim()) {
                userName.classList.add('error');
                document.getElementById('userNameError').classList.add('show');
                isValid = false;
            }
            
            // Проверка email (если заполнен)
            if (userEmail.value && !isValidEmail(userEmail.value)) {
                userEmail.classList.add('error');
                document.getElementById('userEmailError').classList.add('show');
                isValid = false;
            }
            
            // Проверка телефона (если заполнен)
            if (userPhone.value && !isValidPhone(userPhone.value)) {
                userPhone.classList.add('error');
                document.getElementById('userPhoneError').classList.add('show');
                isValid = false;
            }
            
            return isValid;
        }

        // Проверка email
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Проверка телефона
        function isValidPhone(phone) {
            // Простая проверка - минимум 10 цифр
            const digits = phone.replace(/\D/g, '');
            return digits.length >= 10;
        }

        // Отправка формы контактов
        function submitContactForm() {
            if (!validateContactForm()) {
                return;
            }
            
            userInfo = {
                name: document.getElementById('userName').value.trim(),
                email: document.getElementById('userEmail').value.trim(),
                phone: document.getElementById('userPhone').value.trim()
            };
            
            // Сохраняем в localStorage
            localStorage.setItem('chat_user_info_' + config.botSlug, JSON.stringify(userInfo));
            
            startChatWithUser();
        }

        // Пропуск формы контактов
        function skipContactForm() {
            userInfo = {
                name: 'Гость',
                email: null,
                phone: null
            };
            
            startChatWithUser();
        }

        // Начало чата с пользователем
        async function startChatWithUser() {
            // Скрываем форму
            document.getElementById('contactForm').style.display = 'none';
            
            // Показываем имя пользователя
            document.getElementById('userDisplayName').textContent = userInfo.name || 'Гость';
            document.getElementById('userInfoBadge').classList.add('show');
            
            // Показываем поле ввода
            document.getElementById('chatInputContainer').classList.remove('hidden');
            
            // Инициализируем чат на сервере
            try {
                const response = await fetch(config.apiUrl + '/initialize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        user_info: userInfo
                    })
                });

                const data = await response.json();
                sessionId = data.session_id;
                conversationId = data.conversation_id;
                localStorage.setItem('chat_session_' + config.botSlug, sessionId);

                // Показываем приветственное сообщение
                if (data.bot && data.bot.welcome_message) {
                    addMessageToChat('assistant', data.bot.welcome_message);
                } else if (config.welcomeMessage) {
                    addMessageToChat('assistant', config.welcomeMessage);
                } else {
                    addMessageToChat('assistant', `Здравствуйте, ${userInfo.name}! Чем могу помочь?`);
                }

                // Загружаем историю сообщений
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        if (!msg.content.includes('Здравствуйте')) { // Избегаем дублирования приветствия
                            addMessageToChat(msg.role, msg.content, new Date(msg.created_at));
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to initialize chat:', error);
                addMessageToChat('assistant', 'Произошла ошибка при инициализации чата. Пожалуйста, обновите страницу.');
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
                const response = await fetch(config.apiUrl + '/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: sessionId,
                        user_info: userInfo // Отправляем информацию о пользователе
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
                navigator.sendBeacon(config.apiUrl + '/end', JSON.stringify({
                    session_id: sessionId,
                    conversation_id: conversationId
                }));
            }
        });
    </script>
</body>
</html>