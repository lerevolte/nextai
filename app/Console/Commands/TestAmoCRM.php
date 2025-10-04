<?php

namespace App\Console\Commands;

use App\Models\CrmIntegration;
use App\Models\Conversation;
use App\Services\CRM\Providers\AmoCRMProvider;
use Illuminate\Console\Command;

class TestAmoCRM extends Command
{
    protected $signature = 'amocrm:test 
                            {integration_id : ID интеграции AmoCRM}
                            {--action=info : Действие (info|pipelines|users|test-connection|create-lead|create-contact)}
                            {--conversation= : ID conversation для тестов}
                            {--pipeline= : ID воронки для тестов}
                            {--status= : ID статуса для тестов}';

    protected $description = 'Тестирование AmoCRM интеграции';

    public function handle()
    {
        $integrationId = $this->argument('integration_id');
        $action = $this->option('action');

        $integration = CrmIntegration::find($integrationId);

        if (!$integration) {
            $this->error("Интеграция #{$integrationId} не найдена");
            return 1;
        }

        if ($integration->type !== 'amocrm') {
            $this->error("Интеграция #{$integrationId} не является AmoCRM");
            return 1;
        }

        $this->info("🔧 Тестирование AmoCRM интеграции: {$integration->name}");
        $this->newLine();

        try {
            $provider = new AmoCRMProvider($integration);

            switch ($action) {
                case 'info':
                    $this->showInfo($integration, $provider);
                    break;

                case 'test-connection':
                    $this->testConnection($provider);
                    break;

                case 'pipelines':
                    $this->showPipelines($provider);
                    break;

                case 'users':
                    $this->showUsers($provider);
                    break;

                case 'create-lead':
                    $this->testCreateLead($provider, $integration);
                    break;

                case 'create-contact':
                    $this->testCreateContact($provider);
                    break;

                default:
                    $this->error("Неизвестное действие: {$action}");
                    $this->info("Доступные действия: info, test-connection, pipelines, users, create-lead, create-contact");
                    return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Ошибка: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    protected function showInfo(CrmIntegration $integration, AmoCRMProvider $provider)
    {
        $credentials = $integration->credentials ?? [];
        $settings = $integration->settings ?? [];

        $this->info("📋 Информация об интеграции:");
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['ID', $integration->id],
                ['Название', $integration->name],
                ['Поддомен', $credentials['subdomain'] ?? 'не указан'],
                ['Client ID', $credentials['client_id'] ?? 'не указан'],
                ['Есть Access Token', !empty($credentials['access_token']) ? '✓' : '✗'],
                ['Есть Refresh Token', !empty($credentials['refresh_token']) ? '✓' : '✗'],
                ['Pipeline ID', $settings['default_pipeline_id'] ?? 'не указан ❌'],
                ['Status ID', $settings['default_status_id'] ?? 'не указан ❌'],
                ['Responsible User ID', $settings['default_responsible_id'] ?? 'не указан'],
                ['Активна', $integration->is_active ? '✓' : '✗'],
            ]
        );

        if (empty($settings['default_pipeline_id'])) {
            $this->warn("⚠️  Pipeline ID не настроен! Это обязательный параметр.");
        }

        if (empty($settings['default_status_id'])) {
            $this->warn("⚠️  Status ID не настроен! Это обязательный параметр.");
        }
    }

    protected function testConnection(AmoCRMProvider $provider)
    {
        $this->info("🔌 Тестирование подключения...");
        
        if ($provider->testConnection()) {
            $this->info("✅ Подключение успешно!");
        } else {
            $this->error("❌ Не удалось подключиться к AmoCRM");
        }
    }

    protected function showPipelines(AmoCRMProvider $provider)
    {
        $this->info("📊 Получение списка воронок...");
        
        $pipelines = $provider->getPipelines();

        if (empty($pipelines)) {
            $this->warn("Воронки не найдены");
            return;
        }

        foreach ($pipelines as $pipeline) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🎯 Воронка: {$pipeline['name']}");
            $this->info("   ID: {$pipeline['id']}");
            
            $stages = $provider->getPipelineStages($pipeline['id']);
            
            if (!empty($stages)) {
                $this->info("   Этапы:");
                foreach ($stages as $stage) {
                    $this->line("   • {$stage['name']} (ID: {$stage['id']})");
                }
            }
            $this->newLine();
        }

        $this->info("💡 Для использования воронки, добавьте в settings интеграции:");
        $this->line("   'default_pipeline_id' => {ID воронки}");
        $this->line("   'default_status_id' => {ID первого этапа}");
    }

