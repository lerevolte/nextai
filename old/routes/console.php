<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('test:proxy', function () {
    $this->info('=== Testing Proxy Connection ===');
    
    // 1. Проверяем конфигурацию
    $proxyUrl = config('chatbot.proxy_url');
    $this->info("Proxy URL from config: " . ($proxyUrl ?: 'NOT SET'));
    
    if (empty($proxyUrl)) {
        $this->error('Proxy URL is not configured!');
        return;
    }
    
    // 2. Парсим URL прокси
    $proxyParts = parse_url($proxyUrl);
    $this->info("Proxy details:");
    $this->line("  Host: " . ($proxyParts['host'] ?? 'N/A'));
    $this->line("  Port: " . ($proxyParts['port'] ?? 'N/A'));
    $this->line("  Scheme: " . ($proxyParts['scheme'] ?? 'N/A'));
    
    // 3. Тестируем базовое подключение к прокси
    $this->info("\n=== Testing basic connection to proxy ===");
    
    $host = $proxyParts['host'] ?? '';
    $port = $proxyParts['port'] ?? 0;
    
    if (empty($host) || empty($port)) {
        $this->error('Invalid proxy URL format!');
        return;
    }
    
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    
    if ($socket) {
        $this->info("✓ Successfully connected to proxy server");
        fclose($socket);
    } else {
        $this->error("✗ Cannot connect to proxy: [$errno] $errstr");
        $this->warn("Please check if:");
        $this->line("  1. Proxy server is running");
        $this->line("  2. Firewall allows connection to {$host}:{$port}");
        $this->line("  3. Proxy credentials are correct (if required)");
        return;
    }
    
    // 4. Тестируем HTTP-запрос через прокси
    $this->info("\n=== Testing HTTP request through proxy ===");
    
    try {
        $client = new \GuzzleHttp\Client([
            'proxy' => $proxyUrl,
            'timeout' => 10,
            'verify' => false,
            'http_errors' => false,
        ]);
        
        // Тестовый запрос к простому эндпоинту
        $response = $client->get('https://httpbin.org/ip');
        
        if ($response->getStatusCode() === 200) {
            $body = json_decode($response->getBody(), true);
            $this->info("✓ HTTP request through proxy successful");
            $this->line("Your IP (via proxy): " . ($body['origin'] ?? 'unknown'));
        } else {
            $this->error("✗ HTTP request failed with status: " . $response->getStatusCode());
        }
        
    } catch (\Exception $e) {
        $this->error("✗ HTTP request through proxy failed: " . $e->getMessage());
        return;
    }
    
    // 5. Тестируем подключение к OpenAI через прокси
    $this->info("\n=== Testing OpenAI API through proxy ===");
    
    $apiKey = env('OPENAI_API_KEY');
    if (empty($apiKey)) {
        $this->error('OPENAI_API_KEY is not set!');
        return;
    }
    
    try {
        $client = new \GuzzleHttp\Client([
            'proxy' => $proxyUrl,
            'timeout' => 30,
            'verify' => false,
        ]);
        
        $response = $client->get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);
        
        if ($response->getStatusCode() === 200) {
            $this->info("✓ OpenAI API accessible through proxy");
            $data = json_decode($response->getBody(), true);
            $this->line("Available models: " . count($data['data'] ?? []));
        } else {
            $this->error("✗ OpenAI API returned status: " . $response->getStatusCode());
        }
        
    } catch (\Exception $e) {
        $this->error("✗ OpenAI API request failed: " . $e->getMessage());
        $this->warn("\nPossible issues:");
        $this->line("  1. Proxy doesn't support HTTPS/SSL");
        $this->line("  2. Proxy requires authentication");
        $this->line("  3. OpenAI is blocked by proxy");
    }
    
    $this->info("\n=== Test completed ===");
    
})->describe('Test proxy connection and OpenAI API access');


//Schedule::command('knowledge:sync')->hourly();
// Экспорт незавершенных диалогов в CRM каждые 30 минут
Schedule::command('crm:sync export --limit=50')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
// Статистика CRM синхронизации раз в день
Schedule::command('crm:sync stats')
    ->dailyAt('09:00')
    ->emailOutputTo(config('mail.admin_email'));
Schedule::command('functions:run-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();