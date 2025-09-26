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
        <div class="bot-avatar" id="botAvatar">B</div>
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
        <div class="contact-form hidden" id="contactForm">
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
        <input type="text" id="messageInput" class="input-field" placeholder="Введите сообщение..." onkeypress="handleKeyPress(event)">
        <button id="sendButton" class="send-button" onclick="sendMessage()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
        </button>
    </div>
</div>

<script>
    const config = {
        botSlug: '{{ $bot->slug }}',
        apiUrl: '/widget/{{ $bot->slug }}',
        csrfToken: '{{ csrf_token() }}'
    };

    let sessionId = localStorage.getItem('chat_session_' + config.botSlug);
    let conversationId = null;
    let botSettings = {};
    let userInfo = null;

    // --- Инициализация чата при загрузке ---
    async function initChat() {
        try {
            const response = await fetch(config.apiUrl + '/initialize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: JSON.stringify({ session_id: sessionId })
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();
            
            // Обновляем состояние из ответа сервера
            sessionId = data.session_id;
            conversationId = data.conversation_id;
            botSettings = data.bot;
            userInfo = data.user_info;

            localStorage.setItem('chat_session_' + config.botSlug, sessionId);
            
            // Настраиваем UI бота
            document.getElementById('botName').textContent = botSettings.name || 'Чат-бот';
            document.getElementById('botAvatar').textContent = (botSettings.name || 'Б').charAt(0).toUpperCase();

            // Решаем, что показать: форму или чат
            if (userInfo) {
                // Если сервер вернул данные пользователя, значит сессия активна
                localStorage.setItem('chat_user_info_' + config.botSlug, JSON.stringify(userInfo));
                displayChatInterface(data.messages);
            } else if (botSettings.collect_contacts) {
                // Если данных нет и нужно их собирать - показываем форму
                localStorage.removeItem('chat_user_info_' + config.botSlug);
                displayContactForm();
            } else {
                // Если данные не нужны - начинаем как гость
                skipContactForm();
            }

        } catch (error) {
            console.error('Failed to initialize chat:', error);
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = '<p style="text-align: center; padding: 20px; color: #6b7280;">Произошла ошибка при загрузке чата. Пожалуйста, обновите страницу.</p>';
        }
    }

    // --- Управление UI ---

    function displayChatInterface(messages = []) {
        document.getElementById('contactForm').classList.add('hidden');
        document.getElementById('chatInputContainer').classList.remove('hidden');
        
        const badge = document.getElementById('userInfoBadge');
        if (userInfo && userInfo.name) {
            document.getElementById('userDisplayName').textContent = userInfo.name;
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
        
        clearMessages();
        loadMessageHistory(messages);
    }
    
    function displayContactForm() {
        clearMessages();
        document.getElementById('contactForm').classList.remove('hidden');
        document.getElementById('chatInputContainer').classList.add('hidden');
        document.getElementById('userInfoBadge').classList.remove('show');
    }

    function clearMessages() {
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.querySelectorAll('.message').forEach(el => el.remove());
    }

    // --- Обработка формы контактов ---

    function validateContactForm() {
        let isValid = true;
        const userName = document.getElementById('userName');
        const userEmail = document.getElementById('userEmail');
        const userPhone = document.getElementById('userPhone');

        document.querySelectorAll('.error-message').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('input').forEach(el => el.classList.remove('error'));

        if (!userName.value.trim()) {
            userName.classList.add('error');
            document.getElementById('userNameError').classList.add('show');
            isValid = false;
        }
        if (userEmail.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userEmail.value)) {
            userEmail.classList.add('error');
            document.getElementById('userEmailError').classList.add('show');
            isValid = false;
        }
        if (userPhone.value && !/\d{10,}/.test(userPhone.value.replace(/\D/g, ''))) {
            userPhone.classList.add('error');
            document.getElementById('userPhoneError').classList.add('show');
            isValid = false;
        }
        return isValid;
    }

    async function submitContactForm() {
        if (!validateContactForm()) return;
        
        const submittedUserInfo = {
            name: document.getElementById('userName').value.trim(),
            email: document.getElementById('userEmail').value.trim() || null,
            phone: document.getElementById('userPhone').value.trim() || null
        };
        
        // Отправляем данные на сервер для обновления диалога
        await startSessionWithUserInfo(submittedUserInfo);
    }

    async function skipContactForm() {
        await startSessionWithUserInfo({ name: 'Гость', email: null, phone: null });
    }

    async function startSessionWithUserInfo(newUserInfo) {
        try {
            const response = await fetch(config.apiUrl + '/initialize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: JSON.stringify({ session_id: sessionId, user_info: newUserInfo })
            });
            const data = await response.json();

            // Обновляем состояние и UI
            userInfo = data.user_info;
            conversationId = data.conversation_id;
            localStorage.setItem('chat_user_info_' + config.botSlug, JSON.stringify(userInfo));
            displayChatInterface(data.messages);
            
            // Добавляем приветственное сообщение, если история пуста
            if (!data.messages || data.messages.length === 0) {
                 const welcomeMsg = botSettings.welcome_message || `Здравствуйте, ${userInfo.name}! Чем могу помочь?`;
                 addMessageToChat('assistant', welcomeMsg);
            }

        } catch (error) {
            console.error('Failed to start session with user info:', error);
            addMessageToChat('assistant', 'Не удалось начать чат. Пожалуйста, попробуйте снова.');
        }
    }
    
    // --- Логика чата ---

    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        if (!message) return;

        document.getElementById('sendButton').disabled = true;
        addMessageToChat('user', message);
        input.value = '';
        showTypingIndicator();

        try {
            const response = await fetch(config.apiUrl + '/message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: JSON.stringify({ message, session_id: sessionId })
            });
            const data = await response.json();

            if (data.error) {
                addMessageToChat('assistant', data.error);
            } else {
                conversationId = data.conversation_id;
                addMessageToChat('assistant', data.message.content, new Date(data.message.created_at));
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            addMessageToChat('assistant', 'Произошла ошибка. Пожалуйста, попробуйте позже.');
        } finally {
            hideTypingIndicator();
            document.getElementById('sendButton').disabled = false;
        }
    }

    function loadMessageHistory(messages) {
        if (messages && messages.length > 0) {
            messages.forEach(msg => addMessageToChat(msg.role, msg.content, new Date(msg.created_at)));
        }
    }

    function addMessageToChat(role, content, timestamp = new Date()) {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const textDiv = document.createElement('div');
        textDiv.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = formatTime(timestamp);

        contentDiv.appendChild(textDiv);
        contentDiv.appendChild(timeDiv);
        messageDiv.appendChild(contentDiv);
        
        const typingIndicator = document.getElementById('typingIndicator');
        messagesContainer.insertBefore(messageDiv, typingIndicator);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function handleKeyPress(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }

    function showTypingIndicator() {
        document.getElementById('typingIndicator').classList.add('active');
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        document.getElementById('typingIndicator').classList.remove('active');
    }
    
    // --- Утилиты ---

    function formatTime(date) {
        if (!(date instanceof Date)) date = new Date(date);
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // --- Запуск и завершение ---

    document.addEventListener('DOMContentLoaded', initChat);

    window.addEventListener('beforeunload', () => {
        if (conversationId && sessionId) {
            navigator.sendBeacon(
                config.apiUrl + '/end',
                JSON.stringify({
                    session_id: sessionId,
                    conversation_id: conversationId,
                    _token: config.csrfToken
                })
            );
        }
    });

    let lastMessageId = null;
    let pollingInterval = null;

    function startPolling() { // Убрали аргументы
        if (pollingInterval) clearInterval(pollingInterval);
        
        pollingInterval = setInterval(() => {
            pollNewMessages();
        }, 3000); // Можно увеличить интервал до 3 секунд
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    async function pollNewMessages() { // Убрали аргументы, они берутся из глобальных переменных
        try {
            const response = await fetch(`/widget/${config.botSlug}/poll`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    last_message_id: lastMessageId
                })
            });

            if (!response.ok) return;

            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                const b24MessageIdsToConfirm = []; // Массив для ID, которые нужно подтвердить

                data.messages.forEach(message => {
                    // Добавляем сообщение в чат
                    addMessageToChat(message.role, message.content, new Date(message.created_at)); // Используем старую функцию addMessageToChat
                    lastMessageId = message.id;

                    // Если у сообщения есть B24 ID, добавляем его в массив для подтверждения
                    if (message.metadata && message.metadata.bitrix24_message_id) {
                        b24MessageIdsToConfirm.push(message.metadata.bitrix24_message_id);
                    }
                });

                // После добавления всех сообщений, отправляем подтверждение
                if (b24MessageIdsToConfirm.length > 0) {
                    await confirmDelivery(b24MessageIdsToConfirm);
                }
            }
            
            // ... (обработка статуса диалога, если нужно)

        } catch (error) {
            console.error('Polling error:', error);
        }
    }

    function appendMessageToChat(message) {
        const chatContainer = document.getElementById('chat-messages');
        if (!chatContainer) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${message.role}`;
        
        // Добавляем имя оператора если есть
        if (message.role === 'operator' && message.metadata?.operator_name) {
            messageDiv.innerHTML = `
                <div class="operator-name">${message.metadata.operator_name}</div>
                <div class="message-content">${message.content}</div>
            `;
        } else {
            messageDiv.textContent = message.content;
        }
        
        chatContainer.appendChild(messageDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    async function confirmDelivery(b24MessageIds) {
        if (!b24MessageIds || b24MessageIds.length === 0) {
            return;
        }
        
        try {
            await fetch(`/widget/${config.botSlug}/confirm-delivery`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    b24_message_ids: b24MessageIds
                })
            });
        } catch (error) {
            console.error('Delivery confirmation failed:', error);
        }
    }

    // Запускаем polling после инициализации виджета
    // Добавьте это в код инициализации виджета
    startPolling();
</script>
</body>
</html>