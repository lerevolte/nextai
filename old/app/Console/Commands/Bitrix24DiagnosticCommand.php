<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\CrmIntegration;
use App\Models\Conversation;
use App\Services\CRM\Providers\Bitrix24Provider;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use Illuminate\Console\Command;

class Bitrix24DiagnosticCommand extends Command
{
    protected $signature = 'bitrix24:diagnose 
                            {--organization= : Organization ID or slug}
                            {--integration= : Integration ID}
                            {--fix : Attempt to fix issues}
                            {--test-message : Send test message}';
    
    protected $description = 'Diagnose and fix Bitrix24 integration issues';
    
    public function handle(): int
    {
        $this->info('=== Bitrix24 Integration Diagnostic ===');
        
        // Найти интеграцию
        $integration = $this->getIntegration();
        if (!$integration) {
            $this->error('Integration not found');
            return 1;
        }
        
        $this->info("Integration: {$integration->name} (ID: {$integration->id})");
        $this->info("Type: {$integration->type}");
        $this->info("Active: " . ($integration->is_active ? 'Yes' : 'No'));
        
        // Проверить учетные данные
        $this->checkCredentials($integration);
        
        // Проверить подключение
        $this->checkConnection($integration);
        
        // Проверить коннекторы
        $this->checkConnectors($integration);
        
        // Проверить последние диалоги
        $this->checkRecentConversations($integration);
        
        // Тестовое сообщение
        if ($this->option('test-message')) {
            $this->sendTestMessage($integration);
        }
        
        // Исправление проблем
        if ($this->option('fix')) {
            $this->fixIssues($integration);
        }
        
        return 0;
    }
    
    protected function getIntegration(): ?CrmIntegration
    {
        if ($integrationId = $this->option('integration')) {
            return CrmIntegration::find($integrationId);
        }
        
        if ($orgId = $this->option('organization')) {
            $org = is_numeric($orgId) 
                ? \App\Models\Organization::find($orgId)
                : \App\Models\Organization::where('slug', $orgId)->first();
            
            if ($org) {
                return $org->crmIntegrations()
                    ->where('type', 'bitrix24')
                    ->first();
            }
        }
        
        return CrmIntegration::where('type', 'bitrix24')
            ->where('is_active', true)
            ->first();
    }
    
    protected function checkCredentials(CrmIntegration $integration): void
    {
        $this->info("\n--- Checking Credentials ---");
        $credentials = $integration->credentials ?? [];
        
        $hasWebhook = isset($credentials['webhook_url']);
        $hasOAuth = isset($credentials['auth_id']) && isset($credentials['domain']);
        
        if ($hasWebhook) {
            $this->info("✓ Webhook URL: " . $credentials['webhook_url']);
        } else {
            $this->warn("✗ No webhook URL configured");
        }
        
        if ($hasOAuth) {
            $this->info("✓ OAuth configured for domain: " . $credentials['domain']);
            
            if (isset($credentials['auth_expires'])) {
                $expires = date('Y-m-d H:i:s', $credentials['auth_expires']);
                $isExpired = $credentials['auth_expires'] < time();
                
                if ($isExpired) {
                    $this->error("✗ OAuth token expired at: " . $expires);
                } else {
                    $this->info("  Token expires at: " . $expires);
                }
            }
        } else {
            $this->warn("✗ No OAuth configuration (app not installed)");
        }
        
        if (!$hasWebhook && !$hasOAuth) {
            $this->error("✗ No valid connection method configured!");
        }
    }
    
    protected function checkConnection(CrmIntegration $integration): void
    {
        $this->info("\n--- Testing Connection ---");
        
        try {
            $provider = new Bitrix24Provider($integration);
            if ($provider->testConnection()) {
                $this->info("✓ Connection successful");
                
                // Попробуем получить дополнительную информацию
                $users = $provider->getUsers();
                $this->info("  Users found: " . count($users));
                
                $pipelines = $provider->getPipelines();
                $this->info("  Pipelines found: " . count($pipelines));
            } else {
                $this->error("✗ Connection failed");
            }
        } catch (\Exception $e) {
            $this->error("✗ Connection error: " . $e->getMessage());
        }
    }
    
    protected function checkConnectors(CrmIntegration $integration): void
    {
        $this->info("\n--- Checking Connectors ---");
        
        $bots = $integration->bots;
        
        if ($bots->isEmpty()) {
            $this->warn("No bots connected to this integration");
            return;
        }
        
        foreach ($bots as $bot) {
            $this->info("\nBot: {$bot->name} (ID: {$bot->id})");
            
            $isRegistered = $bot->metadata['bitrix24_connector_registered'] ?? false;
            if ($isRegistered) {
                $this->info("  ✓ Connector registered");
                $connectorId = $bot->metadata['bitrix24_connector_id'] ?? 'unknown';
                $this->info("    Connector ID: " . $connectorId);
            } else {
                $this->warn("  ✗ Connector not registered");
            }
            
            $pivot = $bot->pivot;
            $lineId = $pivot->connector_settings['line_id'] ?? null;
            if ($lineId) {
                $this->info("  ✓ Connected to line: " . $lineId);
            } else {
                $this->warn("  ✗ Not connected to any line");
            }
            
            // Проверяем настройки синхронизации
            $this->info("  Sync settings:");
            $this->info("    - Sync contacts: " . ($pivot->sync_contacts ? 'Yes' : 'No'));
            $this->info("    - Sync conversations: " . ($pivot->sync_conversations ? 'Yes' : 'No'));
            $this->info("    - Create leads: " . ($pivot->create_leads ? 'Yes' : 'No'));
            $this->info("    - Create deals: " . ($pivot->create_deals ? 'Yes' : 'No'));
        }
    }
    
