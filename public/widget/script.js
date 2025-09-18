// public/widget/script.js
(function() {
    'use strict';

    // Проверяем, что код выполняется в браузере
    if (typeof window === 'undefined') {
        return;
    }

    const ChatBotWidget = {
        config: {
            botId: null,
            position: 'bottom-right',
            primaryColor: '#667eea',
            buttonSize: 60,
            windowWidth: 380,
            windowHeight: 600,
            zIndex: 9999,
            baseUrl: null // Базовый URL для загрузки iframe
        },

        initialized: false,
        isOpen: false,
        unreadCount: 0,
        button: null,
        window: null,
        iframe: null,

        init: function(options) {
            if (this.initialized) return;

            // Сливаем переданные опции с конфигом по умолчанию
            this.config = Object.assign({}, this.config, options);

            // Если baseUrl не передан в опциях, пытаемся определить его автоматически
            if (!this.config.baseUrl) {
                const scripts = document.getElementsByTagName('script');
                for (let i = 0; i < scripts.length; i++) {
                    if (scripts[i].src && scripts[i].src.includes('/widget/script.js')) {
                        try {
                            const url = new URL(scripts[i].src);
                            this.config.baseUrl = url.origin;
                            break;
                        } catch (e) {
                            console.error('ChatBotWidget: Could not parse script URL.', e);
                        }
                    }
                }
            }

            // Валидация
            if (!this.config.baseUrl) {
                console.error('ChatBotWidget Error: `baseUrl` could not be determined. Please provide it in the init options, e.g., { baseUrl: "https://your-service.com" }');
                return;
            }

            if (!this.config.botId) {
                console.error('ChatBotWidget Error: `botId` is required.');
                return;
            }

            // Используем `DOMContentLoaded` чтобы убедиться, что `body` доступен
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.mount());
            } else {
                this.mount();
            }
        },
        
        mount: function() {
            this.createStyles();
            this.createButton();
            this.createWindow();
            this.attachEventListeners();
            this.initialized = true;
        },

        createStyles: function() {
            if (document.getElementById('chatbot-widget-styles')) {
                return;
            }

            const style = document.createElement('style');
            style.id = 'chatbot-widget-styles';
            style.textContent = `
                .chatbot-widget-button {
                    position: fixed;
                    width: ${this.config.buttonSize}px;
                    height: ${this.config.buttonSize}px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, ${this.config.primaryColor} 0%, #764ba2 100%);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    cursor: pointer;
                    z-index: ${this.config.zIndex};
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: transform 0.3s, box-shadow 0.3s;
                }
                .chatbot-widget-button:hover {
                    transform: scale(1.05);
                    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
                }
                .chatbot-widget-button.bottom-right { bottom: 20px; right: 20px; }
                .chatbot-widget-button.bottom-left { bottom: 20px; left: 20px; }
                .chatbot-widget-button svg { width: 28px; height: 28px; fill: white; }
                .chatbot-widget-badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #ef4444;
                    color: white;
                    border-radius: 10px;
                    padding: 2px 6px;
                    font-size: 11px;
                    font-weight: bold;
                    min-width: 18px;
                    text-align: center;
                }
                .chatbot-widget-window {
                    position: fixed;
                    width: ${this.config.windowWidth}px;
                    height: ${this.config.windowHeight}px;
                    max-width: 100vw;
                    max-height: 100vh;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                    z-index: ${this.config.zIndex};
                    display: none;
                    overflow: hidden;
                    flex-direction: column;
                }
                .chatbot-widget-window.open {
                    display: flex;
                    animation: slideUp 0.3s ease;
                }
                .chatbot-widget-window.bottom-right { bottom: ${this.config.buttonSize + 30}px; right: 20px; }
                .chatbot-widget-window.bottom-left { bottom: ${this.config.buttonSize + 30}px; left: 20px; }
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .chatbot-widget-iframe {
                    width: 100%;
                    height: 100%;
                    border: none;
                }
                @media (max-width: 480px) {
                    .chatbot-widget-window {
                        width: 100vw !important;
                        height: 100vh !important;
                        bottom: 0 !important;
                        right: 0 !important;
                        left: 0 !important;
                        border-radius: 0 !important;
                    }
                    .chatbot-widget-button {
                        bottom: 10px !important;
                        right: 10px !important;
                    }
                }
            `;
            document.head.appendChild(style);
        },

        createButton: function() {
            const button = document.createElement('div');
            button.className = `chatbot-widget-button ${this.config.position}`;
            button.innerHTML = `
                <svg viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 3 .97 4.29L1 23l6.71-1.97C9 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.41 0-2.73-.36-3.88-.98l-.28-.14-2.92.77.79-2.89-.18-.29C4.91 14.73 4.55 13.38 4.55 12c0-4.42 3.58-8 8-8s8 3.58 8 8-3.58 8-8 8zm4.54-5.88c-.25-.12-1.47-.72-1.7-.8-.23-.09-.39-.13-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.44.13-.15.17-.25.25-.42.09-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43-.15 0-.31-.02-.48-.02-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.12.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.2-.58.2-1.08.14-1.18-.06-.1-.23-.17-.48-.29z"/>
                </svg>
                <span class="chatbot-widget-badge" style="display: none;">0</span>
            `;
            document.body.appendChild(button);
            this.button = button;
        },

        createWindow: function() {
            const widgetWindow = document.createElement('div');
            widgetWindow.className = `chatbot-widget-window ${this.config.position}`;
            
            const iframe = document.createElement('iframe');
            iframe.className = 'chatbot-widget-iframe';
            // Теперь эта строка будет работать корректно
            iframe.src = `${this.config.baseUrl}/widget/${this.config.botId}`;
            
            widgetWindow.appendChild(iframe);
            document.body.appendChild(widgetWindow);
            
            this.window = widgetWindow;
            this.iframe = iframe;
        },

        attachEventListeners: function() {
            const self = this;
            
            if (this.button) {
                this.button.addEventListener('click', () => self.toggle());
            }

            window.addEventListener('message', function(event) {
                // Всегда проверяем origin для безопасности
                if (event.origin !== self.config.baseUrl) {
                    return;
                }
                
                if (event.data.type === 'chatbot-new-message' && !self.isOpen) {
                    self.incrementUnreadCount();
                }
                
                if (event.data.type === 'chatbot-close') {
                    self.close();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });
        },

        toggle: function() {
            this.isOpen ? this.close() : this.open();
        },

        open: function() {
            if (this.window) {
                this.window.classList.add('open');
            }
            this.isOpen = true;
            this.resetUnreadCount();
            
            if (this.iframe && this.iframe.contentWindow) {
                // Используем `this.config.baseUrl` вместо '*' для большей безопасности
                this.iframe.contentWindow.postMessage({ type: 'chatbot-opened' }, this.config.baseUrl);
            }
        },

        close: function() {
            if (this.window) {
                this.window.classList.remove('open');
            }
            this.isOpen = false;
        },

        incrementUnreadCount: function() {
            this.unreadCount++;
            this.updateBadge();
        },

        resetUnreadCount: function() {
            this.unreadCount = 0;
            this.updateBadge();
        },

        updateBadge: function() {
            if (!this.button) return;
            const badge = this.button.querySelector('.chatbot-widget-badge');
            if (!badge) return;
            
            if (this.unreadCount > 0) {
                badge.style.display = 'block';
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            } else {
                badge.style.display = 'none';
            }
        }
    };

    if (typeof window !== 'undefined') {
        window.ChatBotWidget = ChatBotWidget;
    }
})();
