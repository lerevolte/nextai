<?php

namespace App\Console\Commands;

use App\Models\CrmIntegration;
use App\Models\Conversation;
use App\Services\CRM\Providers\AvitoProvider;
use Illuminate\Console\Command;

class TestAvito extends Command
{
    protected $signature = 'avito:test 
                            {integration_id : ID интеграции Avito}
                            {--action=info : Действие (info|test-connection|chats|messages|send|item|stats)}
                            {--chat= : ID чата}
                            {--item= : ID объявления}
                            {--message= : Текст сообщения}
                            {--limit=10 : Лимит результатов}';

    protected $description = 'Тестирование Avito интеграции';

    public function handle()
    {
        $integrationId = $this->argument('integration_id');
        $action = $this->option('action');

        $integration = CrmIntegration::find($integrationId);

        if (!$integration) {
            $this->error("Интеграция #{$integrationId} не найдена");
            return 1;
        }

        if ($integration->type !== 'avito') {
            $this->error("Интеграция #{$integrationId} не является Avito");
            return 1;
        }

        $this->info("🔧 Тестирование Avito интеграции: {$integration->name}");
        $this->newLine();

        try {
            $provider = new AvitoProvider($integration);

            switch ($action) {
                case 'info':
                    $this->showInfo($integration, $provider);
                    break;

                case 'test-connection':
                    $this->testConnection($provider);
                    break;

                case 'chats':
                    $this->showChats($provider);
                    break;

                case 'messages':
                    $this->showMessages($provider);
                    break;

                case 'send':
                    $this->sendMessage($provider);
                    break;

                case 'item':
                    $this->showItem($provider);
                    break;

                case 'stats':
                    $this->showStats($provider);
                    break;

                default:
                    $this->error("Неизвестное действие: {$action}");
                    $this->info("Доступные действия: info, test-connection, chats, messages, send, item, stats");
                    return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Ошибка: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    protected function showInfo(CrmIntegration $integration, AvitoProvider $provider)
    {
        $credentials = $integration->credentials ?? [];

        $this->info("📋 Информация об интеграции:");
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['ID', $integration->id],
                ['Название', $integration->name],
                ['Client ID', $credentials['client_id'] ?? 'не указан'],
                ['Client Secret', !empty($credentials['client_secret']) ? '***' . substr($credentials['client_secret'], -4) : 'не указан'],
                ['Есть Access Token', !empty($credentials['access_token']) ? '✓' : '✗'],
                ['Token истекает', $credentials['token_expires_at'] ?? 'не указано'],
                ['Активна', $integration->is_active ? '✓' : '✗'],
            ]
        );

        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            $this->warn("⚠️  Credentials не настроены!");
        }
    }

    protected function testConnection(AvitoProvider $provider)
    {
        $this->info("🔌 Тестирование подключения...");
        
        if ($provider->testConnection()) {
            $this->info("✅ Подключение успешно!");
            
            // Пробуем получить данные аккаунта
            $users = $provider->getUsers();
            if (!empty($users)) {
                $this->info("Информация об аккаунте:");
                $this->table(
                    ['ID', 'Название', 'Email'],
                    array_map(fn($u) => [$u['id'], $u['name'], $u['email'] ?? 'N/A'], $users)
                );
            }
        } else {
            $this->error("❌ Не удалось подключиться к Avito");
            $this->warn("Проверьте:");
            $this->line("  - Правильность Client ID и Client Secret");
            $this->line("  - Активность приложения в личном кабинете Avito");
            $this->line("  - Срок действия токена");
        }
    }

    protected function showChats(AvitoProvider $provider)
    {
        $limit = $this->option('limit');
        $itemId = $this->option('item');
        
        $this->info("💬 Получение списка чатов (лимит: {$limit})...");
        
        $params = ['limit' => $limit];
        if ($itemId) {
            $params['item_id'] = $itemId;
            $this->info("Фильтр по объявлению: {$itemId}");
        }
        
        $chats = $provider->getChats($params);

        if (empty($chats)) {
            $this->warn("Чаты не найдены");
            return;
        }

        $this->info("Найдено чатов: " . count($chats));
        $this->newLine();

        foreach ($chats as $chat) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("💬 Chat ID: {$chat['id']}");
            $this->line("   Пользователь: " . ($chat['users'][0]['name'] ?? 'N/A'));
            $this->line("   Объявление: " . ($chat['context']['value']['title'] ?? 'N/A'));
            $this->line("   Объявление ID: " . ($chat['context']['value']['id'] ?? 'N/A'));
            $this->line("   Создан: " . ($chat['created'] ?? 'N/A'));
            $this->line("   Последнее сообщение: " . ($chat['last_message']['content']['text'] ?? 'N/A'));
            
            if (!empty($chat['unread'])) {
                $this->warn("   📨 Непрочитанных: {$chat['unread']}");
            }
        }

