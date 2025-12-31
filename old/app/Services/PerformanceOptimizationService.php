<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\KnowledgeBase;

class PerformanceOptimizationService
{
    /**
     * Кэширование ответов ботов для частых вопросов
     */
    public function cacheFrequentResponses(Bot $bot): void
    {
        // Анализируем частые вопросы за последние 30 дней
        $frequentQuestions = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.bot_id', $bot->id)
            ->where('messages.role', 'user')
            ->where('messages.created_at', '>=', now()->subDays(30))
            ->select('messages.content', DB::raw('COUNT(*) as frequency'))
            ->groupBy('messages.content')
            ->having('frequency', '>=', 5) // Минимум 5 повторений
            ->orderByDesc('frequency')
            ->limit(100)
            ->get();
        
        foreach ($frequentQuestions as $question) {
            // Получаем типичный ответ на вопрос
            $response = $this->getTypicalResponse($bot, $question->content);
            
            if ($response) {
                // Кэшируем на 24 часа
                $cacheKey = "bot_response:{$bot->id}:" . md5($question->content);
                Cache::put($cacheKey, $response, 86400);
            }
        }
        
        Log::info("Cached {$frequentQuestions->count()} frequent responses for bot {$bot->id}");
    }

    /**
     * Оптимизированный поиск по базе знаний с использованием индексов
     */
    public function optimizedKnowledgeSearch(KnowledgeBase $knowledgeBase, string $query, int $limit = 5): array
    {
        $cacheKey = "kb_search:{$knowledgeBase->id}:" . md5($query);
        
        // Проверяем кэш
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        // Используем полнотекстовый поиск с оптимизацией
        $results = DB::table('knowledge_items')
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->where('is_active', true)
            ->whereRaw("MATCH(title, content) AGAINST(? IN BOOLEAN MODE)", [$query])
            ->select('id', 'title', 'content', DB::raw("MATCH(title, content) AGAINST(? IN BOOLEAN MODE) as relevance"))
            ->setBindings([$query], 'select')
            ->orderByDesc('relevance')
            ->limit($limit)
            ->get();
        
        // Кэшируем результат на 1 час
        Cache::put($cacheKey, $results, 3600);
        
        return $results->toArray();
    }

    /**
     * Пулинг соединений для AI провайдеров
     */
    public function getAiProviderConnection(string $provider): object
    {
        static $connections = [];
        
        if (!isset($connections[$provider])) {
            $connections[$provider] = $this->createProviderConnection($provider);
        }
        
        return $connections[$provider];
    }

    /**
     * Батчинг запросов к AI
     */
    public function batchAiRequests(array $requests): array
    {
        $batches = array_chunk($requests, 10); // Группируем по 10 запросов
        $responses = [];
        
        foreach ($batches as $batch) {
            $batchResponses = $this->processBatch($batch);
            $responses = array_merge($responses, $batchResponses);
        }
        
        return $responses;
    }

