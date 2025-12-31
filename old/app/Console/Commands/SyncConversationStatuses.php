<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncConversationStatuses extends Command
{
    protected $signature = 'conversations:sync-statuses 
                            {--dry-run : Run without making changes}
                            {--force : Force sync all conversations}';
    
    protected $description = 'Sync conversation statuses with Bitrix24 and fix inconsistencies';

    public function handle()
    {
        $this->info('🔄 Starting conversation status sync...');
        
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        // Находим все проблемные разговоры
        $query = Conversation::where('status', 'active')
            ->whereNotNull('metadata->bitrix24_chat_id');
        
        if (!$force) {
            $query->where('last_message_at', '<', now()->subMinutes(5));
        }
        
        $conversations = $query->get();
        
        $this->info("Found {$conversations->count()} conversations to check");
        
        $synced = 0;
        $updated = 0;
        $errors = 0;
        
        $progressBar = $this->output->createProgressBar($conversations->count());
        $progressBar->start();
        
        foreach ($conversations as $conversation) {
            try {
                $synced++;
                
                // Проверяем, есть ли сообщения оператора
                $operatorMessages = $conversation->messages()
                    ->where('role', 'operator')
                    ->where('created_at', '>', now()->subHour())
                    ->get();
                
                $hasOperatorMessages = $operatorMessages->isNotEmpty();
                
                if ($hasOperatorMessages && $conversation->status !== 'waiting_operator') {
                    Log::warning('Fixing conversation status', [
                        'conversation_id' => $conversation->id,
                        'current_status' => $conversation->status,
                        'operator_messages_count' => $operatorMessages->count()
                    ]);
                    
                    if (!$dryRun) {
                        $conversation->update(['status' => 'waiting_operator']);
                        
                        // Добавляем системное сообщение
                        $conversation->messages()->create([
                            'role' => 'system',
                            'content' => 'Статус диалога восстановлен: оператор обрабатывает диалог.',
                            'metadata' => [
                                'type' => 'status_sync',
                                'synced_at' => now()->toIso8601String(),
                            ]
                        ]);
                    }
                    
                    $updated++;
                    $this->newLine();
                    $this->warn("⚠️  Fixed conversation #{$conversation->id}");
                }
                
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to sync conversation status', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);
                
                $this->newLine();
                $this->error("❌ Error syncing conversation #{$conversation->id}: {$e->getMessage()}");
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Статистика
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total checked', $synced],
                ['Updated', $updated],
                ['Errors', $errors],
            ]
        );
        
        if ($dryRun) {
            $this->info('🔍 Dry run completed - no changes were made');
        } else {
            $this->info('✅ Sync completed successfully');
        }
        
        return 0;
    }
}