    protected function showUsers(AmoCRMProvider $provider)
    {
        $this->info("👥 Получение списка пользователей...");
        
        $users = $provider->getUsers();

        if (empty($users)) {
            $this->warn("Пользователи не найдены");
            return;
        }

        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                $user['id'],
                $user['name'] ?? 'N/A',
                $user['email'] ?? 'N/A',
            ];
        }

        $this->table(['ID', 'Имя', 'Email'], $tableData);
    }

    protected function testCreateLead(AmoCRMProvider $provider, CrmIntegration $integration)
    {
        $conversationId = $this->option('conversation');
        
        if (!$conversationId) {
            $this->error("Укажите --conversation=ID для создания лида");
            return;
        }

        $conversation = Conversation::find($conversationId);
        
        if (!$conversation) {
            $this->error("Conversation #{$conversationId} не найден");
            return;
        }

        $this->info("📝 Создание тестового лида из conversation #{$conversationId}...");
        $this->newLine();

        // Показываем данные, которые будут отправлены
        $settings = $integration->settings ?? [];
        
        $this->info("Данные для создания:");
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['Pipeline ID', $settings['default_pipeline_id'] ?? '❌ НЕ УКАЗАН'],
                ['Status ID', $settings['default_status_id'] ?? '❌ НЕ УКАЗАН'],
                ['User Name', $conversation->user_name ?? 'не указано'],
                ['User Email', $conversation->user_email ?? 'не указано'],
                ['User Phone', $conversation->user_phone ?? 'не указано'],
            ]
        );
        $this->newLine();

        if (empty($settings['default_pipeline_id'])) {
            $this->error("❌ Pipeline ID не настроен! Установите его через:");
            $this->line("   php artisan amocrm:test {$integration->id} --action=pipelines");
            return;
        }

        if (empty($settings['default_status_id'])) {
            $this->error("❌ Status ID не настроен!");
            return;
        }

        if (!$this->confirm('Создать лид с этими данными?', true)) {
            $this->info("Отменено");
            return;
        }

        try {
            $result = $provider->createDeal($conversation);

            $this->info("✅ Лид успешно создан!");
            $this->table(
                ['Параметр', 'Значение'],
                [
                    ['Lead ID', $result['lead_id'] ?? 'N/A'],
                    ['Contact ID', $result['contact_id'] ?? 'N/A'],
                ]
            );

            if (isset($result['lead_id'])) {
                $leadUrl = "https://{$integration->credentials['subdomain']}.amocrm.ru/leads/detail/{$result['lead_id']}";
                $this->info("🔗 Ссылка на лид: {$leadUrl}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при создании лида:");
            $this->error($e->getMessage());
            
            // Пробуем распарсить ошибку от AmoCRM
            if (strpos($e->getMessage(), 'validation-errors') !== false) {
                $this->newLine();
                $this->warn("💡 Это ошибка валидации от AmoCRM. Проверьте:");
                $this->line("   1. Правильность Pipeline ID и Status ID");
                $this->line("   2. Что статус принадлежит указанной воронке");
                $this->line("   3. Что у пользователя есть права на создание лидов");
                $this->newLine();
                $this->line("   Используйте команду для просмотра воронок:");
                $this->line("   php artisan amocrm:test {$integration->id} --action=pipelines");
            }
        }
    }

    protected function testCreateContact(AmoCRMProvider $provider)
    {
        $this->info("👤 Создание тестового контакта...");

        $name = $this->ask('Имя контакта', 'Тестовый контакт');
        $email = $this->ask('Email (опционально)', 'test@example.com');
        $phone = $this->ask('Телефон (опционально)', '+79991234567');

        $contactData = [
            'name' => $name,
        ];

        if ($email) {
            $contactData['email'] = $email;
        }

        if ($phone) {
            $contactData['phone'] = $phone;
        }

        try {
            $result = $provider->syncContact($contactData);

            $this->info("✅ Контакт успешно создан/обновлен!");
            $this->table(
                ['Параметр', 'Значение'],
                [
                    ['Contact ID', $result['id'] ?? 'N/A'],
                    ['Action', $result['action'] ?? 'N/A'],
                ]
            );

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при создании контакта:");
            $this->error($e->getMessage());
        }
    }
}