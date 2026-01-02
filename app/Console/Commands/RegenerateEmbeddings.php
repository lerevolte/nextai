<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeItem;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class RegenerateEmbeddings extends Command
{
    protected $signature = 'knowledge:regenerate-embeddings 
                            {--bot= : ID бота (если не указан - все боты)}
                            {--force : Перегенерировать даже если эмбеддинг уже есть}';
    
    protected $description = 'Перегенерация эмбеддингов для элементов базы знаний';

    public function handle(EmbeddingService $embeddingService)
    {
        $botId = $this->option('bot');
        $force = $this->option('force');

        // Получаем элементы для обработки
        $query = KnowledgeItem::where('is_active', true);

        if ($botId) {
            $knowledgeBase = KnowledgeBase::where('bot_id', $botId)->first();
            
            if (!$knowledgeBase) {
                $this->error("База знаний для бота ID {$botId} не найдена");
                return 1;
            }
            
            $query->where('knowledge_base_id', $knowledgeBase->id);
            $this->info("Обработка базы знаний бота ID: {$botId}");
        } else {
            $this->info("Обработка всех баз знаний");
        }

        // Если не force - только элементы с неправильным размером эмбеддинга
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('embedding')
                  ->orWhereRaw("JSON_LENGTH(embedding) != 1536");
            });
        }

        $items = $query->get();
        $total = $items->count();

        if ($total === 0) {
            $this->info("Нет элементов для обработки");
            return 0;
        }

        $this->info("Найдено элементов: {$total}");
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['success' => 0, 'failed' => 0];

        foreach ($items as $item) {
            try {
                $text = $item->title . "\n\n" . $item->content;
                $embedding = $embeddingService->generateEmbedding($text);

                if ($embedding && count($embedding) === 1536) {
                    $item->update(['embedding' => json_encode($embedding)]);
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                    $this->newLine();
                    $this->warn("  Неверный размер эмбеддинга для ID {$item->id}: " . count($embedding ?? []));
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $this->newLine();
                $this->error("  Ошибка для ID {$item->id}: " . $e->getMessage());
            }

            $bar->advance();
            
            // Небольшая задержка чтобы не превысить лимиты API
            usleep(100000); // 0.1 секунды
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Готово!");
        $this->table(
            ['Статус', 'Количество'],
            [
                ['Успешно', $stats['success']],
                ['Ошибки', $stats['failed']],
            ]
        );

        return $stats['failed'] > 0 ? 1 : 0;
    }
}