    /**
     * Оптимизация запросов к базе данных
     */
    public function optimizeDatabaseQueries(): void
    {
        // Анализ медленных запросов
        $slowQueries = DB::select("
            SELECT 
                query,
                exec_count,
                avg_exec_time_ms,
                total_exec_time_ms
            FROM performance_schema.events_statements_summary_by_digest
            WHERE avg_exec_time_ms > 100
            ORDER BY total_exec_time_ms DESC
            LIMIT 10
        ");
        
        foreach ($slowQueries as $query) {
            Log::warning('Slow query detected', [
                'query' => $query->query,
                'avg_time' => $query->avg_exec_time_ms,
                'exec_count' => $query->exec_count
            ]);
        }
        
        // Автоматическое создание отсутствующих индексов
        $this->createMissingIndexes();
        
        // Очистка старых данных
        $this->cleanupOldData();
    }

    /**
     * Создание отсутствующих индексов
     */
    protected function createMissingIndexes(): void
    {
        $indexesToCreate = [
            'conversations' => [
                ['bot_id', 'status', 'created_at'],
                ['channel_id', 'external_id'],
                ['user_email'],
                ['last_message_at']
            ],
            'messages' => [
                ['conversation_id', 'created_at'],
                ['role', 'created_at'],
                ['response_time']
            ],
            'knowledge_items' => [
                ['knowledge_base_id', 'is_active'],
                ['type', 'is_active']
            ]
        ];
        
        foreach ($indexesToCreate as $table => $indexes) {
            foreach ($indexes as $columns) {
                $indexName = 'idx_' . $table . '_' . implode('_', $columns);
                
                // Проверяем, существует ли индекс
                $exists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM information_schema.statistics 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ? 
                    AND index_name = ?
                ", [$table, $indexName]);
                
                if ($exists[0]->count == 0) {
                    $columnsList = implode('`, `', $columns);
                    DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$columnsList}`)");
                    Log::info("Created index {$indexName} on table {$table}");
                }
            }
        }
    }

    /**
     * Очистка старых данных
     */
    protected function cleanupOldData(): void
    {
        // Удаляем старые закрытые диалоги (старше 90 дней)
        $deleted = DB::table('conversations')
            ->where('status', 'closed')
            ->where('closed_at', '<', now()->subDays(90))
            ->delete();
        
        if ($deleted > 0) {
            Log::info("Deleted {$deleted} old conversations");
        }
        
        // Архивируем старые логи
        $this->archiveOldLogs();
        
        // Оптимизируем таблицы
        $tables = ['conversations', 'messages', 'knowledge_items', 'feedback'];
        foreach ($tables as $table) {
            DB::statement("OPTIMIZE TABLE `{$table}`");
        }
    }

    /**
     * Кэширование векторных эмбеддингов
     */
    public function cacheEmbeddings(KnowledgeBase $knowledgeBase): void
    {
        $items = $knowledgeBase->items()
            ->where('is_active', true)
            ->whereNotNull('embedding')
            ->get(['id', 'embedding']);
        
        $embeddings = [];
        foreach ($items as $item) {
            $embeddings[$item->id] = $item->embedding;
        }
        
        // Сохраняем в Redis для быстрого доступа
        Redis::hMSet("kb_embeddings:{$knowledgeBase->id}", $embeddings);
        Redis::expire("kb_embeddings:{$knowledgeBase->id}", 3600); // 1 час
        
        Log::info("Cached {$items->count()} embeddings for knowledge base {$knowledgeBase->id}");
    }

    /**
     * Оптимизированный метод для обработки входящих сообщений
     */
    public function processMessageOptimized(Bot $bot, string $message, Conversation $conversation): string
    {
        // 1. Проверяем кэш частых ответов
        $cacheKey = "bot_response:{$bot->id}:" . md5($message);
        if ($cachedResponse = Cache::get($cacheKey)) {
            Log::info("Using cached response for bot {$bot->id}");
            return $cachedResponse;
        }
        
        // 2. Используем пул соединений для AI провайдера
        $provider = $this->getAiProviderConnection($bot->ai_provider);
        
        // 3. Параллельная обработка: поиск в базе знаний и генерация ответа
        $context = '';
        if ($bot->knowledge_base_enabled && $bot->knowledgeBase) {
            $context = $this->getContextAsync($bot->knowledgeBase, $message);
        }
        
        // 4. Формируем оптимизированный промпт
        $optimizedPrompt = $this->optimizePrompt($bot->system_prompt, $context);
        
        // 5. Используем стриминг для больших ответов
        $response = $this->streamResponse($provider, $optimizedPrompt, $message, $bot);
        
        // 6. Асинхронно сохраняем в кэш если это частый вопрос
        if ($this->isFrequentQuestion($bot, $message)) {
            Cache::put($cacheKey, $response, 86400);
        }
        
        return $response;
    }

    /**
     * Оптимизация промпта для уменьшения токенов
     */
    protected function optimizePrompt(string $systemPrompt, string $context): string
    {
        // Удаляем лишние пробелы и переносы
        $optimized = preg_replace('/\s+/', ' ', $systemPrompt);
        
        // Сокращаем контекст если он слишком большой
        if (strlen($context) > 2000) {
            $context = mb_substr($context, 0, 2000) . '...';
        }
        
        if ($context) {
            $optimized .= "\n\nКонтекст: " . $context;
        }
        
        return trim($optimized);
    }

    /**
     * Мониторинг производительности в реальном времени
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache' => [
                'hit_rate' => $this->getCacheHitRate(),
                'memory_usage' => $this->getCacheMemoryUsage(),
                'keys_count' => Cache::get('cache_keys_count', 0)
            ],
            'database' => [
                'connections' => DB::connection()->select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0,
                'slow_queries' => $this->getSlowQueriesCount(),
                'query_cache_hit_rate' => $this->getQueryCacheHitRate()
            ],
            'api' => [
                'avg_response_time' => $this->getAverageApiResponseTime(),
                'requests_per_minute' => $this->getRequestsPerMinute(),
                'error_rate' => $this->getApiErrorRate()
            ],
            'resources' => [
                'cpu_usage' => $this->getCpuUsage(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_usage' => $this->getDiskUsage()
            ]
        ];
    }

    /**
     * Автоматическое масштабирование ресурсов
     */
    public function autoScale(): void
    {
        $metrics = $this->getPerformanceMetrics();
        
        // Масштабируем воркеры очередей
        if ($metrics['api']['requests_per_minute'] > 1000) {
            $this->scaleQueueWorkers('up');
        } elseif ($metrics['api']['requests_per_minute'] < 100) {
            $this->scaleQueueWorkers('down');
        }
        
        // Увеличиваем кэш если hit rate низкий
        if ($metrics['cache']['hit_rate'] < 70) {
            $this->increaseCacheSize();
        }
        
        // Оповещаем если ресурсы на пределе
        if ($metrics['resources']['cpu_usage'] > 80 || $metrics['resources']['memory_usage'] > 85) {
            $this->alertHighResourceUsage($metrics['resources']);
        }
    }

    /**
     * Предзагрузка данных для ускорения ответов
     */
    public function preloadBotData(Bot $bot): void
    {
        // Предзагружаем базу знаний
        if ($bot->knowledge_base_enabled && $bot->knowledgeBase) {
            $this->cacheEmbeddings($bot->knowledgeBase);
        }
        
        // Предзагружаем частые ответы
        $this->cacheFrequentResponses($bot);
        
        // Прогреваем соединение с AI провайдером
        $this->warmupAiConnection($bot->ai_provider);
    }

    /**
     * Оптимизация использования токенов AI
     */
    public function optimizeTokenUsage(Bot $bot): array
    {
        $recommendations = [];
        
        // Анализируем использование токенов за последние 7 дней
        $stats = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.bot_id', $bot->id)
            ->where('messages.created_at', '>=', now()->subDays(7))
            ->select(
                DB::raw('AVG(tokens_used) as avg_tokens'),
                DB::raw('MAX(tokens_used) as max_tokens'),
                DB::raw('SUM(tokens_used) as total_tokens')
            )
            ->first();
        
        // Рекомендации по оптимизации
        if ($stats->avg_tokens > 500) {
            $recommendations[] = [
                'type' => 'prompt_optimization',
                'message' => 'Средний расход токенов высокий. Рекомендуется оптимизировать промпт.',
                'action' => 'Сократите системный промпт, удалив избыточные инструкции'
            ];
        }
        
        if ($stats->max_tokens > $bot->max_tokens * 0.9) {
            $recommendations[] = [
                'type' => 'max_tokens_warning',
                'message' => 'Некоторые ответы достигают лимита токенов',
                'action' => 'Увеличьте лимит max_tokens или оптимизируйте длину ответов'
            ];
        }
        
        // Оценка экономии при оптимизации
        $potentialSavings = $this->calculatePotentialTokenSavings($bot, $stats);
        
        return [
            'current_usage' => $stats,
            'recommendations' => $recommendations,
            'potential_savings' => $potentialSavings
        ];
    }

    /**
     * Балансировка нагрузки между ботами
     */
    public function loadBalance(Organization $organization): void
    {
        $bots = $organization->bots()
            ->where('is_active', true)
            ->withCount(['conversations' => function($query) {
                $query->where('status', 'active');
            }])
            ->get();
        
        // Находим перегруженные боты
        $avgLoad = $bots->avg('conversations_count');
        $threshold = $avgLoad * 1.5;
        
        foreach ($bots as $bot) {
            if ($bot->conversations_count > $threshold) {
                // Перенаправляем новые диалоги на менее загруженные боты
                $this->redistributeLoad($bot, $bots->where('conversations_count', '<', $avgLoad));
            }
        }
    }

    /**
     * Компрессия данных для экономии места
     */
    public function compressOldData(): void
    {
        // Компрессия старых сообщений
        $oldMessages = DB::table('messages')
            ->where('created_at', '<', now()->subDays(30))
            ->whereRaw('LENGTH(content) > 1000')
            ->get(['id', 'content']);
        
        foreach ($oldMessages as $message) {
            $compressed = gzcompress($message->content, 9);
            DB::table('messages')
                ->where('id', $message->id)
                ->update([
                    'content' => base64_encode($compressed),
                    'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.compressed', true)")
                ]);
        }
        
        Log::info("Compressed {$oldMessages->count()} old messages");
    }

    private function getCacheHitRate(): float
    {
        $hits = Cache::get('cache_hits', 0);
        $misses = Cache::get('cache_misses', 0);
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    private function getAverageApiResponseTime(): float
    {
        return Cache::get('avg_api_response_time', 0);
    }

    private function getRequestsPerMinute(): int
    {
        return Cache::get('requests_per_minute', 0);
    }

    private function getCpuUsage(): float
    {
        $load = sys_getloadavg();
        return round($load[0] * 100, 2);
    }

    private function getMemoryUsage(): float
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        // Конвертируем memory_limit в байты
        $limit = $this->convertToBytes($memoryLimit);
        
        return round(($memoryUsage / $limit) * 100, 2);
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int)$value;
        
        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }
}