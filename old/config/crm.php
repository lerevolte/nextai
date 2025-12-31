<?
return [
    /*
    |--------------------------------------------------------------------------
    | CRM Integrations Configuration
    |--------------------------------------------------------------------------
    |
    | Конфигурация для различных CRM систем
    |
    */

    'providers' => [
        'bitrix24' => [
            'name' => 'Битрикс24',
            'class' => \App\Services\CRM\Providers\Bitrix24Provider::class,
            'webhook_path' => 'webhooks/crm/bitrix24',
            'features' => [
                'leads' => true,
                'deals' => true,
                'contacts' => true,
                'companies' => true,
                'tasks' => true,
                'open_lines' => true,
                'telephony' => true,
            ],
            'rate_limits' => [
                'requests_per_second' => 2,
                'batch_size' => 50,
            ],
        ],
        
        'amocrm' => [
            'name' => 'AmoCRM',
            'class' => \App\Services\CRM\Providers\AmoCRMProvider::class,
            'webhook_path' => 'webhooks/crm/amocrm',
            'oauth' => [
                'authorize_url' => 'https://www.amocrm.ru/oauth',
                'token_url' => 'https://{subdomain}.amocrm.ru/oauth2/access_token',
            ],
            'features' => [
                'leads' => true,
                'deals' => true,
                'contacts' => true,
                'companies' => true,
                'tasks' => true,
                'pipelines' => true,
                'custom_fields' => true,
            ],
            'rate_limits' => [
                'requests_per_second' => 7,
                'batch_size' => 250,
            ],
        ],
        
        'avito' => [
            'name' => 'Avito',
            'class' => \App\Services\CRM\Providers\AvitoProvider::class,
            'webhook_path' => 'webhooks/crm/avito',
            'api_base_url' => 'https://api.avito.ru/',
            'features' => [
                'chats' => true,
                'messages' => true,
                'items' => true,
                'statistics' => true,
                'autoresponder' => true,
            ],
            'rate_limits' => [
                'requests_per_second' => 10,
                'batch_size' => 100,
            ],
        ],
        
        'salebot' => [
            'name' => 'Salebot',
            'class' => \App\Services\CRM\Providers\SalebotProvider::class,
            'webhook_path' => 'webhooks/crm/salebot',
            'api_base_url' => 'https://salebot.pro/api/',
            'features' => [
                'funnels' => true,
                'variables' => true,
                'operators' => true,
                'broadcast' => true,
                'analytics' => true,
                'webhooks' => true,
                'integrations' => true,
            ],
            'rate_limits' => [
                'requests_per_second' => 5,
                'batch_size' => 100,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Настройки синхронизации
    |
    */
    
    'sync' => [
        'queue' => 'crm-sync',
        'timeout' => 300, // секунд
        'retry_after' => 60, // секунд
        'max_attempts' => 3,
        'chunk_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Mappings
    |--------------------------------------------------------------------------
    |
    | Маппинг полей между системами
    |
    */
    
    'field_mappings' => [
        'universal' => [
            'name' => ['NAME', 'name', 'ФИО'],
            'email' => ['EMAIL', 'email', 'E-mail'],
            'phone' => ['PHONE', 'phone', 'Телефон'],
            'company' => ['COMPANY', 'company', 'Компания'],
            'position' => ['POST', 'position', 'Должность'],
            'website' => ['WEB', 'website', 'Сайт'],
            'address' => ['ADDRESS', 'address', 'Адрес'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead Sources
    |--------------------------------------------------------------------------
    |
    | Источники лидов для CRM
    |
    */
    
    'lead_sources' => [
        'chatbot' => 'Чат-бот',
        'widget' => 'Виджет на сайте',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'vk' => 'ВКонтакте',
        'avito' => 'Avito',
        'instagram' => 'Instagram',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Security
    |--------------------------------------------------------------------------
    |
    | Настройки безопасности для webhook
    |
    */
    
    'webhook_security' => [
        'verify_signature' => true,
        'allowed_ips' => env('CRM_WEBHOOK_IPS', ''),
        'secret_key' => env('CRM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Настройки логирования
    |
    */
    
    'logging' => [
        'enabled' => true,
        'channel' => 'crm',
        'log_requests' => env('CRM_LOG_REQUESTS', false),
        'log_responses' => env('CRM_LOG_RESPONSES', false),
        'sensitive_fields' => [
            'password',
            'token',
            'secret',
            'api_key',
            'access_token',
            'refresh_token',
        ],
    ],
];