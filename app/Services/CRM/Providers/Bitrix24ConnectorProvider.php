<?php

namespace App\Services\CRM\Providers;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Models\Message;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Провайдер для интеграции с Битрикс24 через коннектор открытых линий
 * 
 * Этот класс реализует правильную интеграцию согласно документации Битрикс24
 * для создания собственного коннектора открытых линий
 */
class Bitrix24ConnectorProvider
{
    protected Client $client;
    protected CrmIntegration $integration;
    protected string $webhookUrl;
    protected array $config;
    
    /**
     * ID коннектора - уникальный идентификатор для Битрикс24
     * Формат: chatbot_{organization_id}_{bot_id}
     */
    protected string $connectorId;

    public function __construct(CrmIntegration $integration)
    {
        $this->integration = $integration;
        $this->client = new Client(['timeout' => 30]);
        $this->config = $integration->credentials ?? [];
        $this->webhookUrl = rtrim($this->config['webhook_url'], '/') . '/';
        
        // Генерируем уникальный ID коннектора для этой интеграции
        $this->connectorId = 'chatbot_' . $integration->organization_id . '_' . $integration->id;
    }

    /**
     * Регистрация коннектора в Битрикс24
     * Вызывается один раз при создании интеграции
     */
    public function registerConnector(Bot $bot): array
    {
        try {
            // 1. Регистрируем коннектор
            $registerResult = $this->makeRequest('imconnector.register', [
                'ID' => $this->getConnectorIdForBot($bot),
                'NAME' => $bot->name . ' - Чат-бот',
                'ICON' => [
                    'DATA_IMAGE' => $this->getIconDataUrl(),
                    'COLOR' => '#6366F1',
                    'SIZE' => '100%',
                    'POSITION' => 'center',
                ],
                'ICON_DISABLED' => [
                    'DATA_IMAGE' => $this->getIconDataUrl(),
                    'COLOR' => '#9CA3AF',
                    'SIZE' => '100%',
                    'POSITION' => 'center',
                ],
                // URL для настройки коннектора в Битрикс24
                'PLACEMENT_HANDLER' => url('/webhooks/crm/bitrix24/connector/settings'),
            ]);

            if (empty($registerResult['result'])) {
                throw new \Exception('Failed to register connector');
            }

            // 2. Подписываемся на события
            $eventResult = $this->makeRequest('event.bind', [
                'event' => 'OnImConnectorMessageAdd',
                'handler' => url('/webhooks/crm/bitrix24/connector/handler'),
            ]);

            if (empty($eventResult['result'])) {
                throw new \Exception('Failed to bind event');
            }

            // 3. Сохраняем информацию о регистрации
            $bot->update([
                'metadata' => array_merge($bot->metadata ?? [], [
                    'bitrix24_connector_registered' => true,
                    'bitrix24_connector_id' => $this->getConnectorIdForBot($bot),
                ])
            ]);

            Log::info('Bitrix24 connector registered', [
                'connector_id' => $this->getConnectorIdForBot($bot),
                'bot_id' => $bot->id,
            ]);

            return [
                'success' => true,
                'connector_id' => $this->getConnectorIdForBot($bot),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to register Bitrix24 connector', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Активация коннектора для конкретной открытой линии
     * Вызывается из интерфейса Битрикс24 при подключении
     */
    public function activateConnector(Bot $bot, int $lineId, bool $active = true): array
    {
        try {
            $connectorId = $this->getConnectorIdForBot($bot);
            
            // 1. Активируем коннектор
            $result = $this->makeRequest('imconnector.activate', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'ACTIVE' => $active ? 1 : 0,
            ]);

            if (empty($result['result'])) {
                throw new \Exception('Failed to activate connector');
            }

            // 2. Передаем данные виджета (если есть)
            $widgetData = $this->makeRequest('imconnector.connector.data.set', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'DATA' => [
                    'id' => $connectorId . '_line_' . $lineId,
                    'url_im' => route('widget.show', $bot->slug),
                    'name' => $bot->name . ' Widget',
                ],
            ]);

            // 3. Сохраняем информацию о линии
            $this->integration->update([
                'settings' => array_merge($this->integration->settings ?? [], [
                    'line_id' => $lineId,
                    'connector_active' => $active,
                ])
            ]);

            Log::info('Bitrix24 connector activated', [
                'connector_id' => $connectorId,
                'line_id' => $lineId,
                'active' => $active,
            ]);

            return [
                'success' => true,
                'line_id' => $lineId,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to activate Bitrix24 connector', [
                'bot_id' => $bot->id,
                'line_id' => $lineId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Отправка сообщения от пользователя в Битрикс24
     * Используется когда пользователь пишет в чат на сайте
     */
    public function sendUserMessage(Conversation $conversation, Message $message): array
    {
        info('sendUserMessage');
        try {
            $bot = $conversation->bot;
            $connectorId = $this->getConnectorIdForBot($bot);
            $lineId = $this->integration->settings['line_id'] ?? null;
            
            if (!$lineId) {
                throw new \Exception('Line ID not configured');
            }

            // Формируем данные сообщения в формате коннектора
            $messageData = [
                'user' => [
                    'id' => $conversation->external_id ?? $conversation->id,
                    'name' => $conversation->user_name ?? 'Гость',
                    'last_name' => '',
                    'url' => '',
                    'picture' => [
                        'url' => ''
                    ],
                ],
                'message' => [
                    'id' => $message->id,
                    'date' => $message->created_at->timestamp,
                    'text' => $message->content,
                ],
                'chat' => [
                    'id' => 'chat_' . $conversation->id,
                    'name' => 'Чат #' . $conversation->id,
                    'url' => route('conversations.show', [
                        $conversation->bot->organization,
                        $conversation->bot,
                        $conversation
                    ]),
                ],
            ];

            // Добавляем контактные данные если есть
            if ($conversation->user_email) {
                $messageData['user']['email'] = $conversation->user_email;
            }
            if ($conversation->user_phone) {
                $messageData['user']['phone'] = $conversation->user_phone;
            }

            // Отправляем сообщение в Битрикс24
            $result = $this->makeRequest('imconnector.send.messages', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => [$messageData],
            ]);

            if (empty($result['result'])) {
                throw new \Exception('Failed to send message to Bitrix24');
            }

            // Сохраняем ID сообщения в Битрикс24
            $message->update([
                'metadata' => array_merge($message->metadata ?? [], [
                    'bitrix24_message_id' => $result['result']['MESSAGES'][0] ?? null,
                ])
            ]);

            Log::info('Message sent to Bitrix24', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'connector_id' => $connectorId,
            ]);

            return [
                'success' => true,
                'bitrix24_message_id' => $result['result']['MESSAGES'][0] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send message to Bitrix24', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            info($e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Обработка входящего сообщения от оператора из Битрикс24
     * Вызывается через webhook при событии OnImConnectorMessageAdd
     */
    public function handleOperatorMessage(array $data): void
    {
        try {
            $connectorId = $data['CONNECTOR'] ?? '';
            $messages = $data['MESSAGES'] ?? [];
            
            foreach ($messages as $messageData) {
                // Находим диалог по chat ID
                $chatId = str_replace('chat_', '', $messageData['chat']['id'] ?? '');
                $conversation = Conversation::find($chatId);
                
                if (!$conversation) {
                    Log::warning('Conversation not found for Bitrix24 message', [
                        'chat_id' => $chatId,
                    ]);
                    continue;
                }

                // Сохраняем сообщение от оператора
                $message = $conversation->messages()->create([
                    'role' => 'operator',
                    'content' => $messageData['message']['text'] ?? '',
                    'metadata' => [
                        'bitrix24_message_id' => $messageData['message']['id'] ?? null,
                        'bitrix24_user_id' => $messageData['user']['id'] ?? null,
                        'operator_name' => $messageData['user']['name'] ?? 'Оператор',
                    ]
                ]);

                // Подтверждаем доставку сообщения
                $this->confirmMessageDelivery(
                    $conversation->bot,
                    $messageData['im'] ?? null,
                    $messageData['message']['id'] ?? null,
                    $messageData['chat']['id'] ?? null
                );

                // Обновляем статус диалога если нужно
                if ($conversation->status === 'active') {
                    $conversation->update(['status' => 'waiting_operator']);
                }

                Log::info('Operator message received from Bitrix24', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle operator message from Bitrix24', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Подтверждение доставки сообщения
     * Обязательно для корректной работы коннектора
     */
    protected function confirmMessageDelivery(Bot $bot, $imId, $messageId, $chatId): void
    {
        try {
            $connectorId = $this->getConnectorIdForBot($bot);
            $lineId = $this->integration->settings['line_id'] ?? null;
            
            if (!$lineId) {
                return;
            }

            $this->makeRequest('imconnector.send.status.delivery', [
                'CONNECTOR' => $connectorId,
                'LINE' => $lineId,
                'MESSAGES' => [
                    [
                        'im' => $imId,
                        'message' => [
                            'id' => [$messageId]
                        ],
                        'chat' => [
                            'id' => $chatId
                        ],
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to confirm message delivery', [
                'im_id' => $imId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Синхронизация всех сообщений диалога
     * Вызывается при первой синхронизации
     */
    public function syncConversationMessages(Conversation $conversation): void
    {
        info('syncConversationMessages');
        try {
            info('syncConversationMessages1');
            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get();
            
            foreach ($messages as $message) {
                // Пропускаем системные сообщения
                if ($message->role === 'system') {
                    info('system skip');
                    continue;
                }
                
                // Пропускаем если уже синхронизировано
                if ($message->metadata['bitrix24_message_id'] ?? null) {
                    continue;
                }
                
                // Отправляем только сообщения пользователя
                // Сообщения бота будут отправлены как ответы оператора
                if ($message->role === 'user') {
                    info('user send');
                    $this->sendUserMessage($conversation, $message);
                }
            }
            
            Log::info('Conversation messages synced with Bitrix24', [
                'conversation_id' => $conversation->id,
                'messages_count' => $messages->count(),
            ]);
            
        } catch (\Exception $e) {
            info('Failed to sync conversation messages'.$e->getMessage());
            Log::error('Failed to sync conversation messages', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получение ID коннектора для конкретного бота
     */
    protected function getConnectorIdForBot(Bot $bot): string
    {
        return 'chatbot_' . $bot->organization_id . '_' . $bot->id;
    }

    /**
     * Получение иконки коннектора в формате Data URL
     */
    protected function getIconDataUrl(): string
    {
        // SVG иконка чат-бота
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg viewBox="0 0 70 71" xmlns="http://www.w3.org/2000/svg">
                <path fill="#6366F1" d="M35 10c-13.8 0-25 9.2-25 20.5 0 6.5 3.7 12.3 9.5 16.1l-2.4 7.4c-.2.7.5 1.3 1.2.9l8.2-4.1c2.4.5 5 .7 7.5.7 13.8 0 25-9.2 25-20.5S48.8 10 35 10zm-10 25c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3zm10 0c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3zm10 0c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3z"/>
            </svg>
        ');
    }

    /**
     * Выполнение запроса к API Битрикс24
     */
    protected function makeRequest(string $method, array $params = []): array
    {
        try {
            $url = $this->webhookUrl . $method;
            
            $response = $this->client->post($url, [
                'json' => $params,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!empty($result['error'])) {
                throw new \Exception($result['error_description'] ?? $result['error']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Bitrix24 API request failed', [
                'method' => $method,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Удаление коннектора
     * Вызывается при удалении интеграции
     */
    public function unregisterConnector(Bot $bot): bool
    {
        try {
            $connectorId = $this->getConnectorIdForBot($bot);
            
            // Отвязываем обработчик событий
            $this->makeRequest('event.unbind', [
                'event' => 'OnImConnectorMessageAdd',
                'handler' => url('/webhooks/crm/bitrix24/connector/handler'),
            ]);
            
            // Деактивируем коннектор
            if ($lineId = $this->integration->settings['line_id'] ?? null) {
                $this->activateConnector($bot, $lineId, false);
            }
            
            // Удаляем регистрацию коннектора
            $this->makeRequest('imconnector.unregister', [
                'CONNECTOR' => $connectorId,
            ]);
            
            Log::info('Bitrix24 connector unregistered', [
                'connector_id' => $connectorId,
                'bot_id' => $bot->id,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to unregister Bitrix24 connector', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}