        $this->newLine();
        $this->info("💡 Для просмотра сообщений используйте:");
        $this->line("   php artisan avito:test {$this->argument('integration_id')} --action=messages --chat=CHAT_ID");
    }

    protected function showMessages(AvitoProvider $provider)
    {
        $chatId = $this->option('chat');
        
        if (!$chatId) {
            $this->error("Укажите --chat=CHAT_ID для просмотра сообщений");
            return;
        }

        $limit = $this->option('limit');
        $this->info("📨 Получение сообщений чата {$chatId} (лимит: {$limit})...");
        
        $messages = $provider->getChatMessages($chatId, ['limit' => $limit]);

        if (empty($messages)) {
            $this->warn("Сообщения не найдены");
            return;
        }

        $this->info("Найдено сообщений: " . count($messages));
        $this->newLine();

        foreach (array_reverse($messages) as $message) {
            $timestamp = date('Y-m-d H:i:s', $message['created'] ?? time());
            $author = $message['author_id'] ?? 'system';
            $direction = $message['direction'] ?? 'unknown';
            $text = $message['content']['text'] ?? '[no text]';
            
            $icon = $direction === 'in' ? '👤' : '🤖';
            $this->line("{$icon} [{$timestamp}] {$author}:");
            $this->line("   {$text}");
            $this->newLine();
        }
    }

    protected function sendMessage(AvitoProvider $provider)
    {
        $chatId = $this->option('chat');
        $message = $this->option('message');
        
        if (!$chatId) {
            $this->error("Укажите --chat=CHAT_ID");
            return;
        }

        if (!$message) {
            $message = $this->ask('Введите текст сообщения');
        }

        if (!$message) {
            $this->error("Сообщение не может быть пустым");
            return;
        }

        $this->info("📤 Отправка сообщения в чат {$chatId}...");
        $this->line("Текст: {$message}");
        $this->newLine();

        if (!$this->confirm('Отправить сообщение?', true)) {
            $this->info("Отменено");
            return;
        }

        if ($provider->sendMessage($chatId, $message)) {
            $this->info("✅ Сообщение отправлено успешно!");
        } else {
            $this->error("❌ Не удалось отправить сообщение");
        }
    }

    protected function showItem(AvitoProvider $provider)
    {
        $itemId = $this->option('item');
        
        if (!$itemId) {
            $this->error("Укажите --item=ITEM_ID для просмотра объявления");
            return;
        }

        $this->info("🏷️  Получение информации об объявлении {$itemId}...");
        
        $item = $provider->getItem($itemId);

        if (!$item) {
            $this->error("Объявление не найдено");
            return;
        }

        $this->info("Информация об объявлении:");
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['ID', $item['id'] ?? 'N/A'],
                ['Название', $item['title'] ?? 'N/A'],
                ['Категория', $item['category'] ?? 'N/A'],
                ['Цена', isset($item['price']) ? number_format($item['price']) . ' ₽' : 'N/A'],
                ['Статус', $item['status'] ?? 'N/A'],
                ['Создано', $item['created_at'] ?? 'N/A'],
            ]
        );
    }

    protected function showStats(AvitoProvider $provider)
    {
        $itemId = $this->option('item');
        
        if (!$itemId) {
            $this->error("Укажите --item=ITEM_ID для просмотра статистики");
            $this->info("Или список ID через запятую: --item=123,456,789");
            return;
        }

        $itemIds = array_map('trim', explode(',', $itemId));
        
        $this->info("📊 Получение статистики для " . count($itemIds) . " объявлени(я/й)...");
        
        $stats = $provider->getItemsStats($itemIds);

        if (empty($stats)) {
            $this->warn("Статистика не найдена");
            return;
        }

        $tableData = [];
        foreach ($stats as $stat) {
            $tableData[] = [
                $stat['itemId'] ?? 'N/A',
                $stat['stats']['views'] ?? 0,
                $stat['stats']['contacts'] ?? 0,
                $stat['stats']['favorites'] ?? 0,
            ];
        }

        $this->table(
            ['ID объявления', 'Просмотры', 'Контакты', 'В избранном'],
            $tableData
        );
    }
}