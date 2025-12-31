<?php

return [
    'crm' => [
        'bitrix24' => [
            'create_lead' => ['name' => 'Создать лид', 'icon' => '📝'],
            'update_lead' => ['name' => 'Обновить лид', 'icon' => '✏️'],
            'create_deal' => ['name' => 'Создать сделку', 'icon' => '💰'],
            'update_deal' => ['name' => 'Обновить сделку', 'icon' => '🔄'],
            'change_stage' => ['name' => 'Изменить стадию', 'icon' => '📊'],
            'create_task' => ['name' => 'Создать задачу', 'icon' => '📋'],
            'add_comment' => ['name' => 'Добавить комментарий', 'icon' => '💬'],
            'get_entity' => ['name' => 'Получить данные', 'icon' => '🔍'],
        ],
        'amocrm' => [
            // Аналогичные действия
        ],
    ],
    
    'database' => [
        'query' => ['name' => 'SQL запрос', 'icon' => '🗃️'],
        'get_order' => ['name' => 'Получить заказ', 'icon' => '📦'],
        'update_order' => ['name' => 'Обновить заказ', 'icon' => '📝'],
        'check_inventory' => ['name' => 'Проверить наличие', 'icon' => '📊'],
        'get_user_data' => ['name' => 'Данные пользователя', 'icon' => '👤'],
    ],
    
    'communication' => [
        'send_email' => ['name' => 'Отправить Email', 'icon' => '📧'],
        'send_sms' => ['name' => 'Отправить SMS', 'icon' => '📱'],
        'send_telegram' => ['name' => 'Telegram сообщение', 'icon' => '✈️'],
        'send_whatsapp' => ['name' => 'WhatsApp сообщение', 'icon' => '💬'],
        'schedule_call' => ['name' => 'Запланировать звонок', 'icon' => '☎️'],
        'transfer_to_operator' => ['name' => 'Передать оператору', 'icon' => '👤'],
    ],
    
    'calendar' => [
        'create_event' => ['name' => 'Создать событие', 'icon' => '📅'],
        'check_availability' => ['name' => 'Проверить доступность', 'icon' => '🕐'],
        'book_appointment' => ['name' => 'Записать на прием', 'icon' => '📝'],
        'cancel_appointment' => ['name' => 'Отменить запись', 'icon' => '❌'],
        'reschedule' => ['name' => 'Перенести встречу', 'icon' => '🔄'],
    ],
    
    'payment' => [
        'create_invoice' => ['name' => 'Создать счет', 'icon' => '🧾'],
        'check_payment' => ['name' => 'Проверить оплату', 'icon' => '💳'],
        'process_refund' => ['name' => 'Оформить возврат', 'icon' => '💸'],
        'send_payment_link' => ['name' => 'Отправить ссылку оплаты', 'icon' => '🔗'],
    ],
    
    'analytics' => [
        'track_event' => ['name' => 'Отследить событие', 'icon' => '📊'],
        'update_metrics' => ['name' => 'Обновить метрики', 'icon' => '📈'],
        'log_interaction' => ['name' => 'Логировать взаимодействие', 'icon' => '📝'],
    ],
    
    'ai' => [
        'classify_intent' => ['name' => 'Классифицировать намерение', 'icon' => '🤖'],
        'sentiment_analysis' => ['name' => 'Анализ тональности', 'icon' => '😊'],
        'extract_entities' => ['name' => 'Извлечь сущности', 'icon' => '🔍'],
        'generate_response' => ['name' => 'Сгенерировать ответ', 'icon' => '💬'],
        'translate' => ['name' => 'Перевести текст', 'icon' => '🌐'],
    ],
    
    'integration' => [
        'webhook' => ['name' => 'Webhook запрос', 'icon' => '🔗'],
        'api_call' => ['name' => 'API вызов', 'icon' => '🌐'],
        'google_sheets' => ['name' => 'Google Sheets', 'icon' => '📊'],
        'notion' => ['name' => 'Notion', 'icon' => '📝'],
        //'slack' => ['name' => 'Slack уведомление', 'icon' => '💬'],
        //'trello' => ['name' => 'Trello карточка', 'icon' => '📋'],
    ],
    
    'flow' => [
        'condition' => ['name' => 'Условие If/Else', 'icon' => '🔀'],
        'loop' => ['name' => 'Цикл', 'icon' => '🔄'],
        'wait' => ['name' => 'Ожидание', 'icon' => '⏱️'],
        'parallel' => ['name' => 'Параллельное выполнение', 'icon' => '⚡'],
        'call_function' => ['name' => 'Вызвать функцию', 'icon' => '📞'],
    ]
];