    protected function checkRecentConversations(CrmIntegration $integration): void
    {
        $this->info("\n--- Recent Conversations ---");
        
        $botIds = $integration->bots->pluck('id');
        $conversations = Conversation::whereIn('bot_id', $botIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        if ($conversations->isEmpty()) {
            $this->warn("No conversations found");
            return;
        }
        
        foreach ($conversations as $conv) {
            $this->info("\nConversation #{$conv->id}:");
            $this->info("  Created: " . $conv->created_at->format('Y-m-d H:i:s'));
            $this->info("  Status: " . $conv->status);
            $this->info("  User: " . $conv->getUserDisplayName());
            
            if ($conv->crm_lead_id) {
                $this->info("  ✓ Lead ID: " . $conv->crm_lead_id);
            } else {
                $this->warn("  ✗ No lead created");
            }
            
            $chatId = $conv->metadata['bitrix24_chat_id'] ?? null;
            if ($chatId) {
                $this->info("  ✓ Open line chat ID: " . $chatId);
            } else {
                $this->warn("  ✗ No open line chat");
            }
        }
    }
    
    protected function sendTestMessage(CrmIntegration $integration): void
    {
        $this->info("\n--- Sending Test Message ---");
        
        $bot = $integration->bots()->first();
        if (!$bot) {
            $this->error("No bot connected to integration");
            return;
        }
        
        // Создаем тестовый диалог
        $conversation = Conversation::create([
            'bot_id' => $bot->id,
            'channel_id' => $bot->channels()->where('type', 'web')->first()->id ?? 1,
            'external_id' => 'test_' . uniqid(),
            'status' => 'active',
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'user_phone' => '+79991234567',
        ]);
        
        $this->info("Created test conversation: #{$conversation->id}");
        
        // Создаем тестовое сообщение
        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'This is a test message from diagnostic tool',
        ]);
        
        $this->info("Created test message: #{$message->id}");
        
        // Ждем синхронизацию
        sleep(3);
        
        // Проверяем результат
        $conversation->refresh();
        
        if ($conversation->crm_lead_id) {
            $this->info("✓ Lead created: " . $conversation->crm_lead_id);
        } else {
            $this->warn("✗ Lead not created");
        }
        
        $chatId = $conversation->metadata['bitrix24_chat_id'] ?? null;
        if ($chatId) {
            $this->info("✓ Open line chat created: " . $chatId);
        } else {
            $this->warn("✗ Open line chat not created");
        }
    }
    
    protected function fixIssues(CrmIntegration $integration): void
    {
        $this->info("\n--- Attempting to Fix Issues ---");
        
        $credentials = $integration->credentials ?? [];
        $hasOAuth = isset($credentials['auth_id']) && isset($credentials['domain']);
        
        if (!$hasOAuth) {
            $this->warn("OAuth not configured. Please install the app in Bitrix24.");
            return;
        }
        
        // Пере-регистрируем коннекторы
        foreach ($integration->bots as $bot) {
            $isRegistered = $bot->metadata['bitrix24_connector_registered'] ?? false;
            
            if (!$isRegistered) {
                $this->info("Registering connector for bot: {$bot->name}");
                
                try {
                    $provider = new Bitrix24ConnectorProvider($integration);
                    $result = $provider->registerConnector($bot);
                    
                    if ($result['success']) {
                        $this->info("✓ Connector registered successfully");
                    } else {
                        $this->error("✗ Failed to register: " . ($result['error'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    $this->error("✗ Error: " . $e->getMessage());
                }
            }
        }
        
        // Пересинхронизируем последние диалоги без лидов
        $botIds = $integration->bots->pluck('id');
        $conversations = Conversation::whereIn('bot_id', $botIds)
            ->whereNull('crm_lead_id')
            ->where('created_at', '>=', now()->subDay())
            ->limit(10)
            ->get();
        
        if ($conversations->isNotEmpty()) {
            $this->info("\nResynchronizing {$conversations->count()} conversations...");
            
            foreach ($conversations as $conv) {
                try {
                    $provider = new Bitrix24ConnectorProvider($integration);
                    $result = $provider->createOpenLineChat($conv);
                    
                    if ($result['success']) {
                        $this->info("✓ Conversation #{$conv->id} synchronized");
                    } else {
                        $this->warn("✗ Failed to sync #{$conv->id}: " . ($result['error'] ?? 'Unknown'));
                    }
                } catch (\Exception $e) {
                    $this->error("✗ Error syncing #{$conv->id}: " . $e->getMessage());
                }
            }
        }
        
        $this->info("\nFix attempt completed!");
    }
}