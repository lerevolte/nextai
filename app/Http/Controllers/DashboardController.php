<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;
        
        // Получаем период для фильтрации
        $period = $request->get('period', '30'); // 7, 30, 90 дней
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();

        // Кэшируем тяжелые запросы
        $cacheKey = "dashboard:{$organization->id}:{$period}";
        $cacheDuration = 300; // 5 минут

        $metrics = Cache::remember($cacheKey, $cacheDuration, function () use ($organization, $startDate, $endDate) {
            return $this->calculateMetrics($organization, $startDate, $endDate);
        });

        // Данные для графиков
        $chartData = $this->getChartData($organization, $startDate, $endDate);
        
        // Топ боты по эффективности
        $topBots = $this->getTopPerformingBots($organization, $startDate, $endDate);
        
        // Анализ по каналам
        $channelStats = $this->getChannelStatistics($organization, $startDate, $endDate);
        
        // Тепловая карта активности
        $heatmapData = $this->getActivityHeatmap($organization);
        
        // Метрики удовлетворенности
        $satisfactionMetrics = $this->getSatisfactionMetrics($organization, $startDate, $endDate);
        
        // A/B тесты (если есть активные)
        $activeTests = $organization->abTests()->active()->with('variants')->get();

        return view('dashboard.index', compact(
            'organization',
            'metrics',
            'chartData',
            'topBots',
            'channelStats',
            'heatmapData',
            'satisfactionMetrics',
            'activeTests',
            'period'
        ));
    }

    protected function calculateMetrics($organization, $startDate, $endDate)
    {
        $metrics = [];

        // Основные метрики
        $metrics['total_bots'] = $organization->bots()->count();
        $metrics['active_bots'] = $organization->bots()->where('is_active', true)->count();
        
        // Метрики диалогов
        $conversationQuery = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate]);
        
        $metrics['total_conversations'] = (clone $conversationQuery)->count();
        $metrics['active_conversations'] = (clone $conversationQuery)->where('conversations.status', 'active')->count();
        $metrics['completed_conversations'] = (clone $conversationQuery)->where('conversations.status', 'closed')->count();
        
        // Метрики сообщений
        $messageQuery = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('messages.created_at', [$startDate, $endDate]);
        
        $metrics['total_messages'] = (clone $messageQuery)->count();
        $metrics['user_messages'] = (clone $messageQuery)->where('messages.role', 'user')->count();
        $metrics['bot_messages'] = (clone $messageQuery)->where('messages.role', 'assistant')->count();
        
        // Уникальные пользователи
        $metrics['unique_users'] = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->distinct('conversations.external_id')
            ->count('conversations.external_id');
        
        // Среднее время ответа (в секундах)
        $metrics['avg_response_time'] = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('messages.role', 'assistant')
            ->whereNotNull('messages.response_time')
            ->whereBetween('messages.created_at', [$startDate, $endDate])
            ->avg('messages.response_time');
        
        // Средняя длительность диалога
        $avgDuration = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('conversations.status', 'closed')
            ->whereNotNull('conversations.closed_at')
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, conversations.created_at, conversations.closed_at)) as avg_duration')
            ->first();
        
        $metrics['avg_conversation_duration'] = $avgDuration->avg_duration ?? 0;
        
        // Процент успешных диалогов (закрытые без передачи оператору)
        $successfulConversations = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('conversations.status', 'closed')
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->count();
        
        $transferredConversations = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('conversations.status', 'waiting_operator')
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->count();
        
        $metrics['success_rate'] = $successfulConversations > 0 
            ? round(($successfulConversations / ($successfulConversations + $transferredConversations)) * 100, 2) 
            : 0;
        
        // Токены AI
        $metrics['total_tokens_used'] = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->sum('conversations.ai_tokens_used');
        
        // Расчет трендов (сравнение с предыдущим периодом)
        $previousStart = Carbon::parse($startDate)->subDays($startDate->diffInDays($endDate));
        $previousEnd = $startDate;
        
        $previousConversations = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$previousStart, $previousEnd])
            ->count();
        
        $metrics['conversation_trend'] = $previousConversations > 0 
            ? round((($metrics['total_conversations'] - $previousConversations) / $previousConversations) * 100, 2)
            : 0;

        return $metrics;
    }

    protected function getChartData($organization, $startDate, $endDate)
    {
        $days = $startDate->diffInDays($endDate);
        $interval = $days > 30 ? 'week' : 'day';
        $format = $days > 30 ? 'W' : 'd.m';
        
        // Группировка данных по дням/неделям
        $query = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('messages.created_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE(messages.created_at) as date"),
                DB::raw("COUNT(CASE WHEN messages.role = 'user' THEN 1 END) as user_messages"),
                DB::raw("COUNT(CASE WHEN messages.role = 'assistant' THEN 1 END) as bot_messages"),
                DB::raw("AVG(CASE WHEN messages.role = 'assistant' AND messages.response_time IS NOT NULL THEN messages.response_time END) as avg_response_time")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $chartData = [
            'messages' => [],
            'conversations' => [],
            'response_times' => [],
            'satisfaction' => []
        ];
        
        foreach ($query as $row) {
            $date = Carbon::parse($row->date)->format($format);
            $chartData['messages'][] = [
                'date' => $date,
                'user' => $row->user_messages,
                'bot' => $row->bot_messages,
                'total' => $row->user_messages + $row->bot_messages
            ];
            
            $chartData['response_times'][] = [
                'date' => $date,
                'time' => round($row->avg_response_time ?? 0, 2)
            ];
        }
        
        // Данные по диалогам
        $conversationData = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE(conversations.created_at) as date"),
                DB::raw("COUNT(*) as total"),
                DB::raw("COUNT(CASE WHEN status = 'active' THEN 1 END) as active"),
                DB::raw("COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed"),
                DB::raw("COUNT(CASE WHEN status = 'waiting_operator' THEN 1 END) as transferred")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        foreach ($conversationData as $row) {
            $date = Carbon::parse($row->date)->format($format);
            $chartData['conversations'][] = [
                'date' => $date,
                'total' => $row->total,
                'active' => $row->active,
                'closed' => $row->closed,
                'transferred' => $row->transferred
            ];
        }

        return $chartData;
    }

    protected function getTopPerformingBots($organization, $startDate, $endDate)
    {
        return DB::table('bots')
            ->leftJoin('conversations', 'bots.id', '=', 'conversations.bot_id')
            ->leftJoin('messages', function($join) {
                $join->on('conversations.id', '=', 'messages.conversation_id')
                     ->where('messages.role', '=', 'assistant');
            })
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->select(
                'bots.id',
                'bots.name',
                'bots.is_active',
                DB::raw('COUNT(DISTINCT conversations.id) as conversation_count'),
                DB::raw('COUNT(messages.id) as message_count'),
                DB::raw('AVG(messages.response_time) as avg_response_time'),
                DB::raw('AVG(conversations.messages_count) as avg_messages_per_conversation'),
                DB::raw('SUM(CASE WHEN conversations.status = "closed" THEN 1 ELSE 0 END) as completed_conversations'),
                DB::raw('SUM(CASE WHEN conversations.status = "waiting_operator" THEN 1 ELSE 0 END) as transferred_conversations')
            )
            ->groupBy('bots.id', 'bots.name', 'bots.is_active')
            ->orderByDesc('conversation_count')
            ->limit(5)
            ->get()
            ->map(function ($bot) {
                $total = $bot->completed_conversations + $bot->transferred_conversations;
                $bot->success_rate = $total > 0 
                    ? round(($bot->completed_conversations / $total) * 100, 2) 
                    : 0;
                $bot->avg_response_time = round($bot->avg_response_time ?? 0, 2);
                return $bot;
            });
    }

    protected function getChannelStatistics($organization, $startDate, $endDate)
    {
        return DB::table('channels')
            ->join('bots', 'channels.bot_id', '=', 'bots.id')
            ->leftJoin('conversations', 'channels.id', '=', 'conversations.channel_id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->select(
                'channels.type',
                DB::raw('COUNT(DISTINCT conversations.id) as conversation_count'),
                DB::raw('COUNT(DISTINCT conversations.external_id) as unique_users'),
                DB::raw('AVG(conversations.messages_count) as avg_messages'),
                DB::raw('SUM(CASE WHEN conversations.status = "closed" THEN 1 ELSE 0 END) as completed')
            )
            ->groupBy('channels.type')
            ->get()
            ->mapWithKeys(function ($stat) {
                return [$stat->type => $stat];
            });
    }

    protected function getActivityHeatmap($organization)
    {
        $data = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->where('messages.created_at', '>=', Carbon::now()->subDays(7))
            ->select(
                DB::raw('DAYOFWEEK(messages.created_at) - 1 as day'),
                DB::raw('HOUR(messages.created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('day', 'hour')
            ->get();
        
        // Форматируем для heatmap
        $heatmap = [];
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $count = $data->where('day', $day)->where('hour', $hour)->first()->count ?? 0;
                $heatmap[] = [$day, $hour, $count];
            }
        }
        
        return $heatmap;
    }

    protected function getSatisfactionMetrics($organization, $startDate, $endDate)
    {
        $feedback = DB::table('feedback')
            ->join('conversations', 'feedback.conversation_id', '=', 'conversations.id')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('feedback.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN type = "positive" THEN 1 ELSE 0 END) as positive'),
                DB::raw('SUM(CASE WHEN type = "negative" THEN 1 ELSE 0 END) as negative')
            )
            ->first();
        
        return [
            'total' => $feedback->total ?? 0,
            'positive' => $feedback->positive ?? 0,
            'negative' => $feedback->negative ?? 0,
            'satisfaction_rate' => $feedback->total > 0 
                ? round(($feedback->positive / $feedback->total) * 100, 2) 
                : 0
        ];
    }

    // API методы для AJAX обновления метрик
    public function refreshMetrics(Request $request)
    {
        $organization = $request->user()->organization;
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();
        
        // Очищаем кэш для обновления данных
        Cache::forget("dashboard:{$organization->id}:{$period}");
        
        $metrics = $this->calculateMetrics($organization, $startDate, $endDate);
        
        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'updated_at' => now()->toIso8601String()
        ]);
    }

    // Экспорт метрик
    public function exportMetrics(Request $request)
    {
        $organization = $request->user()->organization;
        $period = $request->get('period', '30');
        $format = $request->get('format', 'csv'); // csv, xlsx, pdf
        
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();
        
        $metrics = $this->calculateMetrics($organization, $startDate, $endDate);
        $chartData = $this->getChartData($organization, $startDate, $endDate);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($metrics, $chartData, $organization);
            case 'xlsx':
                return $this->exportToExcel($metrics, $chartData, $organization);
            case 'pdf':
                return $this->exportToPdf($metrics, $chartData, $organization);
            default:
                return response()->json(['error' => 'Invalid format'], 400);
        }
    }
}