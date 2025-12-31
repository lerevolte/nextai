<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Models\Organization;
use App\Services\CRM\CrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrmSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:sync 
                            {action : Action to perform (test|sync|export|stats)}
                            {--organization= : Organization ID or slug}
                            {--integration= : CRM Integration ID}
                            {--bot= : Bot ID}
                            {--conversation= : Conversation ID}
                            {--from= : Date from (Y-m-d)}
                            {--to= : Date to (Y-m-d)}
                            {--limit=100 : Limit records}
                            {--force : Force sync even if already synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage CRM synchronization';

    protected CrmService $crmService;

    public function __construct(CrmService $crmService)
    {
        parent::__construct();
        $this->crmService = $crmService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match($action) {
            'test' => $this->testConnection(),
            'sync' => $this->syncConversations(),
            'export' => $this->exportConversations(),
            'stats' => $this->showStats(),
            default => $this->error("Unknown action: {$action}") ?? 1,
        };
    }

    /**
     * Test CRM connection
     */
    protected function testConnection(): int
    {
        $integration = $this->getIntegration();
        
        if (!$integration) {
            $this->error('Integration not found');
            return 1;
        }

        $this->info("Testing connection to {$integration->name} ({$integration->type})...");

        if ($this->crmService->testConnection($integration)) {
            $this->info('✓ Connection successful!');
            
            // Пробуем получить дополнительную информацию
            $provider = $this->crmService->getProvider($integration);
            if ($provider) {
                $users = $provider->getUsers();
                $this->info('  Users found: ' . count($users));
                
                $pipelines = $provider->getPipelines();
                $this->info('  Pipelines found: ' . count($pipelines));
            }
            
            return 0;
        } else {
            $this->error('✗ Connection failed!');
            return 1;
        }
    }

    /**
     * Sync conversations with CRM
     */
    protected function syncConversations(): int
    {
        $integration = $this->getIntegration();
        
        if (!$integration) {
            $this->error('Integration not found');
            return 1;
        }

        $conversations = $this->getConversations();
        
        if ($conversations->isEmpty()) {
            $this->warn('No conversations found to sync');
            return 0;
        }

        $this->info("Syncing {$conversations->count()} conversations with {$integration->name}...");
        
        $progressBar = $this->output->createProgressBar($conversations->count());
        $progressBar->start();

        $success = 0;
        $failed = 0;

        foreach ($conversations as $conversation) {
            try {
                $provider = $this->crmService->getProvider($integration);
                if ($provider && $provider->syncConversation($conversation)) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('CRM sync failed for conversation', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Sync completed!");
        $this->info("  Success: {$success}");
        $this->warn("  Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Export conversations to CRM
     */
    protected function exportConversations(): int
    {
        $integration = $this->getIntegration();
        
        if (!$integration) {
            $this->error('Integration not found');
            return 1;
        }

        $filters = [
            'bot_id' => $this->option('bot'),
            'date_from' => $this->option('from'),
            'date_to' => $this->option('to'),
            'limit' => $this->option('limit'),
            'skip_synced' => !$this->option('force'),
        ];

        $this->info("Exporting conversations to {$integration->name}...");

        $results = $this->crmService->exportConversations($integration, $filters);

        $this->info("Export completed!");
        
        if (isset($results['exported'])) {
            $this->info("  Exported: {$results['exported']}");
        }
        
        if (isset($results['failed']) && $results['failed'] > 0) {
            $this->warn("  Failed: {$results['failed']}");
            
            if (!empty($results['errors'])) {
                $this->error('Errors:');
                foreach (array_slice($results['errors'], 0, 5) as $error) {
                    $errorMsg = is_array($error) 
                        ? "Conversation {$error['conversation_id']}: {$error['error']}"
                        : $error;
                    $this->error("  - {$errorMsg}");
                }
            }
        }

        return (isset($results['failed']) && $results['failed'] > 0) ? 1 : 0;
    }

    /**
     * Show synchronization statistics
     */
    protected function showStats(): int
    {
        $integration = $this->getIntegration();
        
        if (!$integration) {
            $this->error('Integration not found');
            return 1;
        }

        $from = $this->option('from') ? \Carbon\Carbon::parse($this->option('from')) : now()->subMonth();
        $to = $this->option('to') ? \Carbon\Carbon::parse($this->option('to')) : now();

        $stats = $this->crmService->getSyncStats($integration, $from, $to);

        $this->info("=== CRM Sync Statistics ===");
        $this->info("Integration: {$integration->name} ({$integration->type})");
        $this->info("Period: {$from->format('Y-m-d')} to {$to->format('Y-m-d')}");
        $this->newLine();

        $this->info("Total syncs: {$stats['total_syncs']}");
        $this->info("Successful: {$stats['successful_syncs']}");
        $this->warn("Failed: {$stats['failed_syncs']}");
        $this->newLine();

        if (!empty($stats['by_entity_type'])) {
            $this->info("By Entity Type:");
            foreach ($stats['by_entity_type'] as $type => $data) {
                $this->info("  {$type}:");
                $this->info("    Total: {$data['total']}");
                $this->info("    Success: {$data['success']}");
                $this->warn("    Failed: {$data['failed']}");
            }
        }

        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->error("Recent Errors:");
            foreach ($stats['errors'] as $error) {
                $this->error("  [{$error['datetime']}] {$error['entity_type']}/{$error['action']}: {$error['error']}");
            }
        }

        return 0;
    }

    /**
     * Get CRM integration
     */
    protected function getIntegration(): ?CrmIntegration
    {
        if ($integrationId = $this->option('integration')) {
            return CrmIntegration::find($integrationId);
        }

        if ($organizationId = $this->option('organization')) {
            $organization = is_numeric($organizationId) 
                ? Organization::find($organizationId)
                : Organization::where('slug', $organizationId)->first();
            
            if ($organization) {
                $integrations = $organization->crmIntegrations;
                
                if ($integrations->count() === 1) {
                    return $integrations->first();
                }
                
                if ($integrations->count() > 1) {
                    $this->warn('Multiple integrations found. Please specify --integration');
                    foreach ($integrations as $integration) {
                        $this->info("  ID: {$integration->id} - {$integration->name} ({$integration->type})");
                    }
                    return null;
                }
            }
        }

        // Если ничего не указано, ищем первую активную интеграцию
        return CrmIntegration::where('is_active', true)->first();
    }

    /**
     * Get conversations to sync
     */
    protected function getConversations()
    {
        $query = Conversation::query();

        if ($conversationId = $this->option('conversation')) {
            $query->where('id', $conversationId);
        } elseif ($botId = $this->option('bot')) {
            $query->where('bot_id', $botId);
        } elseif ($organizationId = $this->option('organization')) {
            $organization = is_numeric($organizationId) 
                ? Organization::find($organizationId)
                : Organization::where('slug', $organizationId)->first();
            
            if ($organization) {
                $botIds = $organization->bots()->pluck('id');
                $query->whereIn('bot_id', $botIds);
            }
        }

        if ($from = $this->option('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $this->option('to')) {
            $query->where('created_at', '<=', $to);
        }

        if (!$this->option('force')) {
            $query->whereNull('crm_lead_id')
                  ->whereNull('crm_deal_id');
        }

        return $query->limit($this->option('limit'))->get();
    }
}