<?php

namespace App\Console\Commands;

use App\Models\KnowledgeItem;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    protected $signature = 'knowledge:generate-embeddings {--bot=} {--force}';
    protected $description = 'Generate embeddings for knowledge base items';

    protected EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        parent::__construct();
        $this->embeddingService = $embeddingService;
    }

    public function handle()
    {
        $query = KnowledgeItem::query();
        
        if ($this->option('bot')) {
            $query->whereHas('knowledgeBase', function($q) {
                $q->where('bot_id', $this->option('bot'));
            });
        }

        if (!$this->option('force')) {
            $query->whereNull('embedding');
        }

        $items = $query->get();
        
        $this->info('Found ' . $items->count() . ' items to process');
        
        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            try {
                // Генерируем эмбеддинг для заголовка + контента
                $text = $item->title . "\n\n" . $item->content;
                $embedding = $this->embeddingService->generateEmbedding($text);
                
                if ($embedding) {
                    $item->update(['embedding' => json_encode($embedding)]);
                    $this->line("\nGenerated embedding for: " . $item->title);
                }
            } catch (\Exception $e) {
                $this->error("\nFailed to generate embedding for item #" . $item->id . ": " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nEmbeddings generation completed!");
    }
}