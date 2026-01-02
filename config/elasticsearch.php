<?php

return [
    'host' => env('ELASTICSEARCH_HOST', 'localhost:9200'),
    'user' => env('ELASTICSEARCH_USER', ''),
    'password' => env('ELASTICSEARCH_PASSWORD', ''),
    'cloud_id' => env('ELASTICSEARCH_CLOUD_ID', ''),
    'api_key' => env('ELASTICSEARCH_API_KEY', ''),
    'ssl_verification' => env('ELASTICSEARCH_SSL_VERIFICATION', false),
];