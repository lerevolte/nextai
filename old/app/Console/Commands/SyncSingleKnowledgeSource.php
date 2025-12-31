<?php

namespace App\Console\Commands;

use App\Jobs\SyncKnowledgeSource;
use App\Models\KnowledgeSource;
use Illuminate\Console\Command;
use Exception;

class SyncSingleKnowledgeSource extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knowledge:sync-source {source_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизировать один источник знаний в синхронном режиме для отладки.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sourceId = $this->argument('source_id');
        $source = KnowledgeSource::find($sourceId);

        if (!$source) {
            $this->error("Источник знаний с ID {$sourceId} не найден.");
            return 1;
        }

        $this->info("Запуск синхронизации для источника: {$source->name} (ID: {$sourceId})...");

        try {
            // Создаем экземпляр задачи и вызываем ее обработчик напрямую
            (new SyncKnowledgeSource($source))->handle();
            
            $this->info("Синхронизация успешно завершена.");
            $this->info("Проверьте страницу с логами в веб-интерфейсе для получения подробной информации.");

        } catch (Exception $e) {
            $this->error("Во время синхронизации произошла ошибка:");
            $this->error($e->getMessage());
            $this->line("Файл: " . $e->getFile() . " на строке " . $e->getLine());
            $this->line("---");
            $this->line("Stack Trace:");
            $this->line($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
