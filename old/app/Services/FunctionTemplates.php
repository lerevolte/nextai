<?php

namespace App\Services;

class FunctionTemplates
{
    /**
     * Получить шаблоны функций
     */
    public static function getTemplates(): array
    {
        return [
            'order_status' => [
                'name' => 'check_order_status',
                'display_name' => 'Проверка статуса заказа',
                'description' => 'Получает статус заказа из базы данных или CRM',
                'triggers' => [
                    [
                        'type' => 'intent',
                        'conditions' => ['intent' => 'order_status', 'min_confidence' => 0.7],
                    ],
                    [
                        'type' => 'keyword',
                        'conditions' => [
                            'keywords' => ['статус заказа', 'где мой заказ', 'отследить заказ'],
                            'mode' => 'any',
                        ],
                    ],
                ],
                'parameters' => [
                    ['code' => 'order_id', 'type' => 'string', 'name' => 'Номер заказа', 'is_required' => true],
                ],
                'actions' => [
                    [
                        'type' => 'get_order',
                        'provider' => 'database',
                        'config' => ['query' => 'SELECT * FROM orders WHERE id = :order_id'],
                    ],
                ],
                'behavior' => [
                    'on_success' => 'continue',
                    'success_message' => 'Статус заказа #{order_id}: {status}. Ожидаемая дата доставки: {delivery_date}',
                    'on_error' => 'continue',
                    'error_message' => 'Заказ #{order_id} не найден. Проверьте номер заказа.',
                ],
            ],
            
            'book_appointment' => [
                'name' => 'book_appointment',
                'display_name' => 'Запись на прием',
                'description' => 'Записывает клиента на прием к специалисту',
                'triggers' => [
                    [
                        'type' => 'intent',
                        'conditions' => ['intent' => 'booking', 'min_confidence' => 0.8],
                    ],
                    [
                        'type' => 'keyword',
                        'conditions' => [
                            'keywords' => ['записаться', 'запись на прием', 'забронировать время'],
                            'mode' => 'any',
                        ],
                    ],
                ],
                'parameters' => [
                    ['code' => 'client_name', 'type' => 'string', 'name' => 'Имя клиента', 'is_required' => true],
                    ['code' => 'client_phone', 'type' => 'string', 'name' => 'Телефон', 'is_required' => true],
                    ['code' => 'service', 'type' => 'string', 'name' => 'Услуга', 'is_required' => true],
                    ['code' => 'date', 'type' => 'date', 'name' => 'Дата', 'is_required' => true],
                    ['code' => 'time', 'type' => 'string', 'name' => 'Время', 'is_required' => true],
                ],
                'actions' => [
                    [
                        'type' => 'check_availability',
                        'provider' => 'calendar',
                        'config' => ['calendar_id' => 'main'],
                    ],
                    [
                        'type' => 'create_event',
                        'provider' => 'calendar',
                        'config' => ['calendar_id' => 'main', 'duration' => 60],
                    ],
                    [
                        'type' => 'send_sms',
                        'provider' => 'communication',
                        'config' => ['template' => 'appointment_confirmation'],
                    ],
                    [
                        'type' => 'create_lead',
                        'provider' => 'bitrix24',
                        'config' => ['status_id' => 'NEW'],
                    ],
                ],
                'behavior' => [
                    'on_success' => 'continue',
                    'success_message' => '✅ Вы записаны на {date} в {time}. Мы отправили SMS с подтверждением.',
                    'on_error' => 'continue',
                    'error_message' => 'К сожалению, это время занято. Выберите другое время.',
                ],
            ],
            
            'process_complaint' => [
                'name' => 'process_complaint',
                'display_name' => 'Обработка жалобы',
                'description' => 'Регистрирует жалобу клиента и создает задачу для менеджера',
                'triggers' => [
                    [
                        'type' => 'intent',
                        'conditions' => ['intent' => 'complaint', 'min_confidence' => 0.7],
                    ],
                    [
                        'type' => 'sentiment',
                        'conditions' => ['sentiment' => 'negative', 'threshold' => -0.5],
                    ],
                ],
                'parameters' => [
                    ['code' => 'client_name', 'type' => 'string', 'name' => 'Имя клиента'],
                    ['code' => 'complaint_text', 'type' => 'string', 'name' => 'Текст жалобы', 'is_required' => true],
                    ['code' => 'order_id', 'type' => 'string', 'name' => 'Номер заказа'],
                ],
                'actions' => [
                    [
                        'type' => 'sentiment_analysis',
                        'provider' => 'ai',
                        'config' => [],
                    ],
                    [
                        'type' => 'create_task',
                        'provider' => 'bitrix24',
                        'config' => [
                            'priority' => 'high',
                            'deadline' => '+2 hours',
                            'responsible' => 'manager_group',
                        ],
                    ],
                    [
                        'type' => 'send_email',
                        'provider' => 'communication',
                        'config' => [
                            'to' => 'manager@company.com',
                            'template' => 'urgent_complaint',
                        ],
                    ],
                    [
                        'type' => 'transfer_to_operator',
                        'provider' => 'communication',
                        'config' => ['priority' => 'high'],
                    ],
                ],
                'behavior' => [
                    'on_success' => 'pause',
                    'success_message' => '🔴 Ваша жалоба зарегистрирована. Менеджер свяжется с вами в течение 30 минут.',
                    'on_error' => 'transfer_to_operator',
                ],
            ],
            
            'calculate_price' => [
                'name' => 'calculate_price',
                'display_name' => 'Расчет стоимости',
                'description' => 'Рассчитывает стоимость услуги или товара',
                'triggers' => [
                    [
                        'type' => 'keyword',
                        'conditions' => [
                            'keywords' => ['сколько стоит', 'цена', 'стоимость', 'прайс'],
                            'mode' => 'any',
                        ],
                    ],
                ],
                'parameters' => [
                    ['code' => 'product', 'type' => 'string', 'name' => 'Товар/Услуга'],
                    ['code' => 'quantity', 'type' => 'number', 'name' => 'Количество'],
                    ['code' => 'options', 'type' => 'string', 'name' => 'Дополнительные опции'],
                ],
                'actions' => [
                    [
                        'type' => 'api_call',
                        'provider' => 'integration',
                        'config' => [
                            'url' => 'https://api.company.com/calculate',
                            'method' => 'POST',
                        ],
                    ],
                ],
                'behavior' => [
                    'on_success' => 'continue',
                    'success_message' => '💰 Стоимость: {price} руб.\nВ цену включено: {includes}\nСрок выполнения: {duration}',
                ],
            ],
            
            'loyalty_check' => [
                'name' => 'loyalty_check',
                'display_name' => 'Проверка баланса лояльности',
                'description' => 'Проверяет баланс бонусов и скидок клиента',
                'triggers' => [
                    [
                        'type' => 'keyword',
                        'conditions' => [
                            'keywords' => ['бонусы', 'баланс', 'скидка', 'кэшбек'],
                            'mode' => 'any',
                        ],
                    ],
                ],
                'parameters' => [
                    ['code' => 'client_phone', 'type' => 'string', 'name' => 'Телефон клиента'],
                    ['code' => 'client_email', 'type' => 'string', 'name' => 'Email клиента'],
                ],
                'actions' => [
                    [
                        'type' => 'get_user_data',
                        'provider' => 'database',
                        'config' => ['table' => 'loyalty_cards'],
                    ],
                ],
                'behavior' => [
                    'on_success' => 'continue',
                    'success_message' => '🎁 Ваш баланс: {points} бонусов\n💳 Уровень: {level}\n📅 Действует до: {expires}',
                    'on_error' => 'continue',
                    'error_message' => 'Карта лояльности не найдена. Хотите зарегистрироваться?',
                ],
            ],
        ];
    }
}