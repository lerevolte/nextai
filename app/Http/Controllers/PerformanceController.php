<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Bot;
use App\Models\PerformanceMetric;
use App\Services\PerformanceOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    protected PerformanceOptimizationService $optimizationService;

    public function __construct(PerformanceOptimizationService $optimizationService)
    {
        $this->optimizationService = $optimizationService;
    }

    /**
     * Главная страница мониторинга производительности
     */
    public function index(Organization $organization)
    {
        // Получаем текущие метрики
        $metrics = $this->optimizationService->getPerformanceMetrics();
        
        // Получаем исторические данные за последние 24 часа
        $historicalMetrics = PerformanceMetric::recent(24)
            ->selectRaw('
                metric_type,
                metric_name,
                AVG(value) as avg_value,
                MIN(value) as min_value,
                MAX(value) as max_value
            ')
            ->groupBy('metric_type', 'metric_name')
            ->get()
            ->groupBy('metric_type');
        
        // Получаем рекомендации по оптимизации
        $recommendations = $this->getOptimizationRecommendations($organization);
        
        // Статистика по ботам
        $botsPerformance = $this->getBotsPerformance($organization);

        return view('performance.index', compact(
            'organization', 
            'metrics', 
            'historicalMetrics', 
            'recommendations',
            'botsPerformance'
        ));
    }

    /**
     * API для получения метрик в реальном времени
     */
    public function metrics(Organization $organization)
    {
        $metrics = $this->optimizationService->getPerformanceMetrics();
        
        // Добавляем дополнительные метрики
        $metrics['bots'] = $organization->bots()
            ->withCount(['conversations as active_conversations' => function ($query) {
                $query->where('status', 'active');
            }])
            ->get()
            ->map(function ($bot) {
                return [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'active_conversations' => $bot->active_conversations,
                    'cached_responses' => $bot->cachedResponses()->count(),
                    'cache_hit_rate' => $this->getBotCacheHitRate($bot),
                ];
            });

        return response()->json($metrics);
    }

    /**
     * Запуск оптимизации
     */
    public function optimize(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'type' => 'required|in:cache,database,cleanup,all',
            'bot_id' => 'nullable|exists:bots,id',
        ]);

        try {
            $results = [];

            switch ($validated['type']) {
                case 'cache':
                    // Оптимизация кэша
                    if ($validated['bot_id'] ?? null) {
                        $bot = Bot::find($validated['bot_id']);
                        $this->optimizationService->cacheFrequentResponses($bot);
                        $this->optimizationService->preloadBotData($bot);
                        $results[] = "Кэш бота {$bot->name} оптимизирован";
                    } else {
                        foreach ($organization->bots as $bot) {
                            $this->optimizationService->cacheFrequentResponses($bot);
                        }
                        $results[] = "Кэш всех ботов оптимизирован";
                    }
                    break;

                case 'database':
                    // Оптимизация базы данных
                    $this->optimizationService->optimizeDatabaseQueries();
                    $results[] = "База данных оптимизирована";
                    break;

                case 'cleanup':
                    // Очистка старых данных
                    $this->optimizationService->compressOldData();
                    $results[] = "Старые данные очищены и сжаты";
                    break;

                case 'all':
                    // Полная оптимизация
                    foreach ($organization->bots as $bot) {
                        $this->optimizationService->cacheFrequentResponses($bot);
                        $this->optimizationService->preloadBotData($bot);
                    }
                    $this->optimizationService->optimizeDatabaseQueries();
                    $this->optimizationService->compressOldData();
                    $this->optimizationService->autoScale();
                    $results[] = "Полная оптимизация выполнена";
                    break;
            }

            // Логируем результаты оптимизации
            PerformanceMetric::record('optimization', $validated['type'], 1, [
                'organization_id' => $organization->id,
                'results' => $results
            ]);

            return redirect()
                ->route('performance.index', $organization)
                ->with('success', implode('. ', $results));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка оптимизации: ' . $e->getMessage()]);
        }
    }

    /**
     * Получение рекомендаций по оптимизации
     */
    public function recommendations(Organization $organization)
    {
        $recommendations = [];

        // Анализ использования токенов
        foreach ($organization->bots as $bot) {
            $tokenOptimization = $this->optimizationService->optimizeTokenUsage($bot);
            if (!empty($tokenOptimization['recommendations'])) {
                $recommendations['tokens'][$bot->id] = [
                    'bot_name' => $bot->name,
                    'recommendations' => $tokenOptimization['recommendations'],
                    'potential_savings' => $tokenOptimization['potential_savings'] ?? null
                ];
            }
        }

        // Анализ кэша
        $cacheHitRate = Cache::get('cache_hit_rate', 0);
        if ($cacheHitRate < 70) {
            $recommendations['cache'][] = [
                'type' => 'warning',
                'message' => 'Низкий процент попаданий в кэш (' . $cacheHitRate . '%)',
                'action' => 'Рекомендуется увеличить размер кэша и прекэшировать частые запросы'
            ];
        }

        // Анализ базы данных
        $slowQueries = DB::table('performance_metrics')
            ->where('metric_type', 'slow_query')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        
        if ($slowQueries > 10) {
            $recommendations['database'][] = [
                'type' => 'critical',
                'message' => 'Обнаружено ' . $slowQueries . ' медленных запросов за последние 24 часа',
                'action' => 'Требуется оптимизация запросов и добавление индексов'
            ];
        }

        // Анализ памяти
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        if ($memoryUsage > 256) {
            $recommendations['resources'][] = [
                'type' => 'warning',
                'message' => 'Высокое использование памяти (' . round($memoryUsage) . ' MB)',
                'action' => 'Рекомендуется проверить утечки памяти и оптимизировать код'
            ];
        }

        return view('performance.recommendations', compact('organization', 'recommendations'));
    }

    /**
     * Получение рекомендаций по оптимизации для организации
     */
    protected function getOptimizationRecommendations(Organization $organization): array
    {
        $recommendations = [];

        // Проверяем количество активных диалогов
        $activeConversations = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('conversations.status', 'active')
            ->count();

        if ($activeConversations > 100) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'Много активных диалогов',
                'description' => "У вас {$activeConversations} активных диалогов. Рекомендуется закрывать неактивные диалоги для улучшения производительности.",
                'action' => 'Настроить автозакрытие неактивных диалогов'
            ];
        }

        // Проверяем размер базы знаний
        $largeKnowledgeBases = DB::table('knowledge_items')
            ->join('knowledge_bases', 'knowledge_items.knowledge_base_id', '=', 'knowledge_bases.id')
            ->join('bots', 'knowledge_bases.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->select('knowledge_bases.id', DB::raw('COUNT(*) as items_count'))
            ->groupBy('knowledge_bases.id')
            ->having('items_count', '>', 100)
            ->count();

        if ($largeKnowledgeBases > 0) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'medium',
                'title' => 'Большие базы знаний',
                'description' => "У вас есть базы знаний с более чем 100 элементами. Рекомендуется использовать векторный поиск.",
                'action' => 'Включить векторную индексацию для больших баз знаний'
            ];
        }

        // Проверяем частоту ответов
        $avgResponseTime = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('messages.created_at', '>=', now()->subDay())
            ->avg('messages.response_time');

        if ($avgResponseTime > 3) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'Медленное время ответа',
                'description' => "Среднее время ответа составляет " . round($avgResponseTime, 2) . " секунд.",
                'action' => 'Оптимизировать промпты и включить кэширование ответов'
            ];
        }

        return $recommendations;
    }

    /**
     * Получение производительности ботов
     */
    protected function getBotsPerformance(Organization $organization): array
    {
        return $organization->bots()
            ->withCount([
                'conversations',
                'conversations as active_conversations_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'cachedResponses as cache_count'
            ])
            ->get()
            ->map(function ($bot) {
                $stats = $bot->getStats(1); // Статистика за последний день
                
                return [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'is_active' => $bot->is_active,
                    'total_conversations' => $bot->conversations_count,
                    'active_conversations' => $bot->active_conversations_count,
                    'cache_entries' => $bot->cache_count,
                    'avg_response_time' => round($stats['avg_response_time'] ?? 0, 2),
                    'tokens_used' => $stats['tokens_used'] ?? 0,
                    'cache_hit_rate' => $this->getBotCacheHitRate($bot),
                    'status' => $this->getBotStatus($bot, $stats)
                ];
            })
            ->toArray();
    }

    /**
     * Получение процента попаданий в кэш для бота
     */
    protected function getBotCacheHitRate(Bot $bot): float
    {
        $totalHits = $bot->cachedResponses()->sum('hit_count');
        $totalRequests = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.bot_id', $bot->id)
            ->where('messages.role', 'user')
            ->where('messages.created_at', '>=', now()->subDay())
            ->count();
        
        return $totalRequests > 0 ? round(($totalHits / $totalRequests) * 100, 2) : 0;
    }

    /**
     * Определение статуса бота
     */
    protected function getBotStatus(Bot $bot, array $stats): string
    {
        if (!$bot->is_active) {
            return 'inactive';
        }
        
        if ($stats['avg_response_time'] > 5) {
            return 'slow';
        }
        
        if ($bot->active_conversations_count > 50) {
            return 'overloaded';
        }
        
        return 'healthy';
    }
}