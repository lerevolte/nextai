<?
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PerformanceOptimizationService;

class OptimizePerformance extends Command
{
    protected $signature = 'optimize:performance 
                          {--cache : Optimize cache}
                          {--db : Optimize database}
                          {--cleanup : Cleanup old data}
                          {--all : Run all optimizations}';
    
    protected $description = 'Optimize system performance';

    protected PerformanceOptimizationService $optimizationService;

    public function __construct(PerformanceOptimizationService $optimizationService)
    {
        parent::__construct();
        $this->optimizationService = $optimizationService;
    }

    public function handle()
    {
        $this->info('Starting performance optimization...');
        
        if ($this->option('all') || $this->option('cache')) {
            $this->optimizeCache();
        }
        
        if ($this->option('all') || $this->option('db')) {
            $this->optimizeDatabase();
        }
        
        if ($this->option('all') || $this->option('cleanup')) {
            $this->cleanupOldData();
        }
        
        $this->showPerformanceMetrics();
        
        $this->info('✅ Performance optimization completed!');
        
        return 0;
    }
    
    protected function optimizeCache()
    {
        $this->info('Optimizing cache...');
        $progressBar = $this->output->createProgressBar(3);
        
        // Кэшируем частые ответы для всех активных ботов
        $bots = \App\Models\Bot::where('is_active', true)->get();
        foreach ($bots as $bot) {
            $this->optimizationService->cacheFrequentResponses($bot);
            $progressBar->advance();
        }
        
        // Прогреваем кэш
        \Artisan::call('cache:clear');
        $progressBar->advance();
        
        // Оптимизируем Redis
        \Redis::command('BGREWRITEAOF');
        $progressBar->advance();
        
        $progressBar->finish();
        $this->newLine();
        $this->info('Cache optimized successfully!');
    }
    
    protected function optimizeDatabase()
    {
        $this->info('Optimizing database...');
        
        // Анализируем и оптимизируем запросы
        $this->optimizationService->optimizeDatabaseQueries();
        
        // Обновляем статистику таблиц
        $tables = ['conversations', 'messages', 'knowledge_items', 'bots', 'channels'];
        foreach ($tables as $table) {
            \DB::statement("ANALYZE TABLE `{$table}`");
            $this->line("Analyzed table: {$table}");
        }
        
        $this->info('Database optimized successfully!');
    }
    
    protected function cleanupOldData()
    {
        $this->info('Cleaning up old data...');
        
        // Удаляем старые диалоги
        $deleted = \DB::table('conversations')
            ->where('status', 'closed')
            ->where('closed_at', '<', now()->subDays(90))
            ->delete();
        
        $this->line("Deleted {$deleted} old conversations");
        
        // Компрессия старых данных
        $this->optimizationService->compressOldData();
        
        // Очистка логов
        $logsDeleted = \DB::table('performance_metrics')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
        
        $this->line("Deleted {$logsDeleted} old performance logs");
        
        $this->info('Cleanup completed!');
    }
    
    protected function showPerformanceMetrics()
    {
        $metrics = $this->optimizationService->getPerformanceMetrics();
        
        $this->newLine();
        $this->info('Current Performance Metrics:');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Hit Rate', $metrics['cache']['hit_rate'] . '%'],
                ['DB Connections', $metrics['database']['connections']],
                ['API Response Time', $metrics['api']['avg_response_time'] . 's'],
                ['Requests/min', $metrics['api']['requests_per_minute']],
                ['CPU Usage', $metrics['resources']['cpu_usage'] . '%'],
                ['Memory Usage', $metrics['resources']['memory_usage'] . '%'],
            ]
        );
    }
}
