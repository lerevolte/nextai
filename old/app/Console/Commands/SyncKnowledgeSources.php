<?php

namespace App\Console\Commands;

use App\Models\KnowledgeSource;
use App\Jobs\SyncKnowledgeSource as SyncJob;
use Illuminate\Console\Command;

class SyncKnowledgeSources extends Command
{
    protected $signature = 'knowledge:sync {--source=}';
    protected $description = 'Sync knowledge sources that are due for update';

    public function handle()
    {
        if ($sourceId = $this->option('source')) {
            $source = KnowledgeSource::find($sourceId);
            
            if (!$source) {
                $this->error('Source not found');
                return 1;
            }

            SyncJob::dispatch($source);
            $this->info('Sync job dispatched for: ' . $source->name);
            return 0;
        }

        // Синхронизируем все источники, которые должны обновиться
        $sources = KnowledgeSource::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_sync_at')
                    ->orWhere('next_sync_at', '<=', now());
            })
            ->get();

        $this->info('Found ' . $sources->count() . ' sources to sync');

        foreach ($sources as $source) {
            if ($source->sync_settings['auto_sync'] ?? true) {
                SyncJob::dispatch($source);
                $this->info('Dispatched sync for: ' . $source->name);
            }
        }

        return 0;
    }
}