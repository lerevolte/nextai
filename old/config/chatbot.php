<?php

return [
    'proxy_url' => env('PROXY_URL', null),
    'ai_providers' => [
        'openai' => [
            'models' => [
                'gpt-4o' => 'GPT-4 Optimized',
                'gpt-4o-mini' => 'GPT-4 Mini',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            ],
            'api_key' => env('OPENAI_API_KEY'),
        ],
        'gemini' => [
            'models' => [
                'gemini-pro' => 'Gemini Pro',
                'gemini-pro-vision' => 'Gemini Pro Vision',
            ],
            'api_key' => env('GEMINI_API_KEY'),
        ],
        'deepseek' => [
            'models' => [
                'deepseek-chat' => 'DeepSeek Chat',
                'deepseek-coder' => 'DeepSeek Coder',
            ],
            'api_key' => env('DEEPSEEK_API_KEY'),
        ],
    ],

    'channels' => [
        'web' => [
            'enabled' => true,
            'widget_url' => env('APP_URL') . '/widget',
        ],
        'telegram' => [
            'enabled' => true,
            'webhook_url' => env('APP_URL') . '/webhooks/telegram',
        ],
        'whatsapp' => [
            'enabled' => true,
            'provider' => 'twilio', // или 'meta'
        ],
        // ... другие каналы
    ],

    'limits' => [
        'free' => [
            'bots' => 1,
            'messages_per_month' => 1000,
            'channels' => ['web'],
        ],
        'starter' => [
            'bots' => 3,
            'messages_per_month' => 10000,
            'channels' => ['web', 'telegram'],
        ],
        'pro' => [
            'bots' => 10,
            'messages_per_month' => 100000,
            'channels' => 'all',
        ],
    ],
];