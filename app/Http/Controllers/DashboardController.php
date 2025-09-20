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
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();

        // АГРЕССИВНОЕ КЭШИРОВАНИЕ для ускорения
        $cacheKey = "dashboard_lite:{$organization->id}:{$period}";
        $cacheDuration = 600; // 10 минут

        $data = Cache::remember($cacheKey, $cacheDuration, function () use ($organization, $startDate, $endDate) {
            return [
                'metrics' => $this->getBasicMetrics($organization, $startDate, $endDate),
                'topBots' => $this->getTopBots($organization, $startDate, $endDate),
                'recentActivity' => $this->getRecentActivity($organization)
            ];
        });

        return view('dashboard', array_merge($data, [
            'organization' => $organization,
            'period' => $period
        ]));
    }

    /**
     * Получить только базовые метрики (оптимизированный запрос)
     */
    protected function getBasicMetrics($organization, $startDate, $endDate)
    {
        // Один оптимизированный запрос вместо множества
        $stats = DB::selectOne("
            SELECT 
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(DISTINCT c.external_id) as unique_users,
                COUNT(DISTINCT CASE WHEN c.status = 'active' THEN c.id END) as active_conversations,
                COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as completed_conversations,
                AVG(CASE WHEN m.role = 'assistant' THEN m.response_time END) as avg_response_time,
                COUNT(m.id) as total_messages
            FROM bots b
            LEFT JOIN conversations c ON b.id = c.bot_id 
                AND c.created_at BETWEEN ? AND ?
            LEFT JOIN messages m ON c.id = m.conversation_id 
                AND m.created_at BETWEEN ? AND ?
            WHERE b.organization_id = ?
        ", [$startDate, $endDate, $startDate, $endDate, $organization->id]);

        // Расчет успешности
        $successRate = 0;
        if ($stats->completed_conversations > 0) {
            $transferred = DB::table('conversations')
                ->join('bots', 'conversations.bot_id', '=', 'bots.id')
                ->where('bots.organization_id', $organization->id)
                ->where('conversations.status', 'waiting_operator')
                ->whereBetween('conversations.created_at', [$startDate, $endDate])
                ->count();
            
            $total = $stats->completed_conversations + $transferred;
            $successRate = $total > 0 ? round(($stats->completed_conversations / $total) * 100, 2) : 0;
        }

        return [
            'summary' => [
                'total_conversations' => [
                    'value' => $stats->total_conversations ?? 0,
                    'trend' => 0 // Упрощаем - без трендов для скорости
                ],
                'unique_users' => [
                    'value' => $stats->unique_users ?? 0,
                    'trend' => 0
                ],
                'avg_response_time' => [
                    'value' => round($stats->avg_response_time ?? 0, 2),
                    'trend' => 0
                ],
                'success_rate' => [
                    'value' => $successRate,
                    'trend' => 0
                ],
                'total_messages' => [
                    'value' => $stats->total_messages ?? 0,
                    'trend' => 0
                ],
                'active_conversations' => [
                    'value' => $stats->active_conversations ?? 0,
                    'trend' => 0
                ]
            ]
        ];
    }

    /**
     * Получить топ ботов (упрощенная версия)
     */
    protected function getTopBots($organization, $startDate, $endDate)
    {
        return DB::table('bots')
            ->leftJoin('conversations', function($join) use ($startDate, $endDate) {
                $join->on('bots.id', '=', 'conversations.bot_id')
                     ->whereBetween('conversations.created_at', [$startDate, $endDate]);
            })
            ->where('bots.organization_id', $organization->id)
            ->select(
                'bots.id',
                'bots.name',
                'bots.is_active',
                DB::raw('COUNT(DISTINCT conversations.id) as conversation_count')
            )
            ->groupBy('bots.id', 'bots.name', 'bots.is_active')
            ->orderByDesc('conversation_count')
            ->limit(5)
            ->get();
    }

    /**
     * Получить недавнюю активность
     */
    protected function getRecentActivity($organization)
    {
        return DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->join('channels', 'conversations.channel_id', '=', 'channels.id')
            ->where('bots.organization_id', $organization->id)
            ->select(
                'conversations.id',
                'conversations.status',
                'conversations.user_name',
                'conversations.messages_count',
                'conversations.created_at',
                'bots.name as bot_name',
                'channels.type as channel_type'
            )
            ->orderBy('conversations.created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * API метод для обновления метрик (AJAX)
     */
    public function refreshMetrics(Request $request)
    {
        $organization = $request->user()->organization;
        $period = $request->get('period', '30');
        
        // Очищаем кэш
        Cache::forget("dashboard_lite:{$organization->id}:{$period}");
        
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();
        
        $metrics = $this->getBasicMetrics($organization, $startDate, $endDate);
        
        return response()->json([
            'success' => true,
            'metrics' => $metrics['summary'],
            'updated_at' => now()->format('H:i:s')
        ]);
    }
}