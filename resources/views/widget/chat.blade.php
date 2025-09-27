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
        /* Анимации для модального окна */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        /* Стили для изображений в сообщениях */
        .message-image-container {
            margin: 8px 0;
            position: relative;
        }

        .message-image {
            max-width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
            background: linear-gradient(45deg, #f3f4f6 25%, transparent 25%, transparent 75%, #f3f4f6 75%, #f3f4f6),
                        linear-gradient(45deg, #f3f4f6 25%, transparent 25%, transparent 75%, #f3f4f6 75%, #f3f4f6);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        .message-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Индикатор загрузки для изображений */
        .message-image:not([src]) {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-image:not([src])::after {
            content: 'Загрузка...';
            color: #6b7280;
            font-size: 14px;
        }

        /* Стили для ссылок в сообщениях */
        .message-content a {
            transition: opacity 0.2s;
            display: inline-block;
        }

        .message-content a:hover {
            opacity: 0.8;
        }

        /* Стили для markdown ссылок */
        .message-content a[target="_blank"]::after {
            content: ' ↗';
            font-size: 12px;
            opacity: 0.7;
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
    // --- ГЛОБАЛЬНОЕ СОСТОЯНИЕ ВИДЖЕТА ---
    const state = {
        botSlug: '{{ $bot->slug }}',
        csrfToken: '{{ csrf_token() }}',
        sessionId: localStorage.getItem('chat_session_{{ $bot->slug }}'),
        conversationId: null,
        botSettings: {},
        userInfo: null,
        lastMessageId: 0, // Начинаем с 0, чтобы получить все сообщения
        isPolling: false, // Флаг, чтобы избежать одновременных запросов
        pollingInterval: null
    };

    // --- ФУНКЦИИ ЛОГИРОВАНИЯ ---
    const log = (message, data = '') => console.log(`[ChatWidget] ${message}`, data);

    // --- ОСНОВНАЯ ЛОГИКА ---

    /**
     * 1. Инициализация или перезагрузка чата
     */
    async function initChat() {
        log('Initializing chat...');
        document.getElementById('chatMessages').innerHTML = '<div class="typing-indicator" id="typingIndicator"><span></span><span></span><span></span></div>'; // Очищаем чат
        state.lastMessageId = 0; // Сбрасываем ID последнего сообщения

        try {
            const response = await fetch(`/widget/${state.botSlug}/initialize`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': state.csrfToken },
                body: JSON.stringify({ session_id: state.sessionId })
            });

            if (!response.ok) throw new Error('Initialization failed');
            
            const data = await response.json();
            log('Initialized successfully', data);

            state.sessionId = data.session_id;
            state.conversationId = data.conversation_id;
            state.botSettings = data.bot;
            state.userInfo = data.user_info;
            localStorage.setItem(`chat_session_${state.botSlug}`, state.sessionId);

            // Настраиваем UI
            document.getElementById('botName').textContent = state.botSettings.name || 'Чат-бот';
            document.getElementById('botAvatar').textContent = (state.botSettings.name || 'Б').charAt(0).toUpperCase();
            
            // Загружаем историю и обновляем lastMessageId
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => addMessageToChat(msg.role, msg.content, new Date(msg.created_at), msg.id)); // Добавляем msg.id
                state.lastMessageId = data.messages[data.messages.length - 1].id;
                log(`History loaded. Last message ID is now: ${state.lastMessageId}`);
            }

            // Показываем чат и запускаем поллинг
            document.getElementById('chatInputContainer').classList.remove('hidden');
            startPolling();

        } catch (error) {
            log('ERROR during initialization', error);
        }
    }
    async function confirmDelivery(b24MessageIds) {
        if (!b24MessageIds || b24MessageIds.length === 0) {
            return;
        }
        
        try {
            await fetch(`/widget/${state.botSlug}/confirm-delivery`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': state.csrfToken
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    b24_message_ids: b24MessageIds
                })
            });
        } catch (error) {
            console.error('Delivery confirmation failed:', error);
        }
    }

    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const messageText = input.value.trim();
        if (!messageText) return;

        // 1. Очищаем поле ввода
        input.value = '';

        // 2. Сразу показываем индикатор загрузки и блокируем кнопку
        showTypingIndicator();
        document.getElementById('sendButton').disabled = true;
        log('Отправка сообщения...', { text: messageText });

        try {
            // 3. Отправляем сообщение на сервер.
            // Мы не добавляем его в чат вручную. Поллинг его получит вместе с ответом бота.
            await fetch(`/widget/${state.botSlug}/message`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': state.csrfToken },
                body: JSON.stringify({ message: messageText, session_id: state.sessionId })
            });
            log('Сообщение отправлено. Ожидаем ответ через поллинг.');
        } catch (error) {
            log('ОШИБКА отправки сообщения', error);
            addMessageToChat('assistant', 'Ошибка отправки. Пожалуйста, попробуйте еще раз.');
            // В случае ошибки прячем индикатор
            hideTypingIndicator();
        } finally {
            // Разблокируем кнопку в любом случае
            document.getElementById('sendButton').disabled = false;
        }
    }

    // Добавьте эти две функции рядом с sendMessage
    function showTypingIndicator() {
        document.getElementById('typingIndicator').classList.add('active');
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        document.getElementById('typingIndicator').classList.remove('active');
    }

    /**
     * 3. Регулярный опрос сервера на новые сообщения
     */
    async function pollNewMessages() {
        if (state.isPolling) {
            log('Поллинг уже выполняется. Пропускаем.');
            return;
        }
        
        state.isPolling = true;
        log(`Опрос на новые сообщения после ID: ${state.lastMessageId}`);

        try {
            const response = await fetch(`/widget/${state.botSlug}/poll`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': state.csrfToken },
                body: JSON.stringify({ session_id: state.sessionId, last_message_id: state.lastMessageId })
            });

            if (!response.ok) throw new Error('Poll request failed');

            const data = await response.json();
            if (data.messages && data.messages.length > 0) {
                log(`Получено ${data.messages.length} новых сообщений.`);
                
                hideTypingIndicator();
                // --- НОВЫЙ КОД: Массив для ID, которые нужно подтвердить ---
                const b24MessageIdsToConfirm = [];

                data.messages.forEach(msg => {
                    addMessageToChat(msg.role, msg.content, new Date(msg.created_at), msg.id);
                    state.lastMessageId = msg.id;

                    // Если у сообщения есть ID из Битрикс24, добавляем его в массив для подтверждения
                    if (msg.metadata && msg.metadata.bitrix24_message_id) {
                        b24MessageIdsToConfirm.push(msg.metadata.bitrix24_message_id);
                    }
                });

                // После добавления всех сообщений, отправляем подтверждение, если есть что подтверждать
                if (b24MessageIdsToConfirm.length > 0) {
                    await confirmDelivery(b24MessageIdsToConfirm);
                    log(`Отправлено подтверждение для ${b24MessageIdsToConfirm.length} сообщений.`);
                }
                // --- КОНЕЦ НОВОГО КОДА ---

                log(`Поллинг завершен. ID последнего сообщения: ${state.lastMessageId}`);
            } else {
                log('Новых сообщений не найдено.');
            }
        } catch (error) {
            log('ОШИБКА во время поллинга', error);
        } finally {
            state.isPolling = false;
        }
    }

    // --- УТИЛИТЫ ---

    function startPolling() {
        stopPolling(); // Останавливаем старый, если есть
        state.pollingInterval = setInterval(pollNewMessages, 3000); // Опрашиваем каждые 3 секунды
        log('Polling started.');
    }

    function stopPolling() {
        if (state.pollingInterval) {
            clearInterval(state.pollingInterval);
            state.pollingInterval = null;
            log('Polling stopped.');
        }
    }
    
    function addMessageToChat(role, content, timestamp = new Date(), messageId = null) {
        // Проверяем, не существует ли уже сообщение с таким ID в чате
        if (messageId && document.getElementById(`msg-${messageId}`)) {
            log(`Сообщение ${messageId} уже отображено. Пропускаем дубликат.`);
            return;
        }

        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        // Присваиваем уникальный ID элементу для предотвращения дублирования
        if (messageId) {
            messageDiv.id = `msg-${messageId}`;
        }
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const textDiv = document.createElement('div');
        // Используем textContent для безопасной вставки текста, а затем заменяем переносы строк
        textDiv.innerHTML = formatMessageContent(content);
        //textDiv.innerHTML = textDiv.innerHTML.replace(/\n/g, '<br>');
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = new Date(timestamp).toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit'
        });

        contentDiv.appendChild(textDiv);
        contentDiv.appendChild(timeDiv);
        messageDiv.appendChild(contentDiv);
        
        const typingIndicator = document.getElementById('typingIndicator');
        messagesContainer.insertBefore(messageDiv, typingIndicator);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function formatMessageContent(text) {
        // Сначала экранируем HTML
        let escaped = escapeHtml(text);
        
        // Регулярное выражение для проверки, является ли URL изображением
        const imageExtensions = /\.(jpg|jpeg|png|gif|webp|svg|bmp)(\?.*)?$/i;
        
        // Сохраняем все HTML блоки во временный массив
        const htmlBlocks = [];
        let blockIndex = 0;
        
        // Функция для сохранения HTML блока и возврата placeholder
        function saveHtmlBlock(html) {
            const placeholder = `###HTML_BLOCK_${blockIndex}###`;
            htmlBlocks[blockIndex] = html;
            blockIndex++;
            return placeholder;
        }
        
        // Обрабатываем markdown ссылки формата [текст](url)
        escaped = escaped.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, linkText, url) {
            const cleanUrl = url.replace(/&amp;/g, '&');
            
            if (imageExtensions.test(cleanUrl)) {
                // Создаем HTML для изображения и сохраняем его
                const html = createImageHtml(cleanUrl, linkText);
                return saveHtmlBlock(html);
            } else {
                // Создаем HTML для ссылки и сохраняем его
                const html = createLinkHtml(cleanUrl, linkText);
                return saveHtmlBlock(html);
            }
        });
        
        // Обрабатываем обычные URL
        const urlRegex = /(https?:\/\/[^\s<>\[\]()]+)/gi;
        escaped = escaped.replace(urlRegex, function(url) {
            const cleanUrl = url.replace(/&amp;/g, '&');
            
            if (imageExtensions.test(cleanUrl)) {
                const html = createImageHtml(cleanUrl, 'Изображение');
                return saveHtmlBlock(html);
            } else {
                const html = createLinkHtml(cleanUrl);
                return saveHtmlBlock(html);
            }
        });
        
        // Теперь безопасно заменяем переносы строк на <br>
        escaped = escaped.replace(/\n/g, '<br>');
        
        // Восстанавливаем HTML блоки
        for (let i = 0; i < blockIndex; i++) {
            escaped = escaped.replace(`###HTML_BLOCK_${i}###`, htmlBlocks[i]);
        }
        
        return escaped;
    }

    // Вспомогательная функция для создания HTML изображения
    function createImageHtml(url, altText) {
        const safeUrl = url.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const safeAlt = altText.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        
        return `<div class="message-image-container" style="margin: 8px 0;">` +
               `<img src="${safeUrl}" ` +
               `alt="${safeAlt}" ` +
               `title="${safeAlt}" ` +
               `class="message-image" ` +
               `onclick="openImageModal('${safeUrl}')" ` +
               `onerror="this.onerror=null; this.parentElement.innerHTML='<a href=&quot;${safeUrl}&quot; target=&quot;_blank&quot; style=&quot;color: #667eea; text-decoration: underline;&quot;>${safeAlt}</a>';" ` +
               `style="max-width: 100%; max-height: 300px; border-radius: 8px; cursor: pointer; display: block;">` +
               `<div style="font-size: 11px; color: #6b7280; margin-top: 4px;">` +
               `<span>${safeAlt}</span> • ` +
               `<a href="${safeUrl}" target="_blank" style="color: #667eea; text-decoration: underline;">Открыть в новой вкладке</a>` +
               `</div>` +
               `</div>`;
    }

    // Вспомогательная функция для создания HTML ссылки
    function createLinkHtml(url, text) {
        const safeUrl = url.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        
        // Если текст не передан, генерируем его из URL
        if (!text) {
            try {
                const urlObj = new URL(url);
                text = urlObj.hostname + (urlObj.pathname !== '/' ? urlObj.pathname : '');
                if (text.length > 50) {
                    text = text.substring(0, 47) + '...';
                }
            } catch (e) {
                text = url;
                if (text.length > 50) {
                    text = text.substring(0, 47) + '...';
                }
            }
        }
        
        const safeText = escapeHtml(text);
        return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" style="color: #667eea; text-decoration: underline; word-break: break-all;">${safeText}</a>`;
    }

    // Функция для открытия модального окна с изображением
    function openImageModal(imageUrl) {
        // Создаем модальное окно
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            cursor: pointer;
            animation: fadeIn 0.3s ease;
        `;
        
        // Добавляем изображение
        const img = document.createElement('img');
        img.src = imageUrl;
        img.style.cssText = `
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
        `;
        
        // Добавляем кнопку закрытия
        const closeBtn = document.createElement('div');
        closeBtn.innerHTML = '✕';
        closeBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            transition: background 0.3s;
        `;
        closeBtn.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.2)'; };
        closeBtn.onmouseout = function() { this.style.background = 'rgba(255, 255, 255, 0.1)'; };
        
        // Добавляем элементы в модальное окно
        modal.appendChild(img);
        modal.appendChild(closeBtn);
        
        // Закрытие по клику
        modal.onclick = function(e) {
            if (e.target === modal || e.target === closeBtn) {
                modal.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => document.body.removeChild(modal), 300);
            }
        };
        
        // Закрытие по Escape
        const escapeHandler = function(e) {
            if (e.key === 'Escape') {
                modal.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => document.body.removeChild(modal), 300);
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
        
        // Добавляем модальное окно на страницу
        document.body.appendChild(modal);
    }
    
    function handleKeyPress(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // --- ЗАПУСК ---
    document.addEventListener('DOMContentLoaded', initChat);

</script>
</body>